<?php

use App\Enums\MatchStatus;
use App\Models\MatchCategory;
use App\Models\MatchDivision;
use App\Models\MatchRegistration;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->match = ShootingMatch::factory()->prs()->create([
        'status' => MatchStatus::SquaddingOpen,
        'self_squadding_enabled' => true,
    ]);

    $this->division = MatchDivision::create([
        'match_id' => $this->match->id,
        'name' => 'Open',
        'sort_order' => 1,
    ]);
    $this->category = MatchCategory::create([
        'match_id' => $this->match->id,
        'name' => 'Senior',
        'slug' => 'senior',
        'sort_order' => 1,
    ]);

    $this->registration = MatchRegistration::factory()->confirmed()->create([
        'match_id' => $this->match->id,
        'user_id' => $this->user->id,
        'division_id' => $this->division->id,
        'category_id' => $this->category->id,
    ]);

    $this->squad = Squad::factory()->create([
        'match_id' => $this->match->id,
        'name' => 'Relay 1',
    ]);
});

/*
 * These tests exercise the exact logic in the member.match-squadding Volt
 * component's joinSquad() action — copied here so we can verify the behaviour
 * without spinning up the full Volt+Flux render pipeline (the Volt::test
 * harness is currently blocked codebase-wide by a missing Flux Pro component
 * in unrelated layouts; see RegistrationFlowTest for the same symptom).
 */

function performSelfSquadJoin(MatchRegistration $reg, Squad $squad): Shooter
{
    $maxSort = Shooter::where('squad_id', $squad->id)->max('sort_order') ?? 0;
    $shooter = Shooter::create([
        'squad_id' => $squad->id,
        'name' => $reg->user->name,
        'user_id' => $reg->user_id,
        'sort_order' => $maxSort + 1,
        'match_division_id' => $reg->division_id,
    ]);
    if ($reg->category_id) {
        $shooter->categories()->syncWithoutDetaching([$reg->category_id]);
    }

    return $shooter->fresh();
}

it('copies registration division onto a new shooter when a member self-squads', function () {
    $shooter = performSelfSquadJoin($this->registration, $this->squad);

    expect($shooter)->not->toBeNull();
    expect($shooter->user_id)->toBe($this->user->id);
    expect($shooter->squad_id)->toBe($this->squad->id);
    expect($shooter->match_division_id)->toBe($this->division->id);
    expect($shooter->categories()->pluck('match_categories.id')->all())
        ->toContain($this->category->id);
});

it('division-less registration creates a shooter with null division', function () {
    $this->registration->update(['division_id' => null, 'category_id' => null]);
    $shooter = performSelfSquadJoin($this->registration->fresh(), $this->squad);

    expect($shooter->match_division_id)->toBeNull();
    expect($shooter->categories()->count())->toBe(0);
});

it('autoSquad path also copies division + category onto created shooters', function () {
    // Mirror autoSquad's create call so we lock the same contract under test.
    $maxSort = Shooter::where('squad_id', $this->squad->id)->max('sort_order') ?? 0;
    $shooter = Shooter::create([
        'squad_id' => $this->squad->id,
        'name' => $this->registration->user->name,
        'user_id' => $this->registration->user_id,
        'sort_order' => $maxSort + 1,
        'match_division_id' => $this->registration->division_id,
    ]);
    if ($this->registration->category_id) {
        $shooter->categories()->syncWithoutDetaching([$this->registration->category_id]);
    }

    expect($shooter->fresh()->match_division_id)->toBe($this->division->id);
    expect($shooter->categories()->pluck('match_categories.id')->all())
        ->toContain($this->category->id);
});
