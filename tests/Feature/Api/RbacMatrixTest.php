<?php

use App\Models\Gong;
use App\Models\Organization;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
 * RBAC regression matrix covering the endpoints touched by the RBAC
 * audit (2026-08-02). Each block picks a representative endpoint and
 * proves that every role above the required bar can reach it, and every
 * role below the bar receives a 403.
 *
 * We assert 403 for forbidden roles and `->not->toBe(403)` for allowed
 * roles so business-logic responses (422 for a bad payload, 200 on
 * success, etc.) don't fight the authz assertion — this file exists to
 * lock the AUTHORIZATION LAYER, not the happy paths.
 */

beforeEach(function () {
    $this->org = Organization::factory()->create();

    $this->owner = User::factory()->create();
    $this->org->admins()->attach($this->owner, ['is_owner' => true]);

    $this->md = User::factory()->create();
    $this->org->admins()->attach($this->md, ['is_match_director' => true]);

    $this->ro = User::factory()->create();
    $this->org->admins()->attach($this->ro, ['is_range_officer' => true]);

    $this->shooter = User::factory()->create(); // pure shooter, no org role
    $this->platformAdmin = User::factory()->admin()->create(); // role = owner

    $this->match = ShootingMatch::factory()->active()->create([
        'created_by' => $this->owner->id,
        'organization_id' => $this->org->id,
        'scoring_type' => 'prs',
    ]);

    $stage = TargetSet::factory()->create(['match_id' => $this->match->id]);
    $this->gong = Gong::factory()->create(['target_set_id' => $stage->id, 'number' => 1]);
    $squad = Squad::factory()->create(['match_id' => $this->match->id]);
    $this->shooterRow = Shooter::factory()->create(['squad_id' => $squad->id]);
});

// ── H1: prs-backfill (was unauthenticated GET; now auth:sanctum + MD + POST) ──

test('prs-backfill is unauthenticated → 401', function () {
    $this->postJson("/api/matches/{$this->match->id}/prs-backfill")
        ->assertUnauthorized();
});

test('prs-backfill rejects pure shooters', function () {
    $this->actingAs($this->shooter)
        ->postJson("/api/matches/{$this->match->id}/prs-backfill")
        ->assertForbidden();
});

test('prs-backfill rejects range officers', function () {
    $this->actingAs($this->ro)
        ->postJson("/api/matches/{$this->match->id}/prs-backfill")
        ->assertForbidden();
});

test('prs-backfill accepts match directors', function () {
    $r = $this->actingAs($this->md)
        ->postJson("/api/matches/{$this->match->id}/prs-backfill");
    expect($r->status())->not->toBe(403)->and($r->status())->not->toBe(401);
});

test('prs-backfill accepts platform admins', function () {
    $r = $this->actingAs($this->platformAdmin)
        ->postJson("/api/matches/{$this->match->id}/prs-backfill");
    expect($r->status())->not->toBe(403)->and($r->status())->not->toBe(401);
});

// ── H1 continued: prs-diagnostic (was any authed user; now MD only) ──

test('prs-diagnostic rejects range officers', function () {
    $this->actingAs($this->ro)
        ->getJson("/api/matches/{$this->match->id}/prs-diagnostic")
        ->assertForbidden();
});

test('prs-diagnostic accepts match directors', function () {
    $this->actingAs($this->md)
        ->getJson("/api/matches/{$this->match->id}/prs-diagnostic")
        ->assertOk();
});

// ── M1: match-lifecycle actions moved from RO → MD ──

test('POST /matches/{match}/complete rejects range officers (MD only now)', function () {
    $this->actingAs($this->ro)
        ->postJson("/api/matches/{$this->match->id}/complete")
        ->assertForbidden();
});

test('POST /matches/{match}/complete reaches auth layer for match directors', function () {
    $r = $this->actingAs($this->md)
        ->postJson("/api/matches/{$this->match->id}/complete");
    // 200 on happy path, 422 on business rules, but never 403 for an MD.
    expect($r->status())->not->toBe(403);
});

test('POST /matches/{match}/reopen rejects range officers', function () {
    $this->actingAs($this->ro)
        ->postJson("/api/matches/{$this->match->id}/reopen")
        ->assertForbidden();
});

test('POST /matches/{match}/reopen reaches auth layer for match directors', function () {
    $r = $this->actingAs($this->md)
        ->postJson("/api/matches/{$this->match->id}/reopen");
    expect($r->status())->not->toBe(403);
});

// ── M1 continued: single-shooter correction stays RO+ (operational) ──

test('single-shooter correction accepts range officers', function () {
    $r = $this->actingAs($this->ro)
        ->postJson("/api/matches/{$this->match->id}/shooters/{$this->shooterRow->id}/correct", [
            'scores' => [],
        ]);
    expect($r->status())->not->toBe(403);
});

test('single-shooter correction rejects unrelated shooters', function () {
    $this->actingAs($this->shooter)
        ->postJson("/api/matches/{$this->match->id}/shooters/{$this->shooterRow->id}/correct", [
            'scores' => [],
        ])
        ->assertForbidden();
});

// ── M1 continued: DQ moved from RO → MD ──

test('POST disqualifications rejects range officers (MD only now)', function () {
    $this->actingAs($this->ro)
        ->postJson("/api/matches/{$this->match->id}/disqualifications", [
            'shooter_id' => $this->shooterRow->id,
            'reason' => 'test',
        ])
        ->assertForbidden();
});

test('POST disqualifications reaches auth layer for match directors', function () {
    $r = $this->actingAs($this->md)
        ->postJson("/api/matches/{$this->match->id}/disqualifications", [
            'shooter_id' => $this->shooterRow->id,
            'reason' => 'test',
        ]);
    expect($r->status())->not->toBe(403);
});

// ── L2: badges endpoint moved from public → auth:sanctum ──

test('badges endpoint requires authentication now', function () {
    $this->getJson("/api/matches/{$this->match->id}/badges")
        ->assertUnauthorized();
});

test('badges endpoint is reachable for any authenticated user', function () {
    $this->actingAs($this->shooter)
        ->getJson("/api/matches/{$this->match->id}/badges")
        ->assertOk();
});

// ── H2: org team-management page — mutating actions are OWNER only ──

test('range officers cannot add staff via the team page', function () {
    Livewire::actingAs($this->ro)
        ->test('org.admins', ['organization' => $this->org])
        ->set('email', 'attacker@example.com')
        ->set('newRoles', ['is_match_director' => true])
        ->call('addStaff')
        ->assertStatus(403);
});

test('range officers cannot toggle roles via the team page', function () {
    Livewire::actingAs($this->ro)
        ->test('org.admins', ['organization' => $this->org])
        ->call('toggleRole', $this->ro->id, 'is_match_director')
        ->assertStatus(403);
});

test('range officers cannot remove staff via the team page', function () {
    Livewire::actingAs($this->ro)
        ->test('org.admins', ['organization' => $this->org])
        ->call('removeStaff', $this->md->id)
        ->assertStatus(403);
});

test('owners can manage the team via the team page', function () {
    // A successful call re-renders the component (no abort) — verify the
    // OWNER path is unblocked so the H2 fix isn't over-tightened.
    $target = User::factory()->create(['email' => 'newmd@example.com']);

    Livewire::actingAs($this->owner)
        ->test('org.admins', ['organization' => $this->org])
        ->set('email', $target->email)
        ->set('newRoles', ['is_range_officer' => true])
        ->call('addStaff')
        ->assertHasNoErrors();

    expect($this->org->admins()->where('user_id', $target->id)->exists())->toBeTrue();
});

// ── M3: /score page only issues scoring tokens to canScore() users ──

test('/score redirects pure shooters (no token issued)', function () {
    $this->actingAs($this->shooter)
        ->get('/score')
        ->assertRedirect(route('dashboard'));

    expect($this->shooter->tokens()->where('name', 'scoring-session')->count())->toBe(0);
});

test('/score issues a scoped scoring token for range officers', function () {
    $this->actingAs($this->ro)
        ->get('/score')
        ->assertOk();

    $token = $this->ro->tokens()->where('name', 'scoring-session')->first();
    expect($token)->not->toBeNull()
        ->and($token->abilities)->toContain('scoring')
        ->and($token->abilities)->not->toContain('*');
});
