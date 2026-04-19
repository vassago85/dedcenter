<?php

use App\Models\Gong;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use App\Services\RoyalFlushShotStatusService;

beforeEach(function () {
    $this->creator = User::factory()->create();
    $this->match = ShootingMatch::factory()->active()->create([
        'created_by' => $this->creator->id,
        'royal_flush_enabled' => true,
    ]);

    // Two distances, 5 gongs each — canonical Royal Flush layout.
    $this->ts400 = TargetSet::factory()->create([
        'match_id' => $this->match->id,
        'distance_meters' => 400,
        'label' => '400m',
        'sort_order' => 1,
    ]);
    $this->ts500 = TargetSet::factory()->create([
        'match_id' => $this->match->id,
        'distance_meters' => 500,
        'label' => '500m',
        'sort_order' => 2,
    ]);

    $this->gongs400 = collect(range(1, 5))->map(
        fn ($n) => Gong::factory()->create(['target_set_id' => $this->ts400->id, 'number' => $n])
    );
    $this->gongs500 = collect(range(1, 5))->map(
        fn ($n) => Gong::factory()->create(['target_set_id' => $this->ts500->id, 'number' => $n])
    );

    $this->squad = Squad::factory()->create(['match_id' => $this->match->id]);
    $this->shooter = Shooter::factory()->create(['squad_id' => $this->squad->id]);

    // Helper: record $n hits at the given target set for the current shooter.
    $this->hit = function (int $n, $gongs) {
        foreach ($gongs->take($n) as $g) {
            Score::create([
                'shooter_id' => $this->shooter->id,
                'gong_id' => $g->id,
                'is_hit' => true,
                'recorded_at' => now(),
            ]);
        }
    };
    // Helper: record a miss at a specific gong.
    $this->miss = function ($gong) {
        Score::create([
            'shooter_id' => $this->shooter->id,
            'gong_id' => $gong->id,
            'is_hit' => false,
            'recorded_at' => now(),
        ]);
    };
});

// ── Service-level behaviour ────────────────────────────────────────────

it('arms the banner when shooter has 4 clean hits on a 5-gong distance', function () {
    ($this->hit)(4, $this->gongs400);

    $status = app(RoyalFlushShotStatusService::class)
        ->forShooter($this->match, $this->shooter);

    expect($status['royal_flush_shot'])->toBeTrue()
        ->and($status['armed_target_set_ids'])->toBe([$this->ts400->id]);

    $d400 = collect($status['distances'])->firstWhere('target_set_id', $this->ts400->id);
    expect($d400['hits'])->toBe(4)
        ->and($d400['misses'])->toBe(0)
        ->and($d400['unshot'])->toBe(1)
        ->and($d400['armed'])->toBeTrue()
        ->and($d400['flushed'])->toBeFalse();
});

it('disarms the banner as soon as a miss is recorded at that distance', function () {
    ($this->hit)(3, $this->gongs400);
    ($this->miss)($this->gongs400[3]);

    $status = app(RoyalFlushShotStatusService::class)
        ->forShooter($this->match, $this->shooter);

    expect($status['royal_flush_shot'])->toBeFalse();

    $d400 = collect($status['distances'])->firstWhere('target_set_id', $this->ts400->id);
    expect($d400['armed'])->toBeFalse()
        ->and($d400['misses'])->toBe(1);
});

it('does not arm until the shooter is one gong away (3/3 is not armed)', function () {
    // User spec: banner triggers on the FINAL shot of a clean run — i.e.
    // hits === gong_count - 1, unshot === 1, misses === 0. A 3/3 shooter
    // still has 2 unshot gongs, so the banner stays off.
    ($this->hit)(3, $this->gongs400);

    $status = app(RoyalFlushShotStatusService::class)
        ->forShooter($this->match, $this->shooter);

    $d400 = collect($status['distances'])->firstWhere('target_set_id', $this->ts400->id);
    expect($d400['armed'])->toBeFalse()
        ->and($d400['hits'])->toBe(3)
        ->and($d400['unshot'])->toBe(2);
});

it('does not arm on a pristine distance (0/0 is not armed)', function () {
    // Before any shot is recorded at a distance, hits === gong_count - unshot
    // (0 === 5 - 5) would naively satisfy a loose formula. Confirm the service
    // correctly requires unshot === 1, so "the very first shot" doesn't
    // trigger the banner on every shooter.
    $status = app(RoyalFlushShotStatusService::class)
        ->forShooter($this->match, $this->shooter);

    $d500 = collect($status['distances'])->firstWhere('target_set_id', $this->ts500->id);
    expect($d500['armed'])->toBeFalse()
        ->and($d500['hits'])->toBe(0)
        ->and($d500['unshot'])->toBe(5);
});

it('flushed flag flips on once every gong at the distance is hit', function () {
    ($this->hit)(5, $this->gongs400);

    $status = app(RoyalFlushShotStatusService::class)
        ->forShooter($this->match, $this->shooter);

    $d400 = collect($status['distances'])->firstWhere('target_set_id', $this->ts400->id);
    expect($d400['flushed'])->toBeTrue()
        ->and($d400['armed'])->toBeFalse()
        ->and($d400['unshot'])->toBe(0);
});

it('can arm multiple distances simultaneously', function () {
    ($this->hit)(4, $this->gongs400);
    ($this->hit)(4, $this->gongs500);

    $status = app(RoyalFlushShotStatusService::class)
        ->forShooter($this->match, $this->shooter);

    expect($status['royal_flush_shot'])->toBeTrue()
        ->and($status['armed_target_set_ids'])->toMatchArray([$this->ts400->id, $this->ts500->id]);
});

it('returns a flat unarmed payload when royal_flush_enabled is false', function () {
    $this->match->update(['royal_flush_enabled' => false]);
    ($this->hit)(4, $this->gongs400);

    $status = app(RoyalFlushShotStatusService::class)
        ->forShooter($this->match, $this->shooter);

    expect($status['royal_flush_shot'])->toBeFalse()
        ->and($status['armed_target_set_ids'])->toBe([])
        ->and($status['distances'])->toBe([]);
});

// ── API route: GET royal-flush-status ─────────────────────────────────

it('GET royal-flush-status returns an armed payload for a 4-for-4 shooter', function () {
    ($this->hit)(4, $this->gongs400);

    $this->actingAs($this->creator)
        ->getJson("/api/matches/{$this->match->id}/shooters/{$this->shooter->id}/royal-flush-status")
        ->assertOk()
        ->assertJson([
            'shooter_id' => $this->shooter->id,
            'royal_flush_shot' => true,
            'armed_target_set_ids' => [$this->ts400->id],
        ]);
});

it('GET royal-flush-status 404s for a shooter not in the match', function () {
    $otherMatch = ShootingMatch::factory()->active()->create();
    $otherSquad = Squad::factory()->create(['match_id' => $otherMatch->id]);
    $stranger = Shooter::factory()->create(['squad_id' => $otherSquad->id]);

    $this->actingAs($this->creator)
        ->getJson("/api/matches/{$this->match->id}/shooters/{$stranger->id}/royal-flush-status")
        ->assertNotFound();
});

// ── API route: POST /scores now returns RF status in the response ────

it('POST /scores includes royal_flush status for touched shooters', function () {
    // Pre-seed 3 hits, then submit the 4th as part of the batch — the
    // response should flag the shooter as armed for the 5th shot.
    ($this->hit)(3, $this->gongs400);

    $payload = [
        'scores' => [[
            'shooter_id' => $this->shooter->id,
            'gong_id' => $this->gongs400[3]->id,
            'is_hit' => true,
            'device_id' => 'tablet-1',
            'recorded_at' => now()->toIso8601String(),
        ]],
    ];

    $response = $this->actingAs($this->creator)
        ->postJson("/api/matches/{$this->match->id}/scores", $payload)
        ->assertOk()
        ->assertJsonPath('royal_flush.0.shooter_id', $this->shooter->id)
        ->assertJsonPath('royal_flush.0.royal_flush_shot', true);

    $payload = $response->json();
    expect($payload['royal_flush'][0]['armed_target_set_ids'])->toBe([$this->ts400->id]);
});

it('POST /scores returns royal_flush: [] when the match has no RF shooters touched', function () {
    // Empty batch (no scores, no deletes) — nothing was touched, so no status.
    $this->actingAs($this->creator)
        ->postJson("/api/matches/{$this->match->id}/scores", ['scores' => []])
        ->assertOk()
        ->assertJsonPath('royal_flush', []);
});
