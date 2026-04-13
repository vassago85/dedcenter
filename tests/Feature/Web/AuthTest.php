<?php

use App\Models\User;

it('shows the landing page to guests', function () {
    $this->get('/')->assertOk();
});

it('shows the landing page to logged-in members', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertOk();
});

it('shows the landing page to logged-in admins', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertOk();
});

it('shows login page', function () {
    $this->get('/login')->assertOk();
});

it('shows register page', function () {
    $this->get('/register')->assertOk();
});

it('redirects guests from dashboard to login', function () {
    $this->get('/dashboard')
        ->assertRedirect('/login');
});

it('allows members to access member dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});

it('allows members to access matches browse', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/matches')
        ->assertOk();
});

it('allows admin to access admin dashboard', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get('/admin/dashboard')
        ->assertOk();
});

it('denies members from admin dashboard', function () {
    $user = User::factory()->create(['role' => 'member']);

    $this->actingAs($user)
        ->get('/admin/dashboard')
        ->assertForbidden();
});

it('denies members from admin matches', function () {
    $user = User::factory()->create(['role' => 'member']);

    $this->actingAs($user)
        ->get('/admin/matches')
        ->assertForbidden();
});

it('denies members from admin registrations', function () {
    $user = User::factory()->create(['role' => 'member']);

    $this->actingAs($user)
        ->get('/admin/registrations')
        ->assertForbidden();
});

it('denies members from admin settings', function () {
    $user = User::factory()->create(['role' => 'member']);

    $this->actingAs($user)
        ->get('/admin/settings')
        ->assertForbidden();
});

it('logs out and redirects to landing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});
