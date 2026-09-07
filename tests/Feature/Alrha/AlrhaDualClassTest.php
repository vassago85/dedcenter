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
use Livewire\Volt\Volt;

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

it('scoreboard filters dual-class standings by hunters and varmint', function () {
    $ctx = alrhaDualBuild();

    $hunter = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id,
        'name' => 'Alice Far',
        'alrha_class' => AlrhaClass::Hunters->value,
    ]);
    $varmint = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id,
        'name' => 'Bob Near',
        'alrha_class' => AlrhaClass::Varmint->value,
    ]);

    alrhaShootDual($hunter->id, $ctx['stagesByClass']['hunters']['far_targets'][0], [1]);
    alrhaShootDual($varmint->id, $ctx['stagesByClass']['varmint']['far_targets'][0], [1]);

    $this->actingAs($ctx['owner']);

    Volt::test('scoreboard', ['match' => $ctx['match']])
        ->assertSee('Alice Far')
        ->assertSee('Bob Near')
        ->assertSee('LR Hunters')
        ->assertSee('LR Varmint')
        ->call('filterAlrhaClass', 'hunters')
        ->assertSee('Alice Far')
        ->assertDontSee('Bob Near')
        ->call('filterAlrhaClass', 'varmint')
        ->assertSee('Bob Near')
        ->assertDontSee('Alice Far');
});

/**
 * The scoreboards.alrha include owns the ALRHA-specific UI. This test
 * pins the shape a spectator sees when hitting /scoreboard/{match} for
 * a dual-class ALRHA match:
 *
 *   - CLASS chips (All / LR Hunters / LR Varmint)
 *   - ALRHA prize-table columns: Points, 1st Rd, Furthest (m)
 *   - Dedicated Cold Bore Challenge tab and Categories tab
 *   - No generic "Score" gong-total column from the standard/RF table
 *   - No PRS-only "TB Time / N/T" columns
 *
 * The generic table would render for ALRHA if the dispatcher fell
 * through to scoreboards/royal-flush (the old behaviour that hid the
 * ALRHA columns behind an empty Hits / Miss / Score table).
 */
it('scoreboard renders ALRHA-specific columns and tabs, not the generic gong table', function () {
    $ctx = alrhaDualBuild();

    $hunter = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id,
        'name' => 'Alice Far',
        'alrha_class' => AlrhaClass::Hunters->value,
    ]);
    $varmint = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id,
        'name' => 'Bob Near',
        'alrha_class' => AlrhaClass::Varmint->value,
    ]);

    alrhaShootDual($hunter->id, $ctx['stagesByClass']['hunters']['far_targets'][0], [1]);
    alrhaShootDual($varmint->id, $ctx['stagesByClass']['varmint']['far_targets'][0], [1]);

    $this->actingAs($ctx['owner']);

    Volt::test('scoreboard', ['match' => $ctx['match']])
        // Class chips and tabs the ALRHA template contributes.
        ->assertSee('CLASS')
        ->assertSee('LR Hunters')
        ->assertSee('LR Varmint')
        ->assertSee('Standings')
        ->assertSee('Cold Bore Challenge')
        ->assertSee('Categories')
        // ALRHA prize columns (not present in the standard/RF template).
        ->assertSee('Points')
        ->assertSee('1st', false)
        ->assertSee('Furthest', false)
        // Generic table headings that would leak through if the
        // dispatcher fell back to royal-flush for ALRHA.
        ->assertDontSee('Detailed Breakdown')
        ->assertDontSee('Royal Flush')
        ->assertDontSee('TB Time')
        ->assertDontSee('N/T');
});

/**
 * Switching to the Cold Bore Challenge tab must swap the table body
 * for the per-class CBC prize table, not the generic standings. Pins
 * the ALRHA tab wiring in scoreboards/alrha.blade.php.
 */
it('scoreboard cold bore tab renders per-class CBC prize tables', function () {
    $ctx = alrhaDualBuild();

    $hunter = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id,
        'name' => 'CBC Hunter',
        'alrha_class' => AlrhaClass::Hunters->value,
    ]);
    $varmint = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id,
        'name' => 'CBC Varmint',
        'alrha_class' => AlrhaClass::Varmint->value,
    ]);

    alrhaShootDual($hunter->id, $ctx['stagesByClass']['hunters']['cbc_target'], [1]);
    alrhaShootDual($varmint->id, $ctx['stagesByClass']['varmint']['cbc_target'], [1]);

    $this->actingAs($ctx['owner']);

    Volt::test('scoreboard', ['match' => $ctx['match']])
        ->call('setTab', 'cbc')
        // Both class CBC prize tables render (dual-class match).
        ->assertSee('CBC Hunter')
        ->assertSee('CBC Varmint')
        ->assertSee(AlrhaClass::Hunters->coldBoreTargetName())
        ->assertSee(AlrhaClass::Varmint->coldBoreTargetName());
});

/**
 * Clicking a shooter row on the ALRHA Standings tab must expand into
 * the per-stage / per-target / per-shot breakdown so a spectator can
 * see *why* the total is what it is. Pins:
 *   - toggleExpand($shooterId) drives the disclosure state.
 *   - The scoreboards.partials.alrha-shooter-breakdown partial renders
 *     each stage label and each target name.
 *   - CBC targets carry a "CBC" chip in the expanded body so the "why
 *     doesn't my Cold Bore count?" story is visible in-line.
 */
it('scoreboard standings row expands into per-stage per-target breakdown', function () {
    $ctx = alrhaDualBuild();

    $hunter = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id,
        'name' => 'Breakdown Hunter',
        'alrha_class' => AlrhaClass::Hunters->value,
    ]);

    // Score CBC (should badge as CBC in the breakdown) and one Far target.
    alrhaShootDual($hunter->id, $ctx['stagesByClass']['hunters']['cbc_target'], [1]);
    alrhaShootDual($hunter->id, $ctx['stagesByClass']['hunters']['far_targets'][0], [1, 2]);

    $this->actingAs($ctx['owner']);

    Volt::test('scoreboard', ['match' => $ctx['match']])
        ->call('filterAlrhaClass', 'hunters')
        ->call('toggleExpand', $hunter->id)
        // Stage labels from the alrhaDualBuild fixture.
        ->assertSee('H CBC')
        ->assertSee('H Far')
        // Target rows in the breakdown.
        ->assertSee(AlrhaClass::Hunters->coldBoreTargetName())
        // CBC chip: makes clear this target doesn't feed the main total.
        ->assertSee('CBC');
});

/**
 * Dual-class expand must only show the class the shooter actually
 * shot. A Hunter opening their row must not see Varmint Far/Near/CBC
 * (and vice versa) — those cards were empty zeros and looked like
 * missed stages.
 */
it('scoreboard expand hides the other class stages on a dual-class match', function () {
    $ctx = alrhaDualBuild();

    $hunter = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id,
        'name' => 'Hunter Only',
        'alrha_class' => AlrhaClass::Hunters->value,
    ]);
    $varmint = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id,
        'name' => 'Varmint Only',
        'alrha_class' => AlrhaClass::Varmint->value,
    ]);

    alrhaShootDual($hunter->id, $ctx['stagesByClass']['hunters']['far_targets'][0], [1]);
    alrhaShootDual($varmint->id, $ctx['stagesByClass']['varmint']['far_targets'][0], [1]);

    $this->actingAs($ctx['owner']);

    Volt::test('scoreboard', ['match' => $ctx['match']])
        ->call('filterAlrhaClass', 'hunters')
        ->call('toggleExpand', $hunter->id)
        ->assertSee('H Far')
        ->assertSee('H Near')
        ->assertSee('H CBC')
        ->assertDontSee('V Far')
        ->assertDontSee('V Near')
        ->assertDontSee('V CBC');

    Volt::test('scoreboard', ['match' => $ctx['match']])
        ->call('filterAlrhaClass', 'varmint')
        ->call('toggleExpand', $varmint->id)
        ->assertSee('V Far')
        ->assertSee('V Near')
        ->assertSee('V CBC')
        ->assertDontSee('H Far')
        ->assertDontSee('H Near')
        ->assertDontSee('H CBC');
});

/**
 * A cold-bore hit is a rare, notable event — it doesn't roll into the
 * class prize points but the shooter did it. Pin that the standings
 * row surfaces a "CBC ✓" chip when cbc_hits > 0, so a spectator sees
 * who nailed the cold-bore without opening the CBC tab.
 */
it('scoreboard standings row shows a CBC chip when the shooter hit the cold bore', function () {
    $ctx = alrhaDualBuild();

    $withCbc = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id,
        'name' => 'Cold Bore Ace',
        'alrha_class' => AlrhaClass::Hunters->value,
    ]);
    $noCbc = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id,
        'name' => 'Cold Bore Miss',
        'alrha_class' => AlrhaClass::Hunters->value,
    ]);

    alrhaShootDual($withCbc->id, $ctx['stagesByClass']['hunters']['cbc_target'], [1]);
    alrhaShootDual($withCbc->id, $ctx['stagesByClass']['hunters']['far_targets'][0], [1]);
    // "Miss" shooter puts one round on paper but doesn't get the CBC.
    alrhaShootDual($noCbc->id, $ctx['stagesByClass']['hunters']['far_targets'][0], [1]);

    $this->actingAs($ctx['owner']);

    Volt::test('scoreboard', ['match' => $ctx['match']])
        ->call('filterAlrhaClass', 'hunters')
        // The CBC chip is the literal string "CBC ✓" in the row body.
        ->assertSee('CBC ✓', false);
});

/**
 * The Prize Book tab lays out every ALRHA prize table in the printed-
 * programme order (Hunter Team → Hunter Individual → Hunter category
 * slices → Varmint Open → Varmint Junior → Varmint Ladies) as an
 * accordion. Pins that all six reference sections show up for a
 * dual-class match with hunter teams, junior hunters, and ladies.
 */
it('scoreboard prize book tab surfaces all ALRHA prize sections', function () {
    $ctx = alrhaDualBuild();

    $openCat = MatchCategory::create([
        'match_id' => $ctx['match']->id, 'name' => 'Open', 'slug' => 'open', 'sort_order' => 0,
    ]);
    $ladiesCat = MatchCategory::create([
        'match_id' => $ctx['match']->id, 'name' => 'Ladies', 'slug' => 'ladies', 'sort_order' => 1,
    ]);
    $juniorCat = MatchCategory::create([
        'match_id' => $ctx['match']->id, 'name' => 'Junior', 'slug' => 'junior', 'sort_order' => 2,
    ]);

    $teamA = Team::create([
        'match_id' => $ctx['match']->id, 'name' => 'Team A', 'max_size' => 2, 'sort_order' => 1,
    ]);
    $hunter1 = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id, 'team_id' => $teamA->id, 'name' => 'Hunter Adult',
        'alrha_class' => AlrhaClass::Hunters->value,
    ]);
    $hunter2 = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id, 'team_id' => $teamA->id, 'name' => 'Hunter Jnr',
        'alrha_class' => AlrhaClass::Hunters->value,
    ]);
    $hunter2->categories()->attach($juniorCat->id);

    $varmOpen = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id, 'name' => 'Varmint Open',
        'alrha_class' => AlrhaClass::Varmint->value,
    ]);
    $varmOpen->categories()->attach($openCat->id);
    $varmLadies = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id, 'name' => 'Varmint Ladies',
        'alrha_class' => AlrhaClass::Varmint->value,
    ]);
    $varmLadies->categories()->attach($ladiesCat->id);
    $varmJnr = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id, 'name' => 'Varmint Jnr',
        'alrha_class' => AlrhaClass::Varmint->value,
    ]);
    $varmJnr->categories()->attach($juniorCat->id);

    // One hit each so every prize table has a non-empty rows[] and the
    // Prize Book actually renders the section headers instead of
    // skipping them as empty.
    alrhaShootDual($hunter1->id, $ctx['stagesByClass']['hunters']['far_targets'][0], [1]);
    alrhaShootDual($hunter2->id, $ctx['stagesByClass']['hunters']['far_targets'][0], [1]);
    alrhaShootDual($varmOpen->id, $ctx['stagesByClass']['varmint']['far_targets'][0], [1]);
    alrhaShootDual($varmLadies->id, $ctx['stagesByClass']['varmint']['far_targets'][0], [1]);
    alrhaShootDual($varmJnr->id, $ctx['stagesByClass']['varmint']['far_targets'][0], [1]);
    alrhaShootDual($hunter1->id, $ctx['stagesByClass']['hunters']['cbc_target'], [1]);

    $this->actingAs($ctx['owner']);

    Volt::test('scoreboard', ['match' => $ctx['match']])
        ->call('setTab', 'prize-book')
        // Reference sections from the printed programme.
        ->assertSee('Hunter Team')
        ->assertSee('Hunter Individual')
        ->assertSee('Hunter Junior')
        ->assertSee('Varmint Open')
        ->assertSee('Varmint Junior')
        ->assertSee('Varmint Ladies')
        // CBC prize table is included per class so year-end prizes cover it too.
        ->assertSee('Hunter Cold Bore Challenge');
});

/**
 * Hunters is a team class, so the Teams tab and the Prize Book Team
 * section MUST lead with the team's registered name — not a "Shooter1
 * & Shooter2" concat, which reads like the team name and is what the
 * scoreboard used to render. The pair label stays as a subtitle so
 * spectators can still see who's on the team.
 */
it('scoreboard teams tab leads with the team name, not the pair concat', function () {
    $ctx = alrhaDualBuild();

    $rangeRiders = Team::create([
        'match_id' => $ctx['match']->id,
        'name' => 'Range Riders',
        'max_size' => 2,
        'sort_order' => 1,
    ]);

    $jani = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id, 'team_id' => $rangeRiders->id,
        'name' => 'Jani Goosen', 'alrha_class' => AlrhaClass::Hunters->value,
    ]);
    $morne = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id, 'team_id' => $rangeRiders->id,
        'name' => 'Morne vd Merwe', 'alrha_class' => AlrhaClass::Hunters->value,
    ]);

    alrhaShootDual($jani->id, $ctx['stagesByClass']['hunters']['far_targets'][0], [1, 2]);
    alrhaShootDual($morne->id, $ctx['stagesByClass']['hunters']['far_targets'][0], [1]);

    $this->actingAs($ctx['owner']);

    Volt::test('scoreboard', ['match' => $ctx['match']])
        ->call('filterAlrhaClass', 'hunters')
        ->call('setTab', 'teams')
        // Team name is the primary label on the Teams tab.
        ->assertSee('Range Riders')
        // Pair concat is still visible (as a subtitle) so viewers know the roster.
        ->assertSee('Jani Goosen & Morne vd Merwe');
});
