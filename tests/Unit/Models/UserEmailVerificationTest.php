<?php

use App\Models\User;

it('sets email_verified_at when the PIN matches', function () {
    $user = User::factory()->unverified()->create([
        'email_verification_code' => '123456',
        'email_verification_code_expires_at' => now()->addMinutes(10),
    ]);

    expect($user->verifyWithCode('123456'))->toBeTrue();

    $user->refresh();

    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->email_verification_code)->toBeNull()
        ->and($user->email_verification_code_expires_at)->toBeNull();
});
