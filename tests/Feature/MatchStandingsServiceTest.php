<?php

use App\Models\Gong;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use App\Services\MatchStandingsService;

/**
 * The MatchStandingsService is the SINGLE source of truth for weighted shooter rankings
 * on standard (non-PRS, non-ELR) matches, INCLUDING Royal Flush.
 * Formula per hit: distance_multiplier × gong_multiplier
 */

beforeEach(function () {
    $this->makeGongs = function (TargetSet $ts, int $count): array {
        $g = [];
        for ($i = 1; $i <= $count; $i++) {
            $g[] = Gong::create([
                'target_set_id' => $ts->id,
                'number' => $i,
                'label' => "G{$i}",
                'multiplier' => '1.00',
            ]);
        }
        return $g;
    };

    $this->hit = function (Shooter $s, Gong $g, bool $h = true): Score {
        return Score::create([
            'shooter_id' => $s->id,
            'gong_id' => $g->id,
            'is_hit' => $h,
            'recorded_at' => now(),
        ]);
    };
});

it('ranks shooters using the weighted formula (distance × gong multiplier)', function () {
    $owner = User::factory()->create();
    $match = ShootingMatch::factory()->create([
        'created_by' => $owner->id,
        'scoring_type' => 'standard',
    ]);

    $near = TargetSet::create([
        'match_id' => $match->id, 'label' => '400m',
        'distance_meters' => 400, 'distance_multiplier' => 4.0, 'sort_order' => 1,
    ]);
    $far = TargetSet::create([
        'match_id' => $match->id, 'label' => '700m',
        'distance_meters' => 700, 'distance_multiplier' => 7.0, 'sort_order' => 2,
    ]);

    [$n1, $n2] = ($this->makeGongs)($near, 2);
    [$f1, $f2] = ($this->makeGongs)($far, 2);

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);

    $alice = Shooter::create(['name' => 'Alice', 'squad_id' => $squad->id, 'status' => 'active']);
    ($this->hit)($alice, $n1); ($this->hit)($alice, $n2);

    $bob = Shooter::create(['name' => 'Bob', 'squad_id' => $squad->id, 'status' => 'active']);
    ($this->hit)($bob, $f1); ($this->hit)($bob, $f2);

    $standings = (new MatchStandingsService())->standardStandings($match)->values();

    expect($standings[0]->name)->toBe('Bob')
        ->and((float) $standings[0]->total_score)->toBe(14.0)
        ->and($standings[0]->rank)->toBe(1)
        ->and($standings[1]->name)->toBe('Alice')
        ->and((float) $standings[1]->total_score)->toBe(8.0)
        ->and($standings[1]->rank)->toBe(2);
});

it('puts DQd shooters at the bottom with rank null', function () {
    $owner = User::factory()->create();
    $match = ShootingMatch::factory()->create(['created_by' => $owner->id, 'scoring_type' => 'standard']);

    $ts = TargetSet::create([
        'match_id' => $match->id, 'label' => '500m',
        'distance_meters' => 500, 'distance_multiplier' => 5.0, 'sort_order' => 1,
    ]);
    [$g1] = ($this->makeGongs)($ts, 1);

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);
    Shooter::create(['name' => 'Active', 'squad_id' => $squad->id, 'status' => 'active']);
    $dq = Shooter::create(['name' => 'DQ', 'squad_id' => $squad->id, 'status' => 'dq']);
    ($this->hit)($dq, $g1);

    $standings = (new MatchStandingsService())->standardStandings($match)->values();

    expect($standings[0]->name)->toBe('Active')
        ->and($standings[0]->rank)->toBe(1)
        ->and($standings[1]->name)->toBe('DQ')
        ->and($standings[1]->rank)->toBeNull();
});

it('excludes no-show shooters from the ranking and lists them at the bottom with rank null', function () {
    // This is the bug the admin "mark as no-show" UI solves: a shooter who
    // didn't attend but was accidentally scored as a wall of misses must not
    // occupy a leaderboard slot — they shouldn't drag the field average down,
    // and they shouldn't tie for last place.
    $owner = User::factory()->create();
    $match = ShootingMatch::factory()->create(['created_by' => $owner->id, 'scoring_type' => 'standard']);

    $ts = TargetSet::create([
        'match_id' => $match->id, 'label' => '500m',
        'distance_meters' => 500, 'distance_multiplier' => 5.0, 'sort_order' => 1,
    ]);
    [$g1, $g2] = ($this->makeGongs)($ts, 2);

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);

    // Real competitor with 2 hits.
    $alice = Shooter::create(['name' => 'Alice', 'squad_id' => $squad->id, 'status' => 'active']);
    ($this->hit)($alice, $g1);
    ($this->hit)($alice, $g2);

    // Real competitor with 1 hit.
    $bob = Shooter::create(['name' => 'Bob', 'squad_id' => $squad->id, 'status' => 'active']);
    ($this->hit)($bob, $g1);

    // No-show scored as all misses — should be ignored for ranking but still
    // appear in the collection so the report can render an N/S row.
    $ghost = Shooter::create(['name' => 'Ghost', 'squad_id' => $squad->id, 'status' => 'no_show']);
    ($this->hit)($ghost, $g1, false);
    ($this->hit)($ghost, $g2, false);

    $standings = (new MatchStandingsService())->standardStandings($match)->values();

    expect($standings)->toHaveCount(3)
        ->and($standings[0]->name)->toBe('Alice')
        ->and($standings[0]->rank)->toBe(1)
        ->and($standings[1]->name)->toBe('Bob')
        ->and($standings[1]->rank)->toBe(2)
        ->and($standings[2]->name)->toBe('Ghost')
        ->and($standings[2]->status)->toBe('no_show')
        ->and($standings[2]->rank)->toBeNull();
});

it('excludes no-show shooters from podium awarding', function () {
    // Regression: the podium helper used to only filter DQs. Ensure no-shows
    // are also dropped so we never award podium badges to someone who wasn't there.
    $owner = User::factory()->create();
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    $match = ShootingMatch::factory()->create(['created_by' => $owner->id, 'scoring_type' => 'standard']);
    $ts = TargetSet::create([
        'match_id' => $match->id, 'label' => '500m',
        'distance_meters' => 500, 'distance_multiplier' => 5.0, 'sort_order' => 1,
    ]);
    [$g1, $g2] = ($this->makeGongs)($ts, 2);

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);
    $linked1 = Shooter::create(['name' => 'Linked1', 'squad_id' => $squad->id, 'status' => 'active', 'user_id' => $u1->id]);
    // Linked2 is a no-show with more "points" recorded than Linked1 — must not
    // be placed on the podium despite sitting "above" Linked1 by raw total.
    $linked2 = Shooter::create(['name' => 'Linked2', 'squad_id' => $squad->id, 'status' => 'no_show', 'user_id' => $u2->id]);

    ($this->hit)($linked1, $g1);
    ($this->hit)($linked2, $g1);
    ($this->hit)($linked2, $g2);

    $podium = (new MatchStandingsService())->podiumShooterIds($match, 3);

    expect($podium)->toBe([1 => $linked1->id]);
});

it('returns podium shooter ids ordered by rank for account-linked shooters only', function () {
    $owner = User::factory()->create();
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    $match = ShootingMatch::factory()->create(['created_by' => $owner->id, 'scoring_type' => 'standard']);
    $ts = TargetSet::create([
        'match_id' => $match->id, 'label' => '500m',
        'distance_meters' => 500, 'distance_multiplier' => 5.0, 'sort_order' => 1,
    ]);
    [$g1, $g2, $g3] = ($this->makeGongs)($ts, 3);

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);
    $linked1 = Shooter::create(['name' => 'Linked1', 'squad_id' => $squad->id, 'status' => 'active', 'user_id' => $u1->id]);
    $linked2 = Shooter::create(['name' => 'Linked2', 'squad_id' => $squad->id, 'status' => 'active', 'user_id' => $u2->id]);
    $guest = Shooter::create(['name' => 'Guest', 'squad_id' => $squad->id, 'status' => 'active']);

    ($this->hit)($linked1, $g1); ($this->hit)($linked1, $g2); ($this->hit)($linked1, $g3);
    ($this->hit)($guest, $g1); ($this->hit)($guest, $g2);
    ($this->hit)($linked2, $g1);

    $podium = (new MatchStandingsService())->podiumShooterIds($match, 3);

    expect($podium)->toBe([
        1 => $linked1->id,
        2 => $linked2->id,
    ]);
});
