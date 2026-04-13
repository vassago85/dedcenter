<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\User;
use App\Enums\MatchStatus;
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

test('portal routes return 404 when org is not entitled to the add-on', function () {
    $this->org->update([
        'portal_entitled' => false,
        'portal_enabled' => true,
    ]);

    $this->get(route('portal.home', $this->org))->assertNotFound();
});

test('org settings page shows branding fields', function () {
    $this->org->admins()->attach($this->admin->id, ['is_owner' => true]);

    $this->actingAs($this->admin)
        ->get(route('org.settings', $this->org))
        ->assertOk()
        ->assertSee('Public Portal')
        ->assertSee('Primary Color');
});
