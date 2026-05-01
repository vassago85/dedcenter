<?php

namespace App\Console\Commands;

use App\Enums\MatchStatus;
use App\Models\Achievement;
use App\Models\ShootingMatch;
use App\Models\UserAchievement;
use App\Services\AchievementService;
use App\Services\MatchStandingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Re-evaluate podium (and Royal Flush podium) badges for completed matches.
 *
 * Idempotent:
 *  - Computes the CORRECT top-3 via MatchStandingsService (weighted formula).
 *  - Revokes any podium/rf-podium badges that do not match the correct top-3.
 *  - Awards missing correct badges via AchievementService.
 *
 * Usage:
 *    php artisan badges:reevaluate                  # every Completed match (podium diff only)
 *    php artisan badges:reevaluate 42               # just match #42 (podium diff only)
 *    php artisan badges:reevaluate --dry-run        # show diffs, don't mutate
 *    php artisan badges:reevaluate --full           # clean wipe+rebuild via AchievementService::reevaluateForMatch
 *                                                   #   (deeper — also rebuilds iron-shooter,
 *                                                   #    complete-shooter, deadcenter, stage repeatables)
 */
class ReevaluateBadges extends Command
{
    protected $signature = 'badges:reevaluate {match? : Optional match id. If omitted, all completed matches are re-evaluated.} {--dry-run : Log what would change without persisting} {--full : Full clean rebuild per match (wipes all match-scoped badges then re-awards).}';

    protected $description = 'Recompute podium/rf-podium badges for completed matches using the correct weighted standings. Revokes wrong awards and awards missing correct ones. Pass --full for a deeper clean-rebuild that also covers non-podium badges.';

    private const PODIUM_SLUGS = [
        1 => 'podium-gold',
        2 => 'podium-silver',
        3 => 'podium-bronze',
    ];

    private const RF_PODIUM_SLUGS = [
        1 => 'rf-podium-gold',
        2 => 'rf-podium-silver',
        3 => 'rf-podium-bronze',
    ];

    public function handle(): int
    {
        $matchArg = $this->argument('match');
        $dryRun = (bool) $this->option('dry-run');
        $full = (bool) $this->option('full');

        if ($full && $dryRun) {
            $this->warn('--dry-run is ignored when --full is used (clean rebuild runs inside a transaction and reports actuals).');
        }

        $query = ShootingMatch::query()->where('status', MatchStatus::Completed);
        if ($matchArg !== null) {
            $query->whereKey((int) $matchArg);
        }

        $matches = $query->get();

        if ($matches->isEmpty()) {
            $this->info($matchArg ? "No completed match found with id {$matchArg}." : 'No completed matches to process.');
            return self::SUCCESS;
        }

        $service = new MatchStandingsService();
        $totalRevoked = 0;
        $totalAwarded = 0;
        $totalKept = 0;

        foreach ($matches as $match) {
            $this->line("Match #{$match->id}: {$match->name}");

            if ($full) {
                try {
                    $result = AchievementService::reevaluateForMatch($match);
                    $totalRevoked += (int) $result['revoked'];
                    $totalAwarded += (int) $result['awarded'];
                    $this->line(sprintf('  revoked=%d  awarded=%d  [full rebuild]', $result['revoked'], $result['awarded']));
                } catch (\Throwable $e) {
                    $this->error("  failed: {$e->getMessage()}");
                    Log::warning('badges:reevaluate --full failed', [
                        'match_id' => $match->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                continue;
            }

            $result = $this->reevaluateMatch($match, $service, $dryRun);

            $totalRevoked += $result['revoked'];
            $totalAwarded += $result['awarded'];
            $totalKept += $result['kept'];

            $this->line(sprintf(
                '  revoked=%d  awarded=%d  kept=%d',
                $result['revoked'],
                $result['awarded'],
                $result['kept']
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            '%s complete. revoked=%d awarded=%d kept=%d across %d match(es).',
            $dryRun ? 'Dry run' : 'Re-evaluation',
            $totalRevoked,
            $totalAwarded,
            $totalKept,
            $matches->count()
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{revoked: int, awarded: int, kept: int}
     */
    private function reevaluateMatch(ShootingMatch $match, MatchStandingsService $service, bool $dryRun): array
    {
        $podiumIds = $service->podiumShooterIds($match, 3);
        $rankMap = $this->asUserRankMap($match, $podiumIds);

        // Discipline-gated correctness:
        //   - PRS podium (podium-gold/silver/bronze) is only correct on PRS matches.
        //   - RF podium  (rf-podium-*) is only correct on Royal Flush matches.
        // Any PRS podium sitting on a RF match is a bug and will be revoked; same
        // in the other direction.
        $correctStandard = $match->isPrs() ? $rankMap : [];
        $correctRf = $match->royal_flush_enabled ? $rankMap : [];

        $stats = DB::transaction(function () use ($match, $correctStandard, $correctRf, $dryRun) {
            $revoked = 0;
            $kept = 0;

            $revoked += $this->revokeMismatched($match, self::PODIUM_SLUGS, $correctStandard, $dryRun, $kept);
            $revoked += $this->revokeMismatched($match, self::RF_PODIUM_SLUGS, $correctRf, $dryRun, $kept);

            // If this match is NOT PRS, any first-podium / first-win lifetime badges
            // stamped with this match_id are spurious (they were triggered by the
            // old "PRS podium awarded on RF matches" bug). Revoke them so they can
            // re-fire later on a real PRS podium for the same user.
            if (! $match->isPrs()) {
                $revoked += $this->revokeSpuriousPrsLifetime($match, $dryRun);
            }

            return ['revoked' => $revoked, 'kept' => $kept];
        });

        $awarded = 0;
        if (! $dryRun) {
            try {
                AchievementService::evaluateMatchCompletion($match);
                if ($match->royal_flush_enabled) {
                    AchievementService::evaluateRoyalFlushCompletion($match);
                }
            } catch (\Throwable $e) {
                Log::warning('badges:reevaluate: evaluator threw', [
                    'match_id' => $match->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Tally correct badges that now exist (standard + RF podium).
            $awarded = $this->countCorrectBadges($match, $correctStandard)
                + $this->countCorrectBadges($match, $correctRf, true);
        } else {
            // In dry-run, project "would be awarded" = the correct map size (not yet held).
            $awarded = $this->countMissingCorrectBadges($match, self::PODIUM_SLUGS, $correctStandard)
                + $this->countMissingCorrectBadges($match, self::RF_PODIUM_SLUGS, $correctRf);
        }

        return [
            'revoked' => $stats['revoked'],
            'awarded' => $awarded,
            'kept' => $stats['kept'],
        ];
    }

    /**
     * @param  array<int, int>  $podiumShooterIds  1 => shooterId, ...
     * @return array<int, array{user_id: int, shooter_id: int}>  rank => { user_id, shooter_id }
     */
    private function asUserRankMap(ShootingMatch $match, array $podiumShooterIds): array
    {
        if (empty($podiumShooterIds)) {
            return [];
        }

        $shooterToUser = DB::table('shooters')
            ->whereIn('id', array_values($podiumShooterIds))
            ->pluck('user_id', 'id')
            ->all();

        $map = [];
        foreach ($podiumShooterIds as $rank => $shooterId) {
            $userId = $shooterToUser[$shooterId] ?? null;
            if ($userId === null) {
                continue;
            }
            $map[$rank] = [
                'user_id' => (int) $userId,
                'shooter_id' => (int) $shooterId,
            ];
        }

        return $map;
    }

    /**
     * Revoke badges that do not match the correct rank → (user, shooter) mapping.
     *
     * @param  array<int, string>  $slugs  rank => slug
     * @param  array<int, array{user_id: int, shooter_id: int}>  $correct
     */
    private function revokeMismatched(ShootingMatch $match, array $slugs, array $correct, bool $dryRun, int &$kept): int
    {
        $revoked = 0;

        $achievementIds = Achievement::whereIn('slug', array_values($slugs))->pluck('id', 'slug')->all();
        if (empty($achievementIds)) {
            return 0;
        }

        $held = UserAchievement::where('match_id', $match->id)
            ->whereIn('achievement_id', array_values($achievementIds))
            ->get();

        $slugByAchievementId = array_flip($achievementIds);

        foreach ($held as $ua) {
            $slug = $slugByAchievementId[$ua->achievement_id] ?? null;
            if (! $slug) {
                continue;
            }
            $rank = array_search($slug, $slugs, true);
            if ($rank === false) {
                continue;
            }

            $expected = $correct[$rank] ?? null;
            $isCorrect = $expected !== null
                && (int) $expected['user_id'] === (int) $ua->user_id
                && (
                    $ua->shooter_id === null
                    || (int) $ua->shooter_id === (int) $expected['shooter_id']
                );

            if ($isCorrect) {
                $kept++;
                continue;
            }

            $this->line(sprintf(
                '  - would revoke %s from user #%d (match %d)%s',
                $slug,
                $ua->user_id,
                $match->id,
                $dryRun ? ' [dry-run]' : ''
            ));

            if (! $dryRun) {
                $ua->delete();
            }
            $revoked++;
        }

        return $revoked;
    }

    /**
     * Revoke PRS-discipline lifetime badges (first-podium, first-win) whose
     * triggering match_id is this non-PRS match. These were created by the old
     * bug where PRS podium was awarded for every match type — once the PRS
     * podium row is revoked, the lifetime-badge row must go with it so the
     * next genuine PRS podium re-triggers the lifetime correctly.
     */
    private function revokeSpuriousPrsLifetime(ShootingMatch $match, bool $dryRun): int
    {
        $slugs = ['first-podium', 'first-win'];
        $achievementIds = Achievement::whereIn('slug', $slugs)->pluck('id', 'slug')->all();
        if (empty($achievementIds)) {
            return 0;
        }

        $rows = UserAchievement::where('match_id', $match->id)
            ->whereIn('achievement_id', array_values($achievementIds))
            ->get();

        $revoked = 0;
        $slugByAchievementId = array_flip($achievementIds);

        foreach ($rows as $ua) {
            $slug = $slugByAchievementId[$ua->achievement_id] ?? 'unknown';
            $this->line(sprintf(
                '  - would revoke spurious %s from user #%d (non-PRS match %d)%s',
                $slug,
                $ua->user_id,
                $match->id,
                $dryRun ? ' [dry-run]' : ''
            ));
            if (! $dryRun) {
                $ua->delete();
            }
            $revoked++;
        }

        return $revoked;
    }

    /**
     * @param  array<int, array{user_id: int, shooter_id: int}>  $correct
     */
    private function countCorrectBadges(ShootingMatch $match, array $correct, bool $rf = false): int
    {
        if (empty($correct)) {
            return 0;
        }

        $slugs = $rf ? self::RF_PODIUM_SLUGS : self::PODIUM_SLUGS;
        $achievementIds = Achievement::whereIn('slug', array_values($slugs))->pluck('id', 'slug')->all();

        $count = 0;
        foreach ($correct as $rank => $entry) {
            $slug = $slugs[$rank] ?? null;
            if (! $slug || ! isset($achievementIds[$slug])) {
                continue;
            }
            $exists = UserAchievement::where('match_id', $match->id)
                ->where('achievement_id', $achievementIds[$slug])
                ->where('user_id', $entry['user_id'])
                ->exists();
            if ($exists) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param  array<int, string>  $slugs
     * @param  array<int, array{user_id: int, shooter_id: int}>  $correct
     */
    private function countMissingCorrectBadges(ShootingMatch $match, array $slugs, array $correct): int
    {
        if (empty($correct)) {
            return 0;
        }

        $achievementIds = Achievement::whereIn('slug', array_values($slugs))->pluck('id', 'slug')->all();

        $count = 0;
        foreach ($correct as $rank => $entry) {
            $slug = $slugs[$rank] ?? null;
            if (! $slug || ! isset($achievementIds[$slug])) {
                continue;
            }
            $exists = UserAchievement::where('match_id', $match->id)
                ->where('achievement_id', $achievementIds[$slug])
                ->where('user_id', $entry['user_id'])
                ->exists();
            if (! $exists) {
                $count++;
            }
        }
        return $count;
    }
}
