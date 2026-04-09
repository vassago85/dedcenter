<?php

use App\Models\Gong;
use App\Models\Organization;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->creator = User::factory()->create();
    $this->org->admins()->attach($this->creator, ['is_owner' => true]);

    $this->match = ShootingMatch::factory()->active()->create([
        'created_by' => $this->creator->id,
        'organization_id' => $this->org->id,
    ]);

    $stage = TargetSet::factory()->create(['match_id' => $this->match->id]);
    $this->gong = Gong::factory()->create(['target_set_id' => $stage->id, 'number' => 1]);
    $squad = Squad::factory()->create(['match_id' => $this->match->id]);
    $this->shooter = Shooter::factory()->create(['squad_id' => $squad->id]);
});

function scorePayload(int $shooterId, int $gongId): array
{
    return [
        'scores' => [[
            'shooter_id' => $shooterId,
            'gong_id' => $gongId,
            'is_hit' => true,
            'device_id' => 'test-device',
            'recorded_at' => now()->toISOString(),
        ]],
    ];
}

// -- Authentication tests --

test('unauthenticated user cannot POST scores', function () {
    $this->postJson("/api/matches/{$this->match->id}/scores", scorePayload($this->shooter->id, $this->gong->id))
        ->assertUnauthorized();
});

test('score route web page requires login', function () {
    $this->get('/score')
        ->assertRedirect('/login');
});

test('authenticated user can access score page', function () {
    $this->actingAs($this->creator)
        ->get('/score')
        ->assertOk();
});

// -- Authorization tests --

test('match creator can POST scores', function () {
    $this->actingAs($this->creator)
        ->postJson("/api/matches/{$this->match->id}/scores", scorePayload($this->shooter->id, $this->gong->id))
        ->assertOk();
});

test('org admin of match organization can POST scores', function () {
    $orgAdmin = User::factory()->create();
    $this->org->admins()->attach($orgAdmin, ['is_range_officer' => true]);

    $this->actingAs($orgAdmin)
        ->postJson("/api/matches/{$this->match->id}/scores", scorePayload($this->shooter->id, $this->gong->id))
        ->assertOk();
});

test('site admin can POST scores for any match', function () {
    $siteAdmin = User::factory()->admin()->create();

    $this->actingAs($siteAdmin)
        ->postJson("/api/matches/{$this->match->id}/scores", scorePayload($this->shooter->id, $this->gong->id))
        ->assertOk();
});

test('unrelated user cannot POST scores', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->postJson("/api/matches/{$this->match->id}/scores", scorePayload($this->shooter->id, $this->gong->id))
        ->assertForbidden();
});

test('admin of different org cannot POST scores', function () {
    $otherOrg = Organization::factory()->create();
    $otherAdmin = User::factory()->create();
    $otherOrg->admins()->attach($otherAdmin, ['is_owner' => true]);

    $this->actingAs($otherAdmin)
        ->postJson("/api/matches/{$this->match->id}/scores", scorePayload($this->shooter->id, $this->gong->id))
        ->assertForbidden();
});

// -- Public routes remain open --

test('match list API is public', function () {
    $this->getJson('/api/matches')
        ->assertOk();
});

test('match detail API is public', function () {
    $this->getJson("/api/matches/{$this->match->id}")
        ->assertOk();
});

test('scoreboard API is public', function () {
    $this->getJson("/api/matches/{$this->match->id}/scoreboard")
        ->assertOk();
});

// -- Match without organization --

test('match creator can score match without organization', function () {
    $match = ShootingMatch::factory()->active()->create([
        'created_by' => $this->creator->id,
        'organization_id' => null,
    ]);
    $stage = TargetSet::factory()->create(['match_id' => $match->id]);
    $gong = Gong::factory()->create(['target_set_id' => $stage->id]);
    $squad = Squad::factory()->create(['match_id' => $match->id]);
    $shooter = Shooter::factory()->create(['squad_id' => $squad->id]);

    $this->actingAs($this->creator)
        ->postJson("/api/matches/{$match->id}/scores", scorePayload($shooter->id, $gong->id))
        ->assertOk();
});
