<?php

use App\Enums\PlacementKey;
use App\Enums\SponsorScope;
use App\Models\Organization;
use App\Models\Sponsor;
use App\Models\SponsorAssignment;
use App\Services\PortalAdResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->resolver = new PortalAdResolver;
});

it('returns null for non-portal placement keys', function () {
    $org = Organization::factory()->create();

    expect($this->resolver->resolve($org, 'match_leaderboard'))->toBeNull();
});

it('separates canAccessPortal from hasPortalAdRights on the model', function () {
    $org = Organization::factory()->create([
        'status' => 'active',
        'portal_enabled' => true,
        'portal_ad_rights' => false,
    ]);

    expect($org->canAccessPortal())->toBeTrue();
    expect($org->hasPortalAdRights())->toBeFalse();
});

it('resolves site-wide landing placement from platform scope', function () {
    $sponsor = Sponsor::create(['name' => 'Flagship', 'slug' => 'flagship', 'active' => true]);
    SponsorAssignment::create([
        'sponsor_id' => $sponsor->id,
        'scope_type' => SponsorScope::Platform,
        'scope_id' => null,
        'placement_key' => PlacementKey::LandingHeroMonthly,
        'active' => true,
        'display_order' => 0,
    ]);

    $r = $this->resolver->resolveSiteWide('landing_hero_monthly');

    expect($r)->not->toBeNull();
    expect($r->sponsor->name)->toBe('Flagship');
});
