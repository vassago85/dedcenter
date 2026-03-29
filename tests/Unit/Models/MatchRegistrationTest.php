<?php

use App\Models\MatchRegistration;
use App\Models\Setting;
use App\Models\ShootingMatch;
use App\Models\User;

it('generates a unique payment reference', function () {
    Setting::set('bank_reference_prefix', 'DC');

    $user = User::factory()->create(['name' => 'John Smith']);

    $ref = MatchRegistration::generatePaymentReference($user);

    expect($ref)->toStartWith('DC-SMITH-')
        ->and(strlen($ref))->toBeGreaterThan(5);
});

it('generates unique references for the same user', function () {
    Setting::set('bank_reference_prefix', 'DC');

    $user = User::factory()->create(['name' => 'Jane Doe']);

    $refs = collect(range(1, 5))->map(fn () => MatchRegistration::generatePaymentReference($user));

    expect($refs->unique()->count())->toBe(5);
});

it('has correct relationship to user', function () {
    $user = User::factory()->create();
    $match = ShootingMatch::factory()->create();

    $reg = MatchRegistration::factory()->create([
        'match_id' => $match->id,
        'user_id' => $user->id,
    ]);

    expect($reg->user->id)->toBe($user->id);
    expect($reg->match->id)->toBe($match->id);
});

it('reports correct status helpers', function () {
    $reg = new MatchRegistration(['payment_status' => 'pending_payment']);
    expect($reg->isPending())->toBeTrue();
    expect($reg->isConfirmed())->toBeFalse();

    $reg->payment_status = 'proof_submitted';
    expect($reg->isProofSubmitted())->toBeTrue();

    $reg->payment_status = 'confirmed';
    expect($reg->isConfirmed())->toBeTrue();

    $reg->payment_status = 'rejected';
    expect($reg->isRejected())->toBeTrue();
});
