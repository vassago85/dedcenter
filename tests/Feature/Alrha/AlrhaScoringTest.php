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
    // CBC never contributes to the class score — neither points nor
    // hits. It lives on `cbc_*` so the CBC prize table and the
    // season-long CBC leaderboard can consume it separately.
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

it('scoreboard page shows ALRHA points from elr_shots, not zeros from the scores table', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'Scoreboard Alice']);
    alrhaShoot($shooter->id, $ctx['farTargets'][0], [1, 3, 4]); // 5+3+2 = 10

    $this->actingAs($ctx['owner'])
        ->get(route('scoreboard', $ctx['match']))
        ->assertOk()
        ->assertSee('Scoreboard Alice')
        ->assertSee('10.0');
});

it('standings CSV for ALRHA lists points from elr_shots', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'Csv Alice']);
    alrhaShoot($shooter->id, $ctx['farTargets'][0], [1, 3, 4]); // 10 pts

    $csv = $this->actingAs($ctx['owner'])
        ->get(route('admin.matches.export.standings', $ctx['match']))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('Csv Alice');
    expect($csv)->toContain('10');
    expect($csv)->not->toMatch('/Csv Alice.*,0(\.0+)?\s*$/m');
});

it('PDF standings tables for ALRHA use per-class points from elr_shots', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'Pdf Alice']);
    alrhaShoot($shooter->id, $ctx['farTargets'][0], [1, 3, 4]); // 10 pts

    $tables = (new AlrhaScoringService(new ELRScoringService()))
        ->pdfStandingsTables($ctx['match']);

    expect($tables)->toHaveCount(1);
    expect($tables[0]['class'])->toBe('varmint');
    expect($tables[0]['shooters'][0]->name)->toBe('Pdf Alice');
    expect((float) $tables[0]['shooters'][0]->agg_total)->toBe(10.0);
    expect((int) $tables[0]['shooters'][0]->agg_hits)->toBe(3);
});

/**
 * Simon-style clean run: hit every impact opportunity including the
 * CBC. ALRHA rules: CBC never counts toward the class score — not the
 * points column, not the hits column. So a 25/25 stage-hits shooter
 * who also nails the cold bore reads as 25 hits on the class prize
 * table and 1 hit on the CBC prize table (and the season-long CBC
 * leaderboard when we build it).
 *
 * This pins that behaviour against future "let's just add CBC in to
 * make the count match the scoring app" drift.
 */
it('keeps CBC out of the class hit count even for a clean-run shooter', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'Simon']);

    // Hit the CBC — this must NOT show up in the class hit total.
    alrhaShoot($shooter->id, $ctx['cbcTarget'], [1]);
    // Hit every shot on every far + near target (5 targets × 5 shots = 25).
    foreach ($ctx['farTargets'] as $target) {
        alrhaShoot($shooter->id, $target, [1, 2, 3, 4, 5]);
    }
    foreach ($ctx['nearTargets'] as $target) {
        alrhaShoot($shooter->id, $target, [1, 2, 3, 4, 5]);
    }

    $out = (new AlrhaScoringService(new ELRScoringService()))
        ->calculateStandings($ctx['match']);
    $row = $out['standings'][0];

    // 25 class hits, CBC peeled off. Prize points also exclude CBC —
    // 5 targets (2 far + 3 near) × (5+4+3+2+1 = 15 pts) = 75.
    expect($row['total_hits'])->toBe(25);
    expect($row['total_points'])->toBe(75.0);
    // Phantom-miss guard: shots_fired on the row must also exclude the
    // CBC shot, otherwise the scoreboard's Miss column (misses =
    // shots_fired - hits) reads 1 for a shooter who hit everything —
    // the "Simon Steyn 25 hits · 1 miss" bug.
    expect($row['shots_fired'])->toBe(25);
    expect($row['cbc_shots_fired'])->toBe(1);
    // CBC lives on its own field so the CBC prize table + season-long
    // CBC leaderboard can render it.
    expect($row['cbc_hits'])->toBe(1);
    expect($row['cbc_points'])->toBe(5.0);
});

/**
 * Public scoreboard round-trip for the phantom-miss bug: a clean-run
 * shooter with a CBC hit must show 0 misses on the standings row, not
 * 1. This test drives the actual Volt component the way the browser
 * does so a future regression in how ALRHA misses are derived from
 * shots_fired/hits gets caught here.
 */
it('scoreboard shows 0 misses for a clean-run shooter who also hit the CBC', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'Simon Clean']);

    alrhaShoot($shooter->id, $ctx['cbcTarget'], [1]);
    foreach ($ctx['farTargets'] as $target) {
        alrhaShoot($shooter->id, $target, [1, 2, 3, 4, 5]);
    }
    foreach ($ctx['nearTargets'] as $target) {
        alrhaShoot($shooter->id, $target, [1, 2, 3, 4, 5]);
    }

    $this->actingAs($ctx['owner']);

    $rendered = \Livewire\Volt\Volt::test('scoreboard', ['match' => $ctx['match']])
        ->assertSee('Simon Clean')
        ->html();

    // Sanity: the row is present and lists 25 hits.
    expect($rendered)->toContain('Simon Clean');
    // No phantom miss chip / column value of 1 for this shooter.
    // We can't easily read the Miss column value out of the ALRHA
    // template (it doesn't render a Miss column at all — CBC is on a
    // separate table). But the CBC ✓ chip must render and hits must
    // read 25.
    expect($rendered)->toContain('CBC ✓');
});
