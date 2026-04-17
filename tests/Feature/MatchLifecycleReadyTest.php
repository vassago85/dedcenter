<?php

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;

/**
 * Lifecycle contract tests for the Ready stage, added between SquaddingClosed
 * and Active. Ready means "tablets can pull the match, but scoring is still
 * locked until the first shot lands or the MD taps Start Match."
 */

describe('MatchStatus enum Ready stage', function () {
    it('places Ready between SquaddingClosed and Active by ordinal', function () {
        expect(MatchStatus::SquaddingClosed->ordinal())->toBeLessThan(MatchStatus::Ready->ordinal());
        expect(MatchStatus::Ready->ordinal())->toBeLessThan(MatchStatus::Active->ordinal());
    });

    it('allows forward transitions SquaddingClosed -> Ready -> Active', function () {
        expect(MatchStatus::SquaddingClosed->canTransitionTo(MatchStatus::Ready))->toBeTrue();
        expect(MatchStatus::Ready->canTransitionTo(MatchStatus::Active))->toBeTrue();
    });

    it('allows backward transitions Active -> Ready and Ready -> SquaddingClosed', function () {
        expect(MatchStatus::Active->canTransitionTo(MatchStatus::Ready))->toBeTrue();
        expect(MatchStatus::Ready->canTransitionTo(MatchStatus::SquaddingClosed))->toBeTrue();
    });

    it('does not allow Ready to go directly to Completed', function () {
        expect(MatchStatus::Ready->canTransitionTo(MatchStatus::Completed))->toBeFalse();
    });

    it('produces a forward-direction warning when entering Ready from SquaddingClosed', function () {
        $warning = MatchStatus::Ready->transitionWarning(MatchStatus::SquaddingClosed);
        expect($warning)->toContain('Tablets can download');
    });

    it('produces a distinct backward warning when leaving Ready for SquaddingClosed', function () {
        $warning = MatchStatus::SquaddingClosed->transitionWarning(MatchStatus::Ready);
        expect($warning)->toContain('Match goes back to pre-Ready');
    });
});

describe('PWA /api/matches visibility', function () {
    it('includes Ready matches in the tablet-downloadable list', function () {
        $user = User::factory()->create();
        ShootingMatch::factory()->ready()->create([
            'name' => 'Tablet Ready Match',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/matches');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Tablet Ready Match');
    });

    it('does NOT expose a SquaddingClosed match to the tablet list', function () {
        $user = User::factory()->create();
        ShootingMatch::factory()->create([
            'status' => MatchStatus::SquaddingClosed,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->getJson('/api/matches')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
});

describe('Auto-promote Ready to Active on first score', function () {
    beforeEach(function () {
        $this->creator = User::factory()->create();
        $this->match = ShootingMatch::factory()->ready()->create(['created_by' => $this->creator->id]);
        $this->targetSet = TargetSet::factory()->create(['match_id' => $this->match->id]);
        $this->gong = Gong::factory()->create(['target_set_id' => $this->targetSet->id, 'number' => 1]);
        $this->squad = Squad::factory()->create(['match_id' => $this->match->id]);
        $this->shooter = Shooter::factory()->create(['squad_id' => $this->squad->id]);
    });

    it('transitions Ready -> Active when a score lands', function () {
        $payload = [
            'scores' => [[
                'shooter_id' => $this->shooter->id,
                'gong_id' => $this->gong->id,
                'is_hit' => true,
                'device_id' => 'tablet-1',
                'recorded_at' => now()->toIso8601String(),
            ]],
        ];

        $this->actingAs($this->creator)
            ->postJson("/api/matches/{$this->match->id}/scores", $payload)
            ->assertOk();

        expect($this->match->fresh()->status)->toBe(MatchStatus::Active);
    });

    it('does NOT re-promote an already-Active match', function () {
        $this->match->update(['status' => MatchStatus::Active]);

        $payload = [
            'scores' => [[
                'shooter_id' => $this->shooter->id,
                'gong_id' => $this->gong->id,
                'is_hit' => true,
                'device_id' => 'tablet-1',
                'recorded_at' => now()->toIso8601String(),
            ]],
        ];

        $this->actingAs($this->creator)
            ->postJson("/api/matches/{$this->match->id}/scores", $payload)
            ->assertOk();

        expect($this->match->fresh()->status)->toBe(MatchStatus::Active);
    });

    it('does NOT promote on an empty score payload', function () {
        $this->actingAs($this->creator)
            ->postJson("/api/matches/{$this->match->id}/scores", ['scores' => []])
            ->assertOk();

        expect($this->match->fresh()->status)->toBe(MatchStatus::Ready);
    });
});
