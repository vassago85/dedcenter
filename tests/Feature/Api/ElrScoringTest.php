<?php

use App\Enums\ElrShotResult;
use App\Enums\ElrStageType;
use App\Models\ElrScoringProfile;
use App\Models\ElrShot;
use App\Models\ElrStage;
use App\Models\ElrTarget;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\User;
use App\Services\Scoring\ELRScoringService;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => 'owner']);
    $this->match = ShootingMatch::factory()->active()->elr()->create(['created_by' => $this->owner->id]);

    $this->profile = ElrScoringProfile::create([
        'match_id' => $this->match->id,
        'name' => 'Default 3-shot',
        'multipliers' => [1.00, 0.70, 0.50],
    ]);

    $this->match->update(['elr_scoring_profile_id' => $this->profile->id]);

    $this->ladderStage = ElrStage::create([
        'match_id' => $this->match->id,
        'label' => 'Ladder Stage',
        'stage_type' => ElrStageType::Ladder,
        'elr_scoring_profile_id' => $this->profile->id,
        'sort_order' => 1,
    ]);

    $this->target1 = ElrTarget::create([
        'elr_stage_id' => $this->ladderStage->id,
        'name' => 'T1', 'distance_m' => 1000, 'base_points' => 20,
        'max_shots' => 3, 'must_hit_to_advance' => true, 'sort_order' => 1,
    ]);

    $this->target2 = ElrTarget::create([
        'elr_stage_id' => $this->ladderStage->id,
        'name' => 'T2', 'distance_m' => 1500, 'base_points' => 30,
        'max_shots' => 3, 'must_hit_to_advance' => true, 'sort_order' => 2,
    ]);

    $this->squad = Squad::create(['match_id' => $this->match->id, 'name' => 'Squad A', 'sort_order' => 1]);
    $this->shooter = Shooter::factory()->create(['squad_id' => $this->squad->id]);
});

// ── Profile Multiplier Tests ──

it('applies correct multipliers from scoring profile', function () {
    expect($this->target1->pointsForShot(1))->toBe(20.0)
        ->and($this->target1->pointsForShot(2))->toBe(14.0)
        ->and($this->target1->pointsForShot(3))->toBe(10.0)
        ->and($this->target1->pointsForShot(4))->toBe(0.0);
});

it('applies multipliers to larger base points', function () {
    expect($this->target2->pointsForShot(1))->toBe(30.0)
        ->and($this->target2->pointsForShot(2))->toBe(21.0)
        ->and($this->target2->pointsForShot(3))->toBe(15.0);
});

// ── ELR Scoring Service ──

it('records a hit shot with correct points', function () {
    $service = new ELRScoringService();
    $shot = $service->recordShot($this->shooter, $this->target1, 1, ElrShotResult::Hit, $this->owner->id);

    expect($shot->result)->toBe(ElrShotResult::Hit)
        ->and((float) $shot->points_awarded)->toBe(20.0)
        ->and($shot->shot_number)->toBe(1);
});

it('records a miss shot with zero points', function () {
    $service = new ELRScoringService();
    $shot = $service->recordShot($this->shooter, $this->target1, 1, ElrShotResult::Miss);

    expect($shot->result)->toBe(ElrShotResult::Miss)
        ->and((float) $shot->points_awarded)->toBe(0.0);
});

it('records second shot hit with reduced multiplier', function () {
    $service = new ELRScoringService();
    $service->recordShot($this->shooter, $this->target1, 1, ElrShotResult::Miss);
    $shot = $service->recordShot($this->shooter, $this->target1, 2, ElrShotResult::Hit);

    expect((float) $shot->points_awarded)->toBe(14.0);
});

it('records third shot hit with lowest multiplier', function () {
    $service = new ELRScoringService();
    $service->recordShot($this->shooter, $this->target1, 1, ElrShotResult::Miss);
    $service->recordShot($this->shooter, $this->target1, 2, ElrShotResult::Miss);
    $shot = $service->recordShot($this->shooter, $this->target1, 3, ElrShotResult::Hit);

    expect((float) $shot->points_awarded)->toBe(10.0);
});

// ── Static Stage Scoring ──

it('calculates standings for static stage correctly', function () {
    $staticStage = ElrStage::create([
        'match_id' => $this->match->id,
        'label' => 'Static Stage',
        'stage_type' => ElrStageType::Static,
        'elr_scoring_profile_id' => $this->profile->id,
        'sort_order' => 2,
    ]);

    $st1 = ElrTarget::create([
        'elr_stage_id' => $staticStage->id,
        'name' => 'S1', 'distance_m' => 800, 'base_points' => 10,
        'max_shots' => 3, 'must_hit_to_advance' => false, 'sort_order' => 1,
    ]);

    $st2 = ElrTarget::create([
        'elr_stage_id' => $staticStage->id,
        'name' => 'S2', 'distance_m' => 1200, 'base_points' => 20,
        'max_shots' => 3, 'must_hit_to_advance' => false, 'sort_order' => 2,
    ]);

    $service = new ELRScoringService();
    $service->recordShot($this->shooter, $st1, 1, ElrShotResult::Hit, $this->owner->id);
    $service->recordShot($this->shooter, $st2, 1, ElrShotResult::Miss, $this->owner->id);
    $service->recordShot($this->shooter, $st2, 2, ElrShotResult::Hit, $this->owner->id);

    $result = $service->calculateStandings($this->match);

    expect($result['standings'])->toHaveCount(1);

    $standing = $result['standings'][0];
    expect($standing['total_points'])->toBe(24.0)
        ->and($standing['total_hits'])->toBe(2)
        ->and($standing['first_round_hits'])->toBe(1)
        ->and($standing['second_round_hits'])->toBe(1)
        ->and($standing['furthest_hit_m'])->toBe(1200);
});

// ── Ladder Stage Progression ──

it('tracks ladder stage progression correctly', function () {
    $service = new ELRScoringService();
    $service->recordShot($this->shooter, $this->target1, 1, ElrShotResult::Hit);

    $progress = $service->getStageProgress($this->ladderStage, $this->shooter);

    expect($progress[0]['status'])->toBe('hit')
        ->and($progress[0]['locked'])->toBeFalse()
        ->and($progress[1]['status'])->toBe('pending')
        ->and($progress[1]['locked'])->toBeFalse();
});

it('locks next target when previous is not hit in ladder mode', function () {
    $service = new ELRScoringService();
    $service->recordShot($this->shooter, $this->target1, 1, ElrShotResult::Miss);
    $service->recordShot($this->shooter, $this->target1, 2, ElrShotResult::Miss);
    $service->recordShot($this->shooter, $this->target1, 3, ElrShotResult::Miss);

    $progress = $service->getStageProgress($this->ladderStage, $this->shooter);

    expect($progress[0]['status'])->toBe('exhausted')
        ->and($progress[1]['locked'])->toBeTrue()
        ->and($progress[1]['status'])->toBe('locked');
});

// ── Normalized Score ──

it('calculates normalized scores relative to top shooter', function () {
    $shooter2 = Shooter::factory()->create(['squad_id' => $this->squad->id]);

    $service = new ELRScoringService();
    $service->recordShot($this->shooter, $this->target1, 1, ElrShotResult::Hit);
    $service->recordShot($this->shooter, $this->target2, 1, ElrShotResult::Hit);

    $service->recordShot($shooter2, $this->target1, 1, ElrShotResult::Miss);
    $service->recordShot($shooter2, $this->target1, 2, ElrShotResult::Hit);

    $result = $service->calculateStandings($this->match);

    $first = collect($result['standings'])->firstWhere('rank', 1);
    $second = collect($result['standings'])->firstWhere('rank', 2);

    expect($first['normalized_score'])->toBe(100.0)
        ->and($second['normalized_score'])->toBeLessThan(100.0);
});

// ── Tiebreaker ──

it('breaks ties by furthest target hit', function () {
    $shooter2 = Shooter::factory()->create(['squad_id' => $this->squad->id]);

    $service = new ELRScoringService();
    // Both get 20 points from target1 shot 1
    $service->recordShot($this->shooter, $this->target1, 1, ElrShotResult::Hit);
    $service->recordShot($shooter2, $this->target1, 1, ElrShotResult::Hit);

    // shooter also hits target2 for 30 pts (total 50)
    $service->recordShot($this->shooter, $this->target2, 1, ElrShotResult::Hit);

    // shooter2 also gets 30 pts from target2 (total 50) — but hits it on shot 2
    $service->recordShot($shooter2, $this->target2, 1, ElrShotResult::Miss);
    $service->recordShot($shooter2, $this->target2, 2, ElrShotResult::Hit);

    $result = $service->calculateStandings($this->match);

    // Both have furthest_hit_m = 1500, but shooter has more first_round_hits
    $first = $result['standings'][0];
    expect($first['first_round_hits'])->toBe(2);
});

// ── API Endpoint Tests ──

it('accepts ELR shot submission via API', function () {
    $payload = [
        'shots' => [
            [
                'shooter_id' => $this->shooter->id,
                'elr_target_id' => $this->target1->id,
                'shot_number' => 1,
                'result' => 'hit',
                'device_id' => 'tablet-1',
                'recorded_at' => now()->toIso8601String(),
            ],
        ],
    ];

    $response = $this->actingAs($this->owner)->postJson("/api/matches/{$this->match->id}/elr-shots", $payload);

    $response->assertOk()
        ->assertJsonCount(1, 'data');

    expect(ElrShot::count())->toBe(1);
    expect((float) ElrShot::first()->points_awarded)->toBe(20.0);
});

it('is idempotent for ELR shots', function () {
    $payload = [
        'shots' => [
            [
                'shooter_id' => $this->shooter->id,
                'elr_target_id' => $this->target1->id,
                'shot_number' => 1,
                'result' => 'hit',
                'device_id' => 'tablet-1',
                'recorded_at' => now()->toIso8601String(),
            ],
        ],
    ];

    $this->actingAs($this->owner)->postJson("/api/matches/{$this->match->id}/elr-shots", $payload)->assertOk();
    expect(ElrShot::count())->toBe(1);

    $payload['shots'][0]['result'] = 'miss';
    $this->actingAs($this->owner)->postJson("/api/matches/{$this->match->id}/elr-shots", $payload)->assertOk();

    expect(ElrShot::count())->toBe(1);
    expect(ElrShot::first()->result)->toBe(ElrShotResult::Miss);
    expect((float) ElrShot::first()->points_awarded)->toBe(0.0);
});

it('rejects shots for shooters not in the match', function () {
    $otherMatch = ShootingMatch::factory()->active()->elr()->create();
    $otherSquad = Squad::create(['match_id' => $otherMatch->id, 'name' => 'Other', 'sort_order' => 1]);
    $otherShooter = Shooter::factory()->create(['squad_id' => $otherSquad->id]);

    $payload = [
        'shots' => [
            [
                'shooter_id' => $otherShooter->id,
                'elr_target_id' => $this->target1->id,
                'shot_number' => 1,
                'result' => 'hit',
                'device_id' => 'tablet-1',
                'recorded_at' => now()->toIso8601String(),
            ],
        ],
    ];

    $this->actingAs($this->owner)->postJson("/api/matches/{$this->match->id}/elr-shots", $payload)
        ->assertUnprocessable();
});

it('rejects shots for targets not in the match', function () {
    $otherMatch = ShootingMatch::factory()->active()->elr()->create();
    $otherProfile = ElrScoringProfile::create([
        'match_id' => $otherMatch->id, 'name' => 'X', 'multipliers' => [1.0],
    ]);
    $otherStage = ElrStage::create([
        'match_id' => $otherMatch->id, 'label' => 'X', 'stage_type' => 'static',
        'elr_scoring_profile_id' => $otherProfile->id, 'sort_order' => 1,
    ]);
    $otherTarget = ElrTarget::create([
        'elr_stage_id' => $otherStage->id, 'name' => 'X1', 'distance_m' => 500,
        'base_points' => 5, 'max_shots' => 1, 'sort_order' => 1,
    ]);

    $payload = [
        'shots' => [
            [
                'shooter_id' => $this->shooter->id,
                'elr_target_id' => $otherTarget->id,
                'shot_number' => 1,
                'result' => 'hit',
                'device_id' => 'tablet-1',
                'recorded_at' => now()->toIso8601String(),
            ],
        ],
    ];

    $this->actingAs($this->owner)->postJson("/api/matches/{$this->match->id}/elr-shots", $payload)
        ->assertUnprocessable();
});

it('requires valid result values', function () {
    $payload = [
        'shots' => [
            [
                'shooter_id' => $this->shooter->id,
                'elr_target_id' => $this->target1->id,
                'shot_number' => 1,
                'result' => 'invalid',
                'device_id' => 'tablet-1',
                'recorded_at' => now()->toIso8601String(),
            ],
        ],
    ];

    $this->actingAs($this->owner)->postJson("/api/matches/{$this->match->id}/elr-shots", $payload)
        ->assertUnprocessable();
});

// ── Scoreboard API ──

it('returns ELR scoreboard from the scoreboard endpoint', function () {
    $service = new ELRScoringService();
    $service->recordShot($this->shooter, $this->target1, 1, ElrShotResult::Hit, $this->owner->id);

    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");

    $response->assertOk()
        ->assertJsonPath('match.scoring_type', 'elr')
        ->assertJsonPath('standings.0.total_points', 20.0)
        ->assertJsonPath('standings.0.total_hits', 1);
});

// ── Match API with ELR stages ──

it('includes ELR stages in match detail response', function () {
    $response = $this->getJson("/api/matches/{$this->match->id}");

    $response->assertOk()
        ->assertJsonPath('data.scoring_type', 'elr')
        ->assertJsonPath('data.elr_stages.0.label', 'Ladder Stage')
        ->assertJsonPath('data.elr_stages.0.stage_type', 'ladder')
        ->assertJsonCount(2, 'data.elr_stages.0.targets');
});
