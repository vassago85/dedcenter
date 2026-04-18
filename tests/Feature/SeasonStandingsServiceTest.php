<?php

use App\Models\Gong;
use App\Models\Score;
use App\Models\Season;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use App\Services\SeasonStandingsService;

/**
 * Season leaderboard contract:
 *   per-match relative = round( shooter_total / winner_total × match.leaderboard_points )
 *   season total        = sum of BEST 3 relatives
 *
 * Regular match: leaderboard_points = 100  (user scoring rule)
 * Season final : leaderboard_points = 200  (user scoring rule)
 */

beforeEach(function () {
    $this->mkSeason = fn () => Season::create([
        'name' => 'Test Season '.uniqid(),
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->mkMatch = function (Season $season, string $name, int $points) {
        $owner = User::factory()->create();
        $m = ShootingMatch::factory()->create([
            'created_by' => $owner->id,
            'scoring_type' => 'standard',
            'status' => 'completed',
            'season_id' => $season->id,
            'leaderboard_points' => $points,
            'date' => now()->subDays(30 - ($season->matches()->count() ?? 0)),
        ]);
        $ts = TargetSet::create([
            'match_id' => $m->id, 'label' => '500m',
            'distance_meters' => 500, 'distance_multiplier' => 5.0, 'sort_order' => 1,
        ]);
        return [$m, $ts];
    };

    $this->mkGong = fn (TargetSet $ts, int $n) => Gong::create([
        'target_set_id' => $ts->id, 'number' => $n,
        'label' => "G{$n}", 'multiplier' => '1.00',
    ]);

    $this->hit = function (Shooter $s, Gong $g, bool $hit = true) {
        Score::create(['shooter_id' => $s->id, 'gong_id' => $g->id, 'is_hit' => $hit, 'recorded_at' => now()]);
    };
});

it('scales each match by its leaderboard_points and sums best 3', function () {
    $season = ($this->mkSeason)();
    $user = User::factory()->create();

    // Match 1: regular (100). User hits 5 gongs, winner hit 10 → 50/100.
    [$m1, $ts1] = ($this->mkMatch)($season, 'M1', 100);
    $g1s = collect(range(1, 10))->map(fn ($n) => ($this->mkGong)($ts1, $n));
    $sq1 = Squad::create(['match_id' => $m1->id, 'name' => 'A']);
    $me1 = Shooter::create(['name' => 'Me', 'squad_id' => $sq1->id, 'status' => 'active', 'user_id' => $user->id]);
    $w1 = Shooter::create(['name' => 'Winner1', 'squad_id' => $sq1->id, 'status' => 'active']);
    foreach ($g1s->take(5) as $g) ($this->hit)($me1, $g);
    foreach ($g1s as $g) ($this->hit)($w1, $g);

    // Match 2: regular (100). User hits 8, winner hits 10 → 80/100.
    [$m2, $ts2] = ($this->mkMatch)($season, 'M2', 100);
    $g2s = collect(range(1, 10))->map(fn ($n) => ($this->mkGong)($ts2, $n));
    $sq2 = Squad::create(['match_id' => $m2->id, 'name' => 'A']);
    $me2 = Shooter::create(['name' => 'Me', 'squad_id' => $sq2->id, 'status' => 'active', 'user_id' => $user->id]);
    $w2 = Shooter::create(['name' => 'Winner2', 'squad_id' => $sq2->id, 'status' => 'active']);
    foreach ($g2s->take(8) as $g) ($this->hit)($me2, $g);
    foreach ($g2s as $g) ($this->hit)($w2, $g);

    // Match 3: regular (100). User hits 10, winner hits 10 → 100/100 (tie, user wins outright).
    [$m3, $ts3] = ($this->mkMatch)($season, 'M3', 100);
    $g3s = collect(range(1, 10))->map(fn ($n) => ($this->mkGong)($ts3, $n));
    $sq3 = Squad::create(['match_id' => $m3->id, 'name' => 'A']);
    $me3 = Shooter::create(['name' => 'Me', 'squad_id' => $sq3->id, 'status' => 'active', 'user_id' => $user->id]);
    foreach ($g3s as $g) ($this->hit)($me3, $g);

    // Match 4: season final (200). User hits 9, winner hits 10 → 180/200.
    [$m4, $ts4] = ($this->mkMatch)($season, 'Final', 200);
    $g4s = collect(range(1, 10))->map(fn ($n) => ($this->mkGong)($ts4, $n));
    $sq4 = Squad::create(['match_id' => $m4->id, 'name' => 'A']);
    $me4 = Shooter::create(['name' => 'Me', 'squad_id' => $sq4->id, 'status' => 'active', 'user_id' => $user->id]);
    $w4 = Shooter::create(['name' => 'Winner4', 'squad_id' => $sq4->id, 'status' => 'active']);
    foreach ($g4s->take(9) as $g) ($this->hit)($me4, $g);
    foreach ($g4s as $g) ($this->hit)($w4, $g);

    $standings = (new SeasonStandingsService())->calculate($season->fresh());
    $meRow = collect($standings)->firstWhere('user_id', $user->id);

    // Relative scores: 50, 80, 100, 180. Best 3 = 100 + 180 + 80 = 360. Max possible = 400.
    expect($meRow)->not->toBeNull()
        ->and($meRow['matches_played'])->toBe(4)
        ->and($meRow['counting_results'])->toBe(3)
        ->and($meRow['best3_total'])->toBe(360);

    // Check counted flags
    $counted = collect($meRow['match_results'])->where('counted', true)->pluck('relative_score')->sort()->values();
    expect($counted->all())->toBe([80, 100, 180]);
});

it('counts only best 3 and drops the weakest when 4+ matches exist', function () {
    $season = ($this->mkSeason)();
    $user = User::factory()->create();

    $relatives = [50, 70, 90, 100]; // shooter hits equal to these (out of 100 total gongs hit by winner)
    foreach ($relatives as $i => $rel) {
        [$m, $ts] = ($this->mkMatch)($season, "M{$i}", 100);
        $gs = collect(range(1, 100))->map(fn ($n) => ($this->mkGong)($ts, $n));
        $sq = Squad::create(['match_id' => $m->id, 'name' => 'A']);
        $me = Shooter::create(['name' => 'Me', 'squad_id' => $sq->id, 'status' => 'active', 'user_id' => $user->id]);
        $w = Shooter::create(['name' => "W{$i}", 'squad_id' => $sq->id, 'status' => 'active']);
        foreach ($gs->take($rel) as $g) ($this->hit)($me, $g);
        foreach ($gs as $g) ($this->hit)($w, $g);
    }

    $standings = (new SeasonStandingsService())->calculate($season->fresh());
    $meRow = collect($standings)->firstWhere('user_id', $user->id);

    // Best 3 of [50,70,90,100] = 260
    expect($meRow['best3_total'])->toBe(260)
        ->and($meRow['matches_played'])->toBe(4)
        ->and(collect($meRow['match_results'])->where('counted', false)->first()['relative_score'])->toBe(50);
});

it('handles shooters with fewer than 3 matches', function () {
    $season = ($this->mkSeason)();
    $user = User::factory()->create();

    [$m, $ts] = ($this->mkMatch)($season, 'M1', 100);
    $g = ($this->mkGong)($ts, 1);
    $sq = Squad::create(['match_id' => $m->id, 'name' => 'A']);
    $me = Shooter::create(['name' => 'Me', 'squad_id' => $sq->id, 'status' => 'active', 'user_id' => $user->id]);
    ($this->hit)($me, $g);

    $standings = (new SeasonStandingsService())->calculate($season->fresh());
    $meRow = collect($standings)->firstWhere('user_id', $user->id);

    expect($meRow['matches_played'])->toBe(1)
        ->and($meRow['counting_results'])->toBe(1)
        ->and($meRow['best3_total'])->toBe(100);
});
