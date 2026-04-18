<?php

namespace App\Services;

use App\Models\Shooter;
use App\Models\ShootingMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for per-match shooter standings.
 *
 * Standard matches use the weighted formula:
 *   total = SUM over hits of ( COALESCE(target_sets.distance_multiplier, 1) * gongs.multiplier )
 *
 * Returned rows (plain objects) contain:
 *   shooter_id   (int)
 *   name         (string)
 *   squad        (string|null)
 *   hits         (int)
 *   misses       (int)
 *   total_score  (float, rounded to 2 dp)
 *   status       (string) one of 'active' | 'withdrawn' | 'dq' | 'no_show'
 *   rank         (int|null)  — null for DQ *and* no-show rows
 *
 * Status handling for ranking:
 *   - active / withdrawn → ranked normally by descending total_score
 *   - dq                 → excluded from ranking (rank = null), listed at the end
 *   - no_show            → excluded from ranking (rank = null), listed at the end;
 *                          kept in the collection so we can surface "did not attend"
 *                          rather than silently dropping the entry, but treated as
 *                          NOT competing for stats purposes (hit rate, field avg,
 *                          field size). A shooter accidentally scored as 20 misses
 *                          will stop dragging field average down the moment they
 *                          are flagged as a no-show in the UI.
 */
class MatchStandingsService
{
    /**
     * Shooter statuses that are excluded from the ranked leaderboard.
     * `dq` and `no_show` both produce rank=null rows.
     */
    public const NON_RANKED_STATUSES = ['dq', 'no_show'];

    /**
     * Standings for a standard (non-PRS, non-ELR) match, including Royal Flush.
     *
     * @param  array<int>|null  $onlyShooterIds  optional restriction (e.g. for division/category filter)
     */
    public function standardStandings(ShootingMatch $match, ?array $onlyShooterIds = null): Collection
    {
        $query = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->leftJoin('scores', 'shooters.id', '=', 'scores.shooter_id')
            ->leftJoin('gongs', 'scores.gong_id', '=', 'gongs.id')
            ->leftJoin('target_sets', 'gongs.target_set_id', '=', 'target_sets.id')
            ->where('squads.match_id', $match->id);

        if ($onlyShooterIds !== null) {
            $query->whereIn('shooters.id', $onlyShooterIds);
        }

        $rows = $query
            ->select('shooters.id as shooter_id', 'shooters.name', 'shooters.status', 'squads.name as squad')
            ->selectRaw('COUNT(CASE WHEN scores.is_hit = 1 THEN 1 END) as agg_hits')
            ->selectRaw('COUNT(CASE WHEN scores.is_hit = 0 THEN 1 END) as agg_misses')
            ->selectRaw('COALESCE(SUM(CASE WHEN scores.is_hit = 1 THEN COALESCE(target_sets.distance_multiplier, 1) * gongs.multiplier ELSE 0 END), 0) as agg_total')
            ->groupBy('shooters.id', 'shooters.name', 'shooters.status', 'squads.name')
            ->orderByDesc('agg_total')
            ->get();

        $ranked = $rows->filter(fn ($r) => ! in_array($r->status, self::NON_RANKED_STATUSES, true))->values();
        $nonRanked = $rows->filter(fn ($r) => in_array($r->status, self::NON_RANKED_STATUSES, true))
            // dq before no_show, preserving query order within each group
            ->sortBy(fn ($r) => $r->status === 'dq' ? 0 : 1)
            ->values();

        $standings = collect();

        foreach ($ranked as $i => $row) {
            $standings->push((object) [
                'shooter_id' => (int) $row->shooter_id,
                'name' => $row->name,
                'squad' => $row->squad,
                'hits' => (int) $row->agg_hits,
                'misses' => (int) $row->agg_misses,
                'total_score' => round((float) $row->agg_total, 2),
                'status' => $row->status ?? 'active',
                'rank' => $i + 1,
            ]);
        }

        foreach ($nonRanked as $row) {
            $standings->push((object) [
                'shooter_id' => (int) $row->shooter_id,
                'name' => $row->name,
                'squad' => $row->squad,
                'hits' => (int) $row->agg_hits,
                'misses' => (int) $row->agg_misses,
                'total_score' => round((float) $row->agg_total, 2),
                'status' => $row->status ?? 'dq',
                'rank' => null,
            ]);
        }

        return $standings;
    }

    /**
     * Ordered top-N shooter ids for a match (any scoring type), ranked for podium awarding.
     * Excludes DQ'd and shooters without a user_id (account-linked shooters only).
     *
     * @return array<int, int>  1 => shooterId, 2 => shooterId, 3 => shooterId
     */
    public function podiumShooterIds(ShootingMatch $match, int $topN = 3): array
    {
        if ($match->isPrs()) {
            return $this->prsPodiumShooterIds($match, $topN);
        }

        // Standard and Royal Flush share the weighted ranking.
        $standings = $this->standardStandings($match)
            ->filter(fn ($row) => ! in_array($row->status, self::NON_RANKED_STATUSES, true) && $row->rank !== null);

        $linkedIds = DB::table('shooters')
            ->whereIn('id', $standings->pluck('shooter_id')->all())
            ->whereNotNull('user_id')
            ->pluck('id', 'id')
            ->all();

        $result = [];
        $rank = 1;
        foreach ($standings as $row) {
            if (! isset($linkedIds[$row->shooter_id])) {
                continue;
            }
            $result[$rank] = (int) $row->shooter_id;
            $rank++;
            if ($rank > $topN) {
                break;
            }
        }

        return $result;
    }

    /**
     * PRS podium — defer to the existing AchievementService ranking.
     *
     * @return array<int, int>
     */
    private function prsPodiumShooterIds(ShootingMatch $match, int $topN): array
    {
        // Lazily pull the PRS ranking from AchievementService (keeps the PRS tiebreaker logic in one place).
        $allResults = \App\Models\PrsStageResult::where('match_id', $match->id)
            ->get()
            ->groupBy('shooter_id');
        $stages = $match->targetSets()->get();

        $rankings = \App\Services\AchievementService::buildPrsRankings($match, $allResults, $stages);

        $shootersWithUser = DB::table('shooters')
            ->whereIn('id', array_values($rankings))
            ->whereNotNull('user_id')
            ->pluck('id', 'id')
            ->all();

        $result = [];
        $rank = 1;
        foreach ($rankings as $shooterId) {
            if (! isset($shootersWithUser[$shooterId])) {
                continue;
            }
            $result[$rank] = (int) $shooterId;
            $rank++;
            if ($rank > $topN) {
                break;
            }
        }

        return $result;
    }
}
