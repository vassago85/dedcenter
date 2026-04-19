<?php

namespace App\Services;

use App\Models\ShootingMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Royal Flush highlights for a single match.
 *
 * Reports two kinds of "sweep" achievements at a glance:
 *
 *   1. Royal Flush by distance — a shooter hit every gong at one target
 *      set (distance). Returns per-distance counts and the names of the
 *      shooters who pulled each one off.
 *
 *   2. Perfect Hand — a shooter flushed EVERY distance in the match. Far
 *      rarer; highlighted separately so MDs can celebrate it.
 *
 * Only meaningful for matches with `royal_flush_enabled = true`. For
 * anything else, callers should short-circuit before calling build().
 *
 * Single-source-of-truth shared between:
 *   - the org/admin Match Hub "Royal Flush Highlights" panel
 *   - the Full Match Report PDF (pdf-executive-summary.blade.php)
 *
 * The service intentionally does NOT re-rank standings — it only
 * answers "who hit everything at X?". Ranking logic still lives in
 * MatchStandingsService and SideBetStandingsService.
 */
class RoyalFlushHighlightsService
{
    /**
     * @return array{
     *     flushes_by_distance: array<int, int>,
     *     shooters_by_distance: array<int, list<string>>,
     *     distance_labels: array<int, string>,
     *     total_flushes: int,
     *     perfect_hand_shooters: list<string>,
     *     has_any: bool,
     * }
     */
    public function build(ShootingMatch $match): array
    {
        $empty = [
            'flushes_by_distance' => [],
            'shooters_by_distance' => [],
            'distance_labels' => [],
            'total_flushes' => 0,
            'perfect_hand_shooters' => [],
            'has_any' => false,
        ];

        if (! ($match->royal_flush_enabled ?? false)) {
            return $empty;
        }

        // Pull every target set + its gong count so we know what "full sweep"
        // means at each distance. Ordered by sort_order so downstream output
        // matches the scoreboard / PDF column order.
        $targetSets = $match->targetSets()
            ->withCount('gongs')
            ->orderBy('sort_order')
            ->get();

        if ($targetSets->isEmpty()) {
            return $empty;
        }

        // Only count distances that actually have gongs — an empty target
        // set is a config stub, not something a shooter can flush.
        $targetSets = $targetSets->filter(fn ($ts) => ($ts->gongs_count ?? 0) > 0)->values();
        if ($targetSets->isEmpty()) {
            return $empty;
        }

        // Active shooters only — no-shows / DQs never "flushed" anything
        // regardless of what zero scores they may have on record.
        $shooters = $match->shooters()
            ->where('shooters.status', 'active')
            ->select('shooters.id', 'shooters.name')
            ->get();

        if ($shooters->isEmpty()) {
            return $empty;
        }

        // One pooled query for hit counts — avoids N queries (one per
        // target set per shooter) on bigger matches. We ask the DB for
        // (shooter_id, target_set_id) → hit_count rather than loading the
        // raw Score rows client-side.
        $shooterIds = $shooters->pluck('id')->all();
        $targetSetIds = $targetSets->pluck('id')->all();

        $hitCounts = DB::table('scores')
            ->join('gongs', 'scores.gong_id', '=', 'gongs.id')
            ->whereIn('scores.shooter_id', $shooterIds)
            ->whereIn('gongs.target_set_id', $targetSetIds)
            ->where('scores.is_hit', true)
            ->select('scores.shooter_id', 'gongs.target_set_id')
            ->selectRaw('COUNT(*) as hit_count')
            ->groupBy('scores.shooter_id', 'gongs.target_set_id')
            ->get()
            ->keyBy(fn ($r) => $r->shooter_id.'_'.$r->target_set_id);

        $flushesByDistance = [];
        $shootersByDistance = [];
        $distanceLabels = [];
        $perfectHandShooters = [];

        foreach ($targetSets as $ts) {
            $distM = (int) $ts->distance_meters;
            $flushesByDistance[$distM] = 0;
            $shootersByDistance[$distM] = [];
            $distanceLabels[$distM] = $ts->label ?: ($distM.'m');
        }

        $totalDistances = $targetSets->count();

        foreach ($shooters as $shooter) {
            $flushesThisShooter = 0;

            foreach ($targetSets as $ts) {
                $needed = (int) $ts->gongs_count;
                $key = $shooter->id.'_'.$ts->id;
                $hits = (int) ($hitCounts[$key]->hit_count ?? 0);

                if ($hits >= $needed) {
                    $distM = (int) $ts->distance_meters;
                    $flushesByDistance[$distM]++;
                    $shootersByDistance[$distM][] = $this->displayName($shooter->name);
                    $flushesThisShooter++;
                }
            }

            // Perfect Hand = flushed every distance in the match. We only
            // count shooters who flushed ALL distances (not just every one
            // they attempted) because a "partial perfect" is conceptually
            // incoherent for this award.
            if ($flushesThisShooter === $totalDistances && $totalDistances > 0) {
                $perfectHandShooters[] = $this->displayName($shooter->name);
            }
        }

        $totalFlushes = array_sum($flushesByDistance);

        return [
            'flushes_by_distance' => $flushesByDistance,
            'shooters_by_distance' => $shootersByDistance,
            'distance_labels' => $distanceLabels,
            'total_flushes' => $totalFlushes,
            'perfect_hand_shooters' => $perfectHandShooters,
            'has_any' => $totalFlushes > 0 || count($perfectHandShooters) > 0,
        ];
    }

    /**
     * Shooter names are stored as "Name — Caliber"; the hub panel only
     * wants the human part so it stays compact on narrow screens.
     */
    private function displayName(string $raw): string
    {
        if (str_contains($raw, ' — ')) {
            return trim(explode(' — ', $raw, 2)[0]);
        }
        return $raw;
    }
}
