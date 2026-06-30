<?php

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;

beforeEach(function () {
    // /api/matches and /api/matches/{match} are sanctum-gated AND
    // filtered by ShootingMatch::scopeVisibleToScoringUser. We use a
    // platform owner so every test sees every match, regardless of
    // creator / organisation linkage.
    $this->user = User::factory()->create(['role' => 'owner']);
});

it('returns only active matches', function () {
    ShootingMatch::factory()->create(['status' => MatchStatus::Draft]);
    ShootingMatch::factory()->active()->create(['name' => 'Active Match']);
    ShootingMatch::factory()->completed()->create();

    $response = $this->actingAs($this->user)->getJson('/api/matches');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Active Match');
});

it('returns empty list when no active matches', function () {
    ShootingMatch::factory()->create(['status' => MatchStatus::Draft]);

    $this->actingAs($this->user)->getJson('/api/matches')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns full match detail with nested relations', function () {
    $match = ShootingMatch::factory()->active()->create();
    $ts = TargetSet::factory()->create(['match_id' => $match->id, 'sort_order' => 1]);
    Gong::factory()->create(['target_set_id' => $ts->id, 'number' => 1, 'multiplier' => 2.00]);
    $squad = Squad::factory()->create(['match_id' => $match->id, 'sort_order' => 1]);
    Shooter::factory()->create(['squad_id' => $squad->id, 'sort_order' => 1]);

    $response = $this->actingAs($this->user)->getJson("/api/matches/{$match->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $match->id)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonCount(1, 'data.target_sets')
        ->assertJsonCount(1, 'data.target_sets.0.gongs')
        ->assertJsonCount(1, 'data.squads')
        ->assertJsonCount(1, 'data.squads.0.shooters');
});

it('returns 404 for nonexistent match', function () {
    $this->actingAs($this->user)->getJson('/api/matches/999')
        ->assertNotFound();
});

it('returns 401 to anonymous callers (matches endpoint is auth-only)', function () {
    ShootingMatch::factory()->active()->create();

    $this->getJson('/api/matches')->assertUnauthorized();
});
