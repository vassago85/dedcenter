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
 * Regression: PRS-discipline badges (podium-*, first-podium, first-win) must
 * never be awarded for non-PRS matches. A Royal Flush match should never make
 * the "PRS Badges" section light up on a shooter's profile.
 */

beforeEach(function () {
    // Ensure the seeder catalog is present so awardBadge() can find slugs.
    $this->seed(Database\Seeders\AchievementSeeder::class);

    $this->makeMatch = function (string $scoringType, bool $rf): ShootingMatch {
        $owner = User::factory()->create();
        return ShootingMatch::factory()->create([
            'created_by' => $owner->id,
            'scoring_type' => $scoringType,
            'royal_flush_enabled' => $rf,
        ]);
    };

    $this->addTargetSet = function (ShootingMatch $match, int $meters, float $distMult, int $gongCount) {
        $ts = TargetSet::create([
            'match_id' => $match->id,
            'label' => "{$meters}m",
            'distance_meters' => $meters,
            'distance_multiplier' => $distMult,
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

    $this->hit = function (Shooter $s, Gong $g, bool $hit = true): Score {
        return Score::create([
            'shooter_id' => $s->id,
            'gong_id' => $g->id,
            'is_hit' => $hit,
            'recorded_at' => now(),
        ]);
    };
});

it('does not award PRS podium badges on a Royal Flush match', function () {
    $match = ($this->makeMatch)('standard', true);
    [, $gongs] = ($this->addTargetSet)($match, 500, 5.0, 3);

    $users = User::factory()->count(3)->create();
    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);

    $top = Shooter::create(['name' => 'Top', 'squad_id' => $squad->id, 'status' => 'active', 'user_id' => $users[0]->id]);
    $mid = Shooter::create(['name' => 'Mid', 'squad_id' => $squad->id, 'status' => 'active', 'user_id' => $users[1]->id]);
    $low = Shooter::create(['name' => 'Low', 'squad_id' => $squad->id, 'status' => 'active', 'user_id' => $users[2]->id]);

    ($this->hit)($top, $gongs[0]); ($this->hit)($top, $gongs[1]); ($this->hit)($top, $gongs[2]);
    ($this->hit)($mid, $gongs[0]); ($this->hit)($mid, $gongs[1]);
    ($this->hit)($low, $gongs[0]);

    AchievementService::evaluateMatchCompletion($match);
    AchievementService::evaluateRoyalFlushCompletion($match);

    $prsSlugs = ['podium-gold', 'podium-silver', 'podium-bronze', 'first-podium', 'first-win'];
    $prsAchIds = Achievement::whereIn('slug', $prsSlugs)->pluck('id');

    expect(UserAchievement::where('match_id', $match->id)->whereIn('achievement_id', $prsAchIds)->count())
        ->toBe(0);

    $rfSlugs = ['rf-podium-gold', 'rf-podium-silver', 'rf-podium-bronze'];
    $rfAchIds = Achievement::whereIn('slug', $rfSlugs)->pluck('id');

    expect(UserAchievement::where('match_id', $match->id)->whereIn('achievement_id', $rfAchIds)->count())
        ->toBe(3);
});

it('still awards PRS podium badges on a genuine PRS match', function () {
    $match = ($this->makeMatch)('prs', false);
    [, $gongs] = ($this->addTargetSet)($match, 500, 5.0, 3);

    $users = User::factory()->count(3)->create();
    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Alpha']);

    $top = Shooter::create(['name' => 'Top', 'squad_id' => $squad->id, 'status' => 'active', 'user_id' => $users[0]->id]);
    $mid = Shooter::create(['name' => 'Mid', 'squad_id' => $squad->id, 'status' => 'active', 'user_id' => $users[1]->id]);
    $low = Shooter::create(['name' => 'Low', 'squad_id' => $squad->id, 'status' => 'active', 'user_id' => $users[2]->id]);

    ($this->hit)($top, $gongs[0]); ($this->hit)($top, $gongs[1]); ($this->hit)($top, $gongs[2]);
    ($this->hit)($mid, $gongs[0]); ($this->hit)($mid, $gongs[1]);
    ($this->hit)($low, $gongs[0]);

    AchievementService::evaluateMatchCompletion($match);

    $goldId = Achievement::where('slug', 'podium-gold')->value('id');
    $silverId = Achievement::where('slug', 'podium-silver')->value('id');
    $bronzeId = Achievement::where('slug', 'podium-bronze')->value('id');

    expect(UserAchievement::where('match_id', $match->id)->where('achievement_id', $goldId)->where('user_id', $users[0]->id)->exists())->toBeTrue()
        ->and(UserAchievement::where('match_id', $match->id)->where('achievement_id', $silverId)->where('user_id', $users[1]->id)->exists())->toBeTrue()
        ->and(UserAchievement::where('match_id', $match->id)->where('achievement_id', $bronzeId)->where('user_id', $users[2]->id)->exists())->toBeTrue();

    // The RF podium slugs should NOT exist for a PRS match.
    $rfAchIds = Achievement::whereIn('slug', ['rf-podium-gold', 'rf-podium-silver', 'rf-podium-bronze'])->pluck('id');
    expect(UserAchievement::where('match_id', $match->id)->whereIn('achievement_id', $rfAchIds)->count())->toBe(0);
});
