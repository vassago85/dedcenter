<?php

use App\Models\Gong;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;

/**
 * Locks in the new Full Match Report data shape:
 *   - royalFlushesByDistance[distance] = count of shooters who full-swept
 *     every gong at that distance
 *   - royalFlushShootersByDistance[distance] = display names of those sweepers
 *   - perfectHandShooters = display names who full-swept EVERY distance
 *   - matchFacts = human-readable highlight lines (list of [tag, text])
 *
 * The blade relies on all four keys being present, with sensible defaults
 * when the match isn't a Royal Flush (empty arrays, no facts section).
 */

beforeEach(function () {
    $this->invoke = function (App\Http\Controllers\MatchExportController $c, ShootingMatch $m) {
        $ref = new ReflectionMethod($c, 'buildExecutiveSummaryData');
        $ref->setAccessible(true);
        return $ref->invoke($c, $m);
    };

    $this->owner = User::factory()->create();
});

/**
 * Spins up a Royal Flush match with 2 distances × 2 gongs each so we can
 * cheaply assert every cell state. Returns [match, shooters, gongs].
 */
function makeRfMatch(User $owner): array
{
    $match = ShootingMatch::factory()->create([
        'created_by' => $owner->id,
        'scoring_type' => 'standard',
        'royal_flush_enabled' => true,
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
    $g500_2 = Gong::create(['target_set_id' => $ts500->id, 'number' => 2, 'label' => 'G2', 'multiplier' => '1.50']);

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);

    return [
        'match' => $match,
        'squad' => $squad,
        'gongs' => [
            '400' => [$g400_1, $g400_2],
            '500' => [$g500_1, $g500_2],
        ],
    ];
}

it('counts Royal Flushes per distance', function () {
    $ctx = makeRfMatch($this->owner);

    $alice = Shooter::create(['name' => 'Alice A', 'squad_id' => $ctx['squad']->id, 'status' => 'active']);
    $bob   = Shooter::create(['name' => 'Bob B',   'squad_id' => $ctx['squad']->id, 'status' => 'active']);
    $carol = Shooter::create(['name' => 'Carol C', 'squad_id' => $ctx['squad']->id, 'status' => 'active']);

    // Alice: flushes BOTH distances → perfect hand + RF at 400 and 500.
    foreach ($ctx['gongs']['400'] as $g) Score::create(['shooter_id' => $alice->id, 'gong_id' => $g->id, 'is_hit' => true,  'recorded_at' => now()]);
    foreach ($ctx['gongs']['500'] as $g) Score::create(['shooter_id' => $alice->id, 'gong_id' => $g->id, 'is_hit' => true,  'recorded_at' => now()]);

    // Bob: flushes 400 only; drops one at 500 → RF at 400, not 500.
    foreach ($ctx['gongs']['400'] as $g) Score::create(['shooter_id' => $bob->id,   'gong_id' => $g->id, 'is_hit' => true,  'recorded_at' => now()]);
    Score::create(['shooter_id' => $bob->id, 'gong_id' => $ctx['gongs']['500'][0]->id, 'is_hit' => true,  'recorded_at' => now()]);
    Score::create(['shooter_id' => $bob->id, 'gong_id' => $ctx['gongs']['500'][1]->id, 'is_hit' => false, 'recorded_at' => now()]);

    // Carol: drops one at each distance → no RFs anywhere.
    Score::create(['shooter_id' => $carol->id, 'gong_id' => $ctx['gongs']['400'][0]->id, 'is_hit' => true,  'recorded_at' => now()]);
    Score::create(['shooter_id' => $carol->id, 'gong_id' => $ctx['gongs']['400'][1]->id, 'is_hit' => false, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $carol->id, 'gong_id' => $ctx['gongs']['500'][0]->id, 'is_hit' => false, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $carol->id, 'gong_id' => $ctx['gongs']['500'][1]->id, 'is_hit' => true,  'recorded_at' => now()]);

    $data = ($this->invoke)(new App\Http\Controllers\MatchExportController(), $ctx['match']);

    expect($data)->toHaveKeys(['royalFlushesByDistance', 'royalFlushShootersByDistance', 'perfectHandShooters', 'matchFacts']);

    // Alice + Bob flushed 400 → count 2. Only Alice flushed 500 → count 1.
    expect($data['royalFlushesByDistance'])->toBe([400 => 2, 500 => 1]);

    expect($data['royalFlushShootersByDistance'][400])->toContain('Alice A', 'Bob B')
        ->and($data['royalFlushShootersByDistance'][400])->not->toContain('Carol C')
        ->and($data['royalFlushShootersByDistance'][500])->toBe(['Alice A']);
});

it('identifies Perfect Hand shooters (flushed every distance)', function () {
    $ctx = makeRfMatch($this->owner);

    $alice = Shooter::create(['name' => 'Alice A', 'squad_id' => $ctx['squad']->id, 'status' => 'active']);
    $bob   = Shooter::create(['name' => 'Bob B',   'squad_id' => $ctx['squad']->id, 'status' => 'active']);

    // Alice full-sweeps the match — every gong at every distance.
    foreach ($ctx['gongs']['400'] as $g) Score::create(['shooter_id' => $alice->id, 'gong_id' => $g->id, 'is_hit' => true,  'recorded_at' => now()]);
    foreach ($ctx['gongs']['500'] as $g) Score::create(['shooter_id' => $alice->id, 'gong_id' => $g->id, 'is_hit' => true,  'recorded_at' => now()]);

    // Bob flushes 400 but drops the last shot at 500 — so NOT a perfect hand.
    foreach ($ctx['gongs']['400'] as $g) Score::create(['shooter_id' => $bob->id,   'gong_id' => $g->id, 'is_hit' => true,  'recorded_at' => now()]);
    Score::create(['shooter_id' => $bob->id, 'gong_id' => $ctx['gongs']['500'][0]->id, 'is_hit' => true,  'recorded_at' => now()]);
    Score::create(['shooter_id' => $bob->id, 'gong_id' => $ctx['gongs']['500'][1]->id, 'is_hit' => false, 'recorded_at' => now()]);

    $data = ($this->invoke)(new App\Http\Controllers\MatchExportController(), $ctx['match']);

    expect($data['perfectHandShooters'])->toBe(['Alice A']);
});

it('builds match facts including a Royal Flush total', function () {
    $ctx = makeRfMatch($this->owner);

    $alice = Shooter::create(['name' => 'Alice A', 'squad_id' => $ctx['squad']->id, 'status' => 'active']);
    $bob   = Shooter::create(['name' => 'Bob B',   'squad_id' => $ctx['squad']->id, 'status' => 'active']);

    foreach ($ctx['gongs']['400'] as $g) Score::create(['shooter_id' => $alice->id, 'gong_id' => $g->id, 'is_hit' => true,  'recorded_at' => now()]);
    foreach ($ctx['gongs']['500'] as $g) Score::create(['shooter_id' => $alice->id, 'gong_id' => $g->id, 'is_hit' => true,  'recorded_at' => now()]);
    foreach ($ctx['gongs']['400'] as $g) Score::create(['shooter_id' => $bob->id,   'gong_id' => $g->id, 'is_hit' => true,  'recorded_at' => now()]);
    Score::create(['shooter_id' => $bob->id, 'gong_id' => $ctx['gongs']['500'][0]->id, 'is_hit' => false, 'recorded_at' => now()]);
    Score::create(['shooter_id' => $bob->id, 'gong_id' => $ctx['gongs']['500'][1]->id, 'is_hit' => false, 'recorded_at' => now()]);

    $data = ($this->invoke)(new App\Http\Controllers\MatchExportController(), $ctx['match']);

    expect($data['matchFacts'])->not->toBeEmpty();

    $tags = collect($data['matchFacts'])->pluck('tag')->all();
    // Margin and Flushes should both be present for a contested RF match
    // with at least one full sweep.
    expect($tags)->toContain('Margin')
        ->and($tags)->toContain('Flushes')
        ->and($tags)->toContain('Perfect Hand');

    // Flushes fact should cite the 3 total sweeps (Alice 400, Alice 500, Bob 400).
    $flushesFact = collect($data['matchFacts'])->firstWhere('tag', 'Flushes');
    expect($flushesFact['text'])->toContain('3 Royal Flushes');
});

it('returns empty RF arrays and skips Flushes fact for non-RF matches', function () {
    $owner = User::factory()->create();
    $match = ShootingMatch::factory()->create([
        'created_by' => $owner->id,
        'scoring_type' => 'standard',
        'royal_flush_enabled' => false,
    ]);
    $ts = TargetSet::create([
        'match_id' => $match->id,
        'label' => '300m',
        'distance_meters' => 300,
        'distance_multiplier' => 3.0,
        'sort_order' => 1,
    ]);
    $g = Gong::create(['target_set_id' => $ts->id, 'number' => 1, 'label' => 'G1', 'multiplier' => '1.00']);
    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);

    $alice = Shooter::create(['name' => 'Alice A', 'squad_id' => $squad->id, 'status' => 'active']);
    $bob   = Shooter::create(['name' => 'Bob B',   'squad_id' => $squad->id, 'status' => 'active']);
    Score::create(['shooter_id' => $alice->id, 'gong_id' => $g->id, 'is_hit' => true,  'recorded_at' => now()]);
    Score::create(['shooter_id' => $bob->id,   'gong_id' => $g->id, 'is_hit' => false, 'recorded_at' => now()]);

    $data = ($this->invoke)(new App\Http\Controllers\MatchExportController(), $match);

    expect($data['royalFlushesByDistance'])->toBe([])
        ->and($data['royalFlushShootersByDistance'])->toBe([])
        ->and($data['perfectHandShooters'])->toBe([]);

    $tags = collect($data['matchFacts'])->pluck('tag')->all();
    expect($tags)->not->toContain('Flushes')
        ->and($tags)->not->toContain('Perfect Hand');
});
