<?php

namespace App\Services;

use App\Models\Season;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;

/**
 * Season leaderboard.
 *
 *   match relative score = round( shooter_total / match_winner_total × match.leaderboard_points )
 *   season total         = sum of a shooter's BEST 3 relative scores across the season
 *
 * - Regular match: leaderboard_points = 100  (scores out of 100)
 * - Season final:  leaderboard_points = 200  (scores out of 200)
 * - Max season total is therefore season-dependent: 300 for all-regular,
 *   400 if the season has a finale (100 + 100 + 200).
 */
class SeasonStandingsService
{
    public function calculate(Season $season): array
    {
        $matches = $season->matches()
            ->whereIn('status', ['active', 'completed'])
            ->with(['targetSets.gongs', 'squads.shooters'])
            ->orderBy('date')
            ->get();

        if ($matches->isEmpty()) {
            return [];
        }

        $userScores = [];

        foreach ($matches as $match) {
            $matchStandings = $this->matchStandings($match);
            $winnerScore = max((float) collect($matchStandings)->max('total_score'), 1.0);
            $pointsValue = max(1, (int) ($match->leaderboard_points ?? 100));

            foreach ($matchStandings as $entry) {
                $userId = $entry['user_id'];
                if (! $userId) {
                    continue;
                }

                if (! isset($userScores[$userId])) {
                    $userScores[$userId] = [
                        'user_id' => $userId,
                        'name' => $entry['name'],
                        'match_results' => [],
                    ];
                }

                // Round to nearest integer (user spec).
                $relScore = (int) round(($entry['total_score'] / $winnerScore) * $pointsValue);

                $userScores[$userId]['match_results'][] = [
                    'match_id' => $match->id,
                    'match_name' => $match->name,
                    'match_date' => $match->date?->toDateString(),
                    'points_value' => $pointsValue,
                    'total_score' => $entry['total_score'],
                    'relative_score' => $relScore,
                    'hits' => $entry['hits'],
                    'misses' => $entry['misses'],
                    'counted' => false, // set below after we pick best 3
                ];
            }
        }

        $standings = collect($userScores)->map(function ($entry) {
            $results = collect($entry['match_results']);

            // Pick best 3 (by relative_score, ties don't matter for the sum).
            $topKeys = $results
                ->sortByDesc('relative_score')
                ->take(3)
                ->keys()
                ->all();
            $keySet = array_flip($topKeys);

            // Flag counted results + compute season total.
            $best3Total = 0;
            $entry['match_results'] = $results->map(function ($r, $k) use ($keySet, &$best3Total) {
                $counted = isset($keySet[$k]);
                if ($counted) {
                    $best3Total += (int) $r['relative_score'];
                }
                $r['counted'] = $counted;
                return $r;
            })->values()->all();

            $entry['matches_played'] = count($entry['match_results']);
            $entry['counting_results'] = min(3, $entry['matches_played']);
            $entry['best3_total'] = $best3Total;
            $entry['total_hits'] = collect($entry['match_results'])->sum('hits');
            $entry['total_misses'] = collect($entry['match_results'])->sum('misses');

            return $entry;
        })
        // Composite sort: best3_total desc, then matches_played desc as a tiebreaker.
        ->sortBy(function ($e) {
            return sprintf('%09d|%04d', 999999999 - (int) $e['best3_total'], 9999 - (int) $e['matches_played']);
        })
        ->values()
        ->map(function ($entry, $index) {
            $entry['rank'] = $index + 1;
            return $entry;
        })
        ->toArray();

        return $standings;
    }

    private function matchStandings(ShootingMatch $match): array
    {
        $targetSets = $match->targetSets;
        $allGongs = $targetSets->flatMap->gongs;

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->where(function ($q) {
                $q->whereNull('shooters.status')->orWhere('shooters.status', '!=', 'dq');
            })
            ->select('shooters.id', 'shooters.name', 'shooters.user_id')
            ->get();

        $allScores = Score::query()
            ->whereIn('shooter_id', $shooters->pluck('id'))
            ->whereIn('gong_id', $allGongs->pluck('id'))
            ->get()
            ->groupBy('shooter_id');

        $gongTsMap = [];
        foreach ($targetSets as $ts) {
            foreach ($ts->gongs as $g) {
                $gongTsMap[$g->id] = $ts;
            }
        }

        return $shooters->map(function ($shooter) use ($allScores, $gongTsMap) {
            $scores = $allScores->get($shooter->id, collect());
            $total = 0;
            $hits = 0;
            $misses = 0;

            foreach ($scores as $score) {
                if ($score->is_hit) {
                    $hits++;
                    $ts = $gongTsMap[$score->gong_id] ?? null;
                    $distMult = $ts ? (float) ($ts->distance_multiplier ?? 1) : 1;
                    $gongMult = $score->gong ? $score->gong->multiplier : 1;
                    $total += $distMult * $gongMult;
                } else {
                    $misses++;
                }
            }

            return [
                'user_id' => $shooter->user_id,
                'name' => $shooter->name,
                'total_score' => round($total, 2),
                'hits' => $hits,
                'misses' => $misses,
            ];
        })->toArray();
    }
}
