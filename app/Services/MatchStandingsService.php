<?php

namespace App\Services;

use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Services\Scoring\AlrhaScoringService;
use App\Services\Scoring\ELRScoringService;
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
 * Tiebreaker (when two active shooters share the same total_score):
 *   Falls through to the "small-gong cascade" — the same cascade the
 *   Side Bet / Winning-Hand award uses so the podium reads coherently
 *   across the platform. In order:
 *     1. More hits on the smallest gong (highest multiplier within its
 *        target set) across the whole match wins.
 *     2. If still tied, more of those small-gong hits at the FURTHER
 *        distance wins (lexicographic walk of furthest-first distances).
 *     3. If still tied, cascade to the next-smaller gong rank and
 *        repeat (count, then distances).
 *   This is the "jannie beats daniel at 129 because he hit more Aces /
 *   Kings / further-out gongs" rule — not hit-count, not hit-rate.
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

        // Apply the small-gong-cascade tiebreaker to any ranked shooters
        // tied on total_score. See the class docblock for the full rule.
        // Mirrors the Side Bet / Winning-Hand cascade so a shooter who
        // wins the Side Bet also wins main-standings ties — one coherent
        // rule across the platform for "who hit the smaller /
        // higher-scoring targets". We only incur the extra query when
        // there's an actual tie to resolve.
        $ranked = self::applySmallGongTiebreaker($ranked, $match);

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
     * Build per-shooter "small-gong cascade" profiles used as the
     * standings tiebreaker on standard / Royal Flush matches.
     *
     * Each profile buckets a shooter's hits by the gong's "rank within
     * its target set", where rank 0 = the smallest gong (highest
     * multiplier) and rank N = the largest / lowest multiplier. Within
     * each rank we keep the distances the shooter hit at, sorted
     * furthest-first so {@see compareSmallGongTiebreak()} can walk them
     * position-by-position.
     *
     * This is the single source-of-truth cascade for the "who hit the
     * smaller / higher-scoring targets" question — the same cascade the
     * Side Bet / Winning-Hand award uses, so the podium reads
     * consistently with those awards.
     *
     * Returned shape:
     *   [
     *       <shooterId> => [
     *           'ranks' => [
     *               0 => ['count' => int, 'distances' => int[]],
     *               1 => ['count' => int, 'distances' => int[]],
     *               ...
     *           ],
     *       ],
     *       ...
     *   ]
     *
     * Shooters with zero hits are omitted (treat as a null profile).
     *
     * @param  array<int, int>|null  $onlyShooterIds  restrict to these shooter ids (null = every shooter in the match)
     * @return array<int, array{ranks: array<int, array{count: int, distances: int[]}>}>
     */
    public function smallGongTiebreakProfiles(ShootingMatch $match, ?array $onlyShooterIds = null): array
    {
        $targetSets = $match->targetSets()
            ->orderByDesc('distance_meters')
            ->with(['gongs' => fn ($q) => $q->orderByDesc('multiplier')])
            ->get();

        if ($targetSets->isEmpty()) {
            return [];
        }

        // Map gong_id => (rank-within-set, distance). "Rank 0" is the
        // smallest/hardest gong (highest multiplier) at that distance.
        $gongRankMap = [];
        $maxRanks = 0;
        foreach ($targetSets as $ts) {
            $rank = 0;
            foreach ($ts->gongs as $gong) {
                $gongRankMap[(int) $gong->id] = [
                    'rank' => $rank,
                    'distance' => (int) $ts->distance_meters,
                ];
                $rank++;
            }
            $maxRanks = max($maxRanks, $rank);
        }

        $gongIds = array_keys($gongRankMap);
        if ($gongIds === [] || $maxRanks === 0) {
            return [];
        }

        $hitsQuery = DB::table('scores')
            ->join('shooters', 'scores.shooter_id', '=', 'shooters.id')
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->whereIn('scores.gong_id', $gongIds)
            ->where('scores.is_hit', true)
            ->select('scores.shooter_id', 'scores.gong_id');

        if ($onlyShooterIds !== null) {
            if ($onlyShooterIds === []) {
                return [];
            }
            $hitsQuery->whereIn('scores.shooter_id', $onlyShooterIds);
        }

        $hits = $hitsQuery->get();

        $profiles = [];
        foreach ($hits as $hit) {
            $info = $gongRankMap[(int) $hit->gong_id] ?? null;
            if ($info === null) {
                continue;
            }
            $sid = (int) $hit->shooter_id;
            if (! isset($profiles[$sid])) {
                $profiles[$sid] = ['ranks' => []];
                for ($r = 0; $r < $maxRanks; $r++) {
                    $profiles[$sid]['ranks'][$r] = ['count' => 0, 'distances' => []];
                }
            }
            $profiles[$sid]['ranks'][$info['rank']]['count']++;
            $profiles[$sid]['ranks'][$info['rank']]['distances'][] = $info['distance'];
        }

        // Sort distances within each rank furthest-first so the comparator
        // can compare them position-by-position without re-sorting.
        foreach ($profiles as &$profile) {
            foreach ($profile['ranks'] as &$bucket) {
                rsort($bucket['distances']);
            }
            unset($bucket);
        }
        unset($profile);

        return $profiles;
    }

    /**
     * `usort`-style comparator for two small-gong tiebreak profiles.
     * Negative => $a is better (sorts first); positive => $b is better;
     * 0 => still tied after the full cascade.
     *
     * Null profiles (shooter with no hits at all) always lose to a
     * non-null profile.
     *
     * @param  array{ranks: array<int, array{count: int, distances: int[]}>}|null  $a
     * @param  array{ranks: array<int, array{count: int, distances: int[]}>}|null  $b
     */
    public static function compareSmallGongTiebreak(?array $a, ?array $b): int
    {
        if ($a === null && $b === null) {
            return 0;
        }
        if ($a === null) {
            return 1;
        }
        if ($b === null) {
            return -1;
        }

        $maxRanks = max(count($a['ranks'] ?? []), count($b['ranks'] ?? []));
        for ($r = 0; $r < $maxRanks; $r++) {
            $aBucket = $a['ranks'][$r] ?? ['count' => 0, 'distances' => []];
            $bBucket = $b['ranks'][$r] ?? ['count' => 0, 'distances' => []];

            // Primary within rank: more hits at this gong rank wins.
            if ($aBucket['count'] !== $bBucket['count']) {
                return $bBucket['count'] <=> $aBucket['count'];
            }

            // Tiebreak within rank: furthest distance wins, cascading
            // down the furthest-first distance list.
            $len = max(count($aBucket['distances']), count($bBucket['distances']));
            for ($i = 0; $i < $len; $i++) {
                $ad = $aBucket['distances'][$i] ?? 0;
                $bd = $bBucket['distances'][$i] ?? 0;
                if ($ad !== $bd) {
                    return $bd <=> $ad;
                }
            }
        }

        return 0;
    }

    /**
     * Re-order ranked rows so shooters tied on total_score are resolved
     * by the small-gong cascade. Row shape must expose `shooter_id` and
     * `agg_total`. Returns the re-ordered collection (ranks are NOT
     * re-numbered here — caller still owns rank assignment).
     *
     * Skips the extra query when nothing is tied, which is the common
     * case on a sparsely-scored match.
     *
     * @param  Collection<int, object>  $ranked
     * @return Collection<int, object>
     */
    private static function applySmallGongTiebreaker(Collection $ranked, ShootingMatch $match): Collection
    {
        if ($ranked->count() < 2) {
            return $ranked;
        }

        $totals = $ranked->map(fn ($r) => round((float) $r->agg_total, 2))->all();
        if (count($totals) === count(array_unique($totals, SORT_NUMERIC))) {
            // No ties — current ORDER BY agg_total DESC is already final.
            return $ranked;
        }

        $service = new self;
        $profiles = $service->smallGongTiebreakProfiles(
            $match,
            $ranked->pluck('shooter_id')->map(fn ($v) => (int) $v)->all(),
        );

        $arr = $ranked->all();
        usort($arr, function ($a, $b) use ($profiles) {
            $aTotal = round((float) $a->agg_total, 2);
            $bTotal = round((float) $b->agg_total, 2);
            if ($aTotal !== $bTotal) {
                return $bTotal <=> $aTotal;
            }

            return self::compareSmallGongTiebreak(
                $profiles[(int) $a->shooter_id] ?? null,
                $profiles[(int) $b->shooter_id] ?? null,
            );
        });

        return collect($arr);
    }

    /**
     * Type-aware standings dispatcher. Returns the same row shape as
     * `standardStandings()` — `shooter_id`, `name`, `squad`, `hits`,
     * `misses`, `total_score`, `status`, `rank` — regardless of the
     * match's scoring type.
     *
     * This is the method any UI-facing "give me the leaderboard" caller
     * should use (match hub Top-5, ShooterBestFinishes rank lookup,
     * shooter report). Only badge / achievement code paths that need
     * scoring-type-specific tie-breakers should keep going to the
     * dedicated `*Standings()` methods.
     */
    public function standingsFor(ShootingMatch $match, ?array $onlyShooterIds = null): Collection
    {
        if ($match->isAlrha()) {
            return $this->alrhaStandings($match, $onlyShooterIds);
        }

        if ($match->isElr()) {
            return $this->elrStandings($match, $onlyShooterIds);
        }

        // PRS still doesn't have a first-class standings view here; the
        // match hub already hides Top-5 for PRS via the caller-side
        // guard. Falling back to standardStandings() would read the
        // empty `scores` table and lie, so we return an empty collection
        // and let the caller keep hiding the panel.
        if ($match->isPrs()) {
            return collect();
        }

        return $this->standardStandings($match, $onlyShooterIds);
    }

    /**
     * ALRHA standings mapped into the standard row shape. Uses
     * `AlrhaScoringService` so CBC hits/points are excluded (per §4 of
     * the ALRHA rules) and dual-class matches are surfaced together in
     * one leaderboard for the hub Top-5 (per-class filters live on the
     * scoreboard itself).
     */
    public function alrhaStandings(ShootingMatch $match, ?array $onlyShooterIds = null): Collection
    {
        $data = app(AlrhaScoringService::class)->calculateStandings($match);

        // Flatten per-class blocks into a single ranked list. Dual-class
        // matches are then re-ranked as one field so the hub Top-5 shows
        // the top shooters across the whole match, not just one class.
        $rows = [];
        foreach ($data['per_class'] ?? [] as $block) {
            foreach ($block['standings'] ?? [] as $row) {
                $rows[] = $row;
            }
        }

        // Fallback for single-class matches whose block only sits at the
        // top level (older data shape).
        if ($rows === [] && ! empty($data['standings'])) {
            $rows = $data['standings'];
        }

        return $this->mapElrPipelineRows($rows, $onlyShooterIds);
    }

    /**
     * ELR standings mapped into the standard row shape.
     */
    public function elrStandings(ShootingMatch $match, ?array $onlyShooterIds = null): Collection
    {
        $data = app(ELRScoringService::class)->calculateStandings($match);

        return $this->mapElrPipelineRows($data['standings'] ?? [], $onlyShooterIds);
    }

    /**
     * Take rows out of the ELR/ALRHA scoring pipeline (associative
     * arrays with `total_points` / `total_hits` / `shots_fired`) and
     * project them into the plain-object row shape the rest of the app
     * consumes.
     *
     * Re-ranks after filtering so `rank` is always 1..N over the returned
     * ranked shooters. DQ / no-show rows keep `rank = null` and are
     * pushed to the tail, matching `standardStandings()` semantics.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int>|null                   $onlyShooterIds
     */
    private function mapElrPipelineRows(array $rows, ?array $onlyShooterIds): Collection
    {
        $onlySet = $onlyShooterIds === null ? null : array_flip($onlyShooterIds);

        $ranked = [];
        $nonRanked = [];

        foreach ($rows as $row) {
            // ELR pipeline rows use `id` for the shooter primary key
            // (see ELRScoringService::calculateStandings); AlrhaScoring
            // preserves the same shape. Accept `shooter_id` too so
            // future pipelines can hand us either key.
            $shooterId = (int) ($row['shooter_id'] ?? $row['id'] ?? 0);
            if ($shooterId <= 0) {
                continue;
            }
            if ($onlySet !== null && ! isset($onlySet[$shooterId])) {
                continue;
            }

            $status = $row['status'] ?? 'active';
            $hits = (int) ($row['total_hits'] ?? 0);
            $fired = (int) ($row['shots_fired'] ?? 0);
            $misses = max(0, $fired - $hits);

            $entry = (object) [
                'shooter_id' => $shooterId,
                'name' => (string) ($row['name'] ?? ''),
                'squad' => $row['squad_name'] ?? null,
                'hits' => $hits,
                'misses' => $misses,
                'total_score' => round((float) ($row['total_points'] ?? 0), 2),
                'status' => $status,
                'rank' => null,
            ];

            if (in_array($status, self::NON_RANKED_STATUSES, true)) {
                $nonRanked[] = $entry;
            } else {
                $ranked[] = $entry;
            }
        }

        // Re-rank (rows come in scoring-pipeline order, which is already
        // points-desc, but a filter could have removed the leader; the
        // rank numbers must always be dense 1..N).
        usort($ranked, fn ($a, $b) => $b->total_score <=> $a->total_score);

        $standings = collect();
        foreach ($ranked as $i => $row) {
            $row->rank = $i + 1;
            $standings->push($row);
        }
        // DQ before no-show, otherwise pipeline order.
        usort($nonRanked, fn ($a, $b) => ($a->status === 'dq' ? 0 : 1) <=> ($b->status === 'dq' ? 0 : 1));
        foreach ($nonRanked as $row) {
            $standings->push($row);
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

        // Key by the shooter's TRUE finishing rank — never renumber the
        // account-linked shooters into 1/2/3. Renumbering handed a
        // non-winner the gold whenever a higher finisher was unlinked
        // (e.g. an unclaimed walk-in). If the real winner isn't linked,
        // that podium slot simply goes unawarded until they claim.
        $result = [];
        foreach ($standings as $row) {
            if (! isset($linkedIds[$row->shooter_id])) {
                continue;
            }
            $trueRank = (int) $row->rank;
            if ($trueRank >= 1 && $trueRank <= $topN) {
                $result[$trueRank] = (int) $row->shooter_id;
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

        // Preserve the TRUE finishing rank ($rankings is keyed 1..N in
        // finishing order). Do not renumber the linked shooters, or a
        // non-winner inherits the gold whenever a higher finisher is an
        // unclaimed walk-in. Unlinked winners' slots stay unawarded until
        // they claim (their badge lives on the import placeholder and is
        // transferred by ShooterAccountClaimService::approve()).
        $result = [];
        foreach ($rankings as $trueRank => $shooterId) {
            if ($trueRank > $topN) {
                break;
            }
            if (! isset($shootersWithUser[$shooterId])) {
                continue;
            }
            $result[$trueRank] = (int) $shooterId;
        }

        return $result;
    }
}
