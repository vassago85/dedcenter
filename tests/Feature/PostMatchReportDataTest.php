<?php

use App\Models\Gong;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;

/**
 * Verifies the data shape the new per-distance post-match PDF expects:
 *   - one entry per target set in $distanceTables
 *   - each row has tick/cross/none cells with points for hits
 *   - caliber is parsed from the "Name — Caliber" suffix
 *   - row subtotals match distance_multiplier × gong.multiplier
 */

beforeEach(function () {
    $this->invoke = function (App\Http\Controllers\MatchExportController $c, ShootingMatch $m) {
        $ref = new ReflectionMethod($c, 'buildPostMatchReportData');
        $ref->setAccessible(true);
        return $ref->invoke($c, $m);
    };
});

it('builds per-distance tables with tick/cross cells and parsed caliber', function () {
    $owner = User::factory()->create();
    $match = ShootingMatch::factory()->create([
        'created_by' => $owner->id,
        'scoring_type' => 'standard',
    ]);

    $ts400 = TargetSet::create([
        'match_id' => $match->id,
        'label' => '400m',
        'distance_meters' => 400,
        'distance_multiplier' => 4.0,
        'sort_order' => 1,
    ]);
    $ts500 = TargetSet::create([
        'match_id' => $match->id,
        'label' => '500m',
        'distance_meters' => 500,
        'distance_multiplier' => 5.0,
        'sort_order' => 2,
    ]);

    $g400_1 = Gong::create(['target_set_id' => $ts400->id, 'number' => 1, 'label' => 'G1', 'multiplier' => '1.00']);
    $g400_2 = Gong::create(['target_set_id' => $ts400->id, 'number' => 2, 'label' => 'G2', 'multiplier' => '1.50']);
    $g500_1 = Gong::create(['target_set_id' => $ts500->id, 'number' => 1, 'label' => 'G1', 'multiplier' => '1.00']);

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);

    $alice = Shooter::create(['name' => 'Alice Aardvark — 6x46', 'squad_id' => $squad->id, 'status' => 'active']);
    $bob   = Shooter::create(['name' => 'Bob Baker', 'squad_id' => $squad->id, 'status' => 'active']);

    // Alice: hits 400/G1 (4), hits 400/G2 (6), misses 500/G1 → total 10
    Score::create(['shooter_id' => $alice->id, 'gong_id' => $g400_1->id, 'is_hit' => true,  'recorded_at' => now()]);
    Score::create(['shooter_id' => $alice->id, 'gong_id' => $g400_2->id, 'is_hit' => true,  'recorded_at' => now()]);
    Score::create(['shooter_id' => $alice->id, 'gong_id' => $g500_1->id, 'is_hit' => false, 'recorded_at' => now()]);

    // Bob: hits 500/G1 (5) only → total 5
    Score::create(['shooter_id' => $bob->id,   'gong_id' => $g500_1->id, 'is_hit' => true,  'recorded_at' => now()]);

    $data = ($this->invoke)(new App\Http\Controllers\MatchExportController(), $match);

    expect($data)->toHaveKeys(['match', 'standings', 'distanceTables', 'rfLeaderboard', 'sideBetCascade', 'generatedAt'])
        ->and($data['distanceTables'])->toHaveCount(2);

    $table400 = $data['distanceTables'][0];
    expect($table400['label'])->toBe('400m')
        ->and($table400['distance_multiplier'])->toBe(4.0)
        ->and($table400['gongs'])->toHaveCount(2)
        ->and($table400['gongs'][1]['points_per_hit'])->toBe(6.0);

    // Rows are ordered by overall match rank; Alice (10) > Bob (5)
    expect($table400['rows'][0]['name'])->toBe('Alice Aardvark — 6x46')
        ->and($table400['rows'][0]['caliber'])->toBe('6x46')
        ->and($table400['rows'][0]['rank'])->toBe(1)
        ->and($table400['rows'][0]['cells'][0]['state'])->toBe('hit')
        ->and($table400['rows'][0]['cells'][0]['points'])->toBe(4.0)
        ->and($table400['rows'][0]['cells'][1]['state'])->toBe('hit')
        ->and($table400['rows'][0]['cells'][1]['points'])->toBe(6.0)
        ->and($table400['rows'][0]['hits'])->toBe(2)
        ->and($table400['rows'][0]['misses'])->toBe(0)
        ->and($table400['rows'][0]['subtotal'])->toBe(10.0);

    expect($table400['rows'][1]['name'])->toBe('Bob Baker')
        ->and($table400['rows'][1]['caliber'])->toBeNull()
        ->and($table400['rows'][1]['cells'][0]['state'])->toBe('none')
        ->and($table400['rows'][1]['cells'][1]['state'])->toBe('none')
        ->and($table400['rows'][1]['hits'])->toBe(0);

    // 500m: Alice misses, Bob hits
    $table500 = $data['distanceTables'][1];
    expect($table500['rows'][0]['name'])->toBe('Alice Aardvark — 6x46')
        ->and($table500['rows'][0]['cells'][0]['state'])->toBe('miss')
        ->and($table500['rows'][1]['name'])->toBe('Bob Baker')
        ->and($table500['rows'][1]['cells'][0]['state'])->toBe('hit')
        ->and($table500['rows'][1]['cells'][0]['points'])->toBe(5.0)
        ->and($table500['rows'][1]['subtotal'])->toBe(5.0);
});

it('renders the post-match blade view without errors', function () {
    $owner = User::factory()->create();
    $match = ShootingMatch::factory()->create(['created_by' => $owner->id, 'scoring_type' => 'standard']);

    $ts = TargetSet::create([
        'match_id' => $match->id, 'label' => '500m',
        'distance_meters' => 500, 'distance_multiplier' => 5.0, 'sort_order' => 1,
    ]);
    $g = Gong::create(['target_set_id' => $ts->id, 'number' => 1, 'label' => 'G1', 'multiplier' => '1.00']);
    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);
    $alice = Shooter::create(['name' => 'Alice — 6x46', 'squad_id' => $squad->id, 'status' => 'active']);
    Score::create(['shooter_id' => $alice->id, 'gong_id' => $g->id, 'is_hit' => true, 'recorded_at' => now()]);

    $data = ($this->invoke)(new App\Http\Controllers\MatchExportController(), $match);

    $html = view('exports.pdf-post-match-report', $data)->render();
    expect($html)->toContain('Post-Match Report')
        ->toContain('Alice')
        ->toContain('6x46')
        ->toContain('500m');
});
