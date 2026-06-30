<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->org = Organization::factory()->create(['created_by' => $this->admin->id]);
    $this->org->admins()->attach($this->admin->id, ['is_owner' => true]);
});

test('org create-match page renders without error', function () {
    $this->actingAs($this->admin)
        ->get(route('org.matches.create', ['organization' => $this->org]))
        ->assertOk()
        ->assertSee('New Match')
        ->assertSee('Match Details')
        ->assertSee('Create Match');
});

test('platform admin create-match page renders without error', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.matches.create'))
        ->assertOk()
        ->assertSee('New Match')
        ->assertSee('Match Details')
        ->assertSee('Create Match');
});
