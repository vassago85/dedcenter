<?php

use App\Enums\MatchStatus;
use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['email_verified_at' => now()]);
    $this->org = Organization::factory()->create(['created_by' => $this->owner->id]);
    $this->org->admins()->attach($this->owner->id, ['is_owner' => true]);
});

function finishFlowMatch(array $attrs = []): ShootingMatch
{
    return ShootingMatch::factory()->create(array_merge([
        'organization_id' => test()->org->id,
        'created_by' => test()->owner->id,
        'scoring_type' => 'standard',
    ], $attrs));
}

it('shows the finish-match flow with a Complete step while active', function () {
    $match = finishFlowMatch(['status' => MatchStatus::Active]);

    $this->actingAs($this->owner)
        ->get(route('org.matches.hub', [$this->org, $match]))
        ->assertOk()
        ->assertSee('Finish this match')
        ->assertSee('Complete the match')
        ->assertSee('Publish results');
});

it('prompts to publish once completed with scores hidden', function () {
    $match = finishFlowMatch(['status' => MatchStatus::Completed, 'scores_published' => false]);

    $this->actingAs($this->owner)
        ->get(route('org.matches.hub', [$this->org, $match]))
        ->assertOk()
        ->assertSee('Finish this match')
        ->assertSee('hidden')
        ->assertDontSee('Match finished');
});

it('confirms the match is finished once completed and published', function () {
    $match = finishFlowMatch(['status' => MatchStatus::Completed, 'scores_published' => true]);

    $this->actingAs($this->owner)
        ->get(route('org.matches.hub', [$this->org, $match]))
        ->assertOk()
        ->assertSee('Match finished — results are live');
});

it('does not show the finish flow before the match is active', function () {
    $match = finishFlowMatch(['status' => MatchStatus::Draft]);

    $this->actingAs($this->owner)
        ->get(route('org.matches.hub', [$this->org, $match]))
        ->assertOk()
        ->assertDontSee('Finish this match');
});
