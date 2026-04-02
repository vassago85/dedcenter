<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\ShootingMatch;

it('generates a unique slug on creation', function () {
    $user = User::factory()->create();
    $org = Organization::create([
        'name' => 'Test League',
        'type' => 'league',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    expect($org->slug)->toBe('test-league');

    $org2 = Organization::create([
        'name' => 'Test League',
        'type' => 'league',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    expect($org2->slug)->toBe('test-league-1');
});

it('identifies type correctly', function () {
    $league = Organization::factory()->league()->create();
    $club = Organization::factory()->club()->create();
    $competition = Organization::factory()->competition()->create();
    $challenge = Organization::factory()->challenge()->create();

    expect($league->isLeague())->toBeTrue();
    expect($club->isClub())->toBeTrue();
    expect($competition->isCompetition())->toBeTrue();
    expect($challenge->isChallenge())->toBeTrue();
});

it('detects active and inactive status', function () {
    $active = Organization::factory()->create(['status' => 'active']);
    $inactive = Organization::factory()->create(['status' => 'inactive']);
    $pending = Organization::factory()->pending()->create();

    expect($active->isActive())->toBeTrue();
    expect($inactive->isActive())->toBeFalse();
    expect($inactive->isInactive())->toBeTrue();
    expect($pending->isActive())->toBeFalse();
    expect($pending->isPending())->toBeTrue();
});

it('tracks parent-child relationships', function () {
    $league = Organization::factory()->league()->create();
    $club = Organization::factory()->club()->create(['parent_id' => $league->id]);

    expect($club->parent->id)->toBe($league->id);
    expect($league->children)->toHaveCount(1);
    expect($league->children->first()->id)->toBe($club->id);
});

it('detects ownership', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $org = Organization::factory()->create(['created_by' => $owner->id]);
    $org->admins()->attach($owner->id, ['role' => 'owner']);

    expect($org->isOwnedBy($owner))->toBeTrue();
    expect($org->isOwnedBy($other))->toBeFalse();
});

it('checks if user can manage', function () {
    $admin = User::factory()->admin()->create();
    $orgAdmin = User::factory()->create();
    $member = User::factory()->create();

    $org = Organization::factory()->create();
    $org->admins()->attach($orgAdmin->id, ['role' => 'admin']);

    expect($org->userCanManage($admin))->toBeTrue();
    expect($org->userCanManage($orgAdmin))->toBeTrue();
    expect($org->userCanManage($member))->toBeFalse();
});

it('resolves by slug in routes', function () {
    $org = Organization::factory()->create();

    expect($org->getRouteKeyName())->toBe('slug');
});

it('collects all match IDs for league including children', function () {
    $league = Organization::factory()->league()->create();
    $club = Organization::factory()->club()->create(['parent_id' => $league->id]);

    $leagueMatch = ShootingMatch::factory()->create(['organization_id' => $league->id]);
    $clubMatch = ShootingMatch::factory()->create(['organization_id' => $club->id]);

    $allIds = $league->allMatchIds();

    expect($allIds)->toContain($leagueMatch->id);
    expect($allIds)->toContain($clubMatch->id);
});
