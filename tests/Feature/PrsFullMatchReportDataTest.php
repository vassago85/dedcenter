<?php

use App\Enums\PrsShotResult;
use App\Models\Gong;
use App\Models\PrsShotScore;
use App\Models\PrsStageResult;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;

/**
 * Locks in that the Full Match Report (HTML/PDF + CSV) reads PRS scoring
 * data from `prs_stage_results` + `prs_shot_scores` instead of the legacy
 * `scores` table.
 *
 * Regression: a completed PRS match was rendering with all-zero stat
 * cards, empty heatmap cells, and "0/0 — 0% hit rate" stages because
 * buildPostMatchReportData and the prsStandings/prsDetailed CSVs all
 * queried `scores`, which is never written for modern PRS matches.
 */

beforeEach(function () {
    $this->invokeBuild = function (App\Http\Controllers\MatchExportController $c, ShootingMatch $m) {
        $ref = new ReflectionMethod($c, 'buildPostMatchReportData');
        $ref->setAccessible(true);
        return $ref->invoke($c, $m);
    };

    $this->invokeExec = function (App\Http\Controllers\MatchExportController $c, ShootingMatch $m) {
        $ref = new ReflectionMethod($c, 'buildExecutiveSummaryData');
        $ref->setAccessible(true);
        return $ref->invoke($c, $m);
    };
});

/**
 * Build a small PRS match with 2 stages × 2 gongs each, two shooters, and
 * canonical PRS data (per-shot results + per-stage aggregates with times).
 *
 * Returns [match, [shooter => [...]], stages, gongs] so individual tests
 * can assert against specific shooter rows / stages.
 */
function makePrsMatch(): array
{
    $owner = User::factory()->create();
    $match = ShootingMatch::factory()->create([
        'created_by' => $owner->id,
        'scoring_type' => 'prs',
    ]);

    $stage1 = TargetSet::create([
        'match_id' => $match->id,
        'label' => 'Stage 1',
        'distance_meters' => 0,
        'distance_multiplier' => 1.0,
        'sort_order' => 1,
    ]);
    $stage2 = TargetSet::create([
        'match_id' => $match->id,
        'label' => 'Stage 2 — TB',
        'distance_meters' => 0,
        'distance_multiplier' => 1.0,
        'sort_order' => 2,
        'is_tiebreaker' => true,
    ]);

    $g1a = Gong::create(['target_set_id' => $stage1->id, 'number' => 1, 'label' => 'G1', 'multiplier' => '1.00']);
    $g1b = Gong::create(['target_set_id' => $stage1->id, 'number' => 2, 'label' => 'G2', 'multiplier' => '1.00']);
    $g2a = Gong::create(['target_set_id' => $stage2->id, 'number' => 1, 'label' => 'G1', 'multiplier' => '1.00']);
    $g2b = Gong::create(['target_set_id' => $stage2->id, 'number' => 2, 'label' => 'G2', 'multiplier' => '1.00']);

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);

    $alpha = Shooter::create(['name' => 'Alpha A — 6mm Dasher', 'squad_id' => $squad->id, 'status' => 'active']);
    $bravo = Shooter::create(['name' => 'Bravo B — 6.5 Creedmoor', 'squad_id' => $squad->id, 'status' => 'active']);

    // Stage 1 — Alpha clears both, Bravo hits 1/2.
    PrsStageResult::create([
        'match_id' => $match->id, 'stage_id' => $stage1->id, 'shooter_id' => $alpha->id,
        'hits' => 2, 'misses' => 0, 'not_taken' => 0,
        'raw_time_seconds' => 40.00, 'official_time_seconds' => 40.00,
        'completed_at' => now(),
    ]);
    PrsShotScore::create(['match_id' => $match->id, 'stage_id' => $stage1->id, 'shooter_id' => $alpha->id, 'shot_number' => 1, 'result' => PrsShotResult::Hit]);
    PrsShotScore::create(['match_id' => $match->id, 'stage_id' => $stage1->id, 'shooter_id' => $alpha->id, 'shot_number' => 2, 'result' => PrsShotResult::Hit]);

    PrsStageResult::create([
        'match_id' => $match->id, 'stage_id' => $stage1->id, 'shooter_id' => $bravo->id,
        'hits' => 1, 'misses' => 1, 'not_taken' => 0,
        'raw_time_seconds' => 35.00, 'official_time_seconds' => 35.00,
        'completed_at' => now(),
    ]);
    PrsShotScore::create(['match_id' => $match->id, 'stage_id' => $stage1->id, 'shooter_id' => $bravo->id, 'shot_number' => 1, 'result' => PrsShotResult::Hit]);
    PrsShotScore::create(['match_id' => $match->id, 'stage_id' => $stage1->id, 'shooter_id' => $bravo->id, 'shot_number' => 2, 'result' => PrsShotResult::Miss]);

    // Stage 2 (tiebreaker) — Alpha 1/2 (slow), Bravo 1/2 (faster).
    PrsStageResult::create([
        'match_id' => $match->id, 'stage_id' => $stage2->id, 'shooter_id' => $alpha->id,
        'hits' => 1, 'misses' => 1, 'not_taken' => 0,
        'raw_time_seconds' => 50.00, 'official_time_seconds' => 50.00,
        'completed_at' => now(),
    ]);
    PrsShotScore::create(['match_id' => $match->id, 'stage_id' => $stage2->id, 'shooter_id' => $alpha->id, 'shot_number' => 1, 'result' => PrsShotResult::Hit]);
    PrsShotScore::create(['match_id' => $match->id, 'stage_id' => $stage2->id, 'shooter_id' => $alpha->id, 'shot_number' => 2, 'result' => PrsShotResult::Miss]);

    PrsStageResult::create([
        'match_id' => $match->id, 'stage_id' => $stage2->id, 'shooter_id' => $bravo->id,
        'hits' => 1, 'misses' => 1, 'not_taken' => 0,
        'raw_time_seconds' => 25.00, 'official_time_seconds' => 25.00,
        'completed_at' => now(),
    ]);
    PrsShotScore::create(['match_id' => $match->id, 'stage_id' => $stage2->id, 'shooter_id' => $bravo->id, 'shot_number' => 1, 'result' => PrsShotResult::Miss]);
    PrsShotScore::create(['match_id' => $match->id, 'stage_id' => $stage2->id, 'shooter_id' => $bravo->id, 'shot_number' => 2, 'result' => PrsShotResult::Hit]);

    return [
        'match' => $match,
        'shooters' => ['alpha' => $alpha, 'bravo' => $bravo],
        'stages' => ['s1' => $stage1, 's2' => $stage2],
        'gongs' => [
            's1' => [$g1a, $g1b],
            's2' => [$g2a, $g2b],
        ],
    ];
}

it('builds PRS standings + distance tables from prs_stage_results / prs_shot_scores', function () {
    $ctx = makePrsMatch();
    $controller = new App\Http\Controllers\MatchExportController();

    $data = ($this->invokeBuild)($controller, $ctx['match']);

    expect($data['standings'])->toHaveCount(2);

    $alpha = $data['standings']->firstWhere('name', 'Alpha A — 6mm Dasher');
    $bravo = $data['standings']->firstWhere('name', 'Bravo B — 6.5 Creedmoor');

    // Alpha = 3 hits (2 + 1), Bravo = 2 hits (1 + 1). Alpha wins on hits alone.
    expect($alpha->hits)->toBe(3)
        ->and($alpha->misses)->toBe(1)
        ->and((float) $alpha->total_score)->toBe(3.0)
        ->and($alpha->rank)->toBe(1);

    expect($bravo->hits)->toBe(2)
        ->and($bravo->misses)->toBe(2)
        ->and($bravo->rank)->toBe(2);

    // distanceTables — one per stage, shot cells map to gongs via shot_number.
    expect($data['distanceTables'])->toHaveCount(2);

    $stage1Table = $data['distanceTables'][0];
    expect($stage1Table['label'])->toBe('Stage 1');
    expect($stage1Table['rows'])->toHaveCount(2);

    $alphaRow = collect($stage1Table['rows'])->firstWhere('name', 'Alpha A — 6mm Dasher');
    expect($alphaRow['cells'][0]['state'])->toBe('hit')
        ->and($alphaRow['cells'][1]['state'])->toBe('hit')
        ->and($alphaRow['hits'])->toBe(2)
        ->and($alphaRow['misses'])->toBe(0);

    $bravoRow = collect($stage1Table['rows'])->firstWhere('name', 'Bravo B — 6.5 Creedmoor');
    expect($bravoRow['cells'][0]['state'])->toBe('hit')
        ->and($bravoRow['cells'][1]['state'])->toBe('miss');
});

it('produces non-zero stat cards for a completed PRS match', function () {
    $ctx = makePrsMatch();
    $controller = new App\Http\Controllers\MatchExportController();

    $data = ($this->invokeExec)($controller, $ctx['match']);

    // Regression: pre-fix this returned 0 across the board because the
    // controller looked at the empty `scores` table for PRS matches.
    expect($data['statCards']['totalShooters'])->toBe(2);
    expect($data['statCards']['totalShots'])->toBe(8);  // 2 stages × 2 gongs × 2 shooters
    expect($data['statCards']['totalHits'])->toBe(5);    // Alpha 3 + Bravo 2
    expect($data['statCards']['hitRate'])->toBeGreaterThan(0);
    expect($data['podium']['first']?->name)->toBe('Alpha A — 6mm Dasher');

    expect($data['distanceStats'])->toHaveCount(2);
    foreach ($data['distanceStats'] as $ds) {
        expect($ds['shots'])->toBeGreaterThan(0);
    }
});

it('renders the Full Match Report blade for a PRS match without empty cells', function () {
    $ctx = makePrsMatch();
    $controller = new App\Http\Controllers\MatchExportController();

    $data = ($this->invokeExec)($controller, $ctx['match']);

    $html = view('exports.pdf-executive-summary', $data + ['match' => $ctx['match']])->render();

    expect($html)->toContain('Alpha A')
        ->toContain('Bravo B')
        ->toContain('Stage 1')
        ->toContain('Stage 2');

    // Verify per-stage hit-rate ribbon is populated, not "0/0 — 0% hit rate".
    expect($html)->toContain('3/4')   // Stage 1: 2+1 hits / 4 shots
        ->toContain('2/4');           // Stage 2: 1+1 hits / 4 shots
});

it('builds a PRS Score Sheet grid with per-stage gong cells and stage times', function () {
    $ctx = makePrsMatch();
    $controller = new App\Http\Controllers\MatchExportController();

    $data = ($this->invokeExec)($controller, $ctx['match']);

    // Score Sheet payload only exists for PRS matches with PRS table data.
    expect($data['prsScoreSheet'])->not()->toBeNull();
    expect($data['prsScoreSheet']['stages'])->toHaveCount(2);

    // Stages render with short labels and the tiebreaker flag preserved
    // so the column header can lead with a "TB" badge.
    [$s1, $s2] = $data['prsScoreSheet']['stages'];
    expect($s1['label'])->toBe('Stage 1');
    expect($s1['gong_count'])->toBe(2);
    expect($s1['is_tiebreaker'])->toBeFalse();
    expect($s2['short_label'])->toBe('TB');
    expect($s2['is_tiebreaker'])->toBeTrue();

    // Rows arrive in rank order — Alpha first (3 hits), Bravo second (2).
    expect($data['prsScoreSheet']['rows'])->toHaveCount(2);
    [$alphaRow, $bravoRow] = $data['prsScoreSheet']['rows'];
    expect($alphaRow['display_name'])->toBe('Alpha A');
    expect($alphaRow['rank'])->toBe(1);
    expect($alphaRow['total_hits'])->toBe(3);
    expect($alphaRow['total_misses'])->toBe(1);

    // Per-stage cells map shot_number → state. Alpha's stage 1 is hit/hit;
    // stage 2 is hit/miss. Stage times come from prs_stage_results.
    $alphaStage1 = $alphaRow['stages'][$ctx['stages']['s1']->id];
    expect($alphaStage1['cells'])->toBe(['hit', 'hit']);
    expect($alphaStage1['hits'])->toBe(2);
    expect($alphaStage1['time'])->toBe(40.0);

    $alphaStage2 = $alphaRow['stages'][$ctx['stages']['s2']->id];
    expect($alphaStage2['cells'])->toBe(['hit', 'miss']);
    expect($alphaStage2['time'])->toBe(50.0);

    // Total time sums every stage's official time (Alpha: 40 + 50 = 90).
    expect($alphaRow['total_time'])->toBe(90.0);
});

it('renders the PRS Score Sheet partial with gong dots instead of the distance heatmap', function () {
    $ctx = makePrsMatch();
    $controller = new App\Http\Controllers\MatchExportController();

    $data = ($this->invokeExec)($controller, $ctx['match']);
    $html = view('exports.pdf-executive-summary', $data + ['match' => $ctx['match']])->render();

    // PRS-specific markup is present.
    expect($html)->toContain('SCORE SHEET')
        ->toContain('shot-hit')
        ->toContain('shot-miss')
        ->toContain('prs-grid');

    // Standard heatmap chrome is suppressed for PRS — no "MATCH REPORT"
    // distance-heatmap title and no "multiplier" copy in the PRS branch.
    expect($html)->not()->toContain('MATCH REPORT</span>');
    expect($html)->not()->toContain('multiplier 1×');
});

it('PRS detailed CSV writes hit/miss/time cells from the new tables', function () {
    $ctx = makePrsMatch();
    $controller = new App\Http\Controllers\MatchExportController();

    $ref = new ReflectionMethod($controller, 'prsDetailed');
    $ref->setAccessible(true);

    $stream = fopen('php://temp', 'w+');
    $ref->invoke($controller, $ctx['match'], $stream);
    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);

    $lines = array_values(array_filter(explode("\n", trim($csv))));
    expect($lines)->toHaveCount(3); // header + 2 shooters

    [$header, $row1, $row2] = $lines;
    expect($header)->toContain('Stage 1 G1');
    expect($header)->toContain('Stage 1 Time');

    // Alpha is rank 1; row1 should reference Alpha and contain "H" cells.
    expect($row1)->toContain('Alpha A')
        ->and($row1)->toContain('H,H')   // both stage-1 cells hit
        ->and($row1)->toContain('40');   // stage-1 official time
});
