<?php

use App\Enums\MatchStatus;
use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $this->org = Organization::factory()->create(['created_by' => $this->owner->id]);
    $this->org->admins()->attach($this->owner->id, ['is_owner' => true]);
    $this->match = ShootingMatch::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->owner->id,
        'scoring_type' => 'standard',
        'status' => MatchStatus::Draft,
    ]);
});

it('adds a target set from the bound distance field', function () {
    $this->actingAs($this->owner);

    Volt::test('org.matches.edit', ['organization' => $this->org, 'match' => $this->match])
        ->set('tsDistance', '400')
        ->call('addTargetSet')
        ->assertHasNoErrors();

    expect($this->match->fresh()->targetSets()->count())->toBe(1);
    expect($this->match->fresh()->targetSets()->first()->distance_meters)->toBe(400);
});

it('shows a validation error instead of silently doing nothing on empty distance', function () {
    $this->actingAs($this->owner);

    Volt::test('org.matches.edit', ['organization' => $this->org, 'match' => $this->match])
        ->set('tsDistance', '')
        ->call('addTargetSet')
        ->assertHasErrors(['tsDistance']);

    expect($this->match->fresh()->targetSets()->count())->toBe(0);
});

it('renders the newly added target set in the stages list', function () {
    $this->actingAs($this->owner);

    $component = Volt::test('org.matches.edit', ['organization' => $this->org, 'match' => $this->match])
        ->set('tsDistance', '400')
        ->call('addTargetSet');

    $newId = $this->match->fresh()->targetSets()->first()->id;

    $component->assertSeeHtml('wire:key="ts-'.$newId.'"');
});

it('adds standard targets to a target set', function () {
    $this->actingAs($this->owner);

    $ts = $this->match->targetSets()->create([
        'label' => '400m', 'distance_meters' => 400, 'distance_multiplier' => 4, 'sort_order' => 1,
    ]);

    Volt::test('org.matches.edit', ['organization' => $this->org, 'match' => $this->match])
        ->call('populateStandardTargets', $ts->id)
        ->assertHasNoErrors();

    expect($ts->fresh()->gongs()->count())->toBe(5);
});

it('opens the custom target form when Add Custom Target is clicked', function () {
    $this->actingAs($this->owner);

    $ts = $this->match->targetSets()->create([
        'label' => '400m', 'distance_meters' => 400, 'distance_multiplier' => 4, 'sort_order' => 1,
    ]);

    Volt::test('org.matches.edit', ['organization' => $this->org, 'match' => $this->match])
        ->call('startAddGong', $ts->id)
        ->assertSet('addingGongToTargetSetId', $ts->id)
        ->assertSee('Add Target');
});
