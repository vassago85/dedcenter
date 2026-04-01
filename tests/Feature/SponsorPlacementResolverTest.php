<?php

use App\Enums\PlacementKey;
use App\Enums\SponsorScope;
use App\Models\Sponsor;
use App\Models\SponsorAssignment;
use App\Services\SponsorPlacementResolver;

beforeEach(function () {
    $this->resolver = new SponsorPlacementResolver;
});

it('resolves platform-level assignment when no match override', function () {
    $sponsor = Sponsor::create(['name' => 'Test', 'slug' => 'test', 'active' => true]);
    SponsorAssignment::create([
        'sponsor_id' => $sponsor->id,
        'scope_type' => SponsorScope::Platform,
        'placement_key' => PlacementKey::GlobalLeaderboard,
        'active' => true,
    ]);

    $result = $this->resolver->resolve(PlacementKey::GlobalLeaderboard);
    expect($result)->not->toBeNull();
    expect($result->sponsor->id)->toBe($sponsor->id);
});

it('match-level overrides platform-level', function () {
    $platformSponsor = Sponsor::create(['name' => 'Platform', 'slug' => 'platform', 'active' => true]);
    $matchSponsor = Sponsor::create(['name' => 'Match', 'slug' => 'match', 'active' => true]);

    SponsorAssignment::create([
        'sponsor_id' => $platformSponsor->id,
        'scope_type' => SponsorScope::Platform,
        'placement_key' => PlacementKey::GlobalLeaderboard,
        'active' => true,
    ]);
    SponsorAssignment::create([
        'sponsor_id' => $matchSponsor->id,
        'scope_type' => SponsorScope::Match,
        'scope_id' => 1,
        'placement_key' => PlacementKey::MatchLeaderboard,
        'active' => true,
    ]);

    $result = $this->resolver->resolve(PlacementKey::GlobalLeaderboard, matchId: 1);
    expect($result->sponsor->id)->toBe($matchSponsor->id);
});

it('matchbook-specific overrides match-level', function () {
    $matchSponsor = Sponsor::create(['name' => 'Match', 'slug' => 'match-mb', 'active' => true]);
    $mbSponsor = Sponsor::create(['name' => 'Matchbook', 'slug' => 'matchbook-mb', 'active' => true]);

    SponsorAssignment::create([
        'sponsor_id' => $matchSponsor->id,
        'scope_type' => SponsorScope::Match,
        'scope_id' => 1,
        'placement_key' => PlacementKey::MatchMatchbook,
        'active' => true,
    ]);
    SponsorAssignment::create([
        'sponsor_id' => $mbSponsor->id,
        'scope_type' => SponsorScope::Matchbook,
        'scope_id' => 10,
        'placement_key' => PlacementKey::MatchbookCover,
        'active' => true,
    ]);

    $result = $this->resolver->resolve(PlacementKey::MatchbookCover, matchId: 1, matchBookId: 10);
    expect($result->sponsor->id)->toBe($mbSponsor->id);
});

it('does not resolve inactive sponsors', function () {
    $sponsor = Sponsor::create(['name' => 'Inactive', 'slug' => 'inactive', 'active' => false]);
    SponsorAssignment::create([
        'sponsor_id' => $sponsor->id,
        'scope_type' => SponsorScope::Platform,
        'placement_key' => PlacementKey::GlobalResults,
        'active' => true,
    ]);

    expect($this->resolver->resolve(PlacementKey::GlobalResults))->toBeNull();
});

it('does not resolve expired sponsors', function () {
    $sponsor = Sponsor::create(['name' => 'Expired', 'slug' => 'expired', 'active' => true, 'ends_at' => now()->subDay()]);
    SponsorAssignment::create([
        'sponsor_id' => $sponsor->id,
        'scope_type' => SponsorScope::Platform,
        'placement_key' => PlacementKey::GlobalResults,
        'active' => true,
    ]);

    expect($this->resolver->resolve(PlacementKey::GlobalResults))->toBeNull();
});

it('does not resolve future sponsors', function () {
    $sponsor = Sponsor::create(['name' => 'Future', 'slug' => 'future', 'active' => true, 'starts_at' => now()->addDay()]);
    SponsorAssignment::create([
        'sponsor_id' => $sponsor->id,
        'scope_type' => SponsorScope::Platform,
        'placement_key' => PlacementKey::GlobalResults,
        'active' => true,
    ]);

    expect($this->resolver->resolve(PlacementKey::GlobalResults))->toBeNull();
});

it('returns sponsor with no logo gracefully', function () {
    $sponsor = Sponsor::create(['name' => 'NoLogo', 'slug' => 'no-logo', 'active' => true, 'logo_path' => null]);
    SponsorAssignment::create([
        'sponsor_id' => $sponsor->id,
        'scope_type' => SponsorScope::Platform,
        'placement_key' => PlacementKey::GlobalExports,
        'active' => true,
    ]);

    $result = $this->resolver->resolve(PlacementKey::GlobalExports);
    expect($result)->not->toBeNull();
    expect($result->sponsor->hasLogo())->toBeFalse();
    expect($result->sponsor->name)->toBe('NoLogo');
});

it('returns null when no valid sponsor exists', function () {
    expect($this->resolver->resolve(PlacementKey::GlobalScoring))->toBeNull();
});

it('resolveAll returns collection from highest priority level', function () {
    $s1 = Sponsor::create(['name' => 'S1', 'slug' => 's1', 'active' => true]);
    $s2 = Sponsor::create(['name' => 'S2', 'slug' => 's2', 'active' => true]);

    SponsorAssignment::create(['sponsor_id' => $s1->id, 'scope_type' => SponsorScope::Platform, 'placement_key' => PlacementKey::GlobalLeaderboard, 'active' => true, 'display_order' => 0]);
    SponsorAssignment::create(['sponsor_id' => $s2->id, 'scope_type' => SponsorScope::Match, 'scope_id' => 5, 'placement_key' => PlacementKey::MatchLeaderboard, 'active' => true, 'display_order' => 0]);

    $all = $this->resolver->resolveAll(PlacementKey::GlobalLeaderboard, matchId: 5);
    expect($all)->toHaveCount(1);
    expect($all->first()->sponsor->id)->toBe($s2->id);
});
