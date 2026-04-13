<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->org = Organization::factory()->create(['created_by' => $this->admin->id]);
    $this->org->admins()->attach($this->admin->id, ['is_owner' => true]);
    $this->match = ShootingMatch::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->admin->id,
    ]);
});

test('matchbook editor returns 503 when match books are disabled', function () {
    config(['deadcenter.matchbook_enabled' => false]);

    $this->actingAs($this->admin)
        ->get(route('org.matches.matchbook.edit', [
            'organization' => $this->org,
            'match' => $this->match,
        ]))
        ->assertStatus(503);
});
