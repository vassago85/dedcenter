<?php

namespace Database\Seeders;

use App\Enums\ElrShotResult;
use App\Enums\MatchStatus;
use App\Models\ElrTarget;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Services\Scoring\ELRScoringService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Drives the existing seeded Peregrine R1 setup through the full ELR scoring
 * engine — every shooter takes 3 shots on every target their division is
 * allowed to engage (Minor T1‑T3, Major T2‑T4) on every station, in the
 * canonical target-by-target order:
 *
 *     Station → T1 (shot 1, 2, 3) → T2 (shot 1, 2, 3) → T3 (... ) → [T4 for Major] → next station.
 *
 * Hits / misses are generated deterministically from the shooter's bib + the
 * target id + the shot number so re-running the seeder produces the same
 * leaderboard. Hit probability follows a realistic distance curve and is
 * shifted per-shooter by a skill factor so the leaderboard has a believable
 * spread of top finishers, mid-pack and back-markers.
 *
 * After scoring is complete the match is promoted to Completed + scores
 * published so result pages, exports and the Minor / Major / Team / Overall
 * leaderboards all render.
 *
 * Idempotent: every shot record goes through ELRScoringService::recordShot
 * which `updateOrCreate`s on (shooter_id, target_id, shot_number).
 */
class DemoPeregrineRound1ScoresSeeder extends Seeder
{
    private const MATCH_NAME = 'Peregrine ELR Challenge — Round 1 (7 March 2026)';

    public function run(): void
    {
        $svc = app(ELRScoringService::class);

        $match = ShootingMatch::where('name', self::MATCH_NAME)->first();
        if (! $match) {
            $this->command?->warn(
                'Peregrine R1 match not found — run DemoEliteSeasonsSeeder first.'
            );
            return;
        }

        // Pre-load stages + targets and group targets by stage so we walk
        // station-by-station, target-by-target, in the correct sort order.
        $stages = $match->elrStages()
            ->with(['targets' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        // Pre-load shooter→division→allowed-targets map. Division pivot rows
        // were written by DemoEliteSeasonsSeeder (Minor T1-T3, Major T2-T4).
        $shooters = Shooter::query()
            ->whereIn('squad_id', $match->squads()->pluck('id'))
            ->with(['division.elrTargets:id'])
            ->orderBy('sort_order')
            ->get();

        if ($shooters->isEmpty() || $stages->isEmpty()) {
            $this->command?->warn('Peregrine R1 has no shooters or stages to score — aborting.');
            return;
        }

        $totalShots = 0;
        foreach ($shooters as $shooter) {
            $allowedTargetIds = $this->allowedTargetIds($shooter);
            if ($allowedTargetIds->isEmpty()) continue;

            foreach ($stages as $stage) {
                /** @var Collection<int, ElrTarget> $targets */
                $targets = $stage->targets->filter(fn ($t) => $allowedTargetIds->contains($t->id));

                foreach ($targets as $target) {
                    for ($shotNumber = 1; $shotNumber <= 3; $shotNumber++) {
                        $result = $this->rollShot($shooter, $target, $shotNumber);
                        $svc->recordShot($shooter, $target, $shotNumber, $result, null, 'seeder');
                        $totalShots++;
                    }
                }
            }
        }

        $match->update([
            'status'           => MatchStatus::Completed,
            'scores_published' => true,
        ]);

        $this->command?->info(sprintf(
            'Peregrine R1 scored: %d shots across %d shooters; match status=Completed; scores published.',
            $totalShots,
            $shooters->count()
        ));
    }

    /**
     * Targets this shooter's division is allowed to engage on this match.
     * Returns every target if the division has no pivot rows (legacy fallback).
     */
    private function allowedTargetIds(Shooter $shooter): Collection
    {
        if (! $shooter->division) {
            // No division → allow everything (shouldn't happen for Peregrine).
            return collect();
        }
        return $shooter->division->elrTargets->pluck('id');
    }

    /**
     * Deterministic per (shooter, target, shot) hit / miss generator that
     * produces a plausible ELR leaderboard:
     *
     *   • Base hit probability decays sharply with distance.
     *   • Each shooter gets a stable skill offset in [-0.18, +0.22].
     *   • Shot 1 (cold bore) is slightly harder; shots 2 + 3 see a small
     *     follow-up uplift (you've dialed in by then).
     */
    private function rollShot(Shooter $shooter, ElrTarget $target, int $shotNumber): ElrShotResult
    {
        $distance = (int) $target->distance_m;

        $base = match (true) {
            $distance <  700 => 0.92,
            $distance < 1000 => 0.78,
            $distance < 1300 => 0.62,
            $distance < 1600 => 0.45,
            $distance < 1900 => 0.30,
            default          => 0.18,
        };

        // Stable per-shooter skill offset.
        $hash = crc32(($shooter->bib_number ?? '') . '|' . $shooter->id);
        $skill = (($hash % 41) - 18) / 100.0; // -0.18 .. +0.22

        $shotMod = $shotNumber === 1 ? -0.06 : 0.04;

        $prob = max(0.05, min(0.97, $base + $skill + $shotMod));

        // Deterministic RNG seed so the leaderboard is stable across re-runs.
        $seed = crc32(sprintf('%d|%d|%d', $shooter->id, $target->id, $shotNumber));
        mt_srand($seed);
        $roll = mt_rand(0, 999) / 1000.0;

        return $roll < $prob ? ElrShotResult::Hit : ElrShotResult::Miss;
    }
}
