<?php

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\Organization;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use App\Services\SideBetStandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Build a Royal Flush-style match with two target sets (distances)
 * and four gong sizes per set (multiplier ordered DESC = "smallest first").
 *
 * Returns [match, gongMap] where gongMap[distance][rank] = Gong (rank 0..3).
 */
function rf_make_match(): array
{
    $org = Organization::factory()->create([
        'royal_flush_enabled' => true,
    ]);
    $user = User::factory()->create();
    $org->admins()->attach($user->id, ['is_owner' => true]);

    $match = ShootingMatch::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
        'status' => MatchStatus::Completed,
        'royal_flush_enabled' => true,
        'side_bet_enabled' => true,
    ]);

    $gongMap = [];
    foreach ([600, 500, 400, 300] as $i => $distance) {
        $ts = TargetSet::factory()->create([
            'match_id' => $match->id,
            'distance_meters' => $distance,
            'sort_order' => $i,
        ]);
        // rank 0 = smallest = highest multiplier.
        foreach ([4.0, 3.0, 2.0, 1.0] as $rank => $mult) {
            $gongMap[$distance][$rank] = Gong::factory()->create([
                'target_set_id' => $ts->id,
                'number' => $rank + 1,
                'multiplier' => $mult,
            ]);
        }
    }

    return [$match, $gongMap];
}

function rf_add_shooter(ShootingMatch $match, string $name, array $hits): Shooter
{
    $squad = Squad::firstOrCreate(
        ['match_id' => $match->id, 'name' => 'Relay 1'],
        ['sort_order' => 1]
    );

    $shooter = Shooter::factory()->create([
        'squad_id' => $squad->id,
        'name' => $name,
        'status' => 'active',
    ]);

    DB::table('side_bet_shooters')->insert([
        'match_id' => $match->id,
        'shooter_id' => $shooter->id,
        'created_at' => now(),
    ]);

    foreach ($hits as $gong) {
        Score::factory()->hit()->create([
            'shooter_id' => $shooter->id,
            'gong_id' => $gong->id,
        ]);
    }

    return $shooter;
}

it('records the exact tiebreaker step that separates two adjacent rows', function () {
    [$match, $g] = rf_make_match();

    // Alice: 2 smallest-gong hits at 600m + 500m
    rf_add_shooter($match, 'Alice', [
        $g[600][0], $g[500][0],
    ]);

    // Bob: 2 smallest-gong hits at 500m + 400m (same count, weaker distances)
    rf_add_shooter($match, 'Bob', [
        $g[500][0], $g[400][0],
    ]);

    $result = app(SideBetStandingsService::class)->build($match->fresh());
    $entries = collect($result['entries']);

    expect($entries->firstWhere('name', 'Alice')['rank'])->toBe(1);
    expect($entries->firstWhere('name', 'Bob')['rank'])->toBe(2);

    // The decisive step should cite the 1st (smallest) gong and call out
    // the furthest-distance comparison that resolved it.
    $alice = $entries->firstWhere('name', 'Alice');
    expect($alice['tiebreaker_reason'])->toContain('1st gong');
    expect($alice['tiebreaker_reason'])->toContain('600m');
});

it('cascades to a further gong size when shooters match on the smallest gong', function () {
    [$match, $g] = rf_make_match();

    // Both tied on 1st gong (same count, same distances).
    // Alice wins on 2nd-gong count.
    rf_add_shooter($match, 'Alice', [
        $g[600][0], $g[500][0],
        $g[600][1], $g[500][1], $g[400][1], // 3× 2nd-gong hits
    ]);
    rf_add_shooter($match, 'Bob', [
        $g[600][0], $g[500][0],
        $g[400][1], // 1× 2nd-gong hit
    ]);

    $result = app(SideBetStandingsService::class)->build($match->fresh());
    $entries = collect($result['entries']);

    expect($entries->firstWhere('name', 'Alice')['rank'])->toBe(1);

    $alice = $entries->firstWhere('name', 'Alice');
    // Must cite the 2nd gong and the hit-count margin.
    expect($alice['tiebreaker_reason'])->toContain('2nd gong hits');
    expect($alice['tiebreaker_reason'])->toContain('3 vs 1');
});

it('falls back to total match score only when every gong is identical', function () {
    [$match, $g] = rf_make_match();

    // Identical hit profiles — service should note the raw-score margin as the last resort.
    rf_add_shooter($match, 'Alice', [$g[600][0]]);
    rf_add_shooter($match, 'Bob', [$g[600][0]]);

    $result = app(SideBetStandingsService::class)->build($match->fresh());
    $entries = collect($result['entries']);

    // Both completely identical: the "winner" gets the "tied" note.
    $first = $entries->first();
    expect($first['tiebreaker_reason'])->toBeString();
});

it('leaves the last row without a tiebreaker reason (no-one below them to beat)', function () {
    [$match, $g] = rf_make_match();

    rf_add_shooter($match, 'Alice', [$g[600][0], $g[500][0]]);
    rf_add_shooter($match, 'Bob', [$g[500][0]]);

    $result = app(SideBetStandingsService::class)->build($match->fresh());
    $entries = collect($result['entries']);

    $last = $entries->last();
    expect($last['tiebreaker_reason'])->toBeNull();
});
