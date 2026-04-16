<?php

namespace App\Services;

use App\Models\Season;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use Illuminate\Support\Facades\DB;

class SeasonStandingsService
{
    public function calculate(Season $season): array
    {
        $matches = $season->matches()
            ->whereIn('status', ['active', 'completed'])
            ->with(['targetSets.gongs', 'squads.shooters'])
            ->get();

        if ($matches->isEmpty()) {
            return [];
        }

        $userScores = [];

        foreach ($matches as $match) {
            $matchStandings = $this->matchStandings($match);
            $maxScore = max((float) collect($matchStandings)->max('total_score'), 1.0);

            foreach ($matchStandings as $entry) {
                $userId = $entry['user_id'];
                if (!$userId) continue;

                if (!isset($userScores[$userId])) {
                    $userScores[$userId] = [
                        'user_id' => $userId,
                        'name' => $entry['name'],
                        'matches_played' => 0,
                        'total_relative' => 0,
                        'best_relative' => 0,
                        'worst_relative' => 100,
                        'total_hits' => 0,
                        'total_misses' => 0,
                        'total_points' => 0,
                        'match_results' => [],
                    ];
                }

                $relScore = round($entry['total_score'] / $maxScore * 100, 2);

                $userScores[$userId]['matches_played']++;
                $userScores[$userId]['total_relative'] += $relScore;
                $userScores[$userId]['best_relative'] = max($userScores[$userId]['best_relative'], $relScore);
                $userScores[$userId]['worst_relative'] = min($userScores[$userId]['worst_relative'], $relScore);
                $userScores[$userId]['total_hits'] += $entry['hits'];
                $userScores[$userId]['total_misses'] += $entry['misses'];
                $userScores[$userId]['total_points'] += $entry['total_score'];
                $userScores[$userId]['match_results'][] = [
                    'match_id' => $match->id,
                    'match_name' => $match->name,
                    'match_date' => $match->date?->toDateString(),
                    'total_score' => $entry['total_score'],
                    'relative_score' => $relScore,
                    'hits' => $entry['hits'],
                    'misses' => $entry['misses'],
                ];
            }
        }

        $standings = collect($userScores)->map(function ($entry) {
            $entry['avg_relative'] = $entry['matches_played'] > 0
                ? round($entry['total_relative'] / $entry['matches_played'], 2)
                : 0;
            return $entry;
        })
        ->sortByDesc('total_relative')
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
