<?php

use App\Enums\MatchStatus;
use App\Enums\PrsShotResult;
use App\Models\Gong;
use App\Models\Organization;
use App\Models\PrsShotScore;
use App\Models\PrsStageResult;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\StagePosition;
use App\Models\StageShotSequence;
use App\Models\TargetSet;
use App\Models\User;
use App\Services\MatchReportService;

/*
|--------------------------------------------------------------------------
| Per-shooter accuracy breakdown (first round / follow-up / brackets)
|--------------------------------------------------------------------------
| MatchReportService now attaches an `accuracy_breakdown` payload to
| every report so the share view can show:
|   - First round impact %
|   - Follow-up shot %
|   - Rounds fired %                       (PRS only)
|   - Distance / position / size brackets  (only when stages have meta)
|
| These tests lock the math + the conditional rendering rules so we
| don't accidentally start displaying empty bracket cards on minimal
| matches, or stop showing first-round % on a match that has it.
*/

function makeMatch(string $type = 'standard', bool $rf = false): array
{
    $owner = User::factory()->create(['role' => 'owner']);
    $org = Organization::create([
        'name' => 'Accuracy Test Club',
        'slug' => 'accuracy-test-' . uniqid(),
        'type' => 'club',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
    $match = ShootingMatch::factory()->create([
        'created_by' => $owner->id,
        'organization_id' => $org->id,
        'scoring_type' => $type,
        'royal_flush_enabled' => $rf,
        'status' => MatchStatus::Completed,
    ]);
    $squad = Squad::create(['match_id' => $match->id, 'name' => 'A']);
    $shooter = Shooter::create(['name' => 'Pat', 'squad_id' => $squad->id, 'user_id' => $owner->id, 'status' => 'active']);

    return [$match, $shooter];
}

it('exposes first-round impact tile on every match with at least one shot #1', function () {
    [$match, $shooter] = makeMatch('standard');

    $ts = TargetSet::create([
        'match_id' => $match->id,
        'label' => '300m',
        'distance_meters' => 300,
        'sort_order' => 1,
    ]);
    $gongs = collect([1, 2, 3, 4])->map(fn ($n) => Gong::create([
        'target_set_id' => $ts->id,
        'number' => $n,
        'label' => 'G' . $n,
        'multiplier' => '1.00',
    ]));
    // Hit shot 1, miss shot 2, hit shot 3, hit shot 4
    foreach ([true, false, true, true] as $i => $isHit) {
        Score::create([
            'shooter_id' => $shooter->id,
            'gong_id' => $gongs[$i]->id,
            'is_hit' => $isHit,
            'recorded_at' => now(),
        ]);
    }

    $report = (new MatchReportService)->generateReport($match, $shooter);
    $tiles = collect($report['accuracy_breakdown']['shot_order']);

    // One stage, shot #1 hit → 100% first round impact (1 of 1).
    expect($tiles->firstWhere('label', 'First Round Impact'))
        ->toMatchArray(['hit_rate' => 100.0, 'hits' => 1, 'attempts' => 1]);
    // Shot #1 hit AND shot #2 exists AND shot #2 missed → 0% follow-up
    // (0 of 1 attempts — denominator is "stages where shot #1 hit").
    expect($tiles->firstWhere('label', 'Follow-Up Shot'))
        ->toMatchArray(['hit_rate' => 0.0, 'hits' => 0, 'attempts' => 1]);
});

it('computes follow-up % conditionally on shot #1 hitting (not the flat shot-2 rate)', function () {
    // Three stages, each with shot #1 + shot #2:
    //   Stage A: shot #1 HIT,  shot #2 HIT  → contributes to follow-up: 1/1
    //   Stage B: shot #1 MISS, shot #2 HIT  → does NOT contribute to follow-up
    //   Stage C: shot #1 HIT,  shot #2 MISS → contributes to follow-up: 0/1
    //
    // Flat shot-2-across-all-stages rate would be 2/3 ≈ 67%.
    // Conditional follow-up rate is 1/2 = 50%.
    [$match, $shooter] = makeMatch('standard');

    $stages = [];
    foreach (['A', 'B', 'C'] as $i => $label) {
        $ts = TargetSet::create([
            'match_id' => $match->id,
            'label' => $label,
            'distance_meters' => 300 + ($i * 100),
            'sort_order' => $i + 1,
        ]);
        $g1 = Gong::create(['target_set_id' => $ts->id, 'number' => 1, 'label' => 'G1', 'multiplier' => '1.00']);
        $g2 = Gong::create(['target_set_id' => $ts->id, 'number' => 2, 'label' => 'G2', 'multiplier' => '1.00']);
        $stages[$label] = ['ts' => $ts, 'g1' => $g1, 'g2' => $g2];
    }

    $matrix = [
        'A' => [true, true],
        'B' => [false, true],
        'C' => [true, false],
    ];

    foreach ($matrix as $label => [$hit1, $hit2]) {
        Score::create(['shooter_id' => $shooter->id, 'gong_id' => $stages[$label]['g1']->id, 'is_hit' => $hit1, 'recorded_at' => now()]);
        Score::create(['shooter_id' => $shooter->id, 'gong_id' => $stages[$label]['g2']->id, 'is_hit' => $hit2, 'recorded_at' => now()]);
    }

    $report = (new MatchReportService)->generateReport($match, $shooter);
    $tiles = collect($report['accuracy_breakdown']['shot_order']);

    // First round: 2 of 3 stages hit → 66.7%
    expect($tiles->firstWhere('label', 'First Round Impact'))
        ->toMatchArray(['hit_rate' => 66.7, 'hits' => 2, 'attempts' => 3]);
    // Follow-up: 1 of 2 stages where shot #1 hit also got shot #2 → 50%
    // (Stage B is excluded entirely because shot #1 missed there — its
    // shot #2 hit doesn't inflate the follow-up rate.)
    expect($tiles->firstWhere('label', 'Follow-Up Shot'))
        ->toMatchArray(['hit_rate' => 50.0, 'hits' => 1, 'attempts' => 2]);
});

it('hides the follow-up tile entirely when shot #1 never hit', function () {
    // Shooter went 0/3 on first rounds — there's no follow-up to
    // evaluate. Showing "0% follow-up (0 of 0)" would be misleading
    // and add noise; the tile should just not render.
    [$match, $shooter] = makeMatch('standard');

    foreach (['A', 'B', 'C'] as $i => $label) {
        $ts = TargetSet::create([
            'match_id' => $match->id, 'label' => $label,
            'distance_meters' => 300, 'sort_order' => $i + 1,
        ]);
        $g1 = Gong::create(['target_set_id' => $ts->id, 'number' => 1, 'label' => 'G1', 'multiplier' => '1.00']);
        $g2 = Gong::create(['target_set_id' => $ts->id, 'number' => 2, 'label' => 'G2', 'multiplier' => '1.00']);
        Score::create(['shooter_id' => $shooter->id, 'gong_id' => $g1->id, 'is_hit' => false, 'recorded_at' => now()]);
        Score::create(['shooter_id' => $shooter->id, 'gong_id' => $g2->id, 'is_hit' => true, 'recorded_at' => now()]);
    }

    $report = (new MatchReportService)->generateReport($match, $shooter);
    $tiles = collect($report['accuracy_breakdown']['shot_order']);

    expect($tiles->firstWhere('label', 'First Round Impact'))
        ->toMatchArray(['hit_rate' => 0.0, 'hits' => 0, 'attempts' => 3]);
    expect($tiles->firstWhere('label', 'Follow-Up Shot'))->toBeNull();
});

it('reports rounds-fired % only for PRS matches (where not_taken is meaningful)', function () {
    [$match, $shooter] = makeMatch('prs');
    $ts = TargetSet::create([
        'match_id' => $match->id,
        'label' => 'Stage 1',
        'distance_meters' => 400,
        'total_shots' => 4,
        'sort_order' => 1,
    ]);
    foreach ([1, 2, 3, 4] as $n) {
        Gong::create(['target_set_id' => $ts->id, 'number' => $n, 'label' => 'G' . $n, 'multiplier' => '1.00']);
    }
    // 2 hits, 1 miss, 1 not_taken — fired 3 of 4 → 75%
    PrsStageResult::create([
        'match_id' => $match->id,
        'stage_id' => $ts->id,
        'shooter_id' => $shooter->id,
        'hits' => 2, 'misses' => 1, 'not_taken' => 1,
    ]);
    foreach ([
        [1, PrsShotResult::Hit],
        [2, PrsShotResult::Miss],
        [3, PrsShotResult::Hit],
        [4, PrsShotResult::NotTaken],
    ] as [$n, $r]) {
        PrsShotScore::create([
            'match_id' => $match->id,
            'stage_id' => $ts->id,
            'shooter_id' => $shooter->id,
            'shot_number' => $n,
            'result' => $r,
        ]);
    }

    $report = (new MatchReportService)->generateReport($match, $shooter);
    $rf = $report['accuracy_breakdown']['rounds_fired'];

    expect($rf)->not->toBeNull();
    expect($rf['fired'])->toBe(3);
    expect($rf['total'])->toBe(4);
    expect($rf['pct'])->toBe(75.0);
});

it('hides rounds-fired % on standard matches (no not_taken concept)', function () {
    [$match, $shooter] = makeMatch('standard');
    $ts = TargetSet::create([
        'match_id' => $match->id, 'label' => '300m', 'distance_meters' => 300, 'sort_order' => 1,
    ]);
    Gong::create(['target_set_id' => $ts->id, 'number' => 1, 'label' => 'G1', 'multiplier' => '1.00']);

    $report = (new MatchReportService)->generateReport($match, $shooter);
    expect($report['accuracy_breakdown']['rounds_fired'])->toBeNull();
});

it('emits distance brackets when a match spans multiple distance buckets', function () {
    [$match, $shooter] = makeMatch('standard');

    $stages = [
        ['label' => 'A', 'distance' => 250],
        ['label' => 'B', 'distance' => 450],
        ['label' => 'C', 'distance' => 650],
    ];
    foreach ($stages as $i => $s) {
        $ts = TargetSet::create([
            'match_id' => $match->id,
            'label' => $s['label'],
            'distance_meters' => $s['distance'],
            'sort_order' => $i + 1,
        ]);
        $g = Gong::create(['target_set_id' => $ts->id, 'number' => 1, 'label' => 'G', 'multiplier' => '1.00']);
        Score::create(['shooter_id' => $shooter->id, 'gong_id' => $g->id, 'is_hit' => true, 'recorded_at' => now()]);
    }

    $report = (new MatchReportService)->generateReport($match, $shooter);
    $buckets = collect($report['accuracy_breakdown']['distance_brackets']);

    expect($buckets)->not->toBeNull();
    expect($buckets->pluck('label')->all())
        ->toEqual(['200-300m', '400-500m', '600-800m']);
});

it('hides distance bracket card on a single-bucket match', function () {
    [$match, $shooter] = makeMatch('standard');
    $ts = TargetSet::create([
        'match_id' => $match->id, 'label' => '300m', 'distance_meters' => 300, 'sort_order' => 1,
    ]);
    Gong::create(['target_set_id' => $ts->id, 'number' => 1, 'label' => 'G', 'multiplier' => '1.00']);

    $report = (new MatchReportService)->generateReport($match, $shooter);
    expect($report['accuracy_breakdown']['distance_brackets'])->toBeNull();
});

it('emits position brackets only when PRS stage_shot_sequence is set up', function () {
    [$match, $shooter] = makeMatch('prs');
    $ts = TargetSet::create([
        'match_id' => $match->id, 'label' => 'Stage 1', 'distance_meters' => 400, 'total_shots' => 2, 'sort_order' => 1,
    ]);
    $gongs = collect([1, 2])->map(fn ($n) => Gong::create([
        'target_set_id' => $ts->id, 'number' => $n, 'label' => 'G' . $n, 'multiplier' => '1.00',
    ]));

    $prone = StagePosition::create(['stage_id' => $ts->id, 'name' => 'Prone', 'sort_order' => 1]);
    $standing = StagePosition::create(['stage_id' => $ts->id, 'name' => 'Positional', 'sort_order' => 2]);

    StageShotSequence::create(['stage_id' => $ts->id, 'shot_number' => 1, 'position_id' => $prone->id, 'gong_id' => $gongs[0]->id]);
    StageShotSequence::create(['stage_id' => $ts->id, 'shot_number' => 2, 'position_id' => $standing->id, 'gong_id' => $gongs[1]->id]);

    PrsStageResult::create([
        'match_id' => $match->id, 'stage_id' => $ts->id, 'shooter_id' => $shooter->id,
        'hits' => 1, 'misses' => 1, 'not_taken' => 0,
    ]);
    PrsShotScore::create([
        'match_id' => $match->id, 'stage_id' => $ts->id, 'shooter_id' => $shooter->id,
        'shot_number' => 1, 'result' => PrsShotResult::Hit,
    ]);
    PrsShotScore::create([
        'match_id' => $match->id, 'stage_id' => $ts->id, 'shooter_id' => $shooter->id,
        'shot_number' => 2, 'result' => PrsShotResult::Miss,
    ]);

    $report = (new MatchReportService)->generateReport($match, $shooter);
    $positions = collect($report['accuracy_breakdown']['position_brackets']);

    expect($positions)->not->toBeNull();
    expect($positions->pluck('label')->all())->toEqual(['Positional', 'Prone']);
    expect($positions->firstWhere('label', 'Prone')['hit_rate'])->toBe(100.0);
    expect($positions->firstWhere('label', 'Positional')['hit_rate'])->toBe(0.0);
});

it('hides position brackets on standard / RF matches (no per-shot position data)', function () {
    [$match, $shooter] = makeMatch('standard');
    $ts = TargetSet::create(['match_id' => $match->id, 'label' => '300m', 'distance_meters' => 300, 'sort_order' => 1]);
    Gong::create(['target_set_id' => $ts->id, 'number' => 1, 'label' => 'G', 'multiplier' => '1.00']);

    $report = (new MatchReportService)->generateReport($match, $shooter);
    expect($report['accuracy_breakdown']['position_brackets'])->toBeNull();
});

it('emits target-size brackets when gongs carry size + distance', function () {
    [$match, $shooter] = makeMatch('standard');

    // Three stages with distinct angular sizes:
    //  300m × 100mm = 0.33 mrad  → ≤0.5 mrad bucket
    //  300m × 250mm = 0.83 mrad  → 0.5–1 mrad bucket
    //  300m × 500mm = 1.67 mrad  → 1–2 mrad bucket
    foreach ([
        ['label' => 'Tiny', 'mm' => 100],
        ['label' => 'Mid', 'mm' => 250],
        ['label' => 'Large', 'mm' => 500],
    ] as $i => $s) {
        $ts = TargetSet::create([
            'match_id' => $match->id, 'label' => $s['label'], 'distance_meters' => 300, 'sort_order' => $i + 1,
        ]);
        $g = Gong::create([
            'target_set_id' => $ts->id, 'number' => 1, 'label' => 'G',
            'multiplier' => '1.00', 'target_size_mm' => $s['mm'], 'distance_meters' => 300,
        ]);
        Score::create(['shooter_id' => $shooter->id, 'gong_id' => $g->id, 'is_hit' => true, 'recorded_at' => now()]);
    }

    $report = (new MatchReportService)->generateReport($match, $shooter);
    $sizes = collect($report['accuracy_breakdown']['target_size_brackets']);

    expect($sizes)->not->toBeNull();
    expect($sizes->pluck('label')->all())
        ->toEqual(['≤0.5 mrad', '0.5–1 mrad', '1–2 mrad']);
});
