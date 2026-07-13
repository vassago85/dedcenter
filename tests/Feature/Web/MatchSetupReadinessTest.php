<?php

use App\Concerns\HandlesMatchLifecycleTransitions;
use App\Enums\MatchStatus;
use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\TargetSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

function guardHost(MatchStatus $status): object
{
    $host = new class {
        use HandlesMatchLifecycleTransitions;
        public ShootingMatch $match;
    };

    $owner = User::factory()->create(['role' => 'owner']);
    $host->match = ShootingMatch::factory()->create([
        'created_by' => $owner->id,
        'status' => $status,
        'scoring_type' => 'standard',
    ]);
    Auth::login($owner);

    return $host;
}

it('blocks going live when the match has no stages', function () {
    $host = guardHost(MatchStatus::Ready);

    $host->transitionStatus(MatchStatus::Active->value);

    expect($host->match->fresh()->status)->toBe(MatchStatus::Ready);
});

it('allows going live once a stage exists', function () {
    $host = guardHost(MatchStatus::Ready);
    TargetSet::factory()->create(['match_id' => $host->match->id, 'distance_meters' => 300]);

    $host->transitionStatus(MatchStatus::Active->value);

    expect($host->match->fresh()->status)->toBe(MatchStatus::Active);
});

it('shows the standard setup checklist while a match is still being set up', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $org = Organization::factory()->create(['created_by' => $owner->id]);
    $org->admins()->attach($owner->id, ['is_owner' => true]);

    $match = ShootingMatch::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $owner->id,
        'scoring_type' => 'standard',
        'status' => MatchStatus::Draft,
    ]);

    $this->actingAs($owner)
        ->get(route('org.matches.hub', [$org, $match]))
        ->assertOk()
        ->assertSee('Setup checklist')
        ->assertSee('At least one target distance added')
        ->assertSee('Shooters added to squads');
});
