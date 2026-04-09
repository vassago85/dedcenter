<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\User;
use App\Enums\MatchStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->creator = User::factory()->create();
    $this->otherAdmin = User::factory()->create();
    $this->siteAdmin = User::factory()->create(['role' => 'owner']);

    $this->org->admins()->attach($this->creator->id, ['is_owner' => true]);
    $this->org->admins()->attach($this->otherAdmin->id, ['is_range_officer' => true]);

    $this->match = ShootingMatch::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->creator->id,
        'status' => MatchStatus::Draft,
    ]);
});

// ── Creator can edit ──

it('allows the match creator to access the edit page', function () {
    $this->actingAs($this->creator)
        ->get("/org/{$this->org->slug}/matches/{$this->match->id}")
        ->assertOk();
});

// ── Other org admin cannot edit ──

it('denies a different org range officer from editing the match', function () {
    $this->actingAs($this->otherAdmin)
        ->get("/org/{$this->org->slug}/matches/{$this->match->id}")
        ->assertForbidden();
});

it('allows an org match director to edit a match they did not create', function () {
    $md = User::factory()->create();
    $this->org->admins()->attach($md->id, ['is_match_director' => true]);

    $this->actingAs($md)
        ->get("/org/{$this->org->slug}/matches/{$this->match->id}")
        ->assertOk();
});

// ── Site admin can override ──

it('allows a site admin to edit any match', function () {
    $this->actingAs($this->siteAdmin)
        ->get("/org/{$this->org->slug}/matches/{$this->match->id}")
        ->assertOk();
});

// ── Delete authorization is enforced at the method level ──

it('creator can access org matches index page', function () {
    $this->actingAs($this->creator)
        ->get("/org/{$this->org->slug}/matches")
        ->assertOk()
        ->assertSee($this->match->name);
});

it('other org admin sees the index but not edit/delete buttons for matches they didnt create', function () {
    $this->actingAs($this->otherAdmin)
        ->get("/org/{$this->org->slug}/matches")
        ->assertOk()
        ->assertSee($this->match->name)
        ->assertDontSee('wire:click="deleteMatch(' . $this->match->id . ')"');
});

// ── Anyone can register (member match detail page loads) ──

it('allows any authenticated user to view match detail for registration', function () {
    $this->match->update(['status' => MatchStatus::Active]);
    $randomUser = User::factory()->create();

    $this->actingAs($randomUser)
        ->get("/matches/{$this->match->id}")
        ->assertOk();
});

// ── Create page is accessible to any org admin ──

it('allows any org admin to access the create match page', function () {
    $this->actingAs($this->otherAdmin)
        ->get("/org/{$this->org->slug}/matches/create")
        ->assertOk();
});

// ── Match without created_by (legacy data) denies non-admin ──

it('denies edit for matches with null created_by for non-admin users', function () {
    $this->match->update(['created_by' => null]);

    $this->actingAs($this->otherAdmin)
        ->get("/org/{$this->org->slug}/matches/{$this->match->id}")
        ->assertForbidden();
});

it('allows site admin to edit matches with null created_by', function () {
    $this->match->update(['created_by' => null]);

    $this->actingAs($this->siteAdmin)
        ->get("/org/{$this->org->slug}/matches/{$this->match->id}")
        ->assertOk();
});
