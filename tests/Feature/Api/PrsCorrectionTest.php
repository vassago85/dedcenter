<?php

use App\Enums\PrsShotResult;
use App\Models\CorrectionLog;
use App\Models\Organization;
use App\Models\PrsShotScore;
use App\Models\PrsStageResult;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * PRS correction endpoints the scoring SPA calls. Paths and payloads match
 * the Android hub's ScoreRoutes so one client contract works on both.
 */

beforeEach(function () {
    $org = Organization::factory()->create();
    $this->md = User::factory()->create();
    $org->admins()->attach($this->md, ['is_match_director' => true]);
    $this->ro = User::factory()->create();
    $org->admins()->attach($this->ro, ['is_range_officer' => true]);

    $this->match = ShootingMatch::factory()->active()->prs()->create(['organization_id' => $org->id]);
    $this->stage1 = TargetSet::factory()->create(['match_id' => $this->match->id, 'sort_order' => 1]);
    $this->stage2 = TargetSet::factory()->create(['match_id' => $this->match->id, 'sort_order' => 2]);

    $squad = Squad::factory()->create(['match_id' => $this->match->id]);
    $this->alpha = Shooter::factory()->create(['squad_id' => $squad->id]);
    $this->bravo = Shooter::factory()->create(['squad_id' => $squad->id]);

    $this->scoreStage = function (Shooter $shooter, TargetSet $stage, array $results, float $time = 40.0): void {
        foreach ($results as $i => $result) {
            PrsShotScore::create([
                'match_id' => $this->match->id,
                'shooter_id' => $shooter->id,
                'stage_id' => $stage->id,
                'shot_number' => $i + 1,
                'result' => $result,
                'recorded_at' => now(),
            ]);
        }
        PrsStageResult::create([
            'match_id' => $this->match->id,
            'shooter_id' => $shooter->id,
            'stage_id' => $stage->id,
            'hits' => collect($results)->filter(fn ($r) => $r === PrsShotResult::Hit)->count(),
            'misses' => collect($results)->filter(fn ($r) => $r === PrsShotResult::Miss)->count(),
            'not_taken' => 0,
            'raw_time_seconds' => $time,
            'official_time_seconds' => $time,
            'completed_at' => now(),
        ]);
    };
});

test('reassign moves the stage result and shots to the other shooter', function () {
    ($this->scoreStage)($this->alpha, $this->stage1, [PrsShotResult::Hit, PrsShotResult::Hit, PrsShotResult::Miss]);

    $this->actingAs($this->md)
        ->postJson("/api/matches/{$this->match->id}/stages/{$this->stage1->id}/reassign", [
            'shooter_id' => $this->alpha->id,
            'new_shooter_id' => $this->bravo->id,
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'moved_scores' => 3,
            'shooterId' => $this->bravo->id,
            'stageId' => $this->stage1->id,
            'hits' => 2,
            'misses' => 1,
        ]);

    expect(PrsStageResult::where('shooter_id', $this->alpha->id)->exists())->toBeFalse()
        ->and(PrsStageResult::where('shooter_id', $this->bravo->id)->where('stage_id', $this->stage1->id)->value('hits'))->toBe(2)
        ->and(PrsShotScore::where('shooter_id', $this->bravo->id)->count())->toBe(3)
        ->and(CorrectionLog::where('action', 'reassign')->count())->toBe(1);
});

test('reassign replaces whatever the target shooter already had on that stage', function () {
    ($this->scoreStage)($this->alpha, $this->stage1, [PrsShotResult::Hit, PrsShotResult::Hit]);
    ($this->scoreStage)($this->bravo, $this->stage1, [PrsShotResult::Miss, PrsShotResult::Miss]);

    $this->actingAs($this->md)
        ->postJson("/api/matches/{$this->match->id}/stages/{$this->stage1->id}/reassign", [
            'shooter_id' => $this->alpha->id,
            'new_shooter_id' => $this->bravo->id,
        ])
        ->assertOk();

    expect(PrsStageResult::where('stage_id', $this->stage1->id)->count())->toBe(1)
        ->and(PrsStageResult::where('shooter_id', $this->bravo->id)->value('hits'))->toBe(2)
        ->and(PrsShotScore::where('shooter_id', $this->bravo->id)->where('result', PrsShotResult::Hit)->count())->toBe(2);
});

test('move shifts a shooter result to another stage and keeps the time', function () {
    ($this->scoreStage)($this->alpha, $this->stage1, [PrsShotResult::Hit, PrsShotResult::Miss], 37.5);

    $this->actingAs($this->md)
        ->postJson("/api/matches/{$this->match->id}/stages/{$this->stage1->id}/move", [
            'shooter_id' => $this->alpha->id,
            'new_stage_id' => $this->stage2->id,
        ])
        ->assertOk()
        ->assertJson(['success' => true, 'moved_scores' => 2]);

    $result = PrsStageResult::where('shooter_id', $this->alpha->id)->sole();
    expect($result->stage_id)->toBe($this->stage2->id)
        ->and((float) $result->official_time_seconds)->toBe(37.5)
        ->and(PrsShotScore::where('stage_id', $this->stage1->id)->exists())->toBeFalse()
        ->and(CorrectionLog::where('action', 'move_stage')->count())->toBe(1);
});

test('move rejects moving onto the same stage', function () {
    $this->actingAs($this->md)
        ->postJson("/api/matches/{$this->match->id}/stages/{$this->stage1->id}/move", [
            'shooter_id' => $this->alpha->id,
            'new_stage_id' => $this->stage1->id,
        ])
        ->assertUnprocessable();
});

test('range officers cannot reassign or move', function () {
    $this->actingAs($this->ro)
        ->postJson("/api/matches/{$this->match->id}/stages/{$this->stage1->id}/reassign", [
            'shooter_id' => $this->alpha->id,
            'new_shooter_id' => $this->bravo->id,
        ])
        ->assertForbidden();

    $this->actingAs($this->ro)
        ->postJson("/api/matches/{$this->match->id}/stages/{$this->stage1->id}/move", [
            'shooter_id' => $this->alpha->id,
            'new_stage_id' => $this->stage2->id,
        ])
        ->assertForbidden();
});

test('correction log lists entries newest first for match staff', function () {
    CorrectionLog::create(['match_id' => $this->match->id, 'stage_id' => $this->stage1->id, 'shooter_id' => $this->alpha->id, 'action' => 'reshoot', 'performed_at' => now()->subMinute()]);
    CorrectionLog::create(['match_id' => $this->match->id, 'stage_id' => $this->stage1->id, 'shooter_id' => $this->alpha->id, 'action' => 'reassign', 'performed_at' => now()]);

    $this->actingAs($this->ro)
        ->getJson("/api/matches/{$this->match->id}/correction-logs")
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.action', 'reassign')
        ->assertJsonPath('1.action', 'reshoot');
});

test('correction log is closed to non-staff', function () {
    $this->actingAs(User::factory()->create())
        ->getJson("/api/matches/{$this->match->id}/correction-logs")
        ->assertForbidden();
});
