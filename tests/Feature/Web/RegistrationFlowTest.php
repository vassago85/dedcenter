<?php

use App\Models\MatchRegistration;
use App\Models\Setting;
use App\Models\ShootingMatch;
use App\Models\User;
use App\Enums\MatchStatus;
use Livewire\Volt\Volt;

beforeEach(function () {
    Setting::set('bank_reference_prefix', 'DC');
});

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

    Volt::actingAs($user)
        ->test('member.match-detail', ['match' => $match])
        ->call('register');

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

    Volt::actingAs($user)
        ->test('member.match-detail', ['match' => $match])
        ->call('register');

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

    Volt::actingAs($user)
        ->test('member.match-detail', ['match' => $match])
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
