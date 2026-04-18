<?php

use App\Enums\MatchStatus;
use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\Shooter;
use App\Models\Squad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create([
        'slug' => 'royal-flush-test',
        'royal_flush_enabled' => true,
    ]);
    $this->user = User::factory()->create();
    $this->org->admins()->attach($this->user->id, ['is_owner' => true]);

    $this->match = ShootingMatch::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'status' => MatchStatus::Ready,
        'royal_flush_enabled' => true,
        'side_bet_enabled' => true,
    ]);

    $this->squad = Squad::create([
        'match_id' => $this->match->id,
        'name' => 'Squad A',
        'sort_order' => 1,
    ]);

    $this->shooter = Shooter::create([
        'match_id' => $this->match->id,
        'squad_id' => $this->squad->id,
        'user_id' => $this->user->id,
        'name' => 'Test Shooter',
        'status' => 'active',
    ]);
});

it('loads the dedicated side-bet buy-in page', function () {
    $this->actingAs($this->user)
        ->get("/org/{$this->org->slug}/matches/{$this->match->id}/side-bet")
        ->assertOk()
        ->assertSee('Side Bet Buy-In')
        ->assertSee('Test Shooter');
});

it('shows the enable prompt when side bet is off', function () {
    $this->match->update(['side_bet_enabled' => false]);

    $this->actingAs($this->user)
        ->get("/org/{$this->org->slug}/matches/{$this->match->id}/side-bet")
        ->assertOk()
        ->assertSee('Side Bet is off')
        ->assertSee('Enable Side Bet');
});

it('denies access to someone who cannot edit the match', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get("/org/{$this->org->slug}/matches/{$this->match->id}/side-bet")
        ->assertForbidden();
});

it('registers the side-bet route under the org namespace', function () {
    expect(route('org.matches.side-bet', [$this->org, $this->match]))
        ->toContain("/org/{$this->org->slug}/matches/{$this->match->id}/side-bet");
});

it('shows the Side Bet buy-in link in the match-edit status card when org is Royal Flush', function () {
    $this->actingAs($this->user)
        ->get("/org/{$this->org->slug}/matches/{$this->match->id}")
        ->assertOk()
        ->assertSee('Side Bet Buy-In');
});
