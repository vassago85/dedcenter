<?php

/**
 * ALRHA mark-sense score card acceptance tests.
 *
 * Covers the plan's Tests section end-to-end:
 *
 *   1. A squadded Hunters shooter and a Varmint shooter produce the
 *      right target order and bubble count on the printed card.
 *   2. A decoded payload of hit / miss / hit on shots 1-3 writes
 *      points 5, 0, and 3 — nothing else.
 *   3. A Cold Bore hit is stored and does NOT lift the class total.
 *   4. A blank pair leaves an existing shot in place.
 *   5. A foreign token is rejected (must belong to the asked match).
 *   6. Writes to a Completed match return 423.
 *
 * The decoder-side test (bubble decision is a pure function of
 * sampled brightness) lives in tests/Unit/ScoreCardDecoderTest.php
 * and tests/Browser-manual is covered by Volt interaction tests in
 * this file where feasible.
 */

use App\Enums\AlrhaClass;
use App\Enums\ElrShotResult;
use App\Enums\ElrStageType;
use App\Enums\MatchStatus;
use App\Models\ElrScoringProfile;
use App\Models\ElrShot;
use App\Models\ElrStage;
use App\Models\ElrTarget;
use App\Models\ScoreSheet;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\User;
use App\Services\Scoring\ELRScoringService;
use App\Services\Scoring\ScoreSheetLayoutBuilder;
use Laravel\Sanctum\Sanctum;

// Pest loads test files one at a time, so the alrhaBuild / alrhaShoot
// helpers in AlrhaScoringTest.php are not visible here when this file
// runs in isolation. Re-declare them (guarded by function_exists so the
// full test suite still works) — matches the pattern the sibling file
// uses already.
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

it('builds a printable card layout with the correct target order and bubble count for Varmint', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id,
        'name' => 'Varmint Alice',
    ]);

    $sheet = app(ScoreSheetLayoutBuilder::class)->buildForShooter($ctx['match'], $shooter, $ctx['owner']->id);

    expect($sheet)->toBeInstanceOf(ScoreSheet::class);
    expect($sheet->token)->toBeString()->and(strlen($sheet->token))->toBeGreaterThanOrEqual(8);
    expect($sheet->layout['qr']['payload'])->toBe('DCS1:'.$sheet->token);

    // Firing order on the day: cbc first, then far (700, 600), then
    // near (500, 400, 300). Five targets with 5 shots + one CBC target
    // with 1 shot = 26 shots = 52 bubbles (hit + miss per shot).
    $rows = $sheet->layout['rows'];
    expect($rows)->toHaveCount(6);
    expect(array_map(fn ($r) => $r['alrha_block'], $rows))
        ->toBe(['cbc', 'far', 'far', 'near', 'near', 'near']);
    expect(array_map(fn ($r) => $r['distance_m'], $rows))
        ->toBe([700, 700, 600, 500, 400, 300]);
    expect(ScoreSheetLayoutBuilder::totalBubbles($sheet->layout))->toBe(52);

    // Each row carries the right number of shots. CBC = 1, blocks = 5.
    foreach ($rows as $row) {
        $expected = $row['is_cold_bore'] ? 1 : 5;
        expect($row['max_shots'])->toBe($expected);
        expect($row['shots'])->toHaveCount($expected);
    }
});

it('builds a Hunters card with class-specific distances', function () {
    $ctx = alrhaBuild('hunters');
    $shooter = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id,
        'name' => 'Hunter Bob',
    ]);

    $sheet = app(ScoreSheetLayoutBuilder::class)->buildForShooter($ctx['match'], $shooter, $ctx['owner']->id);

    // Hunters firing order: CBC (1000) first, then Far (1000, 900),
    // then Near (700, 600, 400).
    expect(array_map(fn ($r) => $r['distance_m'], $sheet->layout['rows']))
        ->toBe([1000, 1000, 900, 700, 600, 400]);
});

it('imports hit, miss, hit on the first three shots as points 5, 0, 3', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id,
        'name' => 'Decoded Alice',
    ]);
    $sheet = app(ScoreSheetLayoutBuilder::class)->buildForShooter($ctx['match'], $shooter, $ctx['owner']->id);
    $target = $ctx['farTargets'][0]; // 700 m target

    Sanctum::actingAs($ctx['owner'], ['scoring']);

    $payload = [
        'shots' => [
            shot($shooter, $target, 1, 'hit'),
            shot($shooter, $target, 2, 'miss'),
            shot($shooter, $target, 3, 'hit'),
        ],
    ];

    $response = $this->postJson("/api/matches/{$ctx['match']->id}/elr-shots", $payload);
    $response->assertOk();

    $shots = ElrShot::where('shooter_id', $shooter->id)
        ->where('elr_target_id', $target->id)
        ->orderBy('shot_number')
        ->get();

    expect($shots)->toHaveCount(3);
    expect((float) $shots[0]->points_awarded)->toBe(5.0);
    expect((float) $shots[1]->points_awarded)->toBe(0.0); // miss on shot 2 is zero, doesn't shift later shots
    expect((float) $shots[2]->points_awarded)->toBe(3.0);
});

it('stores a Cold Bore hit but keeps it out of the class match total', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id,
        'name' => 'CBC Alice',
    ]);
    app(ScoreSheetLayoutBuilder::class)->buildForShooter($ctx['match'], $shooter, $ctx['owner']->id);

    Sanctum::actingAs($ctx['owner'], ['scoring']);

    // CBC hit + one regular hit (5 pts) on the 700m target shot 1.
    $this->postJson("/api/matches/{$ctx['match']->id}/elr-shots", [
        'shots' => [
            shot($shooter, $ctx['cbcTarget'], 1, 'hit'),
            shot($shooter, $ctx['farTargets'][0], 1, 'hit'),
        ],
    ])->assertOk();

    $service = app(\App\Services\Scoring\AlrhaScoringService::class);
    $out = $service->calculateStandings($ctx['match']);
    $row = collect($out['standings'])->firstWhere('name', 'CBC Alice');

    expect($row)->not->toBeNull();
    expect($row['total_points'])->toBe(5.0);        // regular 700m hit only
    expect($row['total_hits'])->toBe(1);
    expect($row['cbc_hits'])->toBe(1);
    expect($row['cbc_points'])->toBe(5.0);
});

it('a blank pair leaves an existing stored shot in place', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create([
        'squad_id' => $ctx['squad']->id,
        'name' => 'Blank Alice',
    ]);
    $target = $ctx['farTargets'][0];

    // Seed a pre-existing hit from the normal scoring flow.
    alrhaShoot($shooter->id, $target, [1]);
    $before = ElrShot::where('shooter_id', $shooter->id)
        ->where('elr_target_id', $target->id)
        ->where('shot_number', 1)
        ->first();
    expect($before)->not->toBeNull();
    expect((float) $before->points_awarded)->toBe(5.0);

    app(ScoreSheetLayoutBuilder::class)->buildForShooter($ctx['match'], $shooter, $ctx['owner']->id);

    Sanctum::actingAs($ctx['owner'], ['scoring']);

    // Simulate the client confirm after a blank pair on shot 1: the
    // client NEVER sends blank shots — so the payload only contains
    // the shots the MD actually resolved. Here the scanner saw shot 1
    // as blank (no mark) and shot 3 as a hit, so only shot 3 is in
    // the payload.
    $this->postJson("/api/matches/{$ctx['match']->id}/elr-shots", [
        'shots' => [
            shot($shooter, $target, 3, 'hit'),
        ],
    ])->assertOk();

    // The pre-existing hit on shot 1 must be untouched.
    $afterShot1 = ElrShot::where('shooter_id', $shooter->id)
        ->where('elr_target_id', $target->id)
        ->where('shot_number', 1)
        ->first();
    expect($afterShot1)->not->toBeNull();
    expect($afterShot1->result->value)->toBe('hit');
    expect((float) $afterShot1->points_awarded)->toBe(5.0);

    // And the new hit on shot 3 landed.
    $afterShot3 = ElrShot::where('shooter_id', $shooter->id)
        ->where('elr_target_id', $target->id)
        ->where('shot_number', 3)
        ->first();
    expect($afterShot3)->not->toBeNull();
    expect((float) $afterShot3->points_awarded)->toBe(3.0);
});

it('rejects a lookup for a token that belongs to a different match', function () {
    $ctx = alrhaBuild('varmint');
    $otherCtx = alrhaBuild('varmint');

    $shooter = Shooter::factory()->create(['squad_id' => $otherCtx['squad']->id, 'name' => 'Other']);
    $sheet = app(ScoreSheetLayoutBuilder::class)->buildForShooter($otherCtx['match'], $shooter, $otherCtx['owner']->id);

    Sanctum::actingAs($ctx['owner'], ['scoring']);

    $this->getJson("/api/matches/{$ctx['match']->id}/score-sheets/{$sheet->token}")
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'This card was printed for another match.']);

    // And an unknown token is a plain 404.
    $this->getJson("/api/matches/{$ctx['match']->id}/score-sheets/NOT-A-REAL-TOKEN")
        ->assertStatus(404);
});

it('returns the card on a valid in-match lookup, including any stored shots', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'Lookup Alice']);
    $sheet = app(ScoreSheetLayoutBuilder::class)->buildForShooter($ctx['match'], $shooter, $ctx['owner']->id);

    alrhaShoot($shooter->id, $ctx['farTargets'][0], [1]);

    Sanctum::actingAs($ctx['owner'], ['scoring']);

    $response = $this->getJson("/api/matches/{$ctx['match']->id}/score-sheets/{$sheet->token}");
    $response->assertOk();
    $body = $response->json('sheet');

    expect($body['token'])->toBe($sheet->token);
    expect($body['shooter']['name'])->toBe('Lookup Alice');
    expect($body['shooter']['alrha_class'])->toBe('varmint');
    expect($body['layout']['rows'])->toHaveCount(6);
    // The pre-existing hit is handed back so the preview can show a diff.
    $existing = collect($body['existing_shots'])->firstWhere('shot_number', 1);
    expect($existing['result'])->toBe('hit');
});

it('blocks imports against a Completed match with HTTP 423 until it is reopened', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id]);
    app(ScoreSheetLayoutBuilder::class)->buildForShooter($ctx['match'], $shooter, $ctx['owner']->id);

    $ctx['match']->update(['status' => MatchStatus::Completed]);

    Sanctum::actingAs($ctx['owner'], ['scoring']);

    $this->postJson("/api/matches/{$ctx['match']->id}/elr-shots", [
        'shots' => [
            shot($shooter, $ctx['farTargets'][0], 1, 'hit'),
        ],
    ])->assertStatus(423);
});

it('refuses print and scan to a user who cannot score the match', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id]);
    $sheet = app(ScoreSheetLayoutBuilder::class)->buildForShooter($ctx['match'], $shooter, $ctx['owner']->id);

    $intruder = \App\Models\User::factory()->create(['role' => 'shooter']);
    Sanctum::actingAs($intruder, ['scoring']);

    $this->getJson("/api/matches/{$ctx['match']->id}/score-sheets/{$sheet->token}")
        ->assertStatus(403);
});

it('renders the admin print page with the eligible shooter count', function () {
    $ctx = alrhaBuild('varmint');
    Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'Alice']);
    Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'Bob']);

    $this->actingAs($ctx['owner'])
        ->get("/admin/matches/{$ctx['match']->id}/score-cards")
        ->assertOk()
        ->assertSee('ALRHA score cards')
        ->assertSee('Print cards')
        ->assertSee('Open card scanner')
        ->assertSee('2 eligible shooters');
});

it('404s the print page for non-ALRHA matches so other scoring modes stay clean', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $match = ShootingMatch::factory()->active()->create([
        'created_by' => $owner->id,
        'scoring_type' => 'standard',
    ]);

    $this->actingAs($owner)
        ->get("/admin/matches/{$match->id}/score-cards")
        ->assertStatus(404);
});

it('generates a fresh card and PDF on reprint, retiring the previous token', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'Reprint Alice']);

    $builder = app(ScoreSheetLayoutBuilder::class);
    $first = $builder->buildForShooter($ctx['match'], $shooter, $ctx['owner']->id);
    $second = $builder->buildForShooter($ctx['match'], $shooter, $ctx['owner']->id);

    // One active sheet per shooter: the shooter row is reused, the token rotates.
    expect($second->id)->toBe($first->id);
    expect($second->token)->not->toBe($first->token);
    // ScoreSheet should only have one row for this shooter on this match.
    expect(ScoreSheet::where('match_id', $ctx['match']->id)->where('shooter_id', $shooter->id)->count())->toBe(1);
});

it('prints a single-shooter reprint (walk-in / mistake) and only that shooter', function () {
    $ctx = alrhaBuild('varmint');
    $alice = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'Alice']);
    $bob = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'Bob']);

    $response = $this->actingAs($ctx['owner'])
        ->post("/admin/matches/{$ctx['match']->id}/score-cards/print", [
            'shooter_id' => $bob->id,
        ]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');

    // Only Bob got a score sheet row; Alice stays unprinted.
    expect(ScoreSheet::where('match_id', $ctx['match']->id)->pluck('shooter_id')->all())
        ->toBe([$bob->id]);
});

it('rejects a per-shooter reprint pointing at a foreign shooter', function () {
    $ctx = alrhaBuild('varmint');
    $otherCtx = alrhaBuild('varmint');
    $stranger = Shooter::factory()->create(['squad_id' => $otherCtx['squad']->id, 'name' => 'Stranger']);

    $this->actingAs($ctx['owner'])
        ->post("/admin/matches/{$ctx['match']->id}/score-cards/print", [
            'shooter_id' => $stranger->id,
        ])
        ->assertStatus(404);
});

// ─── Anonymous spare cards (walk-ins) ────────────────────────────────

it('prints a stack of anonymous spare cards with no shooter attached', function () {
    $ctx = alrhaBuild('varmint');

    $response = $this->actingAs($ctx['owner'])
        ->post("/admin/matches/{$ctx['match']->id}/score-cards/print-spares", [
            'count' => 3,
        ]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');

    $spares = ScoreSheet::where('match_id', $ctx['match']->id)->unclaimed()->get();
    expect($spares)->toHaveCount(3);
    foreach ($spares as $s) {
        expect($s->shooter_id)->toBeNull();
        expect($s->alrha_class)->toBe(AlrhaClass::Varmint);
        expect($s->claimed_at)->toBeNull();
        // Class-correct layout (Varmint: CBC/700/600/500/400/300).
        expect(array_map(fn ($r) => $r['distance_m'], $s->layout['rows']))
            ->toBe([700, 700, 600, 500, 400, 300]);
    }
});

it('rejects spare printing on a dual-class match without a class picked', function () {
    // Dual-class: two matches with different classes isn't how we model it,
    // so simulate by setting the match's alrha_class to null and tagging
    // stages with explicit class values.
    $ctx = alrhaBuild('varmint');
    $ctx['match']->update(['alrha_class' => null]);
    // Tag some stages as hunters to force dual-class detection.
    ElrStage::where('match_id', $ctx['match']->id)->update(['alrha_class' => 'varmint']);
    // Add a Hunters stage so count(classes) > 1
    $hStage = ElrStage::create([
        'match_id' => $ctx['match']->id,
        'label' => 'H Far',
        'stage_type' => ElrStageType::Static,
        'elr_scoring_profile_id' => $ctx['profile']->id,
        'sort_order' => 10,
        'alrha_class' => 'hunters',
    ]);
    ElrTarget::create([
        'elr_stage_id' => $hStage->id, 'name' => '1000 m', 'distance_m' => 1000,
        'base_points' => 1, 'max_shots' => 5, 'sort_order' => 1, 'alrha_block' => 'far',
    ]);

    $this->actingAs($ctx['owner'])
        ->post("/admin/matches/{$ctx['match']->id}/score-cards/print-spares", [
            'count' => 2,
        ])
        ->assertStatus(422);
});

it('returns is_spare true and no shooter on lookup of an unclaimed spare', function () {
    $ctx = alrhaBuild('varmint');
    $spare = app(ScoreSheetLayoutBuilder::class)
        ->buildAnonymousSpares($ctx['match'], AlrhaClass::Varmint, 1, $ctx['owner']->id)
        ->first();

    Sanctum::actingAs($ctx['owner'], ['scoring']);

    $this->getJson("/api/matches/{$ctx['match']->id}/score-sheets/{$spare->token}")
        ->assertOk()
        ->assertJsonPath('sheet.is_spare', true)
        ->assertJsonPath('sheet.shooter', null)
        ->assertJsonPath('sheet.alrha_class', 'varmint')
        ->assertJsonPath('sheet.existing_shots', [])
        ->assertJsonStructure(['sheet' => ['assignable_shooters']]);
});

it('claims a spare card atomically, binding shooter_id and claimed_at', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id, 'name' => 'Walk-In Wanda']);
    $spare = app(ScoreSheetLayoutBuilder::class)
        ->buildAnonymousSpares($ctx['match'], AlrhaClass::Varmint, 1, $ctx['owner']->id)
        ->first();

    Sanctum::actingAs($ctx['owner'], ['scoring']);

    $this->postJson(
        "/api/matches/{$ctx['match']->id}/score-sheets/{$spare->token}/claim",
        ['shooter_id' => $shooter->id],
    )->assertOk()->assertJsonPath('shooter_id', $shooter->id);

    $fresh = $spare->fresh();
    expect($fresh->shooter_id)->toBe($shooter->id);
    expect($fresh->claimed_at)->not->toBeNull();

    // Second scan now returns the bound shooter, not a spare.
    $this->getJson("/api/matches/{$ctx['match']->id}/score-sheets/{$spare->token}")
        ->assertOk()
        ->assertJsonPath('sheet.is_spare', false)
        ->assertJsonPath('sheet.shooter.id', $shooter->id);
});

it('rejects a second claim on an already-claimed spare with 409', function () {
    $ctx = alrhaBuild('varmint');
    $a = Shooter::factory()->create(['squad_id' => $ctx['squad']->id]);
    $b = Shooter::factory()->create(['squad_id' => $ctx['squad']->id]);
    $spare = app(ScoreSheetLayoutBuilder::class)
        ->buildAnonymousSpares($ctx['match'], AlrhaClass::Varmint, 1, $ctx['owner']->id)
        ->first();

    Sanctum::actingAs($ctx['owner'], ['scoring']);

    $this->postJson("/api/matches/{$ctx['match']->id}/score-sheets/{$spare->token}/claim",
        ['shooter_id' => $a->id])->assertOk();

    $this->postJson("/api/matches/{$ctx['match']->id}/score-sheets/{$spare->token}/claim",
        ['shooter_id' => $b->id])
        ->assertStatus(409)
        ->assertJsonPath('bound_shooter_id', $a->id);
});

it('refuses to claim a spare to a shooter who is not on the match', function () {
    $ctx = alrhaBuild('varmint');
    $otherCtx = alrhaBuild('varmint');
    $stranger = Shooter::factory()->create(['squad_id' => $otherCtx['squad']->id]);
    $spare = app(ScoreSheetLayoutBuilder::class)
        ->buildAnonymousSpares($ctx['match'], AlrhaClass::Varmint, 1, $ctx['owner']->id)
        ->first();

    Sanctum::actingAs($ctx['owner'], ['scoring']);

    $this->postJson("/api/matches/{$ctx['match']->id}/score-sheets/{$spare->token}/claim",
        ['shooter_id' => $stranger->id])
        ->assertStatus(422);
});

/**
 * Hub mirror endpoint: the Android native app calls this during match
 * import to seed its local score_sheets table.
 */
it('lists every score sheet for a match on the index endpoint for hub import', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id]);
    $assigned = app(ScoreSheetLayoutBuilder::class)
        ->buildForShooter($ctx['match'], $shooter, $ctx['owner']->id);
    $spares = app(ScoreSheetLayoutBuilder::class)
        ->buildAnonymousSpares($ctx['match'], AlrhaClass::Varmint, 2, $ctx['owner']->id);

    Sanctum::actingAs($ctx['owner'], ['scoring']);

    $response = $this->getJson("/api/matches/{$ctx['match']->id}/score-sheets")
        ->assertOk()
        ->assertJsonPath('count', 3)
        ->assertJsonStructure(['sheets' => [['id', 'token', 'shooter_id', 'alrha_class', 'layout', 'is_spare']]]);

    $tokens = collect($response->json('sheets'))->pluck('token')->all();
    expect($tokens)->toContain($assigned->token, ...$spares->pluck('token')->all());

    // Spares show their class; assigned cards don't.
    $assignedPayload = collect($response->json('sheets'))->firstWhere('token', $assigned->token);
    $sparePayload = collect($response->json('sheets'))->firstWhere('token', $spares->first()->token);
    expect($assignedPayload['is_spare'])->toBeFalse();
    expect($assignedPayload['shooter_id'])->toBe($shooter->id);
    expect($sparePayload['is_spare'])->toBeTrue();
    expect($sparePayload['shooter_id'])->toBeNull();
    expect($sparePayload['alrha_class'])->toBe('varmint');
});

/**
 * Hub push-back: a claim POSTed with a recent `claimed_at` must
 * preserve that timestamp (reflects when the hub actually bound
 * the card, not when connectivity returned).
 */
it('preserves a recent claimed_at from the hub on push-back', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id]);
    $spare = app(ScoreSheetLayoutBuilder::class)
        ->buildAnonymousSpares($ctx['match'], AlrhaClass::Varmint, 1, $ctx['owner']->id)
        ->first();

    Sanctum::actingAs($ctx['owner'], ['scoring']);

    $hubClaimedAt = now()->subHours(3)->startOfSecond();

    $this->postJson(
        "/api/matches/{$ctx['match']->id}/score-sheets/{$spare->token}/claim",
        [
            'shooter_id' => $shooter->id,
            'claimed_at' => $hubClaimedAt->toIso8601String(),
        ],
    )->assertOk();

    expect($spare->fresh()->claimed_at->timestamp)->toBe($hubClaimedAt->timestamp);
});

/**
 * Hub push-back guard: a clock-skew timestamp older than 7 days
 * (or in the future) is ignored and we stamp "now" so the
 * scoring timeline stays trustworthy.
 */
it('ignores a bogus claimed_at from the hub and stamps now instead', function () {
    $ctx = alrhaBuild('varmint');
    $shooter = Shooter::factory()->create(['squad_id' => $ctx['squad']->id]);
    $spare = app(ScoreSheetLayoutBuilder::class)
        ->buildAnonymousSpares($ctx['match'], AlrhaClass::Varmint, 1, $ctx['owner']->id)
        ->first();

    Sanctum::actingAs($ctx['owner'], ['scoring']);

    $before = now();
    $this->postJson(
        "/api/matches/{$ctx['match']->id}/score-sheets/{$spare->token}/claim",
        [
            'shooter_id' => $shooter->id,
            'claimed_at' => now()->subYear()->toIso8601String(),
        ],
    )->assertOk();

    $stored = $spare->fresh()->claimed_at;
    expect($stored->timestamp)->toBeGreaterThanOrEqual($before->timestamp);
    expect($stored->timestamp)->toBeLessThanOrEqual(now()->addSecond()->timestamp);
});

/**
 * Build the elr-shots POST payload for a single shot.
 */
function shot(Shooter $shooter, \App\Models\ElrTarget $target, int $shotNumber, string $result): array
{
    return [
        'shooter_id' => $shooter->id,
        'elr_target_id' => $target->id,
        'shot_number' => $shotNumber,
        'result' => $result,
        'device_id' => 'score-sheet:TEST',
        'recorded_at' => now()->toIso8601String(),
    ];
}
