<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\PrsShotScore;
use App\Models\PrsStageResult;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\TargetSet;
use App\Models\UserAchievement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AchievementService
{
    /**
     * Evaluate stage-scoped badges after a single PRS stage is scored.
     */
    public static function evaluateStageCompletion(
        ShootingMatch $match,
        TargetSet $stage,
        Shooter $shooter,
        PrsStageResult $result,
    ): array {
        if (! $shooter->user_id) {
            return [];
        }

        $awarded = [];
        $maxHits = $stage->total_shots ?? $stage->gongs()->count();

        // prs-full-send: all hits, zero not_taken
        if ($result->hits === $maxHits && $result->not_taken === 0 && $maxHits > 0) {
            $badge = self::awardBadge('prs-full-send', $shooter, $match, $stage);
            if ($badge) {
                $awarded[] = $badge;
                $awarded = array_merge($awarded, self::checkLifetime('first-full-send', 'prs-full-send', $shooter, $match));
            }
        }

        // no-drop-stage: max_hits - 1 hits, zero not_taken
        if ($result->hits === $maxHits - 1 && $result->not_taken === 0 && $maxHits > 1) {
            $badge = self::awardBadge('no-drop-stage', $shooter, $match, $stage);
            if ($badge) {
                $awarded[] = $badge;
            }
        }

        // impact-chain: 5+ consecutive hits on a single stage
        $chainLength = self::longestConsecutiveHits($match->id, $stage->id, $shooter->id);
        if ($chainLength >= 5) {
            $badge = self::awardBadge('impact-chain', $shooter, $match, $stage, ['streak' => $chainLength]);
            if ($badge) {
                $awarded[] = $badge;
                $awarded = array_merge($awarded, self::checkLifetime('first-impact-chain', 'impact-chain', $shooter, $match));
            }
        }

        // high-efficiency: >= 80% hit rate on shots actually taken
        $shotsTaken = $result->hits + $result->misses;
        if ($shotsTaken > 0 && ($result->hits / $shotsTaken) >= 0.80) {
            $badge = self::awardBadge('high-efficiency', $shooter, $match, $stage);
            if ($badge) {
                $awarded[] = $badge;
            }
        }

        return $awarded;
    }

    /**
     * Evaluate match-scoped badges after the match is finalized.
     * Call this when scores are published or match is marked completed.
     *
     * Runs for ALL match types:
     *   - Podium badges (gold/silver/bronze) — standard, RF, and PRS
     *   - PRS-specific badges (iron-shooter, complete-shooter, deadcenter) — PRS only
     */
    public static function evaluateMatchCompletion(ShootingMatch $match): array
    {
        $awarded = [];
        $stages = $match->targetSets()->get();

        if ($stages->isEmpty()) {
            return [];
        }

        $shooters = $match->shooters()
            ->where('shooters.status', 'active')
            ->whereNotNull('shooters.user_id')
            ->get();

        if ($shooters->isEmpty()) {
            return [];
        }

        // PRS-specific badges (iron-shooter, complete-shooter, deadcenter)
        if ($match->isPrs()) {
            $totalStages = $stages->count();

            $allResults = PrsStageResult::where('match_id', $match->id)
                ->get()
                ->groupBy('shooter_id');

            $maxHitsByStage = [];
            foreach ($stages as $s) {
                $maxHitsByStage[$s->id] = $s->total_shots ?? $s->gongs()->count();
            }

            foreach ($shooters as $shooter) {
                $results = $allResults->get($shooter->id, collect());
                if ($results->isEmpty()) {
                    continue;
                }

                $completedCount = $results->whereNotNull('completed_at')->count();
                if ($completedCount < $totalStages) {
                    continue;
                }

                // iron-shooter: every stage >= 80% hit rate
                $allAbove80 = true;
                foreach ($results as $r) {
                    $max = $maxHitsByStage[$r->stage_id] ?? 0;
                    if ($max === 0 || ($r->hits / $max) < 0.80) {
                        $allAbove80 = false;
                        break;
                    }
                }
                if ($allAbove80) {
                    $badge = self::awardBadge('iron-shooter', $shooter, $match);
                    if ($badge) {
                        $awarded[] = $badge;
                    }
                }

                // complete-shooter: zero not_taken across all stages, >= 75% overall
                $totalNotTaken = $results->sum('not_taken');
                $totalHits = $results->sum('hits');
                $totalMaxHits = array_sum($maxHitsByStage);
                if ($totalNotTaken === 0 && $totalMaxHits > 0 && ($totalHits / $totalMaxHits) >= 0.75) {
                    $badge = self::awardBadge('complete-shooter', $shooter, $match);
                    if ($badge) {
                        $awarded[] = $badge;
                    }
                }
            }

            // deadcenter: fastest clean run on compulsory tiebreaker
            $awarded = array_merge($awarded, self::evaluateDeadCenter($match, $stages, $allResults));
        }

        // Podium badges — runs for all match types, uses MatchStandingsService for correct weighted ranking
        $podiumIds = (new \App\Services\MatchStandingsService())->podiumShooterIds($match, 3);

        foreach ($podiumIds as $rank => $shooterId) {
            $shooter = $shooters->firstWhere('id', $shooterId);
            if (! $shooter || ! $shooter->user_id) {
                continue;
            }

            $slug = match ($rank) {
                1 => 'podium-gold',
                2 => 'podium-silver',
                3 => 'podium-bronze',
                default => null,
            };

            if (! $slug) {
                break;
            }

            if (! self::hasMatchBadge($slug, $shooter->user_id, $match->id)) {
                $badge = self::awardBadge($slug, $shooter, $match, null, ['rank' => $rank]);
                if ($badge) {
                    $awarded[] = $badge;

                    if ($rank === 1) {
                        $awarded = array_merge($awarded, self::checkLifetime('first-win', 'podium-gold', $shooter, $match));
                    }
                    if ($rank <= 3) {
                        $awarded = array_merge($awarded, self::checkLifetime('first-podium', 'podium-gold', $shooter, $match));
                    }
                }
            }
        }

        return $awarded;
    }

    /**
     * Evaluate Royal Flush badges after a standard match is finalized.
     */
    public static function evaluateRoyalFlushCompletion(ShootingMatch $match): array
    {
        if (! $match->royal_flush_enabled || $match->isPrs() || $match->isElr()) {
            return [];
        }

        $awarded = [];

        $targetSets = $match->targetSets()
            ->orderByDesc('distance_meters')
            ->with('gongs')
            ->get();

        if ($targetSets->isEmpty()) {
            return [];
        }

        $gongCountByTs = [];
        $allGongIds = [];
        foreach ($targetSets as $ts) {
            $gongCountByTs[$ts->id] = $ts->gongs->count();
            foreach ($ts->gongs as $gong) {
                $allGongIds[] = $gong->id;
            }
        }

        if (empty($allGongIds)) {
            return [];
        }

        $shooters = $match->shooters()
            ->where('shooters.status', 'active')
            ->whereNotNull('shooters.user_id')
            ->get();

        if ($shooters->isEmpty()) {
            return [];
        }

        $shooterIds = $shooters->pluck('id')->toArray();

        $hits = Score::whereIn('gong_id', $allGongIds)
            ->whereIn('shooter_id', $shooterIds)
            ->where('is_hit', true)
            ->select('shooter_id', 'gong_id')
            ->get();

        $gongToTs = [];
        foreach ($targetSets as $ts) {
            foreach ($ts->gongs as $gong) {
                $gongToTs[$gong->id] = $ts->id;
            }
        }

        $hitCountByShooterTs = [];
        $hitGongsByShooter = [];
        foreach ($hits as $hit) {
            $tsId = $gongToTs[$hit->gong_id] ?? null;
            if (! $tsId) {
                continue;
            }
            $hitCountByShooterTs[$hit->shooter_id][$tsId] = ($hitCountByShooterTs[$hit->shooter_id][$tsId] ?? 0) + 1;
            $hitGongsByShooter[$hit->shooter_id][] = $hit->gong_id;
        }

        $furthestTs = $targetSets->first();
        $smallGongAtFurthest = $furthestTs?->gongs->sortByDesc('multiplier')->first();

        foreach ($shooters as $shooter) {
            $flushDistances = [];

            foreach ($targetSets as $ts) {
                $hitsAtTs = $hitCountByShooterTs[$shooter->id][$ts->id] ?? 0;
                $needed = $gongCountByTs[$ts->id] ?? 0;

                if ($needed > 0 && $hitsAtTs >= $needed) {
                    $flushDistances[] = $ts->distance_meters;

                    $badge = self::awardBadge('royal-flush', $shooter, $match, $ts, [
                        'distance_meters' => $ts->distance_meters,
                        'target_set_label' => $ts->label,
                    ]);
                    if ($badge) {
                        $awarded[] = $badge;
                        $awarded = array_merge($awarded, self::checkLifetime('first-flush', 'royal-flush', $shooter, $match));
                    }

                    $distSlug = 'flush-' . (int) $ts->distance_meters;
                    $distBadge = self::awardBadge($distSlug, $shooter, $match, $ts, [
                        'distance_meters' => $ts->distance_meters,
                    ]);
                    if ($distBadge) {
                        $awarded[] = $distBadge;
                    }
                }
            }

            if (count($flushDistances) >= 2) {
                $badge = self::awardBadge('flush-collector', $shooter, $match, null, [
                    'flush_count' => count($flushDistances),
                    'distances' => $flushDistances,
                ]);
                if ($badge) {
                    $awarded[] = $badge;
                }
            }

            if ($smallGongAtFurthest && $furthestTs) {
                $shooterGongs = $hitGongsByShooter[$shooter->id] ?? [];
                if (in_array($smallGongAtFurthest->id, $shooterGongs)) {
                    $badge = self::awardBadge('small-gong-sniper', $shooter, $match, $furthestTs, [
                        'distance_meters' => $furthestTs->distance_meters,
                        'gong_label' => $smallGongAtFurthest->label,
                        'multiplier' => (float) $smallGongAtFurthest->multiplier,
                    ]);
                    if ($badge) {
                        $awarded[] = $badge;
                    }
                }
            }
        }

        // Royal Flush podium badges (top 3 by weighted total score, via MatchStandingsService)
        $podiumIds = (new \App\Services\MatchStandingsService())->podiumShooterIds($match, 3);
        foreach ($podiumIds as $rank => $shooterId) {
            $shooter = $shooters->firstWhere('id', $shooterId);
            if (! $shooter || ! $shooter->user_id) {
                continue;
            }

            $slug = match ($rank) {
                1 => 'rf-podium-gold',
                2 => 'rf-podium-silver',
                3 => 'rf-podium-bronze',
                default => null,
            };

            if ($slug && ! self::hasMatchBadge($slug, $shooter->user_id, $match->id)) {
                $badge = self::awardBadge($slug, $shooter, $match, null, ['rank' => $rank]);
                if ($badge) {
                    $awarded[] = $badge;
                }
            }
        }

        if ($match->side_bet_enabled) {
            $awarded = array_merge($awarded, self::evaluateWinningHand($match, $targetSets, $shooters, $hitGongsByShooter));
        }

        return $awarded;
    }

    /**
     * Award "Early Bird" to the first user to register for a PRS match.
     * Call after a MatchRegistration is created.
     */
    public static function evaluateEarlyBird(ShootingMatch $match, int $userId): ?UserAchievement
    {
        if (! $match->isPrs()) {
            return null;
        }

        $achievement = Achievement::bySlug('early-bird');
        if (! $achievement) {
            return null;
        }

        if (! $achievement->is_repeatable) {
            $alreadyHas = UserAchievement::where('user_id', $userId)
                ->where('achievement_id', $achievement->id)
                ->exists();
            if ($alreadyHas) {
                return null;
            }
        }

        $registrationCount = \App\Models\MatchRegistration::where('match_id', $match->id)->count();
        if ($registrationCount > 1) {
            return null;
        }

        $ua = UserAchievement::create([
            'user_id' => $userId,
            'achievement_id' => $achievement->id,
            'match_id' => $match->id,
            'metadata' => ['registered_at' => now()->toIso8601String()],
            'awarded_at' => now(),
        ]);

        Log::info('Achievement awarded: early-bird', [
            'user_id' => $userId,
            'match_id' => $match->id,
        ]);

        return $ua;
    }

    // ── Private helpers ──

    private static function evaluateWinningHand(
        ShootingMatch $match,
        $targetSets,
        $shooters,
        array $hitGongsByShooter,
    ): array {
        $achievement = Achievement::bySlug('winning-hand');
        if (! $achievement) {
            return [];
        }

        $exists = UserAchievement::where('achievement_id', $achievement->id)
            ->where('match_id', $match->id)
            ->exists();
        if ($exists) {
            return [];
        }

        $sideBetIds = DB::table('side_bet_shooters')
            ->where('match_id', $match->id)
            ->pluck('shooter_id')
            ->toArray();

        if (empty($sideBetIds)) {
            return [];
        }

        $gongRankMap = [];
        $maxRanks = 0;
        foreach ($targetSets as $ts) {
            $rank = 0;
            foreach ($ts->gongs->sortByDesc('multiplier') as $gong) {
                $gongRankMap[$gong->id] = [
                    'rank' => $rank,
                    'distance' => $ts->distance_meters,
                ];
                $rank++;
            }
            $maxRanks = max($maxRanks, $rank);
        }

        $totalScores = DB::table('scores')
            ->join('gongs', 'scores.gong_id', '=', 'gongs.id')
            ->join('target_sets', 'gongs.target_set_id', '=', 'target_sets.id')
            ->whereIn('scores.shooter_id', $sideBetIds)
            ->where('scores.is_hit', true)
            ->groupBy('scores.shooter_id')
            ->select('scores.shooter_id')
            ->selectRaw('COALESCE(SUM(COALESCE(target_sets.distance_multiplier, 1) * gongs.multiplier), 0) as total_score')
            ->pluck('total_score', 'scores.shooter_id')
            ->toArray();

        $profiles = [];
        foreach ($sideBetIds as $sid) {
            $shooter = $shooters->firstWhere('id', $sid);
            if (! $shooter || ! $shooter->user_id) {
                continue;
            }

            $profile = [
                'shooter_id' => $sid,
                'total_score' => round((float) ($totalScores[$sid] ?? 0), 2),
                'ranks' => [],
            ];

            for ($r = 0; $r < $maxRanks; $r++) {
                $profile['ranks'][$r] = ['count' => 0, 'distances' => []];
            }

            $gongIds = $hitGongsByShooter[$sid] ?? [];
            foreach ($gongIds as $gongId) {
                if (! isset($gongRankMap[$gongId])) {
                    continue;
                }
                $info = $gongRankMap[$gongId];
                $profile['ranks'][$info['rank']]['count']++;
                $profile['ranks'][$info['rank']]['distances'][] = $info['distance'];
            }

            for ($r = 0; $r < $maxRanks; $r++) {
                rsort($profile['ranks'][$r]['distances']);
            }

            $profiles[] = $profile;
        }

        if (count($profiles) < 1) {
            return [];
        }

        usort($profiles, function ($a, $b) use ($maxRanks) {
            for ($r = 0; $r < $maxRanks; $r++) {
                $aCount = $a['ranks'][$r]['count'];
                $bCount = $b['ranks'][$r]['count'];
                if ($aCount !== $bCount) {
                    return $bCount <=> $aCount;
                }
                $aDist = $a['ranks'][$r]['distances'];
                $bDist = $b['ranks'][$r]['distances'];
                $len = max(count($aDist), count($bDist));
                for ($i = 0; $i < $len; $i++) {
                    $ad = $aDist[$i] ?? 0;
                    $bd = $bDist[$i] ?? 0;
                    if ($ad !== $bd) {
                        return $bd <=> $ad;
                    }
                }
            }
            return $b['total_score'] <=> $a['total_score'];
        });

        if (count($profiles) >= 2) {
            $first = $profiles[0];
            $second = $profiles[1];
            $tied = true;
            for ($r = 0; $r < $maxRanks; $r++) {
                if ($first['ranks'][$r]['count'] !== $second['ranks'][$r]['count']) {
                    $tied = false;
                    break;
                }
                if ($first['ranks'][$r]['distances'] !== $second['ranks'][$r]['distances']) {
                    $tied = false;
                    break;
                }
            }
            if ($tied && $first['total_score'] === $second['total_score']) {
                return [];
            }
        }

        $winnerId = $profiles[0]['shooter_id'];
        $winner = $shooters->firstWhere('id', $winnerId);
        if (! $winner || ! $winner->user_id) {
            return [];
        }

        $ua = UserAchievement::create([
            'user_id' => $winner->user_id,
            'achievement_id' => $achievement->id,
            'match_id' => $match->id,
            'shooter_id' => $winner->id,
            'metadata' => [
                'small_gong_hits' => $profiles[0]['ranks'][0]['count'] ?? 0,
                'distances_hit' => $profiles[0]['ranks'][0]['distances'] ?? [],
            ],
            'awarded_at' => now(),
        ]);

        Log::info('Achievement awarded: winning-hand', [
            'user_id' => $winner->user_id,
            'match_id' => $match->id,
        ]);

        return [$ua];
    }

    private static function evaluateDeadCenter(ShootingMatch $match, $stages, $allResults): array
    {
        $tiebreaker = $stages->firstWhere('is_tiebreaker', true);
        if (! $tiebreaker) {
            return [];
        }

        $maxHits = $tiebreaker->total_shots ?? $tiebreaker->gongs()->count();
        if ($maxHits === 0) {
            return [];
        }

        $qualifying = [];

        foreach ($allResults as $shooterId => $results) {
            $tbResult = $results->firstWhere('stage_id', $tiebreaker->id);
            if (! $tbResult) {
                continue;
            }

            if ($tbResult->hits === $maxHits
                && $tbResult->not_taken === 0
                && $tbResult->raw_time_seconds !== null
                && (float) $tbResult->raw_time_seconds > 0
            ) {
                $qualifying[] = [
                    'shooter_id' => $shooterId,
                    'time' => (float) $tbResult->raw_time_seconds,
                ];
            }
        }

        if (empty($qualifying)) {
            return [];
        }

        usort($qualifying, fn ($a, $b) => $a['time'] <=> $b['time']);

        // Tie rule: if two fastest are tied, no award
        if (count($qualifying) >= 2 && $qualifying[0]['time'] === $qualifying[1]['time']) {
            return [];
        }

        $winnerId = $qualifying[0]['shooter_id'];
        $shooter = Shooter::with('user')->find($winnerId);
        if (! $shooter || ! $shooter->user_id) {
            return [];
        }

        $achievement = Achievement::bySlug('deadcenter');
        if (! $achievement) {
            return [];
        }

        $exists = UserAchievement::where('achievement_id', $achievement->id)
            ->where('match_id', $match->id)
            ->exists();

        if ($exists) {
            return [];
        }

        $ua = UserAchievement::create([
            'user_id' => $shooter->user_id,
            'achievement_id' => $achievement->id,
            'match_id' => $match->id,
            'stage_id' => $tiebreaker->id,
            'shooter_id' => $shooter->id,
            'metadata' => [
                'time' => $qualifying[0]['time'],
                'stage_label' => $tiebreaker->display_name,
            ],
            'awarded_at' => now(),
        ]);

        Log::info('Achievement awarded: deadcenter', [
            'user_id' => $shooter->user_id,
            'match_id' => $match->id,
            'time' => $qualifying[0]['time'],
        ]);

        return [$ua];
    }

    private static function awardBadge(
        string $slug,
        Shooter $shooter,
        ShootingMatch $match,
        ?TargetSet $stage = null,
        ?array $metadata = null,
    ): ?UserAchievement {
        if (! $shooter->user_id) {
            return null;
        }

        $achievement = Achievement::bySlug($slug);
        if (! $achievement) {
            return null;
        }

        if (! $achievement->is_repeatable) {
            $exists = UserAchievement::where('user_id', $shooter->user_id)
                ->where('achievement_id', $achievement->id)
                ->exists();
            if ($exists) {
                return null;
            }
        } elseif ($stage) {
            $exists = UserAchievement::where('user_id', $shooter->user_id)
                ->where('achievement_id', $achievement->id)
                ->where('match_id', $match->id)
                ->where('stage_id', $stage->id)
                ->exists();
            if ($exists) {
                return null;
            }
        } else {
            $exists = UserAchievement::where('user_id', $shooter->user_id)
                ->where('achievement_id', $achievement->id)
                ->where('match_id', $match->id)
                ->exists();
            if ($exists) {
                return null;
            }
        }

        $ua = UserAchievement::create([
            'user_id' => $shooter->user_id,
            'achievement_id' => $achievement->id,
            'match_id' => $match->id,
            'stage_id' => $stage?->id,
            'shooter_id' => $shooter->id,
            'metadata' => $metadata,
            'awarded_at' => now(),
        ]);

        Log::info("Achievement awarded: {$slug}", [
            'user_id' => $shooter->user_id,
            'match_id' => $match->id,
            'stage_id' => $stage?->id,
        ]);

        return $ua;
    }

    private static function checkLifetime(
        string $lifetimeSlug,
        string $triggerSlug,
        Shooter $shooter,
        ShootingMatch $match,
    ): array {
        if (! $shooter->user_id) {
            return [];
        }

        $achievement = Achievement::bySlug($lifetimeSlug);
        if (! $achievement) {
            return [];
        }

        $already = UserAchievement::where('user_id', $shooter->user_id)
            ->where('achievement_id', $achievement->id)
            ->exists();

        if ($already) {
            return [];
        }

        $ua = UserAchievement::create([
            'user_id' => $shooter->user_id,
            'achievement_id' => $achievement->id,
            'match_id' => $match->id,
            'shooter_id' => $shooter->id,
            'metadata' => ['triggered_by' => $triggerSlug],
            'awarded_at' => now(),
        ]);

        Log::info("Lifetime achievement awarded: {$lifetimeSlug}", [
            'user_id' => $shooter->user_id,
            'match_id' => $match->id,
        ]);

        return [$ua];
    }

    private static function hasMatchBadge(string $slug, int $userId, int $matchId): bool
    {
        $achievement = Achievement::bySlug($slug);
        if (! $achievement) {
            return false;
        }

        return UserAchievement::where('user_id', $userId)
            ->where('achievement_id', $achievement->id)
            ->where('match_id', $matchId)
            ->exists();
    }

    private static function longestConsecutiveHits(int $matchId, int $stageId, int $shooterId): int
    {
        $shots = PrsShotScore::where('match_id', $matchId)
            ->where('stage_id', $stageId)
            ->where('shooter_id', $shooterId)
            ->orderBy('shot_number')
            ->pluck('result');

        $max = 0;
        $current = 0;

        foreach ($shots as $result) {
            $value = $result instanceof \BackedEnum ? $result->value : (string) $result;
            if ($value === 'hit') {
                $current++;
                $max = max($max, $current);
            } else {
                $current = 0;
            }
        }

        return $max;
    }

    public static function buildPrsRankings(ShootingMatch $match, $allResults, $stages): array
    {
        $tiebreakerStage = $stages->firstWhere('is_tiebreaker', true);

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->where('shooters.status', 'active')
            ->pluck('shooters.id');

        $entries = [];
        foreach ($shooters as $shooterId) {
            $results = $allResults->get($shooterId, collect());
            $totalHits = $results->sum('hits');

            $tbHits = 0;
            $tbTime = PHP_FLOAT_MAX;
            if ($tiebreakerStage) {
                $tbResult = $results->firstWhere('stage_id', $tiebreakerStage->id);
                if ($tbResult) {
                    $tbHits = $tbResult->hits;
                    $tbTime = $tbResult->official_time_seconds ? (float) $tbResult->official_time_seconds : PHP_FLOAT_MAX;
                }
            }

            $aggTime = $results->whereNotNull('official_time_seconds')
                ->sum(fn ($r) => (float) $r->official_time_seconds);

            $entries[] = [
                'shooter_id' => $shooterId,
                'hits' => $totalHits,
                'tb_hits' => $tbHits,
                'tb_time' => $tbTime,
                'agg_time' => $aggTime,
            ];
        }

        usort($entries, function ($a, $b) {
            if ($a['hits'] !== $b['hits']) return $b['hits'] <=> $a['hits'];
            if ($a['tb_hits'] !== $b['tb_hits']) return $b['tb_hits'] <=> $a['tb_hits'];
            if ($a['tb_time'] !== $b['tb_time']) return $a['tb_time'] <=> $b['tb_time'];
            return $a['agg_time'] <=> $b['agg_time'];
        });

        $rankings = [];
        foreach ($entries as $rank => $entry) {
            $rankings[$rank + 1] = $entry['shooter_id'];
        }

        return $rankings;
    }
}
