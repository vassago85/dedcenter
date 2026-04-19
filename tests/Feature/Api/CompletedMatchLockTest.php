<?php

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;

/*
 * Guards against a stale offline tablet syncing scores into a match that
 * has already been finalised. UI makes the state obvious; these tests
 * assert the server is the authoritative gate.
 */

beforeEach(function () {
    $this->creator = User::factory()->create();
    $this->match = ShootingMatch::factory()->completed()->create(['created_by' => $this->creator->id]);
    $this->targetSet = TargetSet::factory()->create(['match_id' => $this->match->id]);
    $this->gong = Gong::factory()->create(['target_set_id' => $this->targetSet->id, 'number' => 1]);
    $this->squad = Squad::factory()->create(['match_id' => $this->match->id]);
    $this->shooter = Shooter::factory()->create(['squad_id' => $this->squad->id]);
});

it('returns 423 Locked when posting scores to a completed match', function () {
    $payload = [
        'scores' => [
            [
                'shooter_id' => $this->shooter->id,
                'gong_id' => $this->gong->id,
                'is_hit' => true,
                'device_id' => 'tablet-1',
                'recorded_at' => now()->toIso8601String(),
            ],
        ],
    ];

    $response = $this->actingAs($this->creator)
        ->postJson("/api/matches/{$this->match->id}/scores", $payload);

    $response->assertStatus(423)
        ->assertJson(['status' => 'completed'])
        ->assertJsonFragment(['message' => 'Match already scored. Re-open the match to edit scores.']);

    expect(Score::count())->toBe(0);
});

it('allows scoring again after the match is re-opened', function () {
    // Re-open puts the match back to Active
    $this->actingAs($this->creator)
        ->postJson("/api/matches/{$this->match->id}/reopen")
        ->assertOk()
        ->assertJson(['status' => 'active']);

    expect($this->match->fresh()->status)->toBe(MatchStatus::Active);

    $payload = [
        'scores' => [
            [
                'shooter_id' => $this->shooter->id,
                'gong_id' => $this->gong->id,
                'is_hit' => true,
                'device_id' => 'tablet-1',
                'recorded_at' => now()->toIso8601String(),
            ],
        ],
    ];

    $this->actingAs($this->creator)
        ->postJson("/api/matches/{$this->match->id}/scores", $payload)
        ->assertOk();

    expect(Score::count())->toBe(1);
});

it('refuses to re-open a match that is not completed', function () {
    $activeMatch = ShootingMatch::factory()->active()->create(['created_by' => $this->creator->id]);

    $this->actingAs($this->creator)
        ->postJson("/api/matches/{$activeMatch->id}/reopen")
        ->assertStatus(422);
});

it('refuses re-open from a user who is not the match director', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->postJson("/api/matches/{$this->match->id}/reopen")
        ->assertStatus(403);

    expect($this->match->fresh()->status)->toBe(MatchStatus::Completed);
});
