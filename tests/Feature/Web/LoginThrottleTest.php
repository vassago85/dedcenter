<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('locks the web login after five failed attempts, even with the right password', function () {
    $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

    foreach (range(1, 5) as $_) {
        Volt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'wrong')
            ->call('login')
            ->assertHasErrors(['email']);
    }

    Volt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'correct-horse')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

it('clears the failure count after a successful login', function () {
    $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

    foreach (range(1, 4) as $_) {
        Volt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'wrong')
            ->call('login');
    }

    Volt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'correct-horse')
        ->call('login')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
});
