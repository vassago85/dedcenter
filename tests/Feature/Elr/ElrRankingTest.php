<?php

use App\Enums\ElrEngagementMode;
use App\Enums\ElrStageType;
use App\Models\ElrScoringProfile;
use App\Models\ElrStage;
use App\Models\ElrTarget;
use App\Models\MatchDivision;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\Team;
use App\Models\User;
use App\Services\Scoring\ElrRankingService;
use App\Services\Scoring\ElrTeamRangeService;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => 'owner']);
    $this->match = ShootingMatch::factory()->active()->elr()->create([
        'created_by' => $this->owner->id,
        'elr_engagement_mode' => ElrEngagementMode::TeamSequence,
        'scores_published' => true,
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

    $this->g = [];
    foreach (range(1, 4) as $n) {
        $this->g[$n] = ElrTarget::create([
            'elr_stage_id' => $this->stage->id, 'name' => "G{$n}",
            'distance_m' => 800 + $n * 100, 'base_points' => 10, 'max_shots' => 3, 'sort_order' => $n,
        ]);
    }

    $this->squad = Squad::create(['match_id' => $this->match->id, 'name' => 'Squad A', 'sort_order' => 1]);
});

function makeTeam($ctx, string $name, int $sort): Team
{
    return Team::create(['match_id' => $ctx->match->id, 'name' => $name, 'max_size' => 2, 'sort_order' => $sort]);
}

function makeShooter($ctx, Team $team, MatchDivision $div, string $name, int $sort): Shooter
{
    return Shooter::factory()->create([
        'squad_id' => $ctx->squad->id, 'team_id' => $team->id,
        'match_division_id' => $div->id, 'name' => $name, 'sort_order' => $sort,
    ]);
}

it('materialises the division_targets whitelist from gong ranges', function () {
    $service = app(ElrTeamRangeService::class);
    $service->saveRange($this->stage, $this->minor->id, 1, 3);
    $service->saveRange($this->stage, $this->major->id, 2, 4);

    expect($this->minor->elrTargets()->orderBy('elr_targets.sort_order')->pluck('elr_targets.id')->all())
        ->toBe([$this->g[1]->id, $this->g[2]->id, $this->g[3]->id]);
    expect($this->major->elrTargets()->orderBy('elr_targets.sort_order')->pluck('elr_targets.id')->all())
        ->toBe([$this->g[2]->id, $this->g[3]->id, $this->g[4]->id]);

    // Clearing a division's range removes only its rows on this stage.
    $service->saveRange($this->stage, $this->minor->id, null, null);
    expect($this->minor->elrTargets()->count())->toBe(0);
    expect($this->major->elrTargets()->count())->toBe(3);
});

it('excludes stages a team has not completed from rankings', function () {
    app(ElrTeamRangeService::class)->saveRange($this->stage, $this->minor->id, 1, 4);

    $team = makeTeam($this, 'Alpha', 1);
    $s1 = makeShooter($this, $team, $this->minor, 'Alice', 1);

    // Hit on G1 = impact 1 = 10 points, but the team stage is NOT completed.
    \App\Models\ElrShot::create([
        'shooter_id' => $s1->id, 'elr_target_id' => $this->g[1]->id, 'shot_number' => 1,
        'result' => \App\Enums\ElrShotResult::Hit, 'points_awarded' => 10, 'impact_number' => 1,
        'recorded_by' => $this->owner->id, 'device_id' => 'x', 'recorded_at' => now(),
    ]);

    $ranking = app(ElrRankingService::class)->build($this->match->fresh());

    expect($ranking['stages'])->toBe([]);
    expect($ranking['overall'][0]['total_score'])->toBe(0.0);

    // Now complete the stage for the team — it should count.
    \App\Models\ElrTeamStageEntry::create([
        'team_id' => $team->id, 'elr_stage_id' => $this->stage->id,
        'first_shooter_id' => $s1->id, 'completed_at' => now(),
    ]);

    $ranking = app(ElrRankingService::class)->build($this->match->fresh());

    expect($ranking['stages'])->toHaveCount(1);
    expect($ranking['overall'][0]['total_score'])->toBe(10.0);
    expect($ranking['overall'][0]['stage_scores'][$this->stage->id])->toBe(10.0);
});

it('ranks teams and assigns a joint rank on a full tie', function () {
    app(ElrTeamRangeService::class)->saveRange($this->stage, $this->minor->id, 1, 4);

    $alpha = makeTeam($this, 'Alpha', 1);
    $bravo = makeTeam($this, 'Bravo', 2);
    $a1 = makeShooter($this, $alpha, $this->minor, 'Alice', 1);
    $b1 = makeShooter($this, $bravo, $this->minor, 'Bob', 2);

    foreach ([$a1, $b1] as $shooter) {
        \App\Models\ElrShot::create([
            'shooter_id' => $shooter->id, 'elr_target_id' => $this->g[1]->id, 'shot_number' => 1,
            'result' => \App\Enums\ElrShotResult::Hit, 'points_awarded' => 10, 'impact_number' => 1,
            'recorded_by' => $this->owner->id, 'device_id' => 'x', 'recorded_at' => now(),
        ]);
    }

    foreach ([$alpha, $bravo] as $team) {
        \App\Models\ElrTeamStageEntry::create([
            'team_id' => $team->id, 'elr_stage_id' => $this->stage->id, 'completed_at' => now(),
        ]);
    }

    $ranking = app(ElrRankingService::class)->build($this->match->fresh());

    expect($ranking['teams'])->toHaveCount(2);
    expect(collect($ranking['teams'])->pluck('rank')->all())->toBe([1, 1]);
    expect(collect($ranking['teams'])->every(fn ($t) => $t['joint'] === true))->toBeTrue();
});

it('keeps a mixed-division shooter only in their own division ranking', function () {
    app(ElrTeamRangeService::class)->saveRange($this->stage, $this->minor->id, 1, 2);
    app(ElrTeamRangeService::class)->saveRange($this->stage, $this->major->id, 3, 4);

    $team = makeTeam($this, 'Mixed', 1);
    $minorShooter = makeShooter($this, $team, $this->minor, 'Min', 1);
    $majorShooter = makeShooter($this, $team, $this->major, 'Maj', 2);

    \App\Models\ElrTeamStageEntry::create([
        'team_id' => $team->id, 'elr_stage_id' => $this->stage->id, 'completed_at' => now(),
    ]);

    $ranking = app(ElrRankingService::class)->build($this->match->fresh());

    $byName = collect($ranking['divisions'])->keyBy('division');
    expect($byName['Minor']['rows'])->toHaveCount(1);
    expect($byName['Minor']['rows'][0]['name'])->toBe('Min');
    expect($byName['Major']['rows'])->toHaveCount(1);
    expect($byName['Major']['rows'][0]['name'])->toBe('Maj');
});

it('serves rankings on the public endpoint and gates unpublished scores', function () {
    $ranking = $this->getJson("/api/matches/{$this->match->id}/elr-rankings");
    $ranking->assertOk()->assertJsonPath('match.scoring_type', 'elr');

    $this->match->update(['scores_published' => false]);
    $this->getJson("/api/matches/{$this->match->id}/elr-rankings")
        ->assertOk()
        ->assertJsonPath('match.scores_published', false)
        ->assertJsonPath('overall', []);
});

it('stores the alternate_scoring flag and exposes it on the match payload', function () {
    $this->match->update(['alternate_scoring' => true]);

    $this->actingAs($this->owner)
        ->getJson("/api/matches/{$this->match->id}")
        ->assertOk()
        ->assertJsonPath('data.alternate_scoring', true);
});

it('exports a rankings CSV for staff', function () {
    app(ElrTeamRangeService::class)->saveRange($this->stage, $this->minor->id, 1, 4);
    $team = makeTeam($this, 'Alpha', 1);
    $s1 = makeShooter($this, $team, $this->minor, 'Alice', 1);
    \App\Models\ElrShot::create([
        'shooter_id' => $s1->id, 'elr_target_id' => $this->g[1]->id, 'shot_number' => 1,
        'result' => \App\Enums\ElrShotResult::Hit, 'points_awarded' => 10, 'impact_number' => 1,
        'recorded_by' => $this->owner->id, 'device_id' => 'x', 'recorded_at' => now(),
    ]);
    \App\Models\ElrTeamStageEntry::create([
        'team_id' => $team->id, 'elr_stage_id' => $this->stage->id, 'completed_at' => now(),
    ]);

    $response = $this->actingAs($this->owner)
        ->get("/scoreboard/{$this->match->id}/export/elr-rankings?view=overall");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

it('renders the Peregrine shots template: 3 relative targets per class with per-division distances', function () {
    // Minor engages gongs 1-3 (900/1000/1100m), Major engages 2-4
    // (1000/1100/1200m) — each class shoots exactly THREE relative targets.
    app(ElrTeamRangeService::class)->saveRange($this->stage, $this->minor->id, 1, 3);
    app(ElrTeamRangeService::class)->saveRange($this->stage, $this->major->id, 2, 4);

    $team = makeTeam($this, 'Alpha', 1);
    $alice = makeShooter($this, $team, $this->minor, 'Alice', 1); // Minor
    $bob = makeShooter($this, $team, $this->major, 'Bob', 2);     // Major

    // Alice: G1 S1 hit, G1 S2 miss, G1 S3 left unscored.
    \App\Models\ElrShot::create([
        'shooter_id' => $alice->id, 'elr_target_id' => $this->g[1]->id, 'shot_number' => 1,
        'result' => \App\Enums\ElrShotResult::Hit, 'points_awarded' => 10, 'impact_number' => 1,
        'recorded_by' => $this->owner->id, 'device_id' => 'x', 'recorded_at' => now(),
    ]);
    \App\Models\ElrShot::create([
        'shooter_id' => $alice->id, 'elr_target_id' => $this->g[1]->id, 'shot_number' => 2,
        'result' => \App\Enums\ElrShotResult::Miss, 'points_awarded' => 0, 'impact_number' => null,
        'recorded_by' => $this->owner->id, 'device_id' => 'x', 'recorded_at' => now(),
    ]);

    $response = $this->actingAs($this->owner)->get("/scoreboard/{$this->match->id}/export/elr-shots");
    $response->assertOk();

    $body = ltrim($response->streamedContent(), "\xEF\xBB\xBF");
    $lines = array_values(array_filter(explode("\n", trim($body))));
    $rows = array_map(fn ($l) => str_getcsv(trim($l, "\r"), ',', '"', '\\'), $lines);

    // Row 0: title (match name + date).
    expect($rows[0][0])->toBe($this->match->name);

    // Row 1: relative target labels (on the first impact cell of each group).
    expect($rows[1])->toBe([
        '', '', '', '', '',
        'Station 1 - Target 1', '', '',
        'Station 1 - Target 2', '', '',
        'Station 1 - Target 3', '', '',
        '',
    ]);

    // Rows 2-3: per-division absolute distances behind each relative target.
    expect($rows[2])->toBe(['', '', '', '', 'Minor', '900', '', '', '1000', '', '', '1100', '', '', '']);
    expect($rows[3])->toBe(['', '', '', '', 'Major', '1000', '', '', '1100', '', '', '1200', '', '', '']);

    // Row 4: column header.
    expect($rows[4])->toBe([
        'Squad', 'Shooter', 'Team', 'Cartridge', 'Class',
        'W', 'W', 'W', 'W', 'W', 'W', 'W', 'W', 'W',
        'Total Points',
    ]);

    $byName = collect($rows)->keyBy(fn ($r) => $r[1]);

    // Alice (Minor): fixed columns + Target 1 scored (1,0,blank), Targets 2/3
    // engaged-unscored (blank), then her 10-point total.
    expect($byName['Alice'])->toBe([
        'Squad A', 'Alice', 'Alpha', '', 'Minor',
        '1', '0', '',
        '', '', '',
        '', '', '',
        '10',
    ]);

    // Bob (Major): his three relative targets (G2/G3/G4) all engaged-unscored,
    // no points yet.
    expect($byName['Bob'])->toBe([
        'Squad A', 'Bob', 'Alpha', '', 'Major',
        '', '', '',
        '', '', '',
        '', '', '',
        '',
    ]);
});
