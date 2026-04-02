<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\PrsShotScore;
use App\Models\PrsStageResult;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\TargetSet;
use App\Models\UserAchievement;
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
            $badge = self::awardRepeatable('prs-full-send', $shooter, $match, $stage);
            if ($badge) {
                $awarded[] = $badge;
                $awarded = array_merge($awarded, self::checkLifetime('first-full-send', 'prs-full-send', $shooter, $match));
            }
        }

        // no-drop-stage: max_hits - 1 hits, zero not_taken
        if ($result->hits === $maxHits - 1 && $result->not_taken === 0 && $maxHits > 1) {
            $badge = self::awardRepeatable('no-drop-stage', $shooter, $match, $stage);
            if ($badge) {
                $awarded[] = $badge;
            }
        }

        // impact-chain: 5+ consecutive hits on a single stage
        $chainLength = self::longestConsecutiveHits($match->id, $stage->id, $shooter->id);
        if ($chainLength >= 5) {
            $badge = self::awardRepeatable('impact-chain', $shooter, $match, $stage, ['streak' => $chainLength]);
            if ($badge) {
                $awarded[] = $badge;
                $awarded = array_merge($awarded, self::checkLifetime('first-impact-chain', 'impact-chain', $shooter, $match));
            }
        }

        // high-efficiency: >= 90% hit rate on shots actually taken
        $shotsTaken = $result->hits + $result->misses;
        if ($shotsTaken > 0 && ($result->hits / $shotsTaken) >= 0.90) {
            $badge = self::awardRepeatable('high-efficiency', $shooter, $match, $stage);
            if ($badge) {
                $awarded[] = $badge;
            }
        }

        // first-blood: first shooter to complete ALL stages in the match
        $totalStages = $match->targetSets()->count();
        $completedStages = PrsStageResult::where('match_id', $match->id)
            ->where('shooter_id', $shooter->id)
            ->whereNotNull('completed_at')
            ->count();

        if ($completedStages === $totalStages && $totalStages > 0) {
            $anyoneElseComplete = PrsStageResult::where('match_id', $match->id)
                ->where('shooter_id', '!=', $shooter->id)
                ->whereNotNull('completed_at')
                ->select('shooter_id')
                ->groupBy('shooter_id')
                ->havingRaw('COUNT(DISTINCT stage_id) = ?', [$totalStages])
                ->exists();

            if (! $anyoneElseComplete) {
                $existing = self::hasMatchBadge('first-blood', $shooter->user_id, $match->id);
                if (! $existing) {
                    $badge = self::awardRepeatable('first-blood', $shooter, $match);
                    if ($badge) {
                        $awarded[] = $badge;
                    }
                }
            }
        }

        return $awarded;
    }

    /**
     * Evaluate match-scoped badges after the match is finalized.
     * Call this when scores are published or match is marked completed.
     */
    public static function evaluateMatchCompletion(ShootingMatch $match): array
    {
        if (! $match->isPrs()) {
            return [];
        }

        $awarded = [];
        $stages = $match->targetSets()->get();
        $totalStages = $stages->count();

        if ($totalStages === 0) {
            return [];
        }

        $shooters = $match->shooters()
            ->where('shooters.status', 'active')
            ->whereNotNull('shooters.user_id')
            ->get();

        $allResults = PrsStageResult::where('match_id', $match->id)
            ->get()
            ->groupBy('shooter_id');

        $maxHitsByStage = [];
        foreach ($stages as $s) {
            $maxHitsByStage[$s->id] = $s->total_shots ?? $s->gongs()->count();
        }

        // Build rankings (same logic as prsScoreboardNew)
        $rankings = self::buildPrsRankings($match, $allResults, $stages);

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
                $badge = self::awardRepeatable('iron-shooter', $shooter, $match);
                if ($badge) {
                    $awarded[] = $badge;
                }
            }

            // complete-shooter: zero not_taken across all stages, >= 75% overall
            $totalNotTaken = $results->sum('not_taken');
            $totalHits = $results->sum('hits');
            $totalMaxHits = array_sum($maxHitsByStage);
            if ($totalNotTaken === 0 && $totalMaxHits > 0 && ($totalHits / $totalMaxHits) >= 0.75) {
                $badge = self::awardRepeatable('complete-shooter', $shooter, $match);
                if ($badge) {
                    $awarded[] = $badge;
                }
            }
        }

        // podium badges
        foreach ($rankings as $rank => $shooterId) {
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
                $badge = self::awardRepeatable($slug, $shooter, $match, null, ['rank' => $rank]);
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

        // deadcenter: fastest clean run on compulsory tiebreaker
        $awarded = array_merge($awarded, self::evaluateDeadCenter($match, $stages, $allResults));

        return $awarded;
    }

    // ── Private helpers ──

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

    private static function awardRepeatable(
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

        // For repeatable stage badges, prevent duplicate for same shooter+match+stage
        if ($stage && $achievement->is_repeatable) {
            $exists = UserAchievement::where('user_id', $shooter->user_id)
                ->where('achievement_id', $achievement->id)
                ->where('match_id', $match->id)
                ->where('stage_id', $stage->id)
                ->exists();
            if ($exists) {
                return null;
            }
        }

        // For repeatable match badges, prevent duplicate for same shooter+match
        if (! $stage && $achievement->is_repeatable) {
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

    private static function buildPrsRankings(ShootingMatch $match, $allResults, $stages): array
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
