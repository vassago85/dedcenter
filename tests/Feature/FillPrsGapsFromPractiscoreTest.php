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
 * Tests for `prs:fill-practiscore` — the strict-merge importer that fills
 * missing PRS stage results from a PractiScore paste without trampling
 * anything already scored on DeadCenter.
 */

/**
 * Build a 2-stage × 4-gong PRS match with 3 shooters, mirroring the
 * SAPRF/PPRC use case: one shooter pre-scored on DC, one with a
 * placeholder zero-row, one with no row at all. The fixture below uses
 * "Last, First" PractiScore-style names that need normalisation to match
 * the DC "First Last" rows.
 */
function makePractiscoreFixtureMatch(): array
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
        'is_tiebreaker' => true,
    ]);
    $stage2 = TargetSet::create([
        'match_id' => $match->id,
        'label' => 'Stage 2',
        'distance_meters' => 0,
        'distance_multiplier' => 1.0,
        'sort_order' => 2,
    ]);

    foreach ([$stage1, $stage2] as $stage) {
        for ($i = 1; $i <= 4; $i++) {
            Gong::create(['target_set_id' => $stage->id, 'number' => $i, 'label' => "G{$i}", 'multiplier' => '1.00']);
        }
    }

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Squad 1']);

    $scored = Shooter::create(['name' => 'Donovan Cook', 'squad_id' => $squad->id, 'status' => 'active']);
    $placeholder = Shooter::create(['name' => 'Schalk Van Der Merwe', 'squad_id' => $squad->id, 'status' => 'active']);
    $missing = Shooter::create(['name' => 'Francois Van Wyk', 'squad_id' => $squad->id, 'status' => 'active']);

    // Pre-existing live-scored row (must be preserved).
    PrsStageResult::create([
        'match_id' => $match->id,
        'stage_id' => $stage1->id,
        'shooter_id' => $scored->id,
        'hits' => 4,
        'misses' => 0,
        'not_taken' => 0,
        'official_time_seconds' => 80.0,
        'completed_at' => now(),
    ]);

    // Pre-existing placeholder row (all zeros — should be filled in).
    PrsStageResult::create([
        'match_id' => $match->id,
        'stage_id' => $stage1->id,
        'shooter_id' => $placeholder->id,
        'hits' => 0,
        'misses' => 0,
        'not_taken' => 0,
        'official_time_seconds' => null,
    ]);

    return compact('match', 'stage1', 'stage2', 'scored', 'placeholder', 'missing');
}

function writePractiscorePaste(string $path, array $rowsS1, array $rowsS2): void
{
    $hdr1 = "SAPRF Provincial and PPRC Club Match 2 May 2026 - Stage 1 (Skills1) - 2026-05-02\n\n"
        ."Stage Results - Combined\n"
        ."Place\tName\tNo.\tClass\tDiv\tTime\tStage Pts\tStage %\n";
    $hdr2 = "SAPRF Provincial and PPRC Club Match 2 May 2026 - Stage 2 - 2026-05-02\n\n"
        ."Stage Results - Combined\n"
        ."Place\tName\tNo.\tClass\tDiv\tStage Pts\tStage %\n";

    $body1 = '';
    foreach ($rowsS1 as $i => $r) {
        $place = $i + 1;
        $body1 .= "{$place}\t{$r['name']}\t\tCLASS\tOPEN\t{$r['time']}\t{$r['pts']}\t100.00%\n";
    }
    $body2 = '';
    foreach ($rowsS2 as $i => $r) {
        $place = $i + 1;
        $body2 .= "{$place}\t{$r['name']}\t\tCLASS\tOPEN\t{$r['pts']}\t100.00%\n";
    }

    file_put_contents($path, $hdr1.$body1."\n".$hdr2.$body2);
}

it('preserves existing scored data and fills gaps from PractiScore', function () {
    ['match' => $match, 'stage1' => $s1, 'stage2' => $s2,
        'scored' => $scored, 'placeholder' => $placeholder, 'missing' => $missing] = makePractiscoreFixtureMatch();

    $path = storage_path('framework/testing/ps-fixture.txt');
    @mkdir(dirname($path), 0777, true);

    writePractiscorePaste($path,
        rowsS1: [
            ['name' => 'Cook, Donovan', 'pts' => '3.00', 'time' => '99.00'],         // would conflict — must be skipped
            ['name' => 'van der Merwe, Schalk', 'pts' => '4.00', 'time' => '1.05'],  // funny time → 105s
            ['name' => 'wyk, Francois Van', 'pts' => '2.00', 'time' => '90.50'],     // mangled surname order
        ],
        rowsS2: [
            ['name' => 'Cook, Donovan', 'pts' => '4.00'],
            ['name' => 'van der Merwe, Schalk', 'pts' => '3.00'],
            ['name' => 'wyk, Francois Van', 'pts' => '1.00'],
        ],
    );

    expect(\Illuminate\Support\Facades\Artisan::call('prs:fill-practiscore', [
        'match' => $match->id, 'file' => $path,
    ]))->toBe(0);

    // Live-scored row preserved exactly.
    $scoredS1 = PrsStageResult::where(['match_id' => $match->id, 'stage_id' => $s1->id, 'shooter_id' => $scored->id])->first();
    expect($scoredS1->hits)->toBe(4)
        ->and($scoredS1->misses)->toBe(0)
        ->and((float) $scoredS1->official_time_seconds)->toBe(80.0);

    // Placeholder row updated in place — funny "1.05" time normalised to 105s.
    $placeholderS1 = PrsStageResult::where(['match_id' => $match->id, 'stage_id' => $s1->id, 'shooter_id' => $placeholder->id])->first();
    expect($placeholderS1->hits)->toBe(4)
        ->and($placeholderS1->misses)->toBe(0)
        ->and((float) $placeholderS1->official_time_seconds)->toBe(105.0);

    // Missing shooter (mangled surname) inserted via token-set fallback.
    $missingS1 = PrsStageResult::where(['match_id' => $match->id, 'stage_id' => $s1->id, 'shooter_id' => $missing->id])->first();
    expect($missingS1)->not->toBeNull()
        ->and($missingS1->hits)->toBe(2)
        ->and($missingS1->misses)->toBe(2)
        ->and((float) $missingS1->official_time_seconds)->toBe(90.5);

    // Stage 2 (no time column) inserted for everyone — including scored
    // shooter, because they had nothing on file for stage 2.
    expect(PrsStageResult::where(['match_id' => $match->id, 'stage_id' => $s2->id])->count())->toBe(3);
    $cookS2 = PrsStageResult::where(['match_id' => $match->id, 'stage_id' => $s2->id, 'shooter_id' => $scored->id])->first();
    expect($cookS2->hits)->toBe(4)
        ->and($cookS2->official_time_seconds)->toBeNull();

    // Synthetic per-shot rows: hits first, misses next, then NotTaken padding.
    $merweS1Shots = PrsShotScore::where(['match_id' => $match->id, 'stage_id' => $s1->id, 'shooter_id' => $placeholder->id])
        ->orderBy('shot_number')->get();
    expect($merweS1Shots)->toHaveCount(4);
    expect($merweS1Shots->pluck('result')->all())
        ->toBe([PrsShotResult::Hit, PrsShotResult::Hit, PrsShotResult::Hit, PrsShotResult::Hit]);

    $vanWykS2Shots = PrsShotScore::where(['match_id' => $match->id, 'stage_id' => $s2->id, 'shooter_id' => $missing->id])
        ->orderBy('shot_number')->get();
    expect($vanWykS2Shots->pluck('result')->all())
        ->toBe([PrsShotResult::Hit, PrsShotResult::Miss, PrsShotResult::Miss, PrsShotResult::Miss]);

    @unlink($path);
});

it('dry-run writes nothing', function () {
    ['match' => $match, 'stage1' => $s1, 'placeholder' => $placeholder] = makePractiscoreFixtureMatch();

    $path = storage_path('framework/testing/ps-fixture-dry.txt');
    @mkdir(dirname($path), 0777, true);

    writePractiscorePaste($path,
        rowsS1: [['name' => 'van der Merwe, Schalk', 'pts' => '4.00', 'time' => '90.00']],
        rowsS2: [],
    );

    expect(\Illuminate\Support\Facades\Artisan::call('prs:fill-practiscore', [
        'match' => $match->id, 'file' => $path, '--dry-run' => true,
    ]))->toBe(0);

    $row = PrsStageResult::where(['match_id' => $match->id, 'stage_id' => $s1->id, 'shooter_id' => $placeholder->id])->first();
    expect($row->hits)->toBe(0)
        ->and($row->misses)->toBe(0);
    expect(PrsShotScore::where('match_id', $match->id)->count())->toBe(0);

    @unlink($path);
});

it('parses the real SAPRF/PPRC paste fixture cleanly', function () {
    $path = base_path('database/data/practiscore/match-22-2026-05-02.txt');
    expect(is_file($path))->toBeTrue();

    $cmd = new App\Console\Commands\FillPrsGapsFromPractiscore();
    $ref = new ReflectionMethod($cmd, 'parsePaste');
    $ref->setAccessible(true);
    $parsed = $ref->invoke($cmd, file_get_contents($path));

    // 6 stages, 47 rows each.
    expect(array_keys($parsed))->toBe([1, 2, 3, 4, 5, 6]);
    foreach ($parsed as $stageNum => $data) {
        expect($data['rows'])->toHaveCount(47, "stage {$stageNum} should have 47 rows");
    }

    expect($parsed[1]['has_time'])->toBeTrue();
    expect($parsed[2]['has_time'])->toBeFalse();

    // Spot-check: stage 3 max pts = 9 (PractiScore reports stage 3 out of 9).
    expect($parsed[3]['rows']->pluck('pts')->max())->toBe(9.0);
    expect($parsed[1]['rows']->pluck('pts')->max())->toBe(10.0);
});
