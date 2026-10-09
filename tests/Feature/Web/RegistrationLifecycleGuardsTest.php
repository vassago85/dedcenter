<?php

use App\Concerns\HandlesMatchLifecycleTransitions;
use App\Enums\MatchStatus;
use App\Models\MatchCategory;
use App\Models\MatchDivision;
use App\Models\MatchRegistration;
use App\Models\Organization;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

function orgWithOwner(): array
{
    $owner = User::factory()->create();
    $org = Organization::factory()->create();
    $org->admins()->attach($owner, ['is_owner' => true]);

    return [$org, $owner];
}

function shootersFor(MatchRegistration $reg)
{
    return Shooter::whereHas('squad', fn ($q) => $q->where('match_id', $reg->match_id))
        ->where('user_id', $reg->user_id)
        ->get();
}

it('approving a registration twice keeps a single roster entry', function () {
    [$org, $owner] = orgWithOwner();
    $match = ShootingMatch::factory()->create(['organization_id' => $org->id, 'status' => MatchStatus::RegistrationOpen]);
    $reg = MatchRegistration::factory()->proofSubmitted()->create(['match_id' => $match->id]);

    $this->actingAs($owner);
    $page = Volt::test('org.registrations', ['organization' => $org]);
    $page->call('approve', $reg->id);
    $page->call('approve', $reg->id);
    $page->call('approveFreeEntry', $reg->id);

    expect(shootersFor($reg))->toHaveCount(1)
        ->and($reg->fresh()->isConfirmed())->toBeTrue();
});

it('approval carries the division and category chosen at entry onto the roster', function () {
    $match = ShootingMatch::factory()->create(['status' => MatchStatus::RegistrationOpen]);
    $division = MatchDivision::factory()->create(['match_id' => $match->id]);
    $category = MatchCategory::factory()->create(['match_id' => $match->id]);
    $reg = MatchRegistration::factory()->proofSubmitted()->create([
        'match_id' => $match->id,
        'division_id' => $division->id,
        'category_id' => $category->id,
    ]);

    $this->actingAs(User::factory()->create(['role' => 'owner']));
    Volt::test('admin.registrations')->call('approve', $reg->id);

    $shooter = shootersFor($reg)->sole();
    expect($shooter->match_division_id)->toBe($division->id)
        ->and($shooter->categories()->pluck('match_categories.id')->all())->toBe([$category->id]);
});

it('a shooter cannot register for a match whose registration has closed', function (MatchStatus $status) {
    $match = ShootingMatch::factory()->create(['status' => $status, 'entry_fee' => 0]);
    $this->actingAs($user = User::factory()->create());

    Volt::test('member.match-detail', ['match' => $match])
        ->set('caliber', '6.5 CM')->set('bullet_brand_type', 'ELD-M')->set('bullet_weight', '140gr')
        ->set('barrel_brand_length', 'Bartlein')->set('trigger_brand', 'TriggerTech')
        ->set('stock_chassis_brand', 'MPA')->set('muzzle_brake_silencer_brand', 'Area 419')
        ->set('scope_brand_type', 'Razor')->set('scope_mount_brand', 'Spuhr')
        ->set('bipod_brand', 'Harris')->set('contact_number', '0710000000')
        ->call('register');

    expect(MatchRegistration::where('match_id', $match->id)->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(Shooter::where('user_id', $user->id)->exists())->toBeFalse();
})->with([MatchStatus::RegistrationClosed, MatchStatus::SquaddingOpen, MatchStatus::Ready, MatchStatus::Completed]);

it('reopenMatch only moves a completed match back to active', function () {
    $host = new class {
        use HandlesMatchLifecycleTransitions;

        public ShootingMatch $match;
    };
    $host->match = ShootingMatch::factory()->create(['status' => MatchStatus::Draft]);
    Auth::login(User::factory()->create(['role' => 'owner']));

    $host->reopenMatch();
    expect($host->match->fresh()->status)->toBe(MatchStatus::Draft);

    $host->match->update(['status' => MatchStatus::Completed]);
    $host->reopenMatch();
    expect($host->match->fresh()->status)->toBe(MatchStatus::Active);
});

it('closing squadding will not put a match with no stages live', function () {
    [$org, $owner] = orgWithOwner();
    $match = ShootingMatch::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $owner->id,
        'status' => MatchStatus::SquaddingOpen,
    ]);

    $this->actingAs($owner);
    Volt::test('org.matches.squadding', ['organization' => $org, 'match' => $match])->call('closeSquadding');

    expect($match->fresh()->status)->toBe(MatchStatus::SquaddingOpen);
});
