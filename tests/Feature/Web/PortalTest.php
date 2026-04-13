<?php

use App\Enums\MatchStatus;
use App\Enums\PlacementKey;
use App\Enums\SponsorScope;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\ShootingMatch;
use App\Models\Sponsor;
use App\Models\SponsorAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->org = Organization::factory()->competition()->withPortal()->create([
        'created_by' => $this->admin->id,
        'primary_color' => '#b91c1c',
        'secondary_color' => '#0f172a',
        'hero_text' => 'Royal Flush 2026',
        'hero_description' => 'Year-long competition',
    ]);
});

test('portal landing page loads for portal-enabled org', function () {
    $this->get(route('portal.home', $this->org))
        ->assertOk()
        ->assertSee($this->org->hero_text);
});

test('portal landing shows organization logo in hero when logo is set', function () {
    $path = 'org-logos/'.$this->org->id.'/mark.png';
    $this->org->update(['logo_path' => $path]);

    $this->get(route('portal.home', $this->org))
        ->assertOk()
        ->assertSee($path, false)
        ->assertSee($this->org->name.' logo', false);
});

test('portal matches page loads', function () {
    $this->get(route('portal.matches', $this->org))
        ->assertOk()
        ->assertSee('Matches');
});

test('portal leaderboard page loads', function () {
    $this->get(route('portal.leaderboard', $this->org))
        ->assertOk()
        ->assertSee('Leaderboard');
});

test('portal match detail loads', function () {
    $match = ShootingMatch::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->admin->id,
    ]);

    $this->get(route('portal.matches.show', [$this->org, $match]))
        ->assertOk()
        ->assertSee($match->name);
});

test('portal layout contains org name and branding', function () {
    $this->get(route('portal.home', $this->org))
        ->assertOk()
        ->assertSee($this->org->name)
        ->assertSee('#b91c1c');
});

test('portal shows upcoming matches on landing page', function () {
    $match = ShootingMatch::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->admin->id,
        'status' => MatchStatus::Active,
        'date' => now()->addDays(7),
    ]);

    $this->get(route('portal.home', $this->org))
        ->assertOk()
        ->assertSee($match->name);
});

test('portal leaderboard shows empty state when no completed matches', function () {
    $this->get(route('portal.leaderboard', $this->org))
        ->assertOk()
        ->assertSee('No scored results yet');
});

test('portal is reachable when portal_entitled is false (access is not payment-gated)', function () {
    $this->org->update([
        'portal_entitled' => false,
        'portal_enabled' => true,
        'status' => 'active',
    ]);

    $this->get(route('portal.home', $this->org))
        ->assertOk()
        ->assertSee($this->org->hero_text);
});

test('portal routes return 404 when portal is disabled', function () {
    $this->org->update([
        'portal_enabled' => false,
        'status' => 'active',
    ]);

    $this->get(route('portal.home', $this->org))->assertNotFound();
});

test('portal renders platform portal sponsor when org lacks ad rights', function () {
    Setting::set('advertising_enabled', '1');

    $sponsor = Sponsor::create(['name' => 'Network Brand', 'slug' => 'network-brand', 'active' => true]);
    SponsorAssignment::create([
        'sponsor_id' => $sponsor->id,
        'scope_type' => SponsorScope::Platform,
        'scope_id' => null,
        'placement_key' => PlacementKey::PortalHomeStrip,
        'active' => true,
        'display_order' => 0,
    ]);

    $this->org->update(['portal_ad_rights' => false, 'portal_entitled' => false]);

    $this->get(route('portal.home', $this->org))
        ->assertOk()
        ->assertSee('Network Brand');
});

test('portal ignores org-scoped sponsor assignment without ad rights', function () {
    Setting::set('advertising_enabled', '1');

    $platform = Sponsor::create(['name' => 'Platform Only', 'slug' => 'platform-only', 'active' => true]);
    $orgSponsor = Sponsor::create(['name' => 'Hidden Org Brand', 'slug' => 'hidden-org-brand', 'active' => true]);

    SponsorAssignment::create([
        'sponsor_id' => $platform->id,
        'scope_type' => SponsorScope::Platform,
        'scope_id' => null,
        'placement_key' => PlacementKey::PortalHomeStrip,
        'active' => true,
        'display_order' => 0,
    ]);
    SponsorAssignment::create([
        'sponsor_id' => $orgSponsor->id,
        'scope_type' => SponsorScope::Organization,
        'scope_id' => $this->org->id,
        'placement_key' => PlacementKey::PortalHomeStrip,
        'active' => true,
        'display_order' => 0,
    ]);

    $this->org->update(['portal_ad_rights' => false]);

    $this->get(route('portal.home', $this->org))
        ->assertOk()
        ->assertSee('Platform Only')
        ->assertDontSee('Hidden Org Brand');
});

test('portal prefers org sponsor assignment when ad rights are enabled', function () {
    Setting::set('advertising_enabled', '1');

    $platform = Sponsor::create(['name' => 'Platform Fallback', 'slug' => 'platform-fallback', 'active' => true]);
    $orgSponsor = Sponsor::create(['name' => 'Club Pick', 'slug' => 'club-pick', 'active' => true]);

    SponsorAssignment::create([
        'sponsor_id' => $platform->id,
        'scope_type' => SponsorScope::Platform,
        'scope_id' => null,
        'placement_key' => PlacementKey::PortalHomeStrip,
        'active' => true,
        'display_order' => 0,
    ]);
    SponsorAssignment::create([
        'sponsor_id' => $orgSponsor->id,
        'scope_type' => SponsorScope::Organization,
        'scope_id' => $this->org->id,
        'placement_key' => PlacementKey::PortalHomeStrip,
        'active' => true,
        'display_order' => 0,
    ]);

    $this->org->update(['portal_ad_rights' => true]);

    $this->get(route('portal.home', $this->org))
        ->assertOk()
        ->assertSee('Club Pick')
        ->assertDontSee('Platform Fallback');
});

test('org settings page shows branding fields', function () {
    $this->org->admins()->attach($this->admin->id, ['is_owner' => true]);

    $this->actingAs($this->admin)
        ->get(route('org.settings', $this->org))
        ->assertOk()
        ->assertSee('Public portal')
        ->assertSee('Primary Color');
});
