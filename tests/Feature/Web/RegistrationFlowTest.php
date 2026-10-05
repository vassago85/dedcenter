<?php

use App\Models\MatchRegistration;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\ShootingMatch;
use App\Models\User;
use App\Enums\MatchStatus;
use Livewire\Volt\Volt;

beforeEach(function () {
    Setting::set('bank_reference_prefix', 'DC');
});

function fillRequiredEquipment($component)
{
    return $component
        ->set('caliber', '6.5 Creedmoor')
        ->set('bullet_brand_type', 'Hornady ELD-M')
        ->set('bullet_weight', '140gr')
        ->set('barrel_brand_length', 'Bartlein 26"')
        ->set('trigger_brand', 'TriggerTech')
        ->set('stock_chassis_brand', 'MPA')
        ->set('muzzle_brake_silencer_brand', 'Area 419')
        ->set('scope_brand_type', 'Vortex Razor')
        ->set('scope_mount_brand', 'Spuhr')
        ->set('bipod_brand', 'Atlas')
        ->set('contact_number', '0820000000');
}

it('allows a member to view an active match detail page', function () {
    $user = User::factory()->create();
    $match = ShootingMatch::factory()->create(['status' => MatchStatus::Active]);

    $this->actingAs($user)
        ->get(route('matches.show', $match))
        ->assertOk();
});

it('allows a member to register for a free match', function () {
    $user = User::factory()->create();
    $match = ShootingMatch::factory()->create([
        'status' => MatchStatus::Active,
        'entry_fee' => null,
    ]);

    fillRequiredEquipment(Volt::actingAs($user)->test('member.match-detail', ['match' => $match]))
        ->call('register')
        ->assertHasNoErrors();

    $reg = MatchRegistration::where('match_id', $match->id)->where('user_id', $user->id)->first();

    expect($reg)->not->toBeNull()
        ->and($reg->payment_status)->toBe('confirmed');
});

it('allows a member to register for a paid match', function () {
    $user = User::factory()->create();
    $match = ShootingMatch::factory()->create([
        'status' => MatchStatus::Active,
        'entry_fee' => 150.00,
    ]);

    fillRequiredEquipment(Volt::actingAs($user)->test('member.match-detail', ['match' => $match]))
        ->call('register')
        ->assertHasNoErrors();

    $reg = MatchRegistration::where('match_id', $match->id)->where('user_id', $user->id)->first();

    expect($reg)->not->toBeNull()
        ->and($reg->payment_status)->toBe('pending_payment')
        ->and((float) $reg->amount)->toBe(150.00)
        ->and($reg->payment_reference)->toStartWith('DC-');
});

it('prevents double registration for the same match', function () {
    $user = User::factory()->create();
    $match = ShootingMatch::factory()->create([
        'status' => MatchStatus::Active,
        'entry_fee' => null,
    ]);

    fillRequiredEquipment(Volt::actingAs($user)->test('member.match-detail', ['match' => $match]))
        ->call('register')
        ->call('register');

    expect(MatchRegistration::where('match_id', $match->id)->where('user_id', $user->id)->count())->toBe(1);
});

it('allows admin to approve a registration', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();
    $match = ShootingMatch::factory()->create(['status' => MatchStatus::Active]);

    $reg = MatchRegistration::factory()->proofSubmitted()->create([
        'match_id' => $match->id,
        'user_id' => $member->id,
    ]);

    Volt::actingAs($admin)
        ->test('admin.registrations')
        ->call('approve', $reg->id);

    $reg->refresh();

    expect($reg->payment_status)->toBe('confirmed');
    expect($match->shooters()->where('user_id', $member->id)->exists())->toBeTrue();
});

it('allows admin to reject a registration', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();
    $match = ShootingMatch::factory()->create(['status' => MatchStatus::Active]);

    $reg = MatchRegistration::factory()->proofSubmitted()->create([
        'match_id' => $match->id,
        'user_id' => $member->id,
    ]);

    Volt::actingAs($admin)
        ->test('admin.registrations')
        ->call('reject', $reg->id);

    $reg->refresh();

    expect($reg->payment_status)->toBe('rejected');
});

it('shows a closed registration message on the portal when the viewer is not registered', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $match = ShootingMatch::factory()->create([
        'organization_id' => $organization->id,
        'status' => MatchStatus::Active,
    ]);

    Volt::actingAs($user)
        ->test('portal.match-detail', [
            'organization' => $organization,
            'match' => $match,
        ])
        ->assertOk()
        ->assertSee('Registration is closed.');
});

it('lets a member view a squadding match they are not registered for', function () {
    $user = User::factory()->create();
    $match = ShootingMatch::factory()->create([
        'status' => MatchStatus::SquaddingOpen,
    ]);

    Volt::actingAs($user)
        ->test('member.match-detail', ['match' => $match])
        ->assertOk()
        ->assertSee('Registration is closed.');
});
