<?php

use App\Enums\ElrEngagementMode;
use App\Enums\ElrShotResult;
use App\Enums\ElrStageType;
use App\Models\ElrScoringProfile;
use App\Models\ElrShot;
use App\Models\ElrStage;
use App\Models\ElrTarget;
use App\Models\ElrTeamStageEntry;
use App\Models\MatchDivision;
use App\Models\ScoreAuditLog;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\Team;
use App\Models\User;
use App\Services\Scoring\ELRScoringService;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => 'owner']);
    $this->match = ShootingMatch::factory()->active()->elr()->create([
        'created_by' => $this->owner->id,
        'elr_engagement_mode' => ElrEngagementMode::TeamSequence,
        'elr_team_time_limit_seconds' => 600,
    ]);

    $this->profile = ElrScoringProfile::create([
        'match_id' => $this->match->id,
        'name' => '3-shot',
        'multipliers' => [1.00, 0.70, 0.50],
    ]);
    $this->match->update(['elr_scoring_profile_id' => $this->profile->id]);

    $this->minor = MatchDivision::create(['match_id' => $this->match->id, 'name' => 'Minor', 'sort_order' => 1]);
    $this->major = MatchDivision::create(['match_id' => $this->match->id, 'name' => 'Major', 'sort_order' => 2]);

    $this->stage = ElrStage::create([
        'match_id' => $this->match->id,
        'label' => 'Station 1',
        'stage_type' => ElrStageType::Static,
        'elr_scoring_profile_id' => $this->profile->id,
        'sort_order' => 1,
    ]);

    // Four gongs. Minor shoots G1-G3, Major shoots G2-G4 (G2/G3 shared).
    $this->g1 = ElrTarget::create(['elr_stage_id' => $this->stage->id, 'name' => 'G1', 'distance_m' => 800, 'base_points' => 10, 'max_shots' => 3, 'sort_order' => 1]);
    $this->g2 = ElrTarget::create(['elr_stage_id' => $this->stage->id, 'name' => 'G2', 'distance_m' => 1000, 'base_points' => 10, 'max_shots' => 3, 'sort_order' => 2]);
    $this->g3 = ElrTarget::create(['elr_stage_id' => $this->stage->id, 'name' => 'G3', 'distance_m' => 1200, 'base_points' => 10, 'max_shots' => 3, 'sort_order' => 3]);
    $this->g4 = ElrTarget::create(['elr_stage_id' => $this->stage->id, 'name' => 'G4', 'distance_m' => 1400, 'base_points' => 10, 'max_shots' => 3, 'sort_order' => 4]);

    $this->minor->elrTargets()->sync([$this->g1->id, $this->g2->id, $this->g3->id]);
    $this->major->elrTargets()->sync([$this->g2->id, $this->g3->id, $this->g4->id]);

    $this->team = Team::create(['match_id' => $this->match->id, 'name' => 'Team 1', 'max_size' => 2, 'sort_order' => 1]);
    $this->squad = Squad::create(['match_id' => $this->match->id, 'name' => 'Squad A', 'sort_order' => 1]);

    $this->s1 = Shooter::factory()->create([
        'squad_id' => $this->squad->id, 'team_id' => $this->team->id,
        'match_division_id' => $this->minor->id, 'name' => 'Minor Shooter', 'sort_order' => 1,
    ]);
    $this->s2 = Shooter::factory()->create([
        'squad_id' => $this->squad->id, 'team_id' => $this->team->id,
        'match_division_id' => $this->major->id, 'name' => 'Major Shooter', 'sort_order' => 2,
    ]);
});

function postTeamShots(array $shots, $match, $user)
{
    return test()->actingAs($user)->postJson("/api/matches/{$match->id}/elr-shots", [
        'shots' => array_map(fn ($s) => array_merge([
            'device_id' => 'tablet-1',
            'recorded_at' => now()->toIso8601String(),
        ], $s), $shots),
    ]);
}

it('exposes the team-sequence config and teams in the match payload', function () {
    $response = $this->actingAs($this->owner)->getJson("/api/matches/{$this->match->id}");

    $response->assertOk()
        ->assertJsonPath('data.elr_engagement_mode', 'team_sequence')
        ->assertJsonPath('data.elr_team_time_limit_seconds', 600)
        ->assertJsonPath('data.teams.0.name', 'Team 1')
        ->assertJsonCount(2, 'data.teams.0.shooters');
});

it('scores impacts so a miss never consumes a multiplier slot', function () {
    // Minor shooter on G1: miss, hit, hit. With shot-number scoring the two
    // hits would be 0.70 + 0.50 = 12. With IMPACT scoring they are the 1st and
    // 2nd impacts: 1.00 + 0.70 = 17 (base 10).
    postTeamShots([
        ['shooter_id' => $this->s1->id, 'elr_target_id' => $this->g1->id, 'shot_number' => 1, 'result' => 'miss'],
        ['shooter_id' => $this->s1->id, 'elr_target_id' => $this->g1->id, 'shot_number' => 2, 'result' => 'hit'],
        ['shooter_id' => $this->s1->id, 'elr_target_id' => $this->g1->id, 'shot_number' => 3, 'result' => 'hit'],
    ], $this->match, $this->owner)->assertOk();

    $shot2 = ElrShot::where('shooter_id', $this->s1->id)->where('elr_target_id', $this->g1->id)->where('shot_number', 2)->first();
    $shot3 = ElrShot::where('shooter_id', $this->s1->id)->where('elr_target_id', $this->g1->id)->where('shot_number', 3)->first();

    expect((float) $shot2->points_awarded)->toBe(10.0)
        ->and($shot2->impact_number)->toBe(1)
        ->and((float) $shot3->points_awarded)->toBe(7.0)
        ->and($shot3->impact_number)->toBe(2);
});

it('recomputes impacts when an earlier shot is corrected', function () {
    postTeamShots([
        ['shooter_id' => $this->s1->id, 'elr_target_id' => $this->g1->id, 'shot_number' => 1, 'result' => 'miss'],
        ['shooter_id' => $this->s1->id, 'elr_target_id' => $this->g1->id, 'shot_number' => 2, 'result' => 'hit'],
        ['shooter_id' => $this->s1->id, 'elr_target_id' => $this->g1->id, 'shot_number' => 3, 'result' => 'hit'],
    ], $this->match, $this->owner)->assertOk();

    // Correct shot 1 miss -> hit. Now all three are impacts 1,2,3 = 10+7+5.
    postTeamShots([
        ['shooter_id' => $this->s1->id, 'elr_target_id' => $this->g1->id, 'shot_number' => 1, 'result' => 'hit'],
    ], $this->match, $this->owner)->assertOk();

    $shots = ElrShot::where('shooter_id', $this->s1->id)->where('elr_target_id', $this->g1->id)->orderBy('shot_number')->get();

    expect($shots->pluck('impact_number')->all())->toBe([1, 2, 3])
        ->and($shots->sum(fn ($s) => (float) $s->points_awarded))->toBe(22.0);
});

it('builds team standings with per-shooter and team totals', function () {
    // Minor: G1 x1 hit (impact1=10). Major: G4 x1 hit (impact1=10).
    postTeamShots([
        ['shooter_id' => $this->s1->id, 'elr_target_id' => $this->g1->id, 'shot_number' => 1, 'result' => 'hit'],
        ['shooter_id' => $this->s2->id, 'elr_target_id' => $this->g4->id, 'shot_number' => 1, 'result' => 'hit'],
    ], $this->match, $this->owner)->assertOk();

    $standings = (new ELRScoringService)->calculateStandings($this->match->fresh());

    expect($standings['teams'])->toHaveCount(1);
    $team = $standings['teams'][0];
    expect((float) $team['team_total_score'])->toBe(20.0)
        ->and((float) $team['shooter_1_score'])->toBe(10.0)
        ->and((float) $team['shooter_2_score'])->toBe(10.0)
        ->and($team['rank'])->toBe(1);
});

it('records a team-stage timeout and exposes it on the match payload', function () {
    $this->actingAs($this->owner)->postJson("/api/matches/{$this->match->id}/elr-team-stage", [
        'team_id' => $this->team->id,
        'elr_stage_id' => $this->stage->id,
        'first_shooter_id' => $this->s1->id,
        'started_at' => now()->subMinutes(11)->toIso8601String(),
        'completed_at' => now()->toIso8601String(),
        'timed_out' => true,
        'device_id' => 'tablet-1',
    ])->assertOk()->assertJsonPath('data.timed_out', true);

    expect(ElrTeamStageEntry::where('team_id', $this->team->id)->where('timed_out', true)->exists())->toBeTrue();
});

it('reopens a completed team-stage entry and logs it when a shot is corrected', function () {
    // Finish the team's stage.
    $this->actingAs($this->owner)->postJson("/api/matches/{$this->match->id}/elr-team-stage", [
        'team_id' => $this->team->id,
        'elr_stage_id' => $this->stage->id,
        'completed_at' => now()->toIso8601String(),
        'device_id' => 'tablet-1',
    ])->assertOk();

    expect(ElrTeamStageEntry::where('team_id', $this->team->id)->whereNotNull('completed_at')->exists())->toBeTrue();

    // Correct a shot after completion.
    postTeamShots([
        ['shooter_id' => $this->s1->id, 'elr_target_id' => $this->g1->id, 'shot_number' => 1, 'result' => 'hit'],
    ], $this->match, $this->owner)->assertOk();

    $entry = ElrTeamStageEntry::where('team_id', $this->team->id)->where('elr_stage_id', $this->stage->id)->first();
    expect($entry->completed_at)->toBeNull();

    expect(ScoreAuditLog::where('match_id', $this->match->id)->where('action', 'reopened')->exists())->toBeTrue();
});
