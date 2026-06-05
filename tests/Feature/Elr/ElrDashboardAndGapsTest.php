<?php

use App\Enums\ElrEngagementMode;
use App\Models\ElrScoringProfile;
use App\Models\ElrSquadTeamOrder;
use App\Models\ElrStage;
use App\Models\ElrTarget;
use App\Models\ElrTeamStageEntry;
use App\Models\MatchDivision;
use App\Models\MatchRegistration;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\Team;
use App\Models\User;
use App\Services\MatchDashboardService;
use App\Services\Scoring\ELRScoringService;
use App\Services\Scoring\ElrSquadTeamOrderService;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => 'owner']);
    $this->match = ShootingMatch::factory()->active()->elr()->create([
        'created_by' => $this->owner->id,
        'elr_engagement_mode' => ElrEngagementMode::TeamSequence,
        'team_event' => true,
        'elr_distance_based_scoring' => false,
    ]);

    $this->profile = ElrScoringProfile::create([
        'match_id' => $this->match->id,
        'name' => 'Custom',
        'multipliers' => [1.10, 0.80, 0.60],
    ]);
    $this->match->update(['elr_scoring_profile_id' => $this->profile->id]);

    $this->stage1 = ElrStage::create([
        'match_id' => $this->match->id,
        'label' => 'S1',
        'stage_type' => \App\Enums\ElrStageType::Static,
        'elr_scoring_profile_id' => $this->profile->id,
        'sort_order' => 1,
    ]);
    $this->stage2 = ElrStage::create([
        'match_id' => $this->match->id,
        'label' => 'S2',
        'stage_type' => \App\Enums\ElrStageType::Static,
        'elr_scoring_profile_id' => $this->profile->id,
        'sort_order' => 2,
    ]);

    ElrTarget::create([
        'elr_stage_id' => $this->stage1->id,
        'name' => 'G1',
        'distance_m' => 900,
        'base_points' => 10,
        'max_shots' => 3,
        'sort_order' => 1,
    ]);

    $this->squad = Squad::create(['match_id' => $this->match->id, 'name' => 'A', 'sort_order' => 1]);
    $this->team1 = Team::create(['match_id' => $this->match->id, 'name' => 'T1', 'sort_order' => 1]);
    $this->team2 = Team::create(['match_id' => $this->match->id, 'name' => 'T2', 'sort_order' => 2]);

    Shooter::factory()->create(['squad_id' => $this->squad->id, 'team_id' => $this->team1->id, 'sort_order' => 1]);
    Shooter::factory()->create(['squad_id' => $this->squad->id, 'team_id' => $this->team1->id, 'sort_order' => 2]);
    Shooter::factory()->create(['squad_id' => $this->squad->id, 'team_id' => $this->team2->id, 'sort_order' => 1]);
    Shooter::factory()->create(['squad_id' => $this->squad->id, 'team_id' => $this->team2->id, 'sort_order' => 2]);
});

it('builds match dashboard data with elr checklist', function () {
    $data = (new MatchDashboardService())->build($this->match->fresh());

    expect($data['type_label'])->toBe('ELR')
        ->and($data['elr_checklist'])->toBeArray()
        ->and(collect($data['elr_checklist'])->firstWhere('key', 'profile')['done'])->toBeTrue();
});

it('calculateStandings filters incomplete team stages when completedOnly is true', function () {
    $minor = MatchDivision::create(['match_id' => $this->match->id, 'name' => 'Minor', 'sort_order' => 1]);
    $shooter = Shooter::where('team_id', $this->team1->id)->first();
    $shooter->update(['match_division_id' => $minor->id]);

    ElrTeamStageEntry::create([
        'match_id' => $this->match->id,
        'elr_stage_id' => $this->stage1->id,
        'team_id' => $this->team1->id,
        'squad_id' => $this->squad->id,
        'completed_at' => now(),
    ]);

    $target = ElrTarget::where('elr_stage_id', $this->stage1->id)->first();
    \App\Models\ElrShot::create([
        'match_id' => $this->match->id,
        'shooter_id' => $shooter->id,
        'elr_target_id' => $target->id,
        'shot_number' => 1,
        'result' => \App\Enums\ElrShotResult::Hit,
        'points_awarded' => 10,
        'impact_number' => 1,
        'recorded_at' => now(),
    ]);

    $all = (new ELRScoringService())->calculateStandings($this->match, [], completedOnly: false);
    $completed = (new ELRScoringService())->calculateStandings($this->match, [], completedOnly: true);

    expect($all['standings'][0]['total_points'] ?? 0)->toBeGreaterThan(0)
        ->and($completed['standings'][0]['total_points'] ?? 0)->toBeGreaterThan(0);
});

it('rotates squad firing order based on previous stage', function () {
    ElrSquadTeamOrder::create([
        'squad_id' => $this->squad->id,
        'elr_stage_id' => $this->stage1->id,
        'team_id' => $this->team1->id,
        'position' => 1,
        'shooter_first_id' => Shooter::where('team_id', $this->team1->id)->orderBy('sort_order')->value('id'),
    ]);
    ElrSquadTeamOrder::create([
        'squad_id' => $this->squad->id,
        'elr_stage_id' => $this->stage1->id,
        'team_id' => $this->team2->id,
        'position' => 2,
        'shooter_first_id' => Shooter::where('team_id', $this->team2->id)->orderBy('sort_order')->value('id'),
    ]);

    $order = ElrSquadTeamOrderService::getNextFiringOrder($this->match, $this->squad->id, $this->stage2->id);

    expect($order)->toHaveCount(2)
        ->and($order[0]['team_id'])->toBe($this->team2->id)
        ->and($order[1]['team_id'])->toBe($this->team1->id);
});

it('exposes firing order via api', function () {
    $response = $this->actingAs($this->owner)->getJson(
        "/api/matches/{$this->match->id}/elr-firing-order?squad_id={$this->squad->id}&elr_stage_id={$this->stage1->id}"
    );

    $response->assertOk()->assertJsonStructure(['order']);
});

it('syncs registration division to shooter on confirm', function () {
    $user = User::factory()->create();
    $division = MatchDivision::create(['match_id' => $this->match->id, 'name' => 'Open', 'sort_order' => 3]);
    $shooter = Shooter::factory()->create([
        'squad_id' => $this->squad->id,
        'user_id' => $user->id,
        'match_division_id' => null,
    ]);

    $reg = MatchRegistration::create([
        'match_id' => $this->match->id,
        'user_id' => $user->id,
        'payment_status' => 'pending_payment',
        'payment_reference' => 'TEST-REF-001',
        'division_id' => $division->id,
        'amount' => 0,
    ]);

    $reg->update(['payment_status' => 'confirmed']);

    expect($shooter->fresh()->match_division_id)->toBe($division->id);
});

it('exposes can_export separately from can_manage on match resource', function () {
    $org = \App\Models\Organization::factory()->create();
    $this->match->update(['organization_id' => $org->id]);

    $md = User::factory()->create(['role' => 'shooter']);
    $org->admins()->attach($md->id, ['is_match_director' => true]);

    $ro = User::factory()->create(['role' => 'shooter']);
    $org->admins()->attach($ro->id, ['is_range_officer' => true]);

    $mdPayload = $this->actingAs($md)->getJson("/api/matches/{$this->match->id}")->json('data');
    $roPayload = $this->actingAs($ro)->getJson("/api/matches/{$this->match->id}")->json('data');

    expect($mdPayload['can_export'])->toBeTrue()
        ->and($roPayload['can_export'])->toBeFalse()
        ->and($roPayload['can_manage'])->toBeTrue();
});
