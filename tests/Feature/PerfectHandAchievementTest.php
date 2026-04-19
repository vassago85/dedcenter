<?php

use App\Models\Achievement;
use App\Models\Gong;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use App\Models\UserAchievement;
use App\Services\AchievementService;

/**
 * Perfect Hand: the rarest Royal Flush badge.
 *
 * Earned when a shooter hits EVERY gong at EVERY distance in a Royal Flush
 * match. Uses the shared-winner pattern (multiple shooters can earn it per
 * match, every match where they go perfect), so the semantics are
 * is_repeatable=true + per-match uniqueness guarded by hasMatchBadge().
 */

beforeEach(function () {
    $this->seed(Database\Seeders\AchievementSeeder::class);

    $this->makeRfMatch = function (): ShootingMatch {
        $owner = User::factory()->create();
        return ShootingMatch::factory()->create([
            'created_by' => $owner->id,
            'scoring_type' => 'standard',
            'royal_flush_enabled' => true,
        ]);
    };

    $this->addTargetSet = function (ShootingMatch $match, int $meters, int $gongCount) {
        $ts = TargetSet::create([
            'match_id' => $match->id,
            'label' => "{$meters}m",
            'distance_meters' => $meters,
            'distance_multiplier' => 1.0,
            'sort_order' => $meters,
        ]);
        $gongs = [];
        for ($i = 1; $i <= $gongCount; $i++) {
            $gongs[] = Gong::create([
                'target_set_id' => $ts->id,
                'number' => $i,
                'label' => "G{$i}",
                'multiplier' => '1.00',
            ]);
        }
        return [$ts, $gongs];
    };

    $this->hit = function (Shooter $s, Gong $g): Score {
        return Score::create([
            'shooter_id' => $s->id, 'gong_id' => $g->id,
            'is_hit' => true, 'recorded_at' => now(),
        ]);
    };

    $this->miss = function (Shooter $s, Gong $g): Score {
        return Score::create([
            'shooter_id' => $s->id, 'gong_id' => $g->id,
            'is_hit' => false, 'recorded_at' => now(),
        ]);
    };
});

it('awards Perfect Hand when a shooter hits every gong at every distance', function () {
    $match = ($this->makeRfMatch)();
    [, $g400] = ($this->addTargetSet)($match, 400, 3);
    [, $g500] = ($this->addTargetSet)($match, 500, 3);
    [, $g600] = ($this->addTargetSet)($match, 600, 2);

    $user = User::factory()->create();
    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);
    $shooter = Shooter::create([
        'name' => 'Perfect Pete', 'squad_id' => $squad->id,
        'status' => 'active', 'user_id' => $user->id,
    ]);

    foreach ([...$g400, ...$g500, ...$g600] as $gong) {
        ($this->hit)($shooter, $gong);
    }

    AchievementService::evaluateRoyalFlushCompletion($match);

    $achId = Achievement::where('slug', 'perfect-hand')->value('id');
    expect($achId)->not->toBeNull();

    $ua = UserAchievement::where('match_id', $match->id)
        ->where('achievement_id', $achId)
        ->where('user_id', $user->id)
        ->first();

    expect($ua)->not->toBeNull()
        ->and($ua->metadata['distances'])->toContain(400, 500, 600)
        ->and($ua->metadata['total_targets'])->toBe(8);
});

it('does not award Perfect Hand if even one gong is missed', function () {
    $match = ($this->makeRfMatch)();
    [, $g400] = ($this->addTargetSet)($match, 400, 3);
    [, $g500] = ($this->addTargetSet)($match, 500, 3);

    $user = User::factory()->create();
    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);
    $shooter = Shooter::create([
        'name' => 'Nearly Ned', 'squad_id' => $squad->id,
        'status' => 'active', 'user_id' => $user->id,
    ]);

    // All 400m hit, all 500m hit except the last gong — 5/6.
    foreach ($g400 as $gong) ($this->hit)($shooter, $gong);
    ($this->hit)($shooter, $g500[0]);
    ($this->hit)($shooter, $g500[1]);
    ($this->miss)($shooter, $g500[2]);

    AchievementService::evaluateRoyalFlushCompletion($match);

    $achId = Achievement::where('slug', 'perfect-hand')->value('id');
    expect(UserAchievement::where('match_id', $match->id)->where('achievement_id', $achId)->count())
        ->toBe(0);
});

it('does not award Perfect Hand if a gong was simply not shot at (no score row)', function () {
    // A "not taken" gong should break the perfect run — the scoring app records
    // hits and misses explicitly, and the absence of a row is treated as
    // "not taken" by the rest of the report pipeline.
    $match = ($this->makeRfMatch)();
    [, $g400] = ($this->addTargetSet)($match, 400, 3);
    [, $g500] = ($this->addTargetSet)($match, 500, 3);

    $user = User::factory()->create();
    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);
    $shooter = Shooter::create([
        'name' => 'Skipped Sam', 'squad_id' => $squad->id,
        'status' => 'active', 'user_id' => $user->id,
    ]);

    // Hit everything except $g500[2] has no row at all — not taken.
    foreach ($g400 as $gong) ($this->hit)($shooter, $gong);
    ($this->hit)($shooter, $g500[0]);
    ($this->hit)($shooter, $g500[1]);
    // intentionally no score row for $g500[2]

    AchievementService::evaluateRoyalFlushCompletion($match);

    $achId = Achievement::where('slug', 'perfect-hand')->value('id');
    expect(UserAchievement::where('match_id', $match->id)->where('achievement_id', $achId)->count())
        ->toBe(0);
});

it('awards Perfect Hand to every shooter who goes perfect on the same match (shared winners)', function () {
    $match = ($this->makeRfMatch)();
    [, $g400] = ($this->addTargetSet)($match, 400, 2);
    [, $g500] = ($this->addTargetSet)($match, 500, 2);

    $users = User::factory()->count(3)->create();
    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);

    $perfect1 = Shooter::create(['name' => 'A', 'squad_id' => $squad->id, 'status' => 'active', 'user_id' => $users[0]->id]);
    $perfect2 = Shooter::create(['name' => 'B', 'squad_id' => $squad->id, 'status' => 'active', 'user_id' => $users[1]->id]);
    $nearly   = Shooter::create(['name' => 'C', 'squad_id' => $squad->id, 'status' => 'active', 'user_id' => $users[2]->id]);

    foreach ([...$g400, ...$g500] as $gong) {
        ($this->hit)($perfect1, $gong);
        ($this->hit)($perfect2, $gong);
    }
    // 'C' misses the last 500m gong.
    foreach ($g400 as $gong) ($this->hit)($nearly, $gong);
    ($this->hit)($nearly, $g500[0]);
    ($this->miss)($nearly, $g500[1]);

    AchievementService::evaluateRoyalFlushCompletion($match);

    $achId = Achievement::where('slug', 'perfect-hand')->value('id');

    expect(UserAchievement::where('match_id', $match->id)->where('achievement_id', $achId)->where('user_id', $users[0]->id)->exists())->toBeTrue()
        ->and(UserAchievement::where('match_id', $match->id)->where('achievement_id', $achId)->where('user_id', $users[1]->id)->exists())->toBeTrue()
        ->and(UserAchievement::where('match_id', $match->id)->where('achievement_id', $achId)->where('user_id', $users[2]->id)->exists())->toBeFalse();
});

it('does not double-award Perfect Hand if evaluation re-runs for the same match', function () {
    $match = ($this->makeRfMatch)();
    [, $g400] = ($this->addTargetSet)($match, 400, 2);
    [, $g500] = ($this->addTargetSet)($match, 500, 2);

    $user = User::factory()->create();
    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);
    $shooter = Shooter::create([
        'name' => 'Perfect Pete', 'squad_id' => $squad->id,
        'status' => 'active', 'user_id' => $user->id,
    ]);

    foreach ([...$g400, ...$g500] as $gong) ($this->hit)($shooter, $gong);

    AchievementService::evaluateRoyalFlushCompletion($match);
    AchievementService::evaluateRoyalFlushCompletion($match);
    AchievementService::evaluateRoyalFlushCompletion($match);

    $achId = Achievement::where('slug', 'perfect-hand')->value('id');
    expect(UserAchievement::where('match_id', $match->id)->where('achievement_id', $achId)->count())
        ->toBe(1);
});

it('stacks Perfect Hand across separate matches for the same shooter', function () {
    // This is the key distinction from winning-hand: Perfect Hand is repeatable,
    // so the same user can earn it in multiple different matches over a season.
    $user = User::factory()->create();

    $match1 = ($this->makeRfMatch)();
    [, $g1] = ($this->addTargetSet)($match1, 400, 2);
    $squad1 = Squad::create(['match_id' => $match1->id, 'name' => 'Alpha']);
    $shooter1 = Shooter::create([
        'name' => 'Pete', 'squad_id' => $squad1->id,
        'status' => 'active', 'user_id' => $user->id,
    ]);
    foreach ($g1 as $gong) ($this->hit)($shooter1, $gong);
    AchievementService::evaluateRoyalFlushCompletion($match1);

    $match2 = ($this->makeRfMatch)();
    [, $g2] = ($this->addTargetSet)($match2, 500, 2);
    $squad2 = Squad::create(['match_id' => $match2->id, 'name' => 'Alpha']);
    $shooter2 = Shooter::create([
        'name' => 'Pete', 'squad_id' => $squad2->id,
        'status' => 'active', 'user_id' => $user->id,
    ]);
    foreach ($g2 as $gong) ($this->hit)($shooter2, $gong);
    AchievementService::evaluateRoyalFlushCompletion($match2);

    $achId = Achievement::where('slug', 'perfect-hand')->value('id');
    expect(UserAchievement::where('achievement_id', $achId)->where('user_id', $user->id)->count())
        ->toBe(2);
});
