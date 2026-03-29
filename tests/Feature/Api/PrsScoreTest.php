<?php

use App\Models\Gong;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\StageTime;
use App\Models\TargetSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->match = ShootingMatch::factory()->active()->prs()->create(['created_by' => $this->admin->id]);

    $this->stage1 = TargetSet::factory()->create(['match_id' => $this->match->id, 'label' => 'Stage 1', 'sort_order' => 1]);
    $this->stage2 = TargetSet::factory()->create(['match_id' => $this->match->id, 'label' => 'Stage 2', 'sort_order' => 2]);

    $this->gong1a = Gong::factory()->create(['target_set_id' => $this->stage1->id, 'number' => 1, 'multiplier' => 1.00]);
    $this->gong1b = Gong::factory()->create(['target_set_id' => $this->stage1->id, 'number' => 2, 'multiplier' => 1.00]);
    $this->gong2a = Gong::factory()->create(['target_set_id' => $this->stage2->id, 'number' => 1, 'multiplier' => 1.00]);
    $this->gong2b = Gong::factory()->create(['target_set_id' => $this->stage2->id, 'number' => 2, 'multiplier' => 1.00]);

    $squad = Squad::factory()->create(['match_id' => $this->match->id]);
    $this->shooter1 = Shooter::factory()->create(['squad_id' => $squad->id, 'name' => 'Alpha']);
    $this->shooter2 = Shooter::factory()->create(['squad_id' => $squad->id, 'name' => 'Bravo']);
});

test('match reports as prs', function () {
    expect($this->match->isPrs())->toBeTrue();
    expect($this->match->isStandard())->toBeFalse();
});

test('can submit scores with stage times', function () {
    $response = $this->actingAs($this->admin)->postJson("/api/matches/{$this->match->id}/scores", [
        'scores' => [
            ['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1a->id, 'is_hit' => true, 'device_id' => 'test', 'recorded_at' => now()->toISOString()],
            ['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1b->id, 'is_hit' => false, 'device_id' => 'test', 'recorded_at' => now()->toISOString()],
        ],
        'stage_times' => [
            ['shooter_id' => $this->shooter1->id, 'target_set_id' => $this->stage1->id, 'time_seconds' => 45.32, 'device_id' => 'test', 'recorded_at' => now()->toISOString()],
        ],
    ]);

    $response->assertOk();
    expect(Score::count())->toBe(2);
    expect(StageTime::count())->toBe(1);
    expect(StageTime::first()->time_seconds)->toBe('45.32');
});

test('stage times are upserted', function () {
    StageTime::create([
        'shooter_id' => $this->shooter1->id,
        'target_set_id' => $this->stage1->id,
        'time_seconds' => 50.00,
        'device_id' => 'test',
        'recorded_at' => now(),
    ]);

    $this->actingAs($this->admin)->postJson("/api/matches/{$this->match->id}/scores", [
        'scores' => [
            ['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1a->id, 'is_hit' => true, 'device_id' => 'test', 'recorded_at' => now()->toISOString()],
        ],
        'stage_times' => [
            ['shooter_id' => $this->shooter1->id, 'target_set_id' => $this->stage1->id, 'time_seconds' => 42.10, 'device_id' => 'test', 'recorded_at' => now()->toISOString()],
        ],
    ]);

    expect(StageTime::count())->toBe(1);
    expect(StageTime::first()->time_seconds)->toBe('42.10');
});

test('prs scoreboard ranks by hits then time', function () {
    Score::create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1a->id, 'is_hit' => true, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1b->id, 'is_hit' => true, 'recorded_at' => now()]);
    StageTime::create(['shooter_id' => $this->shooter1->id, 'target_set_id' => $this->stage1->id, 'time_seconds' => 45.00, 'device_id' => 'test', 'recorded_at' => now()]);
    StageTime::create(['shooter_id' => $this->shooter1->id, 'target_set_id' => $this->stage2->id, 'time_seconds' => 45.00, 'device_id' => 'test', 'recorded_at' => now()]);

    Score::create(['shooter_id' => $this->shooter2->id, 'gong_id' => $this->gong1a->id, 'is_hit' => true, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $this->shooter2->id, 'gong_id' => $this->gong1b->id, 'is_hit' => true, 'recorded_at' => now()]);
    StageTime::create(['shooter_id' => $this->shooter2->id, 'target_set_id' => $this->stage1->id, 'time_seconds' => 30.00, 'device_id' => 'test', 'recorded_at' => now()]);
    StageTime::create(['shooter_id' => $this->shooter2->id, 'target_set_id' => $this->stage2->id, 'time_seconds' => 30.00, 'device_id' => 'test', 'recorded_at' => now()]);

    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");
    $response->assertOk();

    $leaderboard = $response->json('leaderboard');
    expect($leaderboard)->toHaveCount(2);
    expect($leaderboard[0]['name'])->toBe('Bravo');
    expect($leaderboard[0]['total_score'])->toBe(2);
    expect((float) $leaderboard[0]['total_time'])->toBe(60.0);
    expect($leaderboard[1]['name'])->toBe('Alpha');
    expect((float) $leaderboard[1]['total_time'])->toBe(90.0);
});

test('prs scoreboard ranks higher hits above lower time', function () {
    Score::create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1a->id, 'is_hit' => true, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1b->id, 'is_hit' => true, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong2a->id, 'is_hit' => true, 'recorded_at' => now()]);

    Score::create(['shooter_id' => $this->shooter2->id, 'gong_id' => $this->gong1a->id, 'is_hit' => true, 'recorded_at' => now()]);
    StageTime::create(['shooter_id' => $this->shooter2->id, 'target_set_id' => $this->stage1->id, 'time_seconds' => 30.00, 'device_id' => 'test', 'recorded_at' => now()]);

    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");
    $leaderboard = $response->json('leaderboard');

    expect($leaderboard[0]['name'])->toBe('Alpha');
    expect($leaderboard[0]['total_score'])->toBe(3);
    expect($leaderboard[1]['name'])->toBe('Bravo');
    expect($leaderboard[1]['total_score'])->toBe(1);
});

test('match api returns scoring_type', function () {
    $response = $this->getJson("/api/matches/{$this->match->id}");
    $response->assertOk();
    expect($response->json('data.scoring_type'))->toBe('prs');
});

test('match api returns stage_times for prs match', function () {
    StageTime::create([
        'shooter_id' => $this->shooter1->id,
        'target_set_id' => $this->stage1->id,
        'time_seconds' => 33.50,
        'device_id' => 'test',
        'recorded_at' => now(),
    ]);

    $response = $this->getJson("/api/matches/{$this->match->id}");
    $response->assertOk();
    expect($response->json('data.stage_times'))->toHaveCount(1);
    expect($response->json('data.stage_times.0.time_seconds'))->toBe(33.5);
});

test('prs scoreboard page loads', function () {
    $this->get(route('scoreboard', $this->match))
        ->assertOk()
        ->assertSee('PRS');
});

// ── Tiebreaker Stage Tests ──

test('setting tiebreaker stage clears previous tiebreaker', function () {
    $this->stage1->update(['is_tiebreaker' => true]);

    expect($this->stage1->fresh()->is_tiebreaker)->toBeTrue();

    // Now set stage2 as tiebreaker via the same logic used in the controller
    $this->match->targetSets()->update(['is_tiebreaker' => false]);
    $this->stage2->update(['is_tiebreaker' => true]);

    expect($this->stage1->fresh()->is_tiebreaker)->toBeFalse();
    expect($this->stage2->fresh()->is_tiebreaker)->toBeTrue();
});

test('match api returns is_tiebreaker and par_time_seconds for target sets', function () {
    $this->stage1->update(['is_tiebreaker' => true, 'par_time_seconds' => 90.00]);

    $response = $this->getJson("/api/matches/{$this->match->id}");
    $response->assertOk();

    $targetSets = $response->json('data.target_sets');
    $stage1Api = collect($targetSets)->firstWhere('id', $this->stage1->id);
    $stage2Api = collect($targetSets)->firstWhere('id', $this->stage2->id);

    expect($stage1Api['is_tiebreaker'])->toBeTrue();
    expect((float) $stage1Api['par_time_seconds'])->toBe(90.0);
    expect($stage2Api['is_tiebreaker'])->toBeFalse();
    expect($stage2Api['par_time_seconds'])->toBeNull();
});

// ── 3-Level Tiebreaker Ranking Tests ──

test('prs tiebreaker: tied total hits, different tiebreaker hits', function () {
    $this->stage2->update(['is_tiebreaker' => true]);

    // Alpha: 2 total hits (1 stage1, 1 stage2), tiebreaker stage2 = 1 hit
    Score::create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1a->id, 'is_hit' => true, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1b->id, 'is_hit' => false, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong2a->id, 'is_hit' => true, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong2b->id, 'is_hit' => false, 'recorded_at' => now()]);

    // Bravo: 2 total hits (0 stage1, 2 stage2), tiebreaker stage2 = 2 hits -> wins tiebreaker
    Score::create(['shooter_id' => $this->shooter2->id, 'gong_id' => $this->gong1a->id, 'is_hit' => false, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $this->shooter2->id, 'gong_id' => $this->gong1b->id, 'is_hit' => false, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $this->shooter2->id, 'gong_id' => $this->gong2a->id, 'is_hit' => true, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $this->shooter2->id, 'gong_id' => $this->gong2b->id, 'is_hit' => true, 'recorded_at' => now()]);

    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");
    $leaderboard = $response->json('leaderboard');

    expect($leaderboard[0]['name'])->toBe('Bravo');
    expect($leaderboard[0]['tb_hits'])->toBe(2);
    expect($leaderboard[1]['name'])->toBe('Alpha');
    expect($leaderboard[1]['tb_hits'])->toBe(1);
});

test('prs tiebreaker: tied total + tiebreaker hits, different tiebreaker time', function () {
    $this->stage2->update(['is_tiebreaker' => true]);

    // Alpha: 2 total hits, 1 on tiebreaker stage, tiebreaker time = 50s
    Score::create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1a->id, 'is_hit' => true, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong2a->id, 'is_hit' => true, 'recorded_at' => now()]);
    StageTime::create(['shooter_id' => $this->shooter1->id, 'target_set_id' => $this->stage2->id, 'time_seconds' => 50.00, 'device_id' => 'test', 'recorded_at' => now()]);

    // Bravo: 2 total hits, 1 on tiebreaker stage, tiebreaker time = 30s -> wins
    Score::create(['shooter_id' => $this->shooter2->id, 'gong_id' => $this->gong1a->id, 'is_hit' => true, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $this->shooter2->id, 'gong_id' => $this->gong2a->id, 'is_hit' => true, 'recorded_at' => now()]);
    StageTime::create(['shooter_id' => $this->shooter2->id, 'target_set_id' => $this->stage2->id, 'time_seconds' => 30.00, 'device_id' => 'test', 'recorded_at' => now()]);

    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");
    $leaderboard = $response->json('leaderboard');

    expect($leaderboard[0]['name'])->toBe('Bravo');
    expect((float) $leaderboard[0]['tb_time'])->toBe(30.0);
    expect($leaderboard[1]['name'])->toBe('Alpha');
    expect((float) $leaderboard[1]['tb_time'])->toBe(50.0);
});

test('prs scoreboard returns tb_hits and tb_time fields', function () {
    $this->stage1->update(['is_tiebreaker' => true]);

    Score::create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1a->id, 'is_hit' => true, 'recorded_at' => now()]);
    StageTime::create(['shooter_id' => $this->shooter1->id, 'target_set_id' => $this->stage1->id, 'time_seconds' => 12.34, 'device_id' => 'test', 'recorded_at' => now()]);

    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");
    $leaderboard = $response->json('leaderboard');

    $alpha = collect($leaderboard)->firstWhere('name', 'Alpha');
    expect($alpha)->toHaveKeys(['tb_hits', 'tb_time']);
    expect($alpha['tb_hits'])->toBe(1);
    expect((float) $alpha['tb_time'])->toBe(12.34);
});

test('prs scoreboard without tiebreaker stage still works', function () {
    // No tiebreaker nominated -- falls back to tb_hits=0, tb_time=0 for all
    Score::create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1a->id, 'is_hit' => true, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $this->shooter2->id, 'gong_id' => $this->gong1a->id, 'is_hit' => true, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $this->shooter2->id, 'gong_id' => $this->gong2a->id, 'is_hit' => true, 'recorded_at' => now()]);

    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");
    $leaderboard = $response->json('leaderboard');

    expect($leaderboard[0]['name'])->toBe('Bravo');
    expect($leaderboard[0]['hits'])->toBe(2);
    expect($leaderboard[1]['name'])->toBe('Alpha');
    expect($leaderboard[1]['hits'])->toBe(1);
});

// ── Par Time Tests ──

test('par_time_seconds can be set on target sets', function () {
    $this->stage1->update(['par_time_seconds' => 90.00]);

    expect($this->stage1->fresh()->par_time_seconds)->toBe('90.00');
});

test('par_time_seconds is nullable', function () {
    $this->stage1->update(['par_time_seconds' => null]);

    expect($this->stage1->fresh()->par_time_seconds)->toBeNull();
});

// ── Shot Not Taken Tests ──

test('prs scoreboard returns not_taken count', function () {
    Score::factory()->create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1a->id, 'is_hit' => true]);
    Score::factory()->create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1b->id, 'is_hit' => false]);

    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");
    $response->assertOk();

    $entry = collect($response->json('leaderboard'))->firstWhere('name', 'Alpha');
    expect($entry['hits'])->toBe(1);
    expect($entry['misses'])->toBe(1);
    expect($entry['not_taken'])->toBe(2);
});

test('prs scoreboard total_targets in match meta', function () {
    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");
    $response->assertOk();

    expect($response->json('match.total_targets'))->toBe(4);
});

test('shooter with no scores has all targets as not_taken', function () {
    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");
    $response->assertOk();

    $entry = collect($response->json('leaderboard'))->firstWhere('name', 'Alpha');
    expect($entry['hits'])->toBe(0);
    expect($entry['misses'])->toBe(0);
    expect($entry['not_taken'])->toBe(4);
});

test('shooter who hit all targets has zero not_taken', function () {
    Score::factory()->create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1a->id, 'is_hit' => true]);
    Score::factory()->create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1b->id, 'is_hit' => true]);
    Score::factory()->create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong2a->id, 'is_hit' => true]);
    Score::factory()->create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong2b->id, 'is_hit' => true]);

    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");
    $entry = collect($response->json('leaderboard'))->firstWhere('name', 'Alpha');
    expect($entry['hits'])->toBe(4);
    expect($entry['not_taken'])->toBe(0);
});

// ── Score Deletion (Shot Not Taken sync) ──

test('deleted_scores removes score records from server', function () {
    Score::factory()->create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1a->id, 'is_hit' => true]);
    expect(Score::where('shooter_id', $this->shooter1->id)->where('gong_id', $this->gong1a->id)->exists())->toBeTrue();

    $response = $this->actingAs($this->admin)->postJson("/api/matches/{$this->match->id}/scores", [
        'deleted_scores' => [
            ['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1a->id],
        ],
    ]);

    $response->assertOk();
    expect(Score::where('shooter_id', $this->shooter1->id)->where('gong_id', $this->gong1a->id)->exists())->toBeFalse();
});

test('deleted_scores and new scores can be sent together', function () {
    Score::factory()->create(['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1a->id, 'is_hit' => true]);

    $response = $this->actingAs($this->admin)->postJson("/api/matches/{$this->match->id}/scores", [
        'scores' => [
            [
                'shooter_id' => $this->shooter1->id,
                'gong_id' => $this->gong1b->id,
                'is_hit' => false,
                'device_id' => 'test_dev',
                'recorded_at' => now()->toISOString(),
            ],
        ],
        'deleted_scores' => [
            ['shooter_id' => $this->shooter1->id, 'gong_id' => $this->gong1a->id],
        ],
    ]);

    $response->assertOk();
    expect(Score::where('shooter_id', $this->shooter1->id)->where('gong_id', $this->gong1a->id)->exists())->toBeFalse();
    expect(Score::where('shooter_id', $this->shooter1->id)->where('gong_id', $this->gong1b->id)->exists())->toBeTrue();
});

test('scores-only payload still works without deleted_scores', function () {
    $response = $this->actingAs($this->admin)->postJson("/api/matches/{$this->match->id}/scores", [
        'scores' => [
            [
                'shooter_id' => $this->shooter1->id,
                'gong_id' => $this->gong1a->id,
                'is_hit' => true,
                'device_id' => 'test_dev',
                'recorded_at' => now()->toISOString(),
            ],
        ],
    ]);

    $response->assertOk();
    expect(Score::where('shooter_id', $this->shooter1->id)->where('gong_id', $this->gong1a->id)->exists())->toBeTrue();
});
