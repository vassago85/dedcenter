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

it('keys the podium by TRUE finishing rank and leaves an unlinked finisher\'s slot unawarded', function () {
    // Regression: the helper used to renumber the account-linked shooters
    // into 1/2/3, so an unlinked finisher above a linked one silently handed
    // the higher badge to the wrong person (linked2 would jump to silver).
    // Correct behaviour: keep each linked shooter at their real rank; the
    // guest's rank-2 slot is simply unawarded (an import placeholder would
    // hold it in production so it can be claimed).
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

    // linked1 = true rank 1 (gold); guest = rank 2 but unlinked → unawarded;
    // linked2 = true rank 3 (bronze), NOT promoted to silver.
    expect($podium)->toBe([
        1 => $linked1->id,
        3 => $linked2->id,
    ]);
});

it('breaks total-score ties on the small-gong cascade (jannie beats daniel at 7 by hitting more Aces)', function () {
    // Regression — Royal Flush 3 Oct 2026 podium: Daniel and Jannie both
    // scored 129, Daniel with 17 hits, Jannie with 16. The MD's rule is
    // "ties go to whoever hit the smaller / higher-scoring targets", not
    // whoever had the higher hit count. standardStandings() must apply
    // the Side-Bet-style small-gong cascade so Jannie (more hits on the
    // small/Ace gong) ranks above Daniel at the same total.
    //
    // Scaled down to easy integers: both score 7 points, Daniel with 4
    // hits spread across all gong tiers, Jannie with 3 hits
    // concentrated on the smallest (Ace) gong at each distance.
    $owner = User::factory()->create();
    $match = ShootingMatch::factory()->create([
        'created_by' => $owner->id,
        'scoring_type' => 'standard',
    ]);

    // Royal-Flush-shaped: two target sets, each with Ace / King / Small.
    // Rank 0 within each set = the Ace (highest multiplier). Points per
    // hit = distance_multiplier × gong_multiplier. Both distances use
    // multiplier 1.0 so the arithmetic reads straight off the gong
    // multipliers and distance doesn't skew the primary total.
    $buildSet = function (string $label, int $distance, int $sort) use ($match) {
        $set = TargetSet::create([
            'match_id' => $match->id, 'label' => $label,
            'distance_meters' => $distance, 'distance_multiplier' => 1.0, 'sort_order' => $sort,
        ]);
        return [
            'ace' => Gong::create(['target_set_id' => $set->id, 'number' => 1, 'label' => 'A', 'multiplier' => '3.00']),
            'king' => Gong::create(['target_set_id' => $set->id, 'number' => 2, 'label' => 'K', 'multiplier' => '2.00']),
            'small' => Gong::create(['target_set_id' => $set->id, 'number' => 3, 'label' => '3', 'multiplier' => '1.00']),
        ];
    };
    $near = $buildSet('400m', 400, 1);
    $far = $buildSet('700m', 700, 2);

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Relay 7']);

    // Daniel — 1 Ace + 1 King + 2 Small = 3 + 2 + 1 + 1 = 7
    // (4 hits, 1 Ace at rank 0, lots of filler on the easier gongs)
    $daniel = Shooter::create(['name' => 'Daniel', 'squad_id' => $squad->id, 'status' => 'active']);
    ($this->hit)($daniel, $near['ace']);
    ($this->hit)($daniel, $near['king']);
    ($this->hit)($daniel, $near['small']);
    ($this->hit)($daniel, $far['small']);

    // Jannie — 2 Aces + 1 Small = 3 + 3 + 1 = 7
    // (3 hits, 2 Aces at rank 0 — the "small / high-scoring" targets)
    $jannie = Shooter::create(['name' => 'Jannie', 'squad_id' => $squad->id, 'status' => 'active']);
    ($this->hit)($jannie, $near['ace']);
    ($this->hit)($jannie, $far['ace']);
    ($this->hit)($jannie, $near['small']);

    $standings = (new MatchStandingsService())->standardStandings($match)->values();

    // Both tied on 7. Jannie has 2 Ace (rank-0) hits vs Daniel's 1, so
    // Jannie wins the tiebreak and ranks 1st — even though Daniel landed
    // one more hit overall.
    expect((float) $standings[0]->total_score)->toBe(7.0)
        ->and((float) $standings[1]->total_score)->toBe(7.0)
        ->and($standings[0]->name)->toBe('Jannie')
        ->and($standings[0]->hits)->toBe(3)
        ->and($standings[0]->rank)->toBe(1)
        ->and($standings[1]->name)->toBe('Daniel')
        ->and($standings[1]->hits)->toBe(4)
        ->and($standings[1]->rank)->toBe(2);
});

it('cascades the small-gong tiebreaker to the furthest distance when gong counts tie', function () {
    // Both shooters hit exactly 1 Ace and nothing else — same total, same
    // count at rank 0. The cascade should then prefer the shooter who
    // got that Ace at the LONGER distance.
    $owner = User::factory()->create();
    $match = ShootingMatch::factory()->create([
        'created_by' => $owner->id,
        'scoring_type' => 'standard',
    ]);

    $near = TargetSet::create([
        'match_id' => $match->id, 'label' => '400m',
        'distance_meters' => 400, 'distance_multiplier' => 1.0, 'sort_order' => 1,
    ]);
    $far = TargetSet::create([
        'match_id' => $match->id, 'label' => '700m',
        'distance_meters' => 700, 'distance_multiplier' => 1.0, 'sort_order' => 2,
    ]);
    // Same gong multipliers at both distances so "rank 0 = Ace" is
    // consistent across sets and totals genuinely tie.
    $nearAce = Gong::create(['target_set_id' => $near->id, 'number' => 1, 'label' => 'A', 'multiplier' => '2.00']);
    $farAce = Gong::create(['target_set_id' => $far->id, 'number' => 1, 'label' => 'A', 'multiplier' => '2.00']);

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);

    $closeShooter = Shooter::create(['name' => 'Close', 'squad_id' => $squad->id, 'status' => 'active']);
    ($this->hit)($closeShooter, $nearAce);

    $farShooter = Shooter::create(['name' => 'Far', 'squad_id' => $squad->id, 'status' => 'active']);
    ($this->hit)($farShooter, $farAce);

    $standings = (new MatchStandingsService())->standardStandings($match)->values();

    expect((float) $standings[0]->total_score)->toBe((float) $standings[1]->total_score)
        ->and($standings[0]->name)->toBe('Far')
        ->and($standings[0]->rank)->toBe(1)
        ->and($standings[1]->name)->toBe('Close')
        ->and($standings[1]->rank)->toBe(2);
});

it('PRS podium keeps true rank when the top finishers are unclaimed (match-54 scenario)', function () {
    // Reproduces the PPRC match: the two top finishers are unclaimed
    // walk-ins, and a lower finisher has a real account. The lower shooter
    // must receive BRONZE (their real rank 3), never GOLD by renumbering.
    $owner = User::factory()->create();
    $thirdUser = User::factory()->create();

    $match = ShootingMatch::factory()->create(['created_by' => $owner->id, 'scoring_type' => 'prs']);
    $stage = TargetSet::create([
        'match_id' => $match->id, 'label' => 'Stage 1',
        'distance_meters' => 0, 'distance_multiplier' => 1.0, 'sort_order' => 1,
    ]);

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);
    // Unclaimed winners (no user_id) + one account-linked lower finisher.
    $winner = Shooter::create(['name' => 'Winner', 'squad_id' => $squad->id, 'status' => 'active']);
    $runnerUp = Shooter::create(['name' => 'RunnerUp', 'squad_id' => $squad->id, 'status' => 'active']);
    $third = Shooter::create(['name' => 'Third', 'squad_id' => $squad->id, 'status' => 'active', 'user_id' => $thirdUser->id]);

    foreach ([[$winner, 3], [$runnerUp, 2], [$third, 1]] as [$shooter, $hits]) {
        \App\Models\PrsStageResult::create([
            'match_id' => $match->id, 'stage_id' => $stage->id, 'shooter_id' => $shooter->id,
            'hits' => $hits, 'misses' => 3 - $hits, 'not_taken' => 0,
            'raw_time_seconds' => 30.00, 'official_time_seconds' => 30.00,
            'completed_at' => now(),
        ]);
    }

    $podium = (new MatchStandingsService())->podiumShooterIds($match, 3);

    // Winner (rank 1) + RunnerUp (rank 2) are unclaimed → those slots stay
    // unawarded; Third keeps their real rank 3 (bronze), not promoted to gold.
    expect($podium)->toBe([3 => $third->id]);
});
