<?php

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\PrsShotScore;
use App\Models\Score;
use App\Models\ScoreAuditLog;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Score correction reason threading
|--------------------------------------------------------------------------
| Both ScoreController::store and PrsScoreController::store now accept an
| optional batch-wide `correction_reason` field. When present, it gets
| persisted onto every score_audit_logs.reason for the rows that mutate
| in this batch. The Match Director reads this through the corrections
| feed component.
|
| These tests lock the contract so we don't silently start dropping the
| reason or, conversely, start requiring it on first-time score capture
| (which would break the live scoring app for fresh data).
*/

beforeEach(function () {
    $this->creator = User::factory()->create(['role' => 'owner']);
    $this->match = ShootingMatch::factory()->create([
        'created_by' => $this->creator->id,
        'status' => MatchStatus::Active,
    ]);
    $this->ts = TargetSet::factory()->create(['match_id' => $this->match->id]);
    $this->gong = Gong::factory()->create(['target_set_id' => $this->ts->id, 'number' => 1]);
    $this->squad = Squad::factory()->create(['match_id' => $this->match->id]);
    $this->shooter = Shooter::factory()->create(['squad_id' => $this->squad->id]);
});

it('accepts a fresh standard score WITHOUT a correction_reason (new captures stay frictionless)', function () {
    $this->actingAs($this->creator)
        ->postJson("/api/matches/{$this->match->id}/scores", [
            'scores' => [[
                'shooter_id' => $this->shooter->id,
                'gong_id' => $this->gong->id,
                'is_hit' => true,
                'device_id' => 'tablet-1',
                'recorded_at' => now()->toIso8601String(),
            ]],
        ])
        ->assertOk();

    expect(Score::count())->toBe(1);
    $log = ScoreAuditLog::first();
    expect($log->action)->toBe('created');
    expect($log->reason)->toBeNull();
});

it('persists the correction_reason on a hit→miss flip of an already-recorded score', function () {
    Score::create([
        'shooter_id' => $this->shooter->id,
        'gong_id' => $this->gong->id,
        'is_hit' => true,
        'recorded_at' => now(),
    ]);

    $this->actingAs($this->creator)
        ->postJson("/api/matches/{$this->match->id}/scores", [
            'correction_reason' => 'SO mistapped — caller called miss.',
            'scores' => [[
                'shooter_id' => $this->shooter->id,
                'gong_id' => $this->gong->id,
                'is_hit' => false,
                'device_id' => 'tablet-1',
                'recorded_at' => now()->toIso8601String(),
            ]],
        ])
        ->assertOk();

    $log = ScoreAuditLog::where('action', 'updated')->first();
    expect($log)->not->toBeNull();
    expect($log->reason)->toBe('SO mistapped — caller called miss.');
});

it('persists the correction_reason on a deletion', function () {
    Score::create([
        'shooter_id' => $this->shooter->id,
        'gong_id' => $this->gong->id,
        'is_hit' => true,
        'recorded_at' => now(),
    ]);

    $this->actingAs($this->creator)
        ->postJson("/api/matches/{$this->match->id}/scores", [
            'correction_reason' => 'Wrong shooter scored — removing.',
            'deleted_scores' => [[
                'shooter_id' => $this->shooter->id,
                'gong_id' => $this->gong->id,
            ]],
        ])
        ->assertOk();

    $log = ScoreAuditLog::where('action', 'deleted')->first();
    expect($log)->not->toBeNull();
    expect($log->reason)->toBe('Wrong shooter scored — removing.');
});

it('rejects an unreasonably short correction_reason (validates min 3 chars)', function () {
    $this->actingAs($this->creator)
        ->postJson("/api/matches/{$this->match->id}/scores", [
            'correction_reason' => 'a',
            'scores' => [[
                'shooter_id' => $this->shooter->id,
                'gong_id' => $this->gong->id,
                'is_hit' => true,
                'device_id' => 'tablet-1',
                'recorded_at' => now()->toIso8601String(),
            ]],
        ])
        ->assertStatus(422);
});

it('threads correction_reason into PrsShotScore audit on a result change', function () {
    $this->match->update(['scoring_type' => 'prs']);
    $this->ts->update(['total_shots' => 1]);

    PrsShotScore::create([
        'match_id' => $this->match->id,
        'stage_id' => $this->ts->id,
        'shooter_id' => $this->shooter->id,
        'shot_number' => 1,
        'result' => 'hit',
    ]);

    $this->actingAs($this->creator)
        ->postJson("/api/matches/{$this->match->id}/stages/{$this->ts->id}/score", [
            'shooter_id' => $this->shooter->id,
            'squad_id' => $this->squad->id,
            'shots' => [['shot_number' => 1, 'result' => 'miss']],
            'correction_reason' => 'Caller revised — miss not hit.',
        ])
        ->assertOk();

    $log = ScoreAuditLog::where('auditable_type', PrsShotScore::class)
        ->where('action', 'updated')
        ->first();
    expect($log)->not->toBeNull();
    expect($log->reason)->toBe('Caller revised — miss not hit.');
});
