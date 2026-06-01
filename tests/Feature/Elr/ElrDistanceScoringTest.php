<?php

/**
 * Phase A acceptance tests for the new Peregrine-style ELR scoring engine.
 *
 * These tests pin the 13 behaviours JD's spec called out:
 *   1\u20133  shot multipliers (1.5 / 1.25 / 1.0) applied to distance
 *   4    miss = 0
 *   5    shooter total = sum of shot points
 *   6    team total = sum of both shooters
 *   7    relative % is calculated vs. the top shooter
 *   8    hit rate = hits / shots fired
 *   9    Minor only receives T1\u2013T3 by default (via elr_division_targets)
 *   10   Major only receives T2\u2013T4 by default
 *   11   furthest impact (m) is correct
 *   12   furthest first-round impact (m) is correct
 *   13   editing match settings AFTER scoring does NOT change historical totals
 *
 * Kept separate from the original ElrScoringTest.php so the legacy
 * base_points behaviour stays asserted by that file and this one only
 * covers the new distance-based engine (matches.elr_distance_based_scoring = true).
 */

use App\Enums\ElrShotResult;
use App\Enums\ElrStageType;
use App\Models\ElrScoringProfile;
use App\Models\ElrStage;
use App\Models\ElrTarget;
use App\Models\MatchDivision;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\Team;
use App\Models\User;
use App\Services\Scoring\ELRScoringService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => 'owner']);

    $this->match = ShootingMatch::factory()
        ->active()
        ->elr()
        ->create([
            'created_by' => $this->owner->id,
            'elr_distance_based_scoring' => true,
        ]);

    // Peregrine default multipliers: shot 1 = 1.5, shot 2 = 1.25, shot 3 = 1.0
    $this->profile = ElrScoringProfile::create([
        'match_id' => $this->match->id,
        'name' => 'Peregrine Default',
        'multipliers' => [1.5, 1.25, 1.0],
    ]);

    $this->match->update(['elr_scoring_profile_id' => $this->profile->id]);

    // One "station" (=stage) with four targets at Peregrine-like distances.
    $this->station = ElrStage::create([
        'match_id' => $this->match->id,
        'label' => 'Warrior',
        'sponsor' => 'Gun Warrior',
        'color' => '#F97316',
        'stage_type' => ElrStageType::Static,
        'elr_scoring_profile_id' => $this->profile->id,
        'sort_order' => 1,
    ]);

    $this->t1 = ElrTarget::create([
        'elr_stage_id' => $this->station->id,
        'name' => 'T1', 'distance_m' => 597, 'base_points' => 1,
        'max_shots' => 3, 'sort_order' => 1,
    ]);
    $this->t2 = ElrTarget::create([
        'elr_stage_id' => $this->station->id,
        'name' => 'T2', 'distance_m' => 840, 'base_points' => 1,
        'max_shots' => 3, 'sort_order' => 2,
    ]);
    $this->t3 = ElrTarget::create([
        'elr_stage_id' => $this->station->id,
        'name' => 'T3', 'distance_m' => 930, 'base_points' => 1,
        'max_shots' => 3, 'sort_order' => 3,
    ]);
    $this->t4 = ElrTarget::create([
        'elr_stage_id' => $this->station->id,
        'name' => 'T4', 'distance_m' => 1678, 'base_points' => 1,
        'max_shots' => 3, 'sort_order' => 4,
    ]);

    $this->squad = Squad::create([
        'match_id' => $this->match->id, 'name' => 'Squad A', 'sort_order' => 1,
    ]);

    $this->shooter = Shooter::factory()->create(['squad_id' => $this->squad->id]);
});

// 1\u20133: shot multipliers applied to DISTANCE
it('scores shot 1 hit as distance \u00d7 1.5', function () {
    $svc = new ELRScoringService();
    $shot = $svc->recordShot($this->shooter, $this->t4, 1, ElrShotResult::Hit, $this->owner->id);

    expect((float) $shot->points_awarded)->toBe(round(1678 * 1.5, 2));
});

it('scores shot 2 hit as distance \u00d7 1.25', function () {
    $svc = new ELRScoringService();
    $shot = $svc->recordShot($this->shooter, $this->t4, 2, ElrShotResult::Hit, $this->owner->id);

    expect((float) $shot->points_awarded)->toBe(round(1678 * 1.25, 2));
});

it('scores shot 3 hit as distance \u00d7 1.0', function () {
    $svc = new ELRScoringService();
    $shot = $svc->recordShot($this->shooter, $this->t4, 3, ElrShotResult::Hit, $this->owner->id);

    expect((float) $shot->points_awarded)->toBe(round(1678 * 1.0, 2));
});

// 4: miss = 0
it('scores misses as zero regardless of distance or shot number', function () {
    $svc = new ELRScoringService();

    foreach ([1, 2, 3] as $n) {
        $shot = $svc->recordShot($this->shooter, $this->t4, $n, ElrShotResult::Miss, $this->owner->id);
        expect((float) $shot->points_awarded)->toBe(0.0);
    }
});

// 5: shooter total = sum of shot points
it('sums shooter total across all hits', function () {
    $svc = new ELRScoringService();
    $svc->recordShot($this->shooter, $this->t1, 1, ElrShotResult::Hit);
    $svc->recordShot($this->shooter, $this->t2, 1, ElrShotResult::Hit);
    $svc->recordShot($this->shooter, $this->t4, 2, ElrShotResult::Hit);

    $expected = round(597 * 1.5, 2) + round(840 * 1.5, 2) + round(1678 * 1.25, 2);

    $data = $svc->calculateStandings($this->match);
    $first = collect($data['standings'])->firstWhere('id', $this->shooter->id);

    expect($first['total_points'])->toBe(round($expected, 2));
});

// 6: team total = sum of both shooters
it('sums team total across both shooters', function () {
    $team = Team::create(['match_id' => $this->match->id, 'name' => 'Pair', 'max_size' => 2, 'sort_order' => 1]);
    $a = Shooter::factory()->create(['squad_id' => $this->squad->id, 'team_id' => $team->id]);
    $b = Shooter::factory()->create(['squad_id' => $this->squad->id, 'team_id' => $team->id]);

    $svc = new ELRScoringService();
    $svc->recordShot($a, $this->t1, 1, ElrShotResult::Hit);
    $svc->recordShot($b, $this->t2, 1, ElrShotResult::Hit);

    $team->load('shooters');
    $shotsByShooter = \App\Models\ElrShot::whereIn('shooter_id', [$a->id, $b->id])
        ->get()->groupBy('shooter_id');

    $expected = round(597 * 1.5, 2) + round(840 * 1.5, 2);
    expect($team->elrTotalScore($shotsByShooter))->toBe(round($expected, 2));
});

// 7: relative % vs top
it('calculates relative % against the top shooter', function () {
    $other = Shooter::factory()->create(['squad_id' => $this->squad->id]);

    $svc = new ELRScoringService();
    $svc->recordShot($this->shooter, $this->t4, 1, ElrShotResult::Hit);
    $svc->recordShot($other, $this->t1, 1, ElrShotResult::Hit);

    $data = $svc->calculateStandings($this->match);
    $first = $data['standings'][0];
    $second = $data['standings'][1];

    expect($first['normalized_score'])->toBe(100.0)
        ->and($second['normalized_score'])->toBeLessThan(100.0)
        ->and($second['normalized_score'])->toBeGreaterThan(0.0);
});

// 8: hit rate = hits / shots fired
it('calculates hit rate as hits over shots fired', function () {
    $svc = new ELRScoringService();
    $svc->recordShot($this->shooter, $this->t1, 1, ElrShotResult::Hit);
    $svc->recordShot($this->shooter, $this->t1, 2, ElrShotResult::Miss);
    $svc->recordShot($this->shooter, $this->t2, 1, ElrShotResult::Hit);
    $svc->recordShot($this->shooter, $this->t2, 2, ElrShotResult::Hit);

    $data = $svc->calculateStandings($this->match);
    $entry = collect($data['standings'])->firstWhere('id', $this->shooter->id);

    expect($entry['shots_fired'])->toBe(4)
        ->and($entry['total_hits'])->toBe(3)
        ->and($entry['hit_rate_pct'])->toBe(75.0);
});

// 9: Minor only receives T1\u2013T3
it('only counts targets assigned to a shooters division (Minor = T1\u2013T3)', function () {
    $minor = MatchDivision::create(['match_id' => $this->match->id, 'name' => 'Minor', 'sort_order' => 1]);
    DB::table('elr_division_targets')->insert([
        ['match_division_id' => $minor->id, 'elr_target_id' => $this->t1->id, 'created_at' => now(), 'updated_at' => now()],
        ['match_division_id' => $minor->id, 'elr_target_id' => $this->t2->id, 'created_at' => now(), 'updated_at' => now()],
        ['match_division_id' => $minor->id, 'elr_target_id' => $this->t3->id, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->shooter->update(['match_division_id' => $minor->id]);

    $svc = new ELRScoringService();
    $svc->recordShot($this->shooter, $this->t1, 1, ElrShotResult::Hit);
    $svc->recordShot($this->shooter, $this->t4, 1, ElrShotResult::Hit); // shouldn't count

    $data = $svc->calculateStandings($this->match);
    $entry = collect($data['standings'])->firstWhere('id', $this->shooter->id);

    expect($entry['total_points'])->toBe(round(597 * 1.5, 2));
});

// 10: Major only receives T2\u2013T4
it('only counts targets assigned to a shooters division (Major = T2\u2013T4)', function () {
    $major = MatchDivision::create(['match_id' => $this->match->id, 'name' => 'Major', 'sort_order' => 2]);
    DB::table('elr_division_targets')->insert([
        ['match_division_id' => $major->id, 'elr_target_id' => $this->t2->id, 'created_at' => now(), 'updated_at' => now()],
        ['match_division_id' => $major->id, 'elr_target_id' => $this->t3->id, 'created_at' => now(), 'updated_at' => now()],
        ['match_division_id' => $major->id, 'elr_target_id' => $this->t4->id, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->shooter->update(['match_division_id' => $major->id]);

    $svc = new ELRScoringService();
    $svc->recordShot($this->shooter, $this->t1, 1, ElrShotResult::Hit); // shouldn't count
    $svc->recordShot($this->shooter, $this->t4, 1, ElrShotResult::Hit);

    $data = $svc->calculateStandings($this->match);
    $entry = collect($data['standings'])->firstWhere('id', $this->shooter->id);

    expect($entry['total_points'])->toBe(round(1678 * 1.5, 2));
});

// 11: furthest impact
it('records furthest impact correctly', function () {
    $svc = new ELRScoringService();
    $svc->recordShot($this->shooter, $this->t1, 1, ElrShotResult::Hit);
    $svc->recordShot($this->shooter, $this->t3, 2, ElrShotResult::Hit);
    $svc->recordShot($this->shooter, $this->t4, 3, ElrShotResult::Miss);

    $data = $svc->calculateStandings($this->match);
    $entry = collect($data['standings'])->firstWhere('id', $this->shooter->id);

    expect($entry['furthest_hit_m'])->toBe(930);
});

// 12: furthest first-round impact (metres, not just count)
it('records furthest first-round impact in metres', function () {
    $svc = new ELRScoringService();
    $svc->recordShot($this->shooter, $this->t1, 1, ElrShotResult::Hit);
    $svc->recordShot($this->shooter, $this->t3, 1, ElrShotResult::Hit);
    $svc->recordShot($this->shooter, $this->t4, 2, ElrShotResult::Hit); // shot 2 \u2014 not first round

    $data = $svc->calculateStandings($this->match);
    $entry = collect($data['standings'])->firstWhere('id', $this->shooter->id);

    expect($entry['furthest_first_round_hit_m'])->toBe(930)
        ->and($entry['first_round_hits'])->toBe(2);
});

// 13: editing match settings does NOT change historical scores
it('preserves historical scores when the target distance is edited after scoring', function () {
    $svc = new ELRScoringService();
    $svc->recordShot($this->shooter, $this->t4, 1, ElrShotResult::Hit);

    $originalPoints = (float) \App\Models\ElrShot::where('shooter_id', $this->shooter->id)
        ->where('elr_target_id', $this->t4->id)
        ->where('shot_number', 1)
        ->value('points_awarded');

    // Match director edits the target distance after the fact.
    $this->t4->update(['distance_m' => 9999]);

    // Standings should still reflect the snapshot from the moment of scoring,
    // not the new live distance.
    $data = $svc->calculateStandings($this->match->fresh());
    $entry = collect($data['standings'])->firstWhere('id', $this->shooter->id);

    expect($entry['total_points'])->toBe(round($originalPoints, 2))
        ->and($entry['furthest_hit_m'])->toBe(1678); // snapshot distance, not 9999
});

it('preserves historical scores when the profile multipliers are edited after scoring', function () {
    $svc = new ELRScoringService();
    $svc->recordShot($this->shooter, $this->t4, 1, ElrShotResult::Hit);

    $originalPoints = (float) \App\Models\ElrShot::where('shooter_id', $this->shooter->id)
        ->where('elr_target_id', $this->t4->id)
        ->where('shot_number', 1)
        ->value('points_awarded');

    // Editing the profile must not retroactively change leaderboard totals.
    $this->profile->update(['multipliers' => [10.0, 10.0, 10.0]]);

    $data = $svc->calculateStandings($this->match->fresh());
    $entry = collect($data['standings'])->firstWhere('id', $this->shooter->id);

    // points_awarded was stored at recordShot time, calculateStandings
    // sums that stored value \u2014 not a fresh recompute.
    expect($entry['total_points'])->toBe(round($originalPoints, 2));
});
