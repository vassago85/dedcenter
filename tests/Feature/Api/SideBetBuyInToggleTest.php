<?php

use App\Enums\MatchStatus;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Side-bet buy-in toggle API (MD only)
|--------------------------------------------------------------------------
| Drives the Buy-Ins sub-tab in the scoring SPA. The contract here is the
| pivot table side_bet_shooters — every interaction is per (match, shooter)
| and the response always returns the resulting in_pot flag + totals so
| the SPA can confirm without a follow-up GET.
*/

beforeEach(function () {
    $this->creator = User::factory()->create(['role' => 'owner']);
    $this->match = ShootingMatch::factory()->active()->create([
        'created_by' => $this->creator->id,
        'side_bet_enabled' => true,
        'scoring_type' => 'standard',
    ]);
    $this->squad = Squad::factory()->create(['match_id' => $this->match->id]);
    $this->alice = Shooter::factory()->create(['squad_id' => $this->squad->id, 'name' => 'Alice']);
    $this->bob = Shooter::factory()->create(['squad_id' => $this->squad->id, 'name' => 'Bob']);
});

it('adds a shooter to the pot on first toggle', function () {
    $this->actingAs($this->creator)
        ->postJson("/api/matches/{$this->match->id}/side-bet/toggle/{$this->alice->id}")
        ->assertOk()
        ->assertJson([
            'shooter_id' => $this->alice->id,
            'in_pot' => true,
            'totals' => ['in' => 1, 'total' => 2],
        ]);

    expect(DB::table('side_bet_shooters')->where('shooter_id', $this->alice->id)->exists())->toBeTrue();
});

it('removes a shooter on second toggle', function () {
    $this->match->sideBetShooters()->attach($this->alice->id);

    $this->actingAs($this->creator)
        ->postJson("/api/matches/{$this->match->id}/side-bet/toggle/{$this->alice->id}")
        ->assertOk()
        ->assertJson(['in_pot' => false, 'totals' => ['in' => 0, 'total' => 2]]);

    expect(DB::table('side_bet_shooters')->where('shooter_id', $this->alice->id)->exists())->toBeFalse();
});

it('honours an explicit `in` value (idempotent)', function () {
    // Force IN twice — second call must remain IN, not flip OFF.
    $this->actingAs($this->creator)
        ->postJson("/api/matches/{$this->match->id}/side-bet/toggle/{$this->alice->id}", ['in' => true])
        ->assertJson(['in_pot' => true]);

    $this->actingAs($this->creator)
        ->postJson("/api/matches/{$this->match->id}/side-bet/toggle/{$this->alice->id}", ['in' => true])
        ->assertJson(['in_pot' => true]);

    expect($this->match->sideBetShooters()->count())->toBe(1);
});

it('rejects unauthenticated requests', function () {
    $this->postJson("/api/matches/{$this->match->id}/side-bet/toggle/{$this->alice->id}")
        ->assertUnauthorized();
});

it('rejects non-MD users', function () {
    $rando = User::factory()->create(['role' => 'shooter']);

    $this->actingAs($rando)
        ->postJson("/api/matches/{$this->match->id}/side-bet/toggle/{$this->alice->id}")
        ->assertForbidden();
});

it('rejects shooters from a different match', function () {
    $otherMatch = ShootingMatch::factory()->active()->create(['side_bet_enabled' => true]);
    $otherSquad = Squad::factory()->create(['match_id' => $otherMatch->id]);
    $stranger = Shooter::factory()->create(['squad_id' => $otherSquad->id]);

    $this->actingAs($this->creator)
        ->postJson("/api/matches/{$this->match->id}/side-bet/toggle/{$stranger->id}")
        ->assertStatus(404);
});

it('refuses to toggle when side-bet is disabled on the match', function () {
    $this->match->update(['side_bet_enabled' => false]);

    $this->actingAs($this->creator)
        ->postJson("/api/matches/{$this->match->id}/side-bet/toggle/{$this->alice->id}")
        ->assertStatus(422);
});

it('locks toggles once the match is Completed', function () {
    $this->match->sideBetShooters()->attach($this->alice->id);
    $this->match->update(['status' => MatchStatus::Completed]);

    $this->actingAs($this->creator)
        ->postJson("/api/matches/{$this->match->id}/side-bet/toggle/{$this->alice->id}")
        ->assertStatus(423);

    // Pivot row must be untouched.
    expect(DB::table('side_bet_shooters')->where('shooter_id', $this->alice->id)->exists())->toBeTrue();
});

it('returns the full roster with in_pot flags via GET /side-bet/buy-ins', function () {
    $this->match->sideBetShooters()->attach($this->alice->id);

    $response = $this->actingAs($this->creator)
        ->getJson("/api/matches/{$this->match->id}/side-bet/buy-ins")
        ->assertOk()
        ->assertJsonPath('enabled', true)
        ->assertJsonPath('locked', false)
        ->assertJsonPath('totals.in', 1)
        ->assertJsonPath('totals.total', 2);

    $shooters = collect($response->json('shooters'));
    expect($shooters->firstWhere('name', 'Alice')['in_pot'])->toBeTrue();
    expect($shooters->firstWhere('name', 'Bob')['in_pot'])->toBeFalse();
});

it('GET buy-ins reports locked once the match is Completed', function () {
    $this->match->update(['status' => MatchStatus::Completed]);

    $this->actingAs($this->creator)
        ->getJson("/api/matches/{$this->match->id}/side-bet/buy-ins")
        ->assertOk()
        ->assertJsonPath('locked', true);
});
