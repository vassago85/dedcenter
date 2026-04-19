<?php

namespace App\Services;

use App\Models\Score;
use App\Models\ShootingMatch;
use Illuminate\Support\Facades\DB;

/**
 * Builds the Royal Flush "Side Bet" standings for a match, applying the
 * cascading tiebreaker (smallest-gong hits first, then furthest distance at
 * that gong, then cascade down to the next gong size) and — crucially —
 * recording *why* each row beat the row directly below it.
 *
 * Out of scope:
 *  - the main scoreboard tiebreakers
 *  - any non-Royal-Flush match
 */
class SideBetStandingsService
{
    /**
     * @return array{
     *     entries: list<array{
     *         rank:int,
     *         shooter_id:int,
     *         name:string,
     *         squad_name:string,
     *         small_gong_hits:int,
     *         distances:list<int>,
     *         total_score:float,
     *         ranks:list<array{count:int,distances:list<int>}>,
     *         tiebreaker_reason:?string,
     *     }>,
     *     gong_labels: list<string>,
     *     max_ranks: int,
     * }
     */
    public function build(ShootingMatch $match): array
    {
        $targetSets = $match->targetSets()
            ->orderByDesc('distance_meters')
            ->with(['gongs' => fn ($q) => $q->orderByDesc('multiplier')])
            ->get();

        $gongRankMap = [];
        $maxRanks = 0;
        foreach ($targetSets as $ts) {
            $rank = 0;
            foreach ($ts->gongs as $gong) {
                $gongRankMap[$gong->id] = [
                    'rank' => $rank,
                    'distance' => (int) $ts->distance_meters,
                ];
                $rank++;
            }
            $maxRanks = max($maxRanks, $rank);
        }

        $gongIds = array_keys($gongRankMap);
        if (empty($gongIds)) {
            return ['entries' => [], 'gong_labels' => [], 'max_ranks' => 0];
        }

        $sideBetIds = $match->sideBetShooters()->pluck('shooters.id')->toArray();
        if (empty($sideBetIds)) {
            return ['entries' => [], 'gong_labels' => $this->labelsFor($maxRanks), 'max_ranks' => $maxRanks];
        }

        $shooters = $match->shooters()
            ->with('squad')
            ->whereIn('shooters.id', $sideBetIds)
            ->get();

        $shooterIds = $shooters->pluck('id')->toArray();

        $hits = Score::whereIn('gong_id', $gongIds)
            ->whereIn('shooter_id', $shooterIds)
            ->where('is_hit', true)
            ->select('shooter_id', 'gong_id')
            ->get();

        $totalScores = DB::table('scores')
            ->join('gongs', 'scores.gong_id', '=', 'gongs.id')
            ->join('target_sets', 'gongs.target_set_id', '=', 'target_sets.id')
            ->whereIn('scores.shooter_id', $shooterIds)
            ->where('scores.is_hit', true)
            ->groupBy('scores.shooter_id')
            ->selectRaw('scores.shooter_id, COALESCE(SUM(COALESCE(target_sets.distance_multiplier, 1) * gongs.multiplier), 0) as total_score')
            ->pluck('total_score', 'scores.shooter_id')
            ->toArray();

        $profiles = [];
        foreach ($shooters as $s) {
            $profiles[$s->id] = [
                'shooter_id' => (int) $s->id,
                'shooter' => $s,
                'total_score' => round((float) ($totalScores[$s->id] ?? 0), 2),
                'ranks' => [],
            ];
            for ($r = 0; $r < $maxRanks; $r++) {
                $profiles[$s->id]['ranks'][$r] = ['count' => 0, 'distances' => []];
            }
        }

        foreach ($hits as $hit) {
            if (! isset($gongRankMap[$hit->gong_id], $profiles[$hit->shooter_id])) {
                continue;
            }
            $info = $gongRankMap[$hit->gong_id];
            $profiles[$hit->shooter_id]['ranks'][$info['rank']]['count']++;
            $profiles[$hit->shooter_id]['ranks'][$info['rank']]['distances'][] = $info['distance'];
        }

        foreach ($profiles as &$p) {
            for ($r = 0; $r < $maxRanks; $r++) {
                rsort($p['ranks'][$r]['distances']);
            }
        }
        unset($p);

        $profileList = array_values($profiles);
        usort($profileList, function ($a, $b) use ($maxRanks) {
            for ($r = 0; $r < $maxRanks; $r++) {
                if ($a['ranks'][$r]['count'] !== $b['ranks'][$r]['count']) {
                    return $b['ranks'][$r]['count'] <=> $a['ranks'][$r]['count'];
                }
                $ad = $a['ranks'][$r]['distances'];
                $bd = $b['ranks'][$r]['distances'];
                $len = max(count($ad), count($bd));
                for ($i = 0; $i < $len; $i++) {
                    if (($ad[$i] ?? 0) !== ($bd[$i] ?? 0)) {
                        return ($bd[$i] ?? 0) <=> ($ad[$i] ?? 0);
                    }
                }
            }

            return $b['total_score'] <=> $a['total_score'];
        });

        $gongLabels = $this->labelsFor($maxRanks);
        $entries = [];
        foreach ($profileList as $i => $p) {
            $below = $profileList[$i + 1] ?? null;
            $reason = $below ? $this->describeTiebreaker($p, $below, $maxRanks, $gongLabels) : null;

            $entries[] = [
                'rank' => $i + 1,
                'shooter_id' => $p['shooter_id'],
                'name' => $p['shooter']->name,
                'squad_name' => $p['shooter']->squad?->name ?? '—',
                'small_gong_hits' => $p['ranks'][0]['count'] ?? 0,
                'distances' => $p['ranks'][0]['distances'] ?? [],
                'total_score' => $p['total_score'],
                'ranks' => $p['ranks'],
                'tiebreaker_reason' => $reason,
            ];
        }

        return ['entries' => $entries, 'gong_labels' => $gongLabels, 'max_ranks' => $maxRanks];
    }

    /**
     * Produce a short human-readable reason that explains why $winner is ranked
     * directly above $loser. Returns null if they are not actually tied on the
     * smallest gong and the ranking is "obvious" (different smallest-gong counts).
     */
    private function describeTiebreaker(array $winner, array $loser, int $maxRanks, array $labels): ?string
    {
        for ($r = 0; $r < $maxRanks; $r++) {
            $wc = $winner['ranks'][$r]['count'];
            $lc = $loser['ranks'][$r]['count'];
            $label = $labels[$r] ?? ($r + 1).'th gong';

            if ($wc !== $lc) {
                return $r === 0
                    ? sprintf('Led on %s hits (%d vs %d)', $label, $wc, $lc)
                    : sprintf('Won on %s hits (%d vs %d)', $label, $wc, $lc);
            }

            $wd = $winner['ranks'][$r]['distances'];
            $ld = $loser['ranks'][$r]['distances'];
            $len = max(count($wd), count($ld));
            for ($i = 0; $i < $len; $i++) {
                $wv = $wd[$i] ?? 0;
                $lv = $ld[$i] ?? 0;
                if ($wv !== $lv) {
                    $ordinal = $this->ordinal($i + 1);

                    return sprintf(
                        'Won on %s %s-furthest distance (%dm vs %dm)',
                        $label,
                        $ordinal,
                        $wv,
                        $lv
                    );
                }
            }
        }

        // Identical all the way down — the sort fell through to total_score.
        if ($winner['total_score'] !== $loser['total_score']) {
            return sprintf(
                'Won on total match score (%s vs %s)',
                $this->formatScore($winner['total_score']),
                $this->formatScore($loser['total_score'])
            );
        }

        return 'Tied — matched on every gong';
    }

    /**
     * @return list<string>
     */
    private function labelsFor(int $maxRanks): array
    {
        $labels = [];
        for ($r = 0; $r < $maxRanks; $r++) {
            $labels[] = match ($r) {
                0 => '1st gong',
                1 => '2nd gong',
                2 => '3rd gong',
                default => ($r + 1).'th gong',
            };
        }

        return $labels;
    }

    private function ordinal(int $n): string
    {
        $suffix = match (true) {
            $n % 100 >= 11 && $n % 100 <= 13 => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        };

        return $n.$suffix;
    }

    private function formatScore(float $score): string
    {
        return rtrim(rtrim(number_format($score, 2, '.', ''), '0'), '.');
    }
}
