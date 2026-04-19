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

it('lists shooters alphabetically across squads, not grouped by squad', function () {
    // Two squads, three shooters whose alphabetical order interleaves
    // the squads. If the view were still grouped by squad, Bob would
    // come last (Squad B comes after Squad A). In the new flat A→Z
    // layout, Bob sits between Alice and Carol.
    $squadB = Squad::create(['match_id' => $this->match->id, 'name' => 'Squad B', 'sort_order' => 2]);

    Shooter::create(['squad_id' => $this->squad->id, 'name' => 'Alice Smith', 'status' => 'active']);
    Shooter::create(['squad_id' => $squadB->id,       'name' => 'Bob Jones',    'status' => 'active']);
    Shooter::create(['squad_id' => $this->squad->id, 'name' => 'Carol Adams',  'status' => 'active']);

    $html = $this->actingAs($this->user)
        ->get("/org/{$this->org->slug}/matches/{$this->match->id}/side-bet")
        ->assertOk()
        ->getContent();

    $aliceAt = strpos($html, 'Alice Smith');
    $bobAt   = strpos($html, 'Bob Jones');
    $carolAt = strpos($html, 'Carol Adams');

    expect($aliceAt)->toBeGreaterThan(0)
        ->and($bobAt)->toBeGreaterThan($aliceAt)
        ->and($carolAt)->toBeGreaterThan($bobAt);
});

it('does not render per-squad group headers on the buy-in page', function () {
    $squadB = Squad::create(['match_id' => $this->match->id, 'name' => 'Zulu Squad', 'sort_order' => 99]);
    Shooter::create(['squad_id' => $squadB->id, 'name' => 'Zara Z', 'status' => 'active']);

    $html = $this->actingAs($this->user)
        ->get("/org/{$this->org->slug}/matches/{$this->match->id}/side-bet")
        ->assertOk()
        ->getContent();

    // Squad name MAY appear inline on the shooter row, but it must NOT
    // appear as a section heading (old UI emitted an <h3> per squad).
    // We check that there's no heading-style element that is JUST the
    // squad name — i.e. the old "<h3 class=...>Zulu Squad</h3>"
    // pattern should be gone.
    expect($html)->not->toContain('>Zulu Squad</h3>');
});

it('renders a search input on the buy-in page', function () {
    $this->actingAs($this->user)
        ->get("/org/{$this->org->slug}/matches/{$this->match->id}/side-bet")
        ->assertOk()
        ->assertSee('Search by name, bib number, or squad', false)
        ->assertSee('aria-label="Search shooters"', false);
});
