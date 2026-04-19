<?php

namespace App\Services;

use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use Illuminate\Support\Collection;

/**
 * Computes "is the next shot at this distance the Royal Flush shot?"
 *
 * A shot is a Royal-Flush candidate when ALL of the following are true:
 *   - the match has royal_flush_enabled = true
 *   - the match is NOT PRS and NOT ELR (Royal Flush is standard-match only,
 *     matching AchievementService::evaluateRoyalFlushCompletion())
 *   - the shooter has hit every gong recorded so far at a target_set (e.g. 4/4
 *     hits + 0 misses at a 5-gong distance) and still has at least one gong
 *     left to shoot at that distance
 *
 * We surface this server-side so the mobile scoring app (and the forthcoming
 * web scoring pad) can render a "ROYAL FLUSH SHOT" banner over the
 * hit/miss buttons before the RO taps the 5th gong at that distance. No
 * acknowledgement / confirm step is needed — just a visible cue so the RO
 * knows what's riding on the next tap.
 *
 * Data shape per shooter:
 *   [
 *     'shooter_id'     => 42,
 *     'royal_flush_shot' => true,                        // convenience: any distance armed?
 *     'armed_target_set_ids' => [17, 21],                // target_sets where the very next
 *                                                        // shot would be the RF shot
 *     'distances' => [
 *       [
 *         'target_set_id'   => 17,
 *         'distance_meters' => 500,
 *         'label'           => '500m',
 *         'hits'            => 4,
 *         'misses'          => 0,
 *         'unshot'          => 1,
 *         'gong_count'      => 5,
 *         'armed'           => true,    // ← the banner trigger
 *         'flushed'         => false,   // 5/5 already achieved — banner off, celebration on
 *       ],
 *       // ...one entry per target_set in the match
 *     ],
 *   ]
 */
class RoyalFlushShotStatusService
{
    /**
     * @return array<string, mixed> shooter-centric status payload (see class docblock for shape)
     */
    public function forShooter(ShootingMatch $match, Shooter $shooter): array
    {
        return $this->forShooters($match, collect([$shooter]))
            ->first() ?? $this->emptyStatus($shooter, false);
    }

    /**
     * Bulk variant — one round-trip for a whole squad / the shooters touched
     * by a score-submission batch. Always returns one entry per passed-in
     * shooter, even when RF is disabled on the match, so callers can assume
     * a stable shape.
     *
     * @param  iterable<Shooter>  $shooters
     * @return Collection<int, array<string, mixed>>
     */
    public function forShooters(ShootingMatch $match, iterable $shooters): Collection
    {
        $shooterCollection = collect($shooters);

        if ($shooterCollection->isEmpty()) {
            return collect();
        }

        $rfEnabled = (bool) $match->royal_flush_enabled
            && ! $match->isPrs()
            && ! $match->isElr();

        if (! $rfEnabled) {
            // RF disabled — return flat "not armed" status for every shooter
            // so the mobile app can treat the response uniformly.
            return $shooterCollection
                ->map(fn (Shooter $s) => $this->emptyStatus($s, false))
                ->values();
        }

        $targetSets = $match->targetSets()
            ->with(['gongs' => fn ($q) => $q->orderBy('number')])
            ->orderBy('sort_order')
            ->get();

        if ($targetSets->isEmpty()) {
            return $shooterCollection
                ->map(fn (Shooter $s) => $this->emptyStatus($s, true))
                ->values();
        }

        // Build a gong_id → [target_set_id, is_last_gong] lookup once so we
        // can classify hits/misses per target_set in a single pass.
        $gongToTs = [];
        $gongCountByTs = [];
        $allGongIds = [];
        foreach ($targetSets as $ts) {
            $gongCountByTs[$ts->id] = $ts->gongs->count();
            foreach ($ts->gongs as $gong) {
                $gongToTs[$gong->id] = $ts->id;
                $allGongIds[] = $gong->id;
            }
        }

        $shooterIds = $shooterCollection->pluck('id')->all();

        // Pull every recorded score for these shooters at gongs in the match.
        // is_hit=true → counted as a hit; is_hit=false → a miss (kills the RF
        // for that distance). An un-scored gong is a pending shot.
        $scoreRows = Score::whereIn('shooter_id', $shooterIds)
            ->whereIn('gong_id', $allGongIds)
            ->select('shooter_id', 'gong_id', 'is_hit')
            ->get();

        // shooter_id → ts_id → ['hits' => int, 'misses' => int]
        $counts = [];
        foreach ($scoreRows as $row) {
            $tsId = $gongToTs[$row->gong_id] ?? null;
            if (! $tsId) {
                continue;
            }
            $counts[$row->shooter_id][$tsId]['hits']
                = ($counts[$row->shooter_id][$tsId]['hits'] ?? 0) + ($row->is_hit ? 1 : 0);
            $counts[$row->shooter_id][$tsId]['misses']
                = ($counts[$row->shooter_id][$tsId]['misses'] ?? 0) + ($row->is_hit ? 0 : 1);
        }

        return $shooterCollection->map(function (Shooter $shooter) use ($targetSets, $gongCountByTs, $counts) {
            $distances = [];
            $armedIds = [];
            $anyArmed = false;

            foreach ($targetSets as $ts) {
                $hits = $counts[$shooter->id][$ts->id]['hits'] ?? 0;
                $misses = $counts[$shooter->id][$ts->id]['misses'] ?? 0;
                $gongCount = $gongCountByTs[$ts->id] ?? 0;
                $unshot = max(0, $gongCount - $hits - $misses);

                $flushed = $gongCount > 0 && $hits >= $gongCount;

                // Armed when the NEXT shot is the 5th-of-5 (or nth-of-n):
                //   - no misses yet at this distance
                //   - exactly one gong left to shoot (so this tap is the
                //     "flush or break" moment)
                //   - all earlier gongs at the distance are hits
                // A pristine distance (0 hits / 0 misses) is NOT armed — the
                // banner fires only on the very last shot, matching the
                // user's spec: "4 out of 4 hits and scoring the 5th shot".
                $armed = $gongCount > 1
                    && $misses === 0
                    && $unshot === 1
                    && $hits === ($gongCount - 1);

                if ($armed) {
                    $armedIds[] = $ts->id;
                    $anyArmed = true;
                }

                $distances[] = [
                    'target_set_id' => $ts->id,
                    'distance_meters' => $ts->distance_meters,
                    'label' => $ts->label,
                    'hits' => $hits,
                    'misses' => $misses,
                    'unshot' => $unshot,
                    'gong_count' => $gongCount,
                    'armed' => $armed,
                    'flushed' => $flushed,
                ];
            }

            return [
                'shooter_id' => $shooter->id,
                'royal_flush_shot' => $anyArmed,
                'armed_target_set_ids' => $armedIds,
                'distances' => $distances,
            ];
        })->values();
    }

    /**
     * Uniform "nothing armed" payload — used when RF is disabled on the
     * match or no target sets exist yet — so clients always receive the
     * same shape.
     */
    private function emptyStatus(Shooter $shooter, bool $matchHasTargets): array
    {
        return [
            'shooter_id' => $shooter->id,
            'royal_flush_shot' => false,
            'armed_target_set_ids' => [],
            'distances' => [],
        ];
    }
}
