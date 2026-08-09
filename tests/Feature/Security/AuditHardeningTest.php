<?php

use App\Models\MatchRegistration;
use App\Models\Organization;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
 * Regression coverage for the "fix all" security pass (2026-08-09) that
 * followed the RBAC audit. Each block locks a specific hole that was
 * closed:
 *   - Sanctum `scoring` ability now enforced on the scoring API.
 *   - updateShooterStatus requires the score bar (dq requires MD).
 *   - proof-of-payment is gated (admin / owning-org admin / uploader).
 *   - org registration approve/reject is scoped to the org's matches.
 */

beforeEach(function () {
    $this->org = Organization::factory()->create();

    $this->md = User::factory()->create();
    $this->org->admins()->attach($this->md, ['is_match_director' => true]);

    $this->ro = User::factory()->create();
    $this->org->admins()->attach($this->ro, ['is_range_officer' => true]);

    $this->shooter = User::factory()->create(); // pure shooter, no org role
    $this->platformAdmin = User::factory()->admin()->create();

    $this->match = ShootingMatch::factory()->active()->create([
        'created_by' => $this->md->id,
        'organization_id' => $this->org->id,
        'scoring_type' => 'prs',
    ]);

    TargetSet::factory()->create(['match_id' => $this->match->id]);
    $squad = Squad::factory()->create(['match_id' => $this->match->id]);
    $this->shooterRow = Shooter::factory()->create(['squad_id' => $squad->id]);
});

// ── H12: Sanctum `scoring` ability enforced on the scoring surface ──

test('a token without the scoring ability is rejected from the scoring API', function () {
    Sanctum::actingAs($this->ro, ['member']);

    $this->postJson("/api/matches/{$this->match->id}/scores", [])
        ->assertForbidden();
});

test('a scoping token with the scoring ability reaches the scoring API', function () {
    Sanctum::actingAs($this->ro, ['scoring']);

    // Range officer holds the score policy, and the token carries `scoring`,
    // so the request clears both bars — any non-403 (422 for empty payload,
    // etc.) proves the authorization layer let it through.
    expect($this->postJson("/api/matches/{$this->match->id}/scores", [])->status())
        ->not->toBe(403);
});

// ── C1: updateShooterStatus requires the score bar; dq requires MD ──

test('a pure shooter cannot change another shooter status', function () {
    $this->actingAs($this->shooter)
        ->patchJson("/api/matches/{$this->match->id}/shooters/{$this->shooterRow->id}/status", [
            'status' => 'no_show',
        ])
        ->assertForbidden();
});

test('a range officer can flag a shooter as no_show', function () {
    $r = $this->actingAs($this->ro)
        ->patchJson("/api/matches/{$this->match->id}/shooters/{$this->shooterRow->id}/status", [
            'status' => 'no_show',
        ]);
    expect($r->status())->not->toBe(403);
});

test('a range officer cannot dq a shooter through the status endpoint', function () {
    $this->actingAs($this->ro)
        ->patchJson("/api/matches/{$this->match->id}/shooters/{$this->shooterRow->id}/status", [
            'status' => 'dq',
        ])
        ->assertForbidden();
});

test('a match director can dq a shooter through the status endpoint', function () {
    $r = $this->actingAs($this->md)
        ->patchJson("/api/matches/{$this->match->id}/shooters/{$this->shooterRow->id}/status", [
            'status' => 'dq',
        ]);
    expect($r->status())->not->toBe(403);
});

// ── H13: proof-of-payment access is gated (private disk + policy) ──

test('proof of payment is not accessible to an unrelated member', function () {
    $registration = MatchRegistration::factory()->proofSubmitted()->create([
        'match_id' => $this->match->id,
        'user_id' => $this->shooter->id,
    ]);

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('registrations.proof-of-payment', $registration))
        ->assertForbidden();
});

test('proof of payment authorizes the uploader and owning-org staff', function () {
    $registration = MatchRegistration::factory()->proofSubmitted()->create([
        'match_id' => $this->match->id,
        'user_id' => $this->shooter->id,
    ]);

    // Uploader, org MD and platform admin all clear authorization; the fake
    // file doesn't exist on disk so they land on 404 (missing file) rather
    // than 403 (forbidden) — which is exactly the boundary we're locking.
    foreach ([$this->shooter, $this->md, $this->platformAdmin] as $viewer) {
        expect($this->actingAs($viewer)->get(route('registrations.proof-of-payment', $registration))->status())
            ->not->toBe(403);
    }
});

// ── C2: org registration approve/reject scoped to the org's own matches ──

test('an org admin cannot approve another organizations registration', function () {
    $otherOrg = Organization::factory()->create();
    $otherMatch = ShootingMatch::factory()->active()->create([
        'organization_id' => $otherOrg->id,
    ]);
    $foreignReg = MatchRegistration::factory()->create([
        'match_id' => $otherMatch->id,
        'user_id' => $this->shooter->id,
        'payment_status' => 'proof_submitted',
    ]);

    try {
        Livewire::actingAs($this->md)
            ->test('org.registrations', ['organization' => $this->org])
            ->call('approve', $foreignReg->id);
    } catch (\Throwable $e) {
        // findOrFail scoping surfaces as ModelNotFoundException / 404.
    }

    expect($foreignReg->fresh()->payment_status)->toBe('proof_submitted');
});
