<?php

/**
 * ALRHA hub + reports data-flow regression tests.
 *
 * Pins the "not populated with data" bug reported on Dwandzani /
 * match 55: after the initial ALRHA scoreboard fix (which taught the
 * public scoreboard to read `elr_shots`), three other surfaces still
 * silently reached for the empty `scores` table:
 *
 *   1. `MatchDashboardService::scoringProgress()` — hub said
 *      "0 shots recorded" for ALRHA matches that had shots in
 *      `elr_shots`.
 *   2. `MatchStandingsService::standardStandings()` — hub Top-5
 *      "weighted standings" listed every shooter at 0.0 pts /
 *      0 hits / 0 misses for ALRHA.
 *   3. `MatchReportService::generateReport()` — shooter match report
 *      preview (`/…/matches/{id}/report/preview`) showed Rank #1,
 *      Score 0.0 / 0.0, Hits 0, Misses 0.
 *
 * Fix routed all three through the ELR pipeline via
 * `usesElrPipeline()` / the new `standingsFor()` dispatcher / a new
 * `generateAlrhaReport()`. These tests fail loudly if any surface
 * ever drifts back onto the `scores` table for an ALRHA match.
 */

use App\Enums\AlrhaClass;
use App\Enums\ElrShotResult;
use App\Enums\ElrStageType;
use App\Models\ElrScoringProfile;
use App\Models\ElrStage;
use App\Models\ElrTarget;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\User;
use App\Services\MatchDashboardService;
use App\Services\MatchReportService;
use App\Services\MatchStandingsService;
use App\Services\Scoring\ELRScoringService;

/**
 * Local rig helpers — mirror the setup in AlrhaScoringTest.php but
 * scoped to this file (via function_exists) so we don't accidentally
 * couple to Pest's file load order. Same shape / same enums / same
 * profile so the rows the services return are directly comparable.
 */
if (! function_exists('alrhaBuild')) {
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
}

if (! function_exists('alrhaShoot')) {
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
}

it('hub scoring progress reports shots recorded from elr_shots for ALRHA', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'Hub Alice']);
    // 5 far-target shots, 3 hits (5+3+2 = 10 pts).
    alrhaShoot($shooter->id, $ctx['farTargets'][0], [1, 3, 4]);

    $dash = (new MatchDashboardService)->build($ctx['match']);

    // Was 0 pre-fix (Score::whereIn(...) hit the empty scores table).
    expect($dash['scoring_progress']['shots_recorded'])->toBe(5);
    expect($dash['scoring_progress']['hits'])->toBe(3);
    expect($dash['scoring_progress']['misses'])->toBe(2);
    expect($dash['scoring_progress']['completion_pct'])->toBe(60.0);
});

it('MatchStandingsService::standingsFor returns ALRHA rows with non-zero total_score', function () {
    $ctx = alrhaBuild('varmint');
    $alice = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'Top Alice']);
    $bob = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'Runner-up Bob']);

    // Alice: 5+3+2 = 10 pts. Bob: 5 = 5 pts.
    alrhaShoot($alice->id, $ctx['farTargets'][0], [1, 3, 4]);
    alrhaShoot($bob->id, $ctx['farTargets'][0], [1]);

    $standings = (new MatchStandingsService)->standingsFor($ctx['match']);
    $ranked = $standings->filter(fn ($r) => $r->rank !== null)->values();

    // Was empty / all-zero pre-fix (standardStandings() joins on
    // scores/gongs which are empty for ALRHA).
    expect($ranked)->toHaveCount(2);
    expect($ranked[0]->name)->toBe('Top Alice');
    expect($ranked[0]->rank)->toBe(1);
    expect($ranked[0]->total_score)->toBe(10.0);
    expect($ranked[0]->hits)->toBe(3);
    expect($ranked[0]->misses)->toBe(2); // 5 shots fired − 3 hits
    expect($ranked[1]->name)->toBe('Runner-up Bob');
    expect($ranked[1]->rank)->toBe(2);
    expect($ranked[1]->total_score)->toBe(5.0);
});

it('MatchStandingsService::standingsFor excludes CBC from the ALRHA total_score', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'CBC Aware']);

    // 5 far-target hits (all shots) → 5+4+3+2+1 = 15 pts. Plus a CBC
    // hit that must NOT feed the class score row.
    alrhaShoot($shooter->id, $ctx['cbcTarget'], [1]);
    alrhaShoot($shooter->id, $ctx['farTargets'][0], [1, 2, 3, 4, 5]);

    $row = (new MatchStandingsService)
        ->standingsFor($ctx['match'])
        ->firstWhere('shooter_id', $shooter->id);

    // 15 pts, not 20 — CBC 5 pts stays off the standings row per
    // ALRHA rules §4. Same for hits/misses (5 hits, 0 misses, not 6/0).
    expect($row->total_score)->toBe(15.0);
    expect($row->hits)->toBe(5);
    expect($row->misses)->toBe(0);
});

// Dual-class hub merging (Hunters + Varmint on one Top-5) is
// exercised indirectly by the existing AlrhaDualClassTest suite; a
// dedicated regression here would need its own dual-class fixture
// scaffold (both class rigs, stage `alrha_class` tags) which is out
// of scope for this data-flow bug.

it('MatchReportService::generateReport populates ALRHA summary from elr_shots, not zeros', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'Report Alice']);
    alrhaShoot($shooter->id, $ctx['farTargets'][0], [1, 3, 4]); // 10 pts, 3 hits, 2 misses
    alrhaShoot($shooter->id, $ctx['cbcTarget'], [1]);           // CBC hit — must NOT roll in

    $report = (new MatchReportService)->generateReport($ctx['match'], $shooter);

    // Was 0/0/0 pre-fix (match() fell through to default =>
    // generateStandardReport, which reads the empty scores table).
    expect($report['summary']['total_score'])->toBe(10.0);
    expect($report['summary']['hits'])->toBe(3);
    expect($report['summary']['misses'])->toBe(2);
    // CBC surfaces separately so the shooter can see they hit it
    // even though it doesn't count towards their match score.
    expect($report['summary']['cbc_hits'] ?? null)->toBe(1);
    expect($report['summary']['cbc_points'] ?? null)->toBe(5.0);

    // Rank must be within the shooter's class (1-of-1 here).
    expect($report['placement']['rank'])->toBe(1);
    expect($report['placement']['total'])->toBe(1);

    // Match block tags the ALRHA class so downstream views can
    // render the class chip / prize table branch.
    expect($report['match']['scoring_type'])->toBe('alrha');
    expect($report['match']['alrha_class'] ?? null)->toBe('varmint');
});

it('public ALRHA shooter report route renders points from elr_shots, not zeros', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'Preview Alice']);
    alrhaShoot($shooter->id, $ctx['farTargets'][0], [1, 3, 4]); // 10 pts

    // Public per-shooter report is what shooters follow from the
    // scoreboard link. Was silently rendering 0/0 for ALRHA pre-fix.
    $ctx['match']->update(['scores_published' => true]);

    $this->get(url('/scoreboard/'.$ctx['match']->id.'/report/'.$shooter->id))
        ->assertOk()
        ->assertSee('Preview Alice')
        ->assertSee('10.0');
});

it('MatchStandingsService::elrStandings returns non-zero total_score for ELR matches', function () {
    // Sibling regression: same fix applied to ELR so hubs of pure ELR
    // matches don't accidentally drift back onto the empty scores
    // table if a caller ever swaps to standingsFor() for ELR too.
    $ctx = alrhaBuild('varmint'); // build supplies an ELR-shaped rig
    $ctx['match']->update(['scoring_type' => 'elr']);
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'ELR Alice']);
    alrhaShoot($shooter->id, $ctx['farTargets'][0], [1]); // 5 pts under 5-4-3-2-1

    $rows = (new MatchStandingsService)->elrStandings($ctx['match']);

    expect($rows)->toHaveCount(1);
    expect($rows->first()->total_score)->toBeGreaterThan(0.0);
    expect($rows->first()->name)->toBe('ELR Alice');
});
