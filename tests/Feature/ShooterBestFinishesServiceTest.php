<?php

use App\Enums\MatchStatus;
use App\Enums\PrsShotResult;
use App\Models\Gong;
use App\Models\Organization;
use App\Models\PrsShotScore;
use App\Models\PrsStageResult;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use App\Services\ShooterBestFinishesService;

/**
 * ShooterBestFinishesService contract:
 *   - One row per org the user has completed matches in.
 *   - best_rank = lowest rank number the user achieved in any completed match.
 *   - percentile = round-up of (best_rank / field_size * 100).
 *   - DQ / no-show results are counted toward matches_shot but don't lower best_rank.
 */
beforeEach(function () {
    $this->makeCompletedMatch = function (Organization $org, array $userScoreMap) {
        $owner = User::factory()->create();
        $match = ShootingMatch::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $owner->id,
            'scoring_type' => 'standard',
            'status' => MatchStatus::Completed,
        ]);

        $ts = TargetSet::create([
            'match_id' => $match->id,
            'label' => '500m',
            'distance_meters' => 500,
            'distance_multiplier' => 1.0,
            'sort_order' => 1,
        ]);

        $maxHits = max(array_map(fn ($e) => $e['hits'] ?? 0, $userScoreMap));
        $gongs = [];
        for ($g = 1; $g <= max(1, $maxHits); $g++) {
            $gongs[] = Gong::create([
                'target_set_id' => $ts->id,
                'number' => $g,
                'label' => "G{$g}",
                'multiplier' => '1.00',
            ]);
        }

        $squad = Squad::create(['match_id' => $match->id, 'name' => 'Squad A', 'sort_order' => 1]);

        foreach ($userScoreMap as $entry) {
            $shooter = Shooter::factory()->create([
                'squad_id' => $squad->id,
                'user_id' => $entry['user']?->id,
                'status' => $entry['status'] ?? 'active',
            ]);
            for ($i = 0; $i < ($entry['hits'] ?? 0); $i++) {
                Score::create([
                    'shooter_id' => $shooter->id,
                    'gong_id' => $gongs[$i]->id,
                    'is_hit' => true,
                    'recorded_at' => now(),
                ]);
            }
        }

        return $match;
    };
});

it('returns best rank and percentile per organization', function () {
    $user = User::factory()->create();
    $rival = User::factory()->create();
    $org = Organization::factory()->create();

    ($this->makeCompletedMatch)($org, [
        ['user' => $rival, 'hits' => 10],
        ['user' => $user, 'hits' => 5],
    ]);

    $rows = (new ShooterBestFinishesService)->forUser($user);

    expect($rows)->toHaveCount(1);
    expect($rows[0]->organization->id)->toBe($org->id);
    expect($rows[0]->best_rank)->toBe(2);
    expect($rows[0]->field_size)->toBe(2);
    expect($rows[0]->percentile)->toBe(100);
    expect($rows[0]->matches_shot)->toBe(1);
});

it('keeps the best rank across multiple matches in the same org', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();

    ($this->makeCompletedMatch)($org, [
        ['user' => User::factory()->create(), 'hits' => 10],
        ['user' => User::factory()->create(), 'hits' => 8],
        ['user' => User::factory()->create(), 'hits' => 7],
        ['user' => $user, 'hits' => 5],
    ]);
    ($this->makeCompletedMatch)($org, [
        ['user' => $user, 'hits' => 20],
        ['user' => User::factory()->create(), 'hits' => 10],
    ]);

    $rows = (new ShooterBestFinishesService)->forUser($user);

    expect($rows)->toHaveCount(1);
    expect($rows[0]->best_rank)->toBe(1);
    expect($rows[0]->matches_shot)->toBe(2);
});

it('splits best finishes by organization', function () {
    $user = User::factory()->create();
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    ($this->makeCompletedMatch)($orgA, [
        ['user' => User::factory()->create(), 'hits' => 10],
        ['user' => $user, 'hits' => 5],
    ]);
    ($this->makeCompletedMatch)($orgB, [
        ['user' => $user, 'hits' => 20],
    ]);

    $rows = (new ShooterBestFinishesService)->forUser($user);

    expect($rows)->toHaveCount(2);
    // Sorted by best_rank ascending: orgB (rank 1) first, then orgA (rank 2).
    expect($rows[0]->organization->id)->toBe($orgB->id);
    expect($rows[0]->best_rank)->toBe(1);
    expect($rows[1]->organization->id)->toBe($orgA->id);
    expect($rows[1]->best_rank)->toBe(2);
});

it('ignores DQ and no-show results for ranking but still counts matches_shot', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();

    ($this->makeCompletedMatch)($org, [
        ['user' => User::factory()->create(), 'hits' => 10],
        ['user' => $user, 'hits' => 5, 'status' => 'dq'],
    ]);

    $rows = (new ShooterBestFinishesService)->forUser($user);

    expect($rows)->toHaveCount(1);
    expect($rows[0]->best_rank)->toBeNull();
    expect($rows[0]->percentile)->toBeNull();
    expect($rows[0]->matches_shot)->toBe(1);
});

it('returns empty collection for users with no completed matches', function () {
    $user = User::factory()->create();

    $rows = (new ShooterBestFinishesService)->forUser($user);

    expect($rows)->toHaveCount(0);
});

/*
 * Regression: a completed PRS match was showing "Awaiting a ranked finish"
 * on the dashboard's per-org best-finish card, even though the per-shooter
 * report correctly ranked the user. Cause: rankForShooter() returned null
 * for any PRS or ELR match. We now resolve PRS/ELR ranks via
 * MatchReportService, so the dashboard matches the report.
 */
it('surfaces a PRS rank from prs_stage_results (no more "Awaiting a ranked finish")', function () {
    $user = User::factory()->create();
    $rivalUser = User::factory()->create();
    $org = Organization::factory()->create();

    $match = ShootingMatch::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $rivalUser->id,
        'scoring_type' => 'prs',
        'status' => MatchStatus::Completed,
    ]);

    $stage = TargetSet::create([
        'match_id' => $match->id,
        'label' => 'Stage 1',
        'distance_meters' => 0,
        'distance_multiplier' => 1.0,
        'sort_order' => 1,
    ]);
    $g1 = Gong::create(['target_set_id' => $stage->id, 'number' => 1, 'label' => 'G1', 'multiplier' => '1.00']);
    $g2 = Gong::create(['target_set_id' => $stage->id, 'number' => 2, 'label' => 'G2', 'multiplier' => '1.00']);

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Squad A', 'sort_order' => 1]);

    $rivalShooter = Shooter::factory()->create([
        'squad_id' => $squad->id,
        'user_id' => $rivalUser->id,
        'status' => 'active',
    ]);
    $userShooter = Shooter::factory()->create([
        'squad_id' => $squad->id,
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    // Rival clears the stage (2/2), our user gets 1/2. Rival should rank #1, user #2.
    PrsStageResult::create([
        'match_id' => $match->id, 'stage_id' => $stage->id, 'shooter_id' => $rivalShooter->id,
        'hits' => 2, 'misses' => 0, 'not_taken' => 0,
        'raw_time_seconds' => 30.0, 'official_time_seconds' => 30.0,
        'completed_at' => now(),
    ]);
    PrsShotScore::create(['match_id' => $match->id, 'stage_id' => $stage->id, 'shooter_id' => $rivalShooter->id, 'shot_number' => 1, 'result' => PrsShotResult::Hit]);
    PrsShotScore::create(['match_id' => $match->id, 'stage_id' => $stage->id, 'shooter_id' => $rivalShooter->id, 'shot_number' => 2, 'result' => PrsShotResult::Hit]);

    PrsStageResult::create([
        'match_id' => $match->id, 'stage_id' => $stage->id, 'shooter_id' => $userShooter->id,
        'hits' => 1, 'misses' => 1, 'not_taken' => 0,
        'raw_time_seconds' => 35.0, 'official_time_seconds' => 35.0,
        'completed_at' => now(),
    ]);
    PrsShotScore::create(['match_id' => $match->id, 'stage_id' => $stage->id, 'shooter_id' => $userShooter->id, 'shot_number' => 1, 'result' => PrsShotResult::Hit]);
    PrsShotScore::create(['match_id' => $match->id, 'stage_id' => $stage->id, 'shooter_id' => $userShooter->id, 'shot_number' => 2, 'result' => PrsShotResult::Miss]);

    $rows = (new ShooterBestFinishesService)->forUser($user);

    expect($rows)->toHaveCount(1);
    expect($rows[0]->organization->id)->toBe($org->id);
    expect($rows[0]->best_rank)->toBe(2);
    expect($rows[0]->field_size)->toBe(2);
    expect($rows[0]->percentile)->toBe(100);
    expect($rows[0]->matches_shot)->toBe(1);
});
