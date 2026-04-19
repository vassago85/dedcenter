<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Season;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use Illuminate\Support\Collection;

/**
 * Season leaderboard.
 *
 * Relative scoring (default — `organizations.uses_relative_scoring = true`):
 *   match relative score = round( shooter_total / match_winner_total × match.leaderboard_points )
 *   season total         = sum of a shooter's BEST N relative scores across the season
 *
 * Absolute scoring (`organizations.uses_relative_scoring = false`):
 *   match relative score = round(shooter_total)   (just the weighted raw total, rounded)
 *   season total         = sum of a shooter's BEST N raw-rounded scores
 *
 * N defaults to 3 but is configurable per organisation via `organizations.best_of`.
 * A `best_of` of NULL or 0 counts every match the shooter played.
 *
 * Regular match: leaderboard_points = 100  (scores out of 100)
 * Season final:  leaderboard_points = 200  (scores out of 200)
 */
class SeasonStandingsService
{
    /** Default number of "best" scores to count when an org has no best_of preference. */
    public const DEFAULT_BEST_OF = 3;

    public function calculate(Season $season): array
    {
        $matches = $season->matches()
            ->whereIn('status', ['active', 'completed'])
            ->with(['targetSets.gongs', 'squads.shooters', 'organization'])
            ->orderBy('date')
            ->get();

        // Season standings follow the owning organisation's preferences.
        $org = $season->organization ?? $matches->first()?->organization;

        return $this->standingsFromMatches($matches, $org);
    }

    /**
     * Org-scoped (no-season) standings for the public portal leaderboard.
     * Aggregates every active/completed match in the given org ids using the
     * host organisation's scoring preferences.
     *
     * When $orgIds refers to a league that merges child clubs, we use the
     * FIRST id as the "host" (that's the league itself in practice — the
     * portal always calls this with the viewing org first in the list).
     *
     * @param  Collection<int>|array<int>  $orgIds
     */
    public function calculateForOrganizations($orgIds): array
    {
        $ids = collect($orgIds)->all();
        if (count($ids) === 0) {
            return [];
        }

        $matches = ShootingMatch::query()
            ->whereIn('organization_id', $ids)
            ->whereIn('status', ['active', 'completed'])
            ->with(['targetSets.gongs', 'squads.shooters', 'organization'])
            ->orderBy('date')
            ->get();

        $hostOrg = Organization::find($ids[0]);

        return $this->standingsFromMatches($matches, $hostOrg);
    }

    /**
     * Shared core used by both season and org-scoped calculations.
     */
    private function standingsFromMatches(Collection $matches, ?Organization $hostOrg = null): array
    {
        if ($matches->isEmpty()) {
            return [];
        }

        $usesRelative = $hostOrg ? (bool) $hostOrg->uses_relative_scoring : true;
        $bestOf = $hostOrg && $hostOrg->best_of > 0 ? (int) $hostOrg->best_of : self::DEFAULT_BEST_OF;

        $userScores = [];

        foreach ($matches as $match) {
            $matchStandings = $this->matchStandings($match);
            $winnerScore = max((float) collect($matchStandings)->max('total_score'), 1.0);
            $pointsValue = max(1, (int) ($match->leaderboard_points ?? 100));

            foreach ($matchStandings as $entry) {
                // Group by user_id when linked, else by a normalized name+caliber key
                // so "Henri Klopper — 6x46" across matches still aggregates correctly.
                $key = $entry['user_id']
                    ? 'uid:'.$entry['user_id']
                    : 'name:'.strtolower(trim((string) $entry['name']));

                if (! isset($userScores[$key])) {
                    $userScores[$key] = [
                        'user_id' => $entry['user_id'],
                        'name' => $entry['name'],
                        'match_results' => [],
                    ];
                }

                // Integer score per match. Relative mode scales to the match's
                // points cap; absolute mode keeps the raw weighted total.
                $relScore = $usesRelative
                    ? (int) round(($entry['total_score'] / $winnerScore) * $pointsValue)
                    : (int) round($entry['total_score']);

                $userScores[$key]['match_results'][] = [
                    'match_id' => $match->id,
                    'match_name' => $match->name,
                    'match_date' => $match->date?->toDateString(),
                    'points_value' => $pointsValue,
                    'total_score' => $entry['total_score'],
                    'relative_score' => $relScore,
                    'hits' => $entry['hits'],
                    'misses' => $entry['misses'],
                    'counted' => false, // set below after we pick best N
                ];
            }
        }

        $standings = collect($userScores)->map(function ($entry) use ($bestOf) {
            $results = collect($entry['match_results']);

            // Pick best N (by relative_score, ties don't matter for the sum).
            $topKeys = $results
                ->sortByDesc('relative_score')
                ->take($bestOf)
                ->keys()
                ->all();
            $keySet = array_flip($topKeys);

            // Flag counted results + compute season total.
            $seasonTotal = 0;
            $entry['match_results'] = $results->map(function ($r, $k) use ($keySet, &$seasonTotal) {
                $counted = isset($keySet[$k]);
                if ($counted) {
                    $seasonTotal += (int) $r['relative_score'];
                }
                $r['counted'] = $counted;
                return $r;
            })->values()->all();

            $entry['matches_played'] = count($entry['match_results']);
            $entry['counting_results'] = min($bestOf, $entry['matches_played']);
            $entry['best3_total'] = $seasonTotal; // kept for backwards-compat with existing view code
            $entry['season_total'] = $seasonTotal;
            $entry['total_hits'] = collect($entry['match_results'])->sum('hits');
            $entry['total_misses'] = collect($entry['match_results'])->sum('misses');

            return $entry;
        })
        // Composite sort: season_total desc, matches_played desc as a tiebreaker.
        ->sortBy(function ($e) {
            return sprintf('%09d|%04d', 999999999 - (int) $e['season_total'], 9999 - (int) $e['matches_played']);
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
                // Exclude DQs and no-shows from season standings — they didn't
                // legitimately compete in the match.
                $q->whereNull('shooters.status')
                    ->orWhereNotIn('shooters.status', ['dq', 'no_show']);
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
