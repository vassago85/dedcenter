<?php

/**
 * ALRHA scoring engine acceptance tests.
 *
 * Pins the behaviours from the ALRHA plan and the R&R doc:
 *   1. Shot-index scoring: 5-4-3-2-1 (base_points ignored)
 *   2. Cold Bore Challenge is excluded from match total but appears
 *      on its own CBC prize table row
 *   3. Coached shooters are ranked but excluded from category prize
 *      lists
 *   4. Hunters class produces both individual and team leaderboards
 *   5. Varmint class produces individual only, no team leaderboard
 *   6. Categories (Open / Ladies / Junior) each get their own ranked
 *      slice
 *   7. Tie-break order: total_points → first_round_hits → furthest_hit_m
 */

use App\Enums\AlrhaClass;
use App\Enums\ElrShotResult;
use App\Enums\ElrStageType;
use App\Models\ElrScoringProfile;
use App\Models\ElrStage;
use App\Models\ElrTarget;
use App\Models\MatchCategory;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\Team;
use App\Models\User;
use App\Services\Scoring\AlrhaScoringService;
use App\Services\Scoring\ELRScoringService;

function alrhaBuild(string $classValue): array
{
    $owner = User::factory()->create(['role' => 'owner']);
    $class = AlrhaClass::from($classValue);

    $match = ShootingMatch::factory()->active()->alrha($classValue)->create([
        'created_by' => $owner->id,
        'elr_distance_based_scoring' => false,
    ]);

    $profile = ElrScoringProfile::create([
        'match_id' => $match->id,
        'name' => 'ALRHA 5-4-3-2-1',
        'multipliers' => [5, 4, 3, 2, 1],
    ]);
    $match->update(['elr_scoring_profile_id' => $profile->id]);

    $cbcStage = ElrStage::create([
        'match_id' => $match->id, 'label' => 'CBC',
        'stage_type' => ElrStageType::Static,
        'elr_scoring_profile_id' => $profile->id, 'sort_order' => 1,
    ]);
    $cbcTarget = ElrTarget::create([
        'elr_stage_id' => $cbcStage->id,
        'name' => $class->coldBoreTargetName(),
        'distance_m' => $class->coldBoreDistance(),
        'base_points' => 1, 'max_shots' => 1, 'sort_order' => 1,
        'is_cold_bore' => true, 'alrha_block' => 'cbc',
    ]);

    $farStage = ElrStage::create([
        'match_id' => $match->id, 'label' => 'Far',
        'stage_type' => ElrStageType::Static,
        'elr_scoring_profile_id' => $profile->id, 'sort_order' => 2,
    ]);
    $farTargets = [];
    foreach ($class->farBlockDistances() as $i => $distance) {
        $farTargets[] = ElrTarget::create([
            'elr_stage_id' => $farStage->id,
            'name' => "{$distance} m",
            'distance_m' => $distance,
            'base_points' => 1, 'max_shots' => 5, 'sort_order' => $i + 1,
            'alrha_block' => 'far',
        ]);
    }

    $nearStage = ElrStage::create([
        'match_id' => $match->id, 'label' => 'Near',
        'stage_type' => ElrStageType::Static,
        'elr_scoring_profile_id' => $profile->id, 'sort_order' => 3,
    ]);
    $nearTargets = [];
    foreach ($class->nearBlockDistances() as $i => $distance) {
        $nearTargets[] = ElrTarget::create([
            'elr_stage_id' => $nearStage->id,
            'name' => "{$distance} m",
            'distance_m' => $distance,
            'base_points' => 1, 'max_shots' => 5, 'sort_order' => $i + 1,
            'alrha_block' => 'near',
        ]);
    }

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'R1', 'sort_order' => 1]);

    return compact('owner', 'match', 'class', 'profile', 'squad', 'cbcTarget', 'farTargets', 'nearTargets');
}

function alrhaShoot(int $shooterId, ElrTarget $target, array $shotNumbersHit): void
{
    $service = new ELRScoringService();
    $shooter = Shooter::findOrFail($shooterId);
    $target->loadMissing('stage.match', 'stage.scoringProfile');

    for ($n = 1; $n <= $target->max_shots; $n++) {
        $service->recordShot(
            $shooter,
            $target,
            $n,
            in_array($n, $shotNumbersHit, true) ? ElrShotResult::Hit : ElrShotResult::Miss,
            $shooter->user_id ?? 1,
            'device-1',
        );
    }
}

it('scores shots with the 5-4-3-2-1 index and totals ignore CBC', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'A']);

    // CBC hit → 5 pts, but must NOT roll into total.
    alrhaShoot($shooter->id, $ctx['cbcTarget'], [1]);

    // Far target #1 (700 m): hits on shots 1, 3, 4 → 5 + 3 + 2 = 10 pts.
    alrhaShoot($shooter->id, $ctx['farTargets'][0], [1, 3, 4]);

    $service = new AlrhaScoringService(new ELRScoringService());
    $out = $service->calculateStandings($ctx['match']);
    $row = $out['standings'][0];

    expect($row['total_points'])->toBe(10.0);
    expect($row['cbc_points'])->toBe(5.0);
    expect($row['cbc_hits'])->toBe(1);
    expect($row['total_hits'])->toBe(3);

    // CBC prize table row present.
    expect($out['cbc'])->toHaveCount(1);
    expect($out['cbc'][0]['cbc_points'])->toBe(5.0);
});

it('matches the doc example: miss-miss-hit-hit-hit on a target = 6 pts', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'A']);

    // 700m target, shots 3/4/5 hit → 3 + 2 + 1 = 6 pts.
    alrhaShoot($shooter->id, $ctx['farTargets'][0], [3, 4, 5]);

    $service = new AlrhaScoringService(new ELRScoringService());
    $row = $service->calculateStandings($ctx['match'])['standings'][0];

    expect($row['total_points'])->toBe(6.0);
});

it('ranks Hunters by team total and produces a teams payload', function () {
    $ctx = alrhaBuild('hunters');
    $teamA = Team::create(['match_id' => $ctx['match']->id, 'name' => 'A', 'max_size' => 2, 'sort_order' => 1]);
    $teamB = Team::create(['match_id' => $ctx['match']->id, 'name' => 'B', 'max_size' => 2, 'sort_order' => 2]);

    $a1 = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'team_id' => $teamA->id, 'name' => 'A1']);
    $a2 = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'team_id' => $teamA->id, 'name' => 'A2']);
    $b1 = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'team_id' => $teamB->id, 'name' => 'B1']);
    $b2 = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'team_id' => $teamB->id, 'name' => 'B2']);

    // Team A: (5+4)=9 + (5)=5 → 14. Team B: (5)=5 + (5)=5 → 10.
    alrhaShoot($a1->id, $ctx['farTargets'][0], [1, 2]);
    alrhaShoot($a2->id, $ctx['farTargets'][0], [1]);
    alrhaShoot($b1->id, $ctx['farTargets'][0], [1]);
    alrhaShoot($b2->id, $ctx['farTargets'][0], [1]);

    $service = new AlrhaScoringService(new ELRScoringService());
    $out = $service->calculateStandings($ctx['match']);

    expect($out['teams'])->toHaveCount(2);
    expect($out['teams'][0]['team'])->toBe('A');
    expect($out['teams'][0]['team_total_points'])->toBe(14.0);
    expect($out['teams'][1]['team'])->toBe('B');
    expect($out['teams'][1]['team_total_points'])->toBe(10.0);
});

it('does not build a team leaderboard for Varmint matches', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id]);
    alrhaShoot($shooter->id, $ctx['farTargets'][0], [1]);

    $service = new AlrhaScoringService(new ELRScoringService());
    $out = $service->calculateStandings($ctx['match']);

    expect($out['teams'])->toBe([]);
});

it('excludes coached shooters from category prize lists but keeps them in overall ranking', function () {
    $ctx = alrhaBuild('varmint');
    MatchCategory::create(['match_id' => $ctx['match']->id, 'name' => 'Open', 'slug' => 'open', 'sort_order' => 0]);

    $eligible = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id, 'name' => 'Eligible', 'is_coached' => false,
    ]);
    $coached = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id, 'name' => 'Coached', 'is_coached' => true,
    ]);

    alrhaShoot($eligible->id, $ctx['farTargets'][0], [1]); // 5 pts
    alrhaShoot($coached->id, $ctx['farTargets'][0], [1, 2]); // 9 pts, higher

    $service = new AlrhaScoringService(new ELRScoringService());
    $out = $service->calculateStandings($ctx['match']);

    // Coached is #1 overall (higher points).
    expect($out['standings'][0]['name'])->toBe('Coached');
    expect($out['standings'][0]['is_coached'])->toBeTrue();

    // Category prize list excludes the coached shooter.
    $open = collect($out['categories'])->firstWhere('slug', 'open');
    expect($open['rows'])->toHaveCount(1);
    expect($open['rows'][0]['name'])->toBe('Eligible');
});
