<?php

use App\Models\Gong;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;

beforeEach(function () {
    $this->creator = User::factory()->create();
    $this->match = ShootingMatch::factory()->active()->create(['created_by' => $this->creator->id]);
    $this->targetSet = TargetSet::factory()->create(['match_id' => $this->match->id]);
    $this->gong1 = Gong::factory()->create(['target_set_id' => $this->targetSet->id, 'number' => 1]);
    $this->gong2 = Gong::factory()->create(['target_set_id' => $this->targetSet->id, 'number' => 2]);
    $this->squad = Squad::factory()->create(['match_id' => $this->match->id]);
    $this->shooter = Shooter::factory()->create(['squad_id' => $this->squad->id]);
});

it('accepts batch score submission', function () {
    $payload = [
        'scores' => [
            [
                'shooter_id' => $this->shooter->id,
                'gong_id' => $this->gong1->id,
                'is_hit' => true,
                'device_id' => 'tablet-1',
                'recorded_at' => now()->toIso8601String(),
            ],
            [
                'shooter_id' => $this->shooter->id,
                'gong_id' => $this->gong2->id,
                'is_hit' => false,
                'device_id' => 'tablet-1',
                'recorded_at' => now()->toIso8601String(),
            ],
        ],
    ];

    $response = $this->actingAs($this->creator)->postJson("/api/matches/{$this->match->id}/scores", $payload);

    $response->assertOk()
        ->assertJsonCount(2, 'data');

    expect(Score::count())->toBe(2);
    expect(Score::where('is_hit', true)->count())->toBe(1);
});

it('is idempotent via updateOrCreate', function () {
    $payload = [
        'scores' => [
            [
                'shooter_id' => $this->shooter->id,
                'gong_id' => $this->gong1->id,
                'is_hit' => true,
                'device_id' => 'tablet-1',
                'recorded_at' => now()->toIso8601String(),
            ],
        ],
    ];

    $this->actingAs($this->creator)->postJson("/api/matches/{$this->match->id}/scores", $payload)->assertOk();
    expect(Score::count())->toBe(1);

    $payload['scores'][0]['is_hit'] = false;
    $this->actingAs($this->creator)->postJson("/api/matches/{$this->match->id}/scores", $payload)->assertOk();

    expect(Score::count())->toBe(1);
    expect(Score::first()->is_hit)->toBeFalse();
});

it('rejects scores for shooters not in this match', function () {
    $otherMatch = ShootingMatch::factory()->active()->create();
    $otherSquad = Squad::factory()->create(['match_id' => $otherMatch->id]);
    $otherShooter = Shooter::factory()->create(['squad_id' => $otherSquad->id]);

    $payload = [
        'scores' => [
            [
                'shooter_id' => $otherShooter->id,
                'gong_id' => $this->gong1->id,
                'is_hit' => true,
                'device_id' => 'tablet-1',
                'recorded_at' => now()->toIso8601String(),
            ],
        ],
    ];

    $this->actingAs($this->creator)->postJson("/api/matches/{$this->match->id}/scores", $payload)
        ->assertUnprocessable();
});

it('rejects scores for gongs not in this match', function () {
    $otherMatch = ShootingMatch::factory()->active()->create();
    $otherTs = TargetSet::factory()->create(['match_id' => $otherMatch->id]);
    $otherGong = Gong::factory()->create(['target_set_id' => $otherTs->id]);

    $payload = [
        'scores' => [
            [
                'shooter_id' => $this->shooter->id,
                'gong_id' => $otherGong->id,
                'is_hit' => true,
                'device_id' => 'tablet-1',
                'recorded_at' => now()->toIso8601String(),
            ],
        ],
    ];

    $this->actingAs($this->creator)->postJson("/api/matches/{$this->match->id}/scores", $payload)
        ->assertUnprocessable();
});

it('requires all score fields', function () {
    $this->actingAs($this->creator)->postJson("/api/matches/{$this->match->id}/scores", ['scores' => [[]]])
        ->assertUnprocessable();
});
