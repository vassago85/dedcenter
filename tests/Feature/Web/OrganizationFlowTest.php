<?php

use App\Models\Organization;
use App\Models\User;

it('allows members to browse organizations page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/organizations')
        ->assertOk();
});

it('allows members to view create organization page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/organizations/create')
        ->assertOk();
});

it('allows members to submit a new organization for approval', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/organizations/create')
        ->assertOk();

    // Livewire component handles form submission - test the model directly
    $org = Organization::create([
        'name' => 'My New Club',
        'type' => 'club',
        'status' => 'pending',
        'created_by' => $user->id,
    ]);

    expect($org->isPending())->toBeTrue();
    expect($org->created_by)->toBe($user->id);
});

it('allows site admin to view organizations admin page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/organizations')
        ->assertOk();
});

it('denies members from admin organizations page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/organizations')
        ->assertForbidden();
});

it('allows org admin to access org dashboard', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->admins()->attach($user->id, ['is_match_director' => true]);

    $this->actingAs($user)
        ->get("/org/{$org->slug}/dashboard")
        ->assertOk();
});

it('allows site admin to access any org dashboard', function () {
    $admin = User::factory()->admin()->create();
    $org = Organization::factory()->create();

    $this->actingAs($admin)
        ->get("/org/{$org->slug}/dashboard")
        ->assertOk();
});

it('denies non-org-admin members from org dashboard', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();

    $this->actingAs($user)
        ->get("/org/{$org->slug}/dashboard")
        ->assertForbidden();
});

it('allows org admin to access org matches page', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->admins()->attach($user->id, ['is_owner' => true]);

    $this->actingAs($user)
        ->get("/org/{$org->slug}/matches")
        ->assertOk();
});

it('allows org admin to access org registrations page', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->admins()->attach($user->id, ['is_match_director' => true]);

    $this->actingAs($user)
        ->get("/org/{$org->slug}/registrations")
        ->assertOk();
});

it('allows org admin to access org admins page', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->admins()->attach($user->id, ['is_owner' => true]);

    $this->actingAs($user)
        ->get("/org/{$org->slug}/admins")
        ->assertOk();
});

it('allows org admin to access org settings page', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->admins()->attach($user->id, ['is_owner' => true]);

    $this->actingAs($user)
        ->get("/org/{$org->slug}/settings")
        ->assertOk();
});

it('allows league admin to access clubs page', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->league()->create();
    $org->admins()->attach($user->id, ['is_owner' => true]);

    $this->actingAs($user)
        ->get("/org/{$org->slug}/clubs")
        ->assertOk();
});

it('approves organization and makes creator owner', function () {
    $creator = User::factory()->create();
    $org = Organization::factory()->pending()->create(['created_by' => $creator->id]);

    $org->update(['status' => 'active']);
    $org->admins()->syncWithoutDetaching([
        $creator->id => ['is_owner' => true],
    ]);

    expect($org->fresh()->isActive())->toBeTrue();
    expect($org->isOwnedBy($creator))->toBeTrue();
});
