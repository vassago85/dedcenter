<?php

/**
 * ALRHA dual-class match acceptance tests.
 *
 * Pins the "one match, both classes" behaviours introduced by the
 * ALRHA dual-class plan:
 *
 *   1. A single ShootingMatch can carry both hunters + varmint stage
 *      trees, tagged via elr_stages.alrha_class.
 *   2. Shooters are partitioned by shooter.alrha_class; a hunter shot
 *      on a hunter target NEVER contributes to varmint standings
 *      (and vice versa).
 *   3. CBC prize table stays per-class.
 *   4. Category prize tables stay per-class (Hunters: open/junior;
 *      Varmint: open/ladies/junior).
 *   5. The scoreboard exposes a `per_class` map keyed by class value
 *      plus back-compat top-level standings for the primary class.
 *   6. Shared-rifle adjacency validation stays class-agnostic — a
 *      hunter + varmint shooter in adjacent relays still trigger the
 *      warning.
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
use App\Services\Scoring\AlrhaSharedRifleValidator;
use App\Services\Scoring\ELRScoringService;

/**
 * Build one dual-class ALRHA match with:
 *   - shared profile
 *   - CBC + Far + Near stages tagged per class
 *   - one shared Squad ("R1")
 * Returns handles the tests need to plant shooters and shots.
 */
function alrhaDualBuild(): array
{
    $owner = User::factory()->create(['role' => 'owner']);

    $match = ShootingMatch::factory()->active()->alrhaDualClass()->create([
        'created_by' => $owner->id,
        'elr_distance_based_scoring' => false,
        'team_size' => 2,
    ]);

    $profile = ElrScoringProfile::create([
        'match_id' => $match->id,
        'name' => 'ALRHA 5-4-3-2-1',
        'multipliers' => [5, 4, 3, 2, 1],
    ]);
    $match->update(['elr_scoring_profile_id' => $profile->id]);

    $stagesByClass = [];
    $sort = 0;
    foreach ([AlrhaClass::Hunters, AlrhaClass::Varmint] as $class) {
        $prefix = $class === AlrhaClass::Hunters ? 'H' : 'V';

        $cbcStage = ElrStage::create([
            'match_id' => $match->id,
            'label' => "$prefix CBC",
            'stage_type' => ElrStageType::Static,
            'elr_scoring_profile_id' => $profile->id,
            'sort_order' => ++$sort,
            'alrha_class' => $class->value,
        ]);
        $cbcTarget = ElrTarget::create([
            'elr_stage_id' => $cbcStage->id,
            'name' => $class->coldBoreTargetName(),
            'distance_m' => $class->coldBoreDistance(),
            'base_points' => 1, 'max_shots' => 1, 'sort_order' => 1,
            'is_cold_bore' => true, 'alrha_block' => 'cbc',
        ]);

        $farStage = ElrStage::create([
            'match_id' => $match->id,
            'label' => "$prefix Far",
            'stage_type' => ElrStageType::Static,
            'elr_scoring_profile_id' => $profile->id,
            'sort_order' => ++$sort,
            'alrha_class' => $class->value,
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
            'match_id' => $match->id,
            'label' => "$prefix Near",
            'stage_type' => ElrStageType::Static,
            'elr_scoring_profile_id' => $profile->id,
            'sort_order' => ++$sort,
            'alrha_class' => $class->value,
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

        $stagesByClass[$class->value] = [
            'cbc_stage' => $cbcStage,
            'cbc_target' => $cbcTarget,
            'far_stage' => $farStage,
            'far_targets' => $farTargets,
            'near_stage' => $nearStage,
            'near_targets' => $nearTargets,
        ];
    }

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'R1', 'sort_order' => 1]);

    return compact('owner', 'match', 'profile', 'squad', 'stagesByClass');
}

function alrhaShootDual(int $shooterId, ElrTarget $target, array $shotNumbersHit): void
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

it('detects dual-class matches from stage tags', function () {
    $ctx = alrhaDualBuild();
    $match = $ctx['match']->fresh();

    expect($match->isAlrha())->toBeTrue();
    expect($match->isDualClassAlrha())->toBeTrue();
    expect(array_map(fn (AlrhaClass $c) => $c->value, $match->alrhaClasses()))
        ->toEqualCanonicalizing(['hunters', 'varmint']);
});

it('partitions standings by shooter class — hunter shots never affect varmint totals', function () {
    $ctx = alrhaDualBuild();

    $hunter = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id, 'name' => 'Hunter A',
        'alrha_class' => AlrhaClass::Hunters->value,
    ]);
    $varmint = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id, 'name' => 'Varmint A',
        'alrha_class' => AlrhaClass::Varmint->value,
    ]);

    // Hunter scores on hunters/far #1: 1+3+4 = 5+3+2 = 10 pts.
    alrhaShootDual($hunter->id, $ctx['stagesByClass']['hunters']['far_targets'][0], [1, 3, 4]);
    // Varmint scores on varmint/far #1: 1+2 = 5+4 = 9 pts.
    alrhaShootDual($varmint->id, $ctx['stagesByClass']['varmint']['far_targets'][0], [1, 2]);

    $service = new AlrhaScoringService(new ELRScoringService());
    $out = $service->calculateStandings($ctx['match']);

    expect($out['match']['is_dual_class'])->toBeTrue();
    expect($out)->toHaveKey('per_class');
    expect(array_keys($out['per_class']))->toEqualCanonicalizing(['hunters', 'varmint']);

    $hunters = $out['per_class']['hunters'];
    $varmints = $out['per_class']['varmint'];

    expect($hunters['standings'])->toHaveCount(1);
    expect($hunters['standings'][0]['name'])->toBe('Hunter A');
    expect($hunters['standings'][0]['total_points'])->toBe(10.0);

    expect($varmints['standings'])->toHaveCount(1);
    expect($varmints['standings'][0]['name'])->toBe('Varmint A');
    expect($varmints['standings'][0]['total_points'])->toBe(9.0);
});

it('keeps CBC per class and excludes it from class totals', function () {
    $ctx = alrhaDualBuild();

    $hunter = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id, 'name' => 'Hunter A',
        'alrha_class' => AlrhaClass::Hunters->value,
    ]);
    $varmint = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id, 'name' => 'Varmint A',
        'alrha_class' => AlrhaClass::Varmint->value,
    ]);

    // Both shoot CBC = 5 pts. Neither should count towards their class total.
    alrhaShootDual($hunter->id, $ctx['stagesByClass']['hunters']['cbc_target'], [1]);
    alrhaShootDual($varmint->id, $ctx['stagesByClass']['varmint']['cbc_target'], [1]);

    // Plus a non-CBC hit each.
    alrhaShootDual($hunter->id, $ctx['stagesByClass']['hunters']['far_targets'][0], [1]);
    alrhaShootDual($varmint->id, $ctx['stagesByClass']['varmint']['far_targets'][0], [1]);

    $service = new AlrhaScoringService(new ELRScoringService());
    $out = $service->calculateStandings($ctx['match']);

    $hunters = $out['per_class']['hunters'];
    $varmints = $out['per_class']['varmint'];

    expect($hunters['standings'][0]['total_points'])->toBe(5.0);
    expect($hunters['standings'][0]['cbc_points'])->toBe(5.0);
    expect($hunters['cbc'])->toHaveCount(1);

    expect($varmints['standings'][0]['total_points'])->toBe(5.0);
    expect($varmints['standings'][0]['cbc_points'])->toBe(5.0);
    expect($varmints['cbc'])->toHaveCount(1);
});

it('builds a hunter-only team leaderboard even in a dual-class match', function () {
    $ctx = alrhaDualBuild();

    $teamA = Team::create([
        'match_id' => $ctx['match']->id, 'name' => 'A', 'max_size' => 2, 'sort_order' => 1,
    ]);

    $a1 = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id, 'team_id' => $teamA->id, 'name' => 'A1',
        'alrha_class' => AlrhaClass::Hunters->value,
    ]);
    $a2 = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id, 'team_id' => $teamA->id, 'name' => 'A2',
        'alrha_class' => AlrhaClass::Hunters->value,
    ]);
    // Varmint shooter with a stray team_id must not appear on the hunters
    // team leaderboard (guard against roster mis-tagging).
    Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id, 'team_id' => $teamA->id, 'name' => 'Vintruder',
        'alrha_class' => AlrhaClass::Varmint->value,
    ]);

    alrhaShootDual($a1->id, $ctx['stagesByClass']['hunters']['far_targets'][0], [1, 2]); // 9
    alrhaShootDual($a2->id, $ctx['stagesByClass']['hunters']['far_targets'][0], [1]);    // 5

    $service = new AlrhaScoringService(new ELRScoringService());
    $out = $service->calculateStandings($ctx['match']);

    expect($out['per_class']['hunters']['teams'])->toHaveCount(1);
    expect($out['per_class']['hunters']['teams'][0]['team_total_points'])->toBe(14.0);
    // Varmint class never builds a team payload, even if a varmint shooter
    // has a team_id.
    expect($out['per_class']['varmint']['teams'])->toBe([]);
});

it('emits per-class category prize tables', function () {
    $ctx = alrhaDualBuild();
    MatchCategory::create([
        'match_id' => $ctx['match']->id, 'name' => 'Open', 'slug' => 'open', 'sort_order' => 0,
    ]);
    MatchCategory::create([
        'match_id' => $ctx['match']->id, 'name' => 'Ladies', 'slug' => 'ladies', 'sort_order' => 1,
    ]);

    Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id, 'name' => 'Hunter A',
        'alrha_class' => AlrhaClass::Hunters->value,
    ]);
    Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id, 'name' => 'Varmint A',
        'alrha_class' => AlrhaClass::Varmint->value,
    ]);

    $service = new AlrhaScoringService(new ELRScoringService());
    $out = $service->calculateStandings($ctx['match']);

    $hunterCats = collect($out['per_class']['hunters']['categories'])->pluck('slug')->all();
    $varmintCats = collect($out['per_class']['varmint']['categories'])->pluck('slug')->all();

    expect($hunterCats)->toEqualCanonicalizing(['open', 'junior']);
    expect($varmintCats)->toEqualCanonicalizing(['open', 'ladies', 'junior']);
});

it('still fires the shared-rifle adjacency warning across classes', function () {
    $ctx = alrhaDualBuild();

    // Two relays; the adjacency validator flags rifle sharing between
    // R1 <-> R2 regardless of class.
    $r2 = Squad::create(['match_id' => $ctx['match']->id, 'name' => 'R2', 'sort_order' => 2]);

    Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id, 'name' => 'Hunter A',
        'alrha_class' => AlrhaClass::Hunters->value,
        'shared_rifle_key' => 'rifle-1',
    ]);
    Shooter::factory()->create([
        'squad_id' => $r2->id, 'name' => 'Varmint A',
        'alrha_class' => AlrhaClass::Varmint->value,
        'shared_rifle_key' => 'rifle-1',
    ]);

    $validator = new AlrhaSharedRifleValidator();
    $conflicts = $validator->findConflicts($ctx['match']->fresh());

    expect($conflicts)->not->toBeEmpty();
    expect($conflicts[0]['key'])->toBe('rifle-1');
    expect(collect($conflicts[0]['shooters'])->pluck('name')->all())
        ->toEqualCanonicalizing(['Hunter A', 'Varmint A']);
});
