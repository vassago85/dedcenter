<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MatchDivision;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\StageTime;
use App\Models\TargetSet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScoreboardController extends Controller
{
    public function show(Request $request, ShootingMatch $match)
    {
        if ($match->isElr()) {
            return $this->elrScoreboard($match);
        }

        if ($request->boolean('detailed')) {
            return $this->detailedScoreboard($match, $request);
        }

        if ($match->isPrs()) {
            return $this->prsScoreboard($match, $request);
        }

        return $this->standardScoreboard($match, $request);
    }

    private function elrScoreboard(ShootingMatch $match)
    {
        $service = new \App\Services\Scoring\ELRScoringService();
        return response()->json($service->calculateStandings($match));
    }

    private function detailedScoreboard(ShootingMatch $match, Request $request)
    {
        $targetSets = $match->targetSets()
            ->orderBy('sort_order')
            ->with(['gongs' => fn ($q) => $q->orderBy('number')])
            ->get();

        $targetSetsPayload = $targetSets->map(fn ($ts) => [
            'id' => $ts->id,
            'label' => $ts->label,
            'distance_meters' => $ts->distance_meters,
            'distance_multiplier' => (float) ($ts->distance_multiplier ?? 1),
            'gongs' => $ts->gongs->map(fn ($g) => [
                'id' => $g->id,
                'number' => $g->number,
                'label' => $g->label,
                'multiplier' => $g->multiplier,
            ]),
        ]);

        $allGongs = $targetSets->flatMap->gongs;
        $totalGongCount = $allGongs->count();

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.id', 'shooters.name', 'shooters.bib_number', 'shooters.status', 'squads.name as squad_name')
            ->get();

        $allScores = Score::query()
            ->whereIn('shooter_id', $shooters->pluck('id'))
            ->whereIn('gong_id', $allGongs->pluck('id'))
            ->get()
            ->groupBy('shooter_id');

        $standings = $shooters->map(function ($shooter) use ($allScores, $targetSets, $totalGongCount) {
            $shooterScores = $allScores->get($shooter->id, collect());
            $scoresByGong = $shooterScores->keyBy('gong_id');

            $totalScore = 0;
            $totalHits = 0;
            $totalMisses = 0;

            $distances = [];
            foreach ($targetSets as $ts) {
                $distMult = (float) ($ts->distance_multiplier ?? 1);
                $distHits = 0;
                $distMisses = 0;
                $distSubtotal = 0;
                $gongDetails = [];

                foreach ($ts->gongs as $g) {
                    $score = $scoresByGong->get($g->id);
                    $isHit = $score ? (bool) $score->is_hit : null;
                    $points = round($distMult * $g->multiplier, 2);

                    if ($score) {
                        if ($isHit) {
                            $distHits++;
                            $totalHits++;
                            $distSubtotal += $points;
                            $totalScore += $points;
                        } else {
                            $distMisses++;
                            $totalMisses++;
                        }
                    }

                    $gongDetails[] = [
                        'gong_id' => $g->id,
                        'gong_number' => $g->number,
                        'gong_label' => $g->label,
                        'multiplier' => $g->multiplier,
                        'points' => $points,
                        'is_hit' => $isHit,
                    ];
                }

                $distances[$ts->id] = [
                    'target_set_id' => $ts->id,
                    'label' => $ts->label,
                    'distance_meters' => $ts->distance_meters,
                    'distance_multiplier' => $distMult,
                    'hits' => $distHits,
                    'misses' => $distMisses,
                    'subtotal' => round($distSubtotal, 2),
                    'gongs' => $gongDetails,
                ];
            }

            return [
                'id' => $shooter->id,
                'name' => $shooter->name,
                'bib_number' => $shooter->bib_number,
                'squad_name' => $shooter->squad_name,
                'status' => $shooter->status ?? 'active',
                'total_score' => round($totalScore, 2),
                'total_hits' => $totalHits,
                'total_misses' => $totalMisses,
                'total_gongs' => $totalGongCount,
                'distances' => $distances,
            ];
        })
        ->sortByDesc('total_score')
        ->values();

        $maxScore = $standings->max('total_score') ?: 1;
        $standings = $standings->map(function ($entry) use ($maxScore, $totalGongCount) {
            $entry['relative_score'] = round($entry['total_score'] / $maxScore * 100, 2);
            $entry['hit_rate'] = $totalGongCount > 0 ? round($entry['total_hits'] / $totalGongCount * 100, 2) : 0;
            return $entry;
        });

        return response()->json([
            'match' => [
                'name' => $match->name,
                'date' => $match->date?->toDateString(),
                'location' => $match->location,
                'scoring_type' => $match->scoring_type ?? 'standard',
            ],
            'target_sets' => $targetSetsPayload,
            'standings' => $standings,
        ]);
    }

    private function matchMeta(ShootingMatch $match): array
    {
        $totalTargets = DB::table('gongs')
            ->join('target_sets', 'gongs.target_set_id', '=', 'target_sets.id')
            ->where('target_sets.match_id', $match->id)
            ->count();

        $meta = [
            'id' => $match->id,
            'name' => $match->name,
            'scoring_type' => $match->scoring_type ?? 'standard',
            'total_targets' => $totalTargets,
        ];

        $divisions = $match->divisions()->orderBy('sort_order')->get();
        if ($divisions->isNotEmpty()) {
            $meta['divisions'] = $divisions->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values();
        }

        $categories = $match->categories()->orderBy('sort_order')->get();
        if ($categories->isNotEmpty()) {
            $meta['categories'] = $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug])->values();
        }

        return $meta;
    }

    private function categoryShooterIds(?string $categoryFilter): ?array
    {
        if (!$categoryFilter) return null;
        return DB::table('match_category_shooter')
            ->where('match_category_id', $categoryFilter)
            ->pluck('shooter_id')
            ->toArray();
    }

    private function divisionLookup(ShootingMatch $match): array
    {
        return DB::table('shooters')
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->leftJoin('match_divisions', 'shooters.match_division_id', '=', 'match_divisions.id')
            ->where('squads.match_id', $match->id)
            ->pluck('match_divisions.name', 'shooters.id')
            ->toArray();
    }

    private function divisionIdLookup(ShootingMatch $match): array
    {
        return DB::table('shooters')
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->pluck('shooters.match_division_id', 'shooters.id')
            ->toArray();
    }

    private function standardScoreboard(ShootingMatch $match, Request $request)
    {
        $divisionFilter = $request->query('division');
        $categoryFilter = $request->query('category');
        $divisionNames = $this->divisionLookup($match);
        $divisionIds = $this->divisionIdLookup($match);
        $catShooterIds = $this->categoryShooterIds($categoryFilter);

        $query = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->leftJoin('scores', 'shooters.id', '=', 'scores.shooter_id')
            ->leftJoin('gongs', 'scores.gong_id', '=', 'gongs.id')
            ->leftJoin('target_sets', 'gongs.target_set_id', '=', 'target_sets.id')
            ->where('squads.match_id', $match->id);

        if ($divisionFilter) {
            $query->where('shooters.match_division_id', $divisionFilter);
        }

        if ($catShooterIds !== null) {
            $query->whereIn('shooters.id', $catShooterIds);
        }

        $shooters = $query
            ->select('shooters.id as shooter_id', 'shooters.name', 'squads.name as squad')
            ->selectRaw('COUNT(CASE WHEN scores.is_hit = 1 THEN 1 END) as agg_hits')
            ->selectRaw('COUNT(CASE WHEN scores.is_hit = 0 THEN 1 END) as agg_misses')
            ->selectRaw('COALESCE(SUM(CASE WHEN scores.is_hit = 1 THEN COALESCE(target_sets.distance_multiplier, 1) * gongs.multiplier ELSE 0 END), 0) as agg_total')
            ->groupBy('shooters.id', 'shooters.name', 'squads.name')
            ->orderByDesc('agg_total')
            ->get();

        $maxScore = (float) ($shooters->max('agg_total') ?: 1);

        $leaderboard = $shooters->values()->map(fn ($shooter, $index) => [
            'rank' => $index + 1,
            'shooter_id' => (int) $shooter->shooter_id,
            'name' => $shooter->name,
            'squad' => $shooter->squad,
            'division_id' => $divisionIds[(int) $shooter->shooter_id] ?? null,
            'division' => $divisionNames[(int) $shooter->shooter_id] ?? null,
            'hits' => (int) $shooter->agg_hits,
            'misses' => (int) $shooter->agg_misses,
            'total_score' => round((float) $shooter->agg_total, 2),
            'relative_score' => round((float) $shooter->agg_total / $maxScore * 100, 2),
        ]);

        $response = [
            'match' => $this->matchMeta($match),
            'leaderboard' => $leaderboard,
        ];

        if ($match->side_bet_enabled) {
            $response['match']['side_bet_enabled'] = true;
            $response['side_bet'] = $this->sideBetLeaderboard($match, $catShooterIds, $divisionFilter);
        }

        return response()->json($response);
    }

    /**
     * Build the side bet leaderboard for a standard match.
     *
     * Ranking: most hits on the smallest gong (highest multiplier) across all target sets.
     * Tiebreaker: compare distances (furthest first) where each shooter hit that gong rank.
     * Cascade: if still tied, repeat for the next smallest gong rank.
     */
    private function sideBetLeaderboard(ShootingMatch $match, ?array $catShooterIds, ?string $divisionFilter): array
    {
        $targetSets = $match->targetSets()
            ->orderByDesc('distance_meters')
            ->with(['gongs' => fn ($q) => $q->orderByDesc('multiplier')])
            ->get();

        if ($targetSets->isEmpty()) {
            return [];
        }

        $gongRankMap = [];
        $maxRanks = 0;
        foreach ($targetSets as $ts) {
            $rank = 0;
            foreach ($ts->gongs as $gong) {
                $gongRankMap[$gong->id] = [
                    'rank' => $rank,
                    'distance' => $ts->distance_meters,
                    'multiplier' => (float) $gong->multiplier,
                ];
                $rank++;
            }
            $maxRanks = max($maxRanks, $rank);
        }

        $gongIds = array_keys($gongRankMap);
        if (empty($gongIds)) {
            return [];
        }

        $shooterQuery = DB::table('shooters')
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.id', 'shooters.name', 'squads.name as squad');

        if ($divisionFilter) {
            $shooterQuery->where('shooters.match_division_id', $divisionFilter);
        }
        if ($catShooterIds !== null) {
            $shooterQuery->whereIn('shooters.id', $catShooterIds);
        }

        $shooters = $shooterQuery->get();
        $shooterIds = $shooters->pluck('id')->toArray();

        if (empty($shooterIds)) {
            return [];
        }

        $hits = DB::table('scores')
            ->whereIn('scores.gong_id', $gongIds)
            ->whereIn('scores.shooter_id', $shooterIds)
            ->where('scores.is_hit', true)
            ->select('scores.shooter_id', 'scores.gong_id')
            ->get();

        $profiles = [];
        foreach ($shooters as $s) {
            $profiles[$s->id] = [
                'shooter_id' => $s->id,
                'name' => $s->name,
                'squad' => $s->squad,
                'ranks' => [],
            ];
            for ($r = 0; $r < $maxRanks; $r++) {
                $profiles[$s->id]['ranks'][$r] = [
                    'count' => 0,
                    'distances' => [],
                ];
            }
        }

        foreach ($hits as $hit) {
            if (!isset($gongRankMap[$hit->gong_id])) continue;
            $info = $gongRankMap[$hit->gong_id];
            $sid = $hit->shooter_id;
            if (!isset($profiles[$sid])) continue;

            $profiles[$sid]['ranks'][$info['rank']]['count']++;
            $profiles[$sid]['ranks'][$info['rank']]['distances'][] = $info['distance'];
        }

        foreach ($profiles as &$p) {
            for ($r = 0; $r < $maxRanks; $r++) {
                rsort($p['ranks'][$r]['distances']);
            }
        }
        unset($p);

        usort($profiles, function ($a, $b) use ($maxRanks) {
            for ($r = 0; $r < $maxRanks; $r++) {
                $aCount = $a['ranks'][$r]['count'];
                $bCount = $b['ranks'][$r]['count'];
                if ($aCount !== $bCount) return $bCount <=> $aCount;

                $aDist = $a['ranks'][$r]['distances'];
                $bDist = $b['ranks'][$r]['distances'];
                $len = max(count($aDist), count($bDist));
                for ($i = 0; $i < $len; $i++) {
                    $ad = $aDist[$i] ?? 0;
                    $bd = $bDist[$i] ?? 0;
                    if ($ad !== $bd) return $bd <=> $ad;
                }
            }
            return 0;
        });

        $result = [];
        foreach ($profiles as $index => $p) {
            $entry = [
                'rank' => $index + 1,
                'shooter_id' => $p['shooter_id'],
                'name' => $p['name'],
                'squad' => $p['squad'],
                'small_gong_hits' => $p['ranks'][0]['count'] ?? 0,
                'distances_hit' => $p['ranks'][0]['distances'] ?? [],
            ];
            $result[] = $entry;
        }

        return $result;
    }

    private function prsScoreboard(ShootingMatch $match, Request $request)
    {
        $divisionFilter = $request->query('division');
        $categoryFilter = $request->query('category');
        $divisionNames = $this->divisionLookup($match);
        $divisionIds = $this->divisionIdLookup($match);
        $catShooterIds = $this->categoryShooterIds($categoryFilter);

        $hasNewResults = \App\Models\PrsStageResult::where('match_id', $match->id)->exists();

        if ($hasNewResults) {
            return $this->prsScoreboardNew($match, $divisionFilter, $catShooterIds, $divisionNames, $divisionIds);
        }

        return $this->prsScoreboardLegacy($match, $divisionFilter, $catShooterIds, $divisionNames, $divisionIds);
    }

    private function prsScoreboardNew(ShootingMatch $match, ?string $divisionFilter, ?array $catShooterIds, array $divisionNames, array $divisionIds)
    {
        $targetSets = $match->targetSets()->get();
        $tiebreakerStage = $targetSets->firstWhere('is_tiebreaker', true);

        $query = \App\Models\Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->where('shooters.status', 'active');

        if ($divisionFilter) {
            $query->where('shooters.match_division_id', $divisionFilter);
        }

        if ($catShooterIds !== null) {
            $query->whereIn('shooters.id', $catShooterIds);
        }

        $shooters = $query->select('shooters.id as shooter_id', 'shooters.name', 'squads.name as squad')->get();

        $allResults = \App\Models\PrsStageResult::where('match_id', $match->id)->get()->groupBy('shooter_id');

        $totalShots = $targetSets->sum('total_shots') ?: DB::table('gongs')
            ->whereIn('target_set_id', $targetSets->pluck('id'))
            ->count();

        $entries = $shooters->map(function ($shooter) use ($allResults, $tiebreakerStage, $divisionNames, $divisionIds, $totalShots) {
            $sid = (int) $shooter->shooter_id;
            $results = $allResults->get($sid, collect());

            $totalHits = $results->sum('hits');
            $totalMisses = $results->sum('misses');
            $totalNotTaken = $results->sum('not_taken');

            $aggTime = $results->whereNotNull('official_time_seconds')->sum(fn ($r) => (float) $r->official_time_seconds);

            $tbHits = 0;
            $tbTime = null;
            if ($tiebreakerStage) {
                $tbResult = $results->firstWhere('stage_id', $tiebreakerStage->id);
                if ($tbResult) {
                    $tbHits = $tbResult->hits;
                    $tbTime = $tbResult->official_time_seconds ? (float) $tbResult->official_time_seconds : null;
                }
            }

            return [
                'shooter_id' => $sid,
                'name' => $shooter->name,
                'squad' => $shooter->squad,
                'division_id' => $divisionIds[$sid] ?? null,
                'division' => $divisionNames[$sid] ?? null,
                'hits' => $totalHits,
                'misses' => $totalMisses,
                'not_taken' => $totalNotTaken,
                'agg_time' => round($aggTime, 2),
                'tb_hits' => $tbHits,
                'tb_time' => $tbTime,
            ];
        });

        $sorted = $entries->sort(function ($a, $b) {
            if ($a['hits'] !== $b['hits']) return $b['hits'] <=> $a['hits'];
            if ($a['tb_hits'] !== $b['tb_hits']) return $b['tb_hits'] <=> $a['tb_hits'];

            $aTbTime = $a['tb_time'] ?? PHP_FLOAT_MAX;
            $bTbTime = $b['tb_time'] ?? PHP_FLOAT_MAX;
            if ($aTbTime !== $bTbTime) return $aTbTime <=> $bTbTime;

            return $a['agg_time'] <=> $b['agg_time'];
        })->values();

        $leaderboard = $sorted->map(fn ($entry, $index) => [
            'rank' => $index + 1,
            'shooter_id' => $entry['shooter_id'],
            'name' => $entry['name'],
            'squad' => $entry['squad'],
            'division_id' => $entry['division_id'],
            'division' => $entry['division'],
            'hits' => $entry['hits'],
            'misses' => $entry['misses'],
            'not_taken' => $entry['not_taken'],
            'total_score' => $entry['hits'],
            'total_time' => $entry['agg_time'],
            'tb_hits' => $entry['tb_hits'],
            'tb_time' => $entry['tb_time'] !== null ? round($entry['tb_time'], 2) : 0.0,
        ]);

        return response()->json([
            'match' => $this->matchMeta($match),
            'leaderboard' => $leaderboard,
        ]);
    }

    private function prsScoreboardLegacy(ShootingMatch $match, ?string $divisionFilter, ?array $catShooterIds, array $divisionNames, array $divisionIds)
    {
        $targetSets = $match->targetSets()->get();
        $targetSetIds = $targetSets->pluck('id');

        $totalTargets = DB::table('gongs')
            ->whereIn('target_set_id', $targetSetIds)
            ->count();
        $tiebreakerStage = $targetSets->firstWhere('is_tiebreaker', true);
        $tiebreakerStageId = $tiebreakerStage?->id;

        $shooterTimes = StageTime::query()
            ->whereIn('target_set_id', $targetSetIds)
            ->get()
            ->groupBy('shooter_id')
            ->map(fn ($times) => (float) $times->sum('time_seconds'))
            ->toArray();

        $tbHits = [];
        $tbTimes = [];
        if ($tiebreakerStageId) {
            $tbGongIds = DB::table('gongs')->where('target_set_id', $tiebreakerStageId)->pluck('id');

            $tbHits = Score::whereIn('gong_id', $tbGongIds)
                ->where('is_hit', true)
                ->select('shooter_id', DB::raw('COUNT(*) as hit_count'))
                ->groupBy('shooter_id')
                ->pluck('hit_count', 'shooter_id')
                ->map(fn ($v) => (int) $v)
                ->toArray();

            $tbTimes = StageTime::where('target_set_id', $tiebreakerStageId)
                ->pluck('time_seconds', 'shooter_id')
                ->map(fn ($v) => (float) $v)
                ->toArray();
        }

        $query = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->leftJoin('scores', 'shooters.id', '=', 'scores.shooter_id')
            ->where('squads.match_id', $match->id);

        if ($divisionFilter) {
            $query->where('shooters.match_division_id', $divisionFilter);
        }

        if ($catShooterIds !== null) {
            $query->whereIn('shooters.id', $catShooterIds);
        }

        $shooters = $query
            ->select('shooters.id as shooter_id', 'shooters.name', 'squads.name as squad')
            ->selectRaw('COUNT(CASE WHEN scores.is_hit = 1 THEN 1 END) as agg_hits')
            ->selectRaw('COUNT(CASE WHEN scores.is_hit = 0 THEN 1 END) as agg_misses')
            ->groupBy('shooters.id', 'shooters.name', 'squads.name')
            ->get();

        $entries = $shooters->map(function ($shooter) use ($shooterTimes, $tbHits, $tbTimes, $divisionNames, $divisionIds, $totalTargets) {
            $sid = (int) $shooter->shooter_id;
            return [
                'shooter_id' => $sid,
                'name' => $shooter->name,
                'squad' => $shooter->squad,
                'division_id' => $divisionIds[$sid] ?? null,
                'division' => $divisionNames[$sid] ?? null,
                'hits' => (int) $shooter->agg_hits,
                'misses' => (int) $shooter->agg_misses,
                'not_taken' => $totalTargets - (int) $shooter->agg_hits - (int) $shooter->agg_misses,
                'agg_time' => $shooterTimes[$sid] ?? 0.0,
                'tb_hits' => $tbHits[$sid] ?? 0,
                'tb_time' => $tbTimes[$sid] ?? 0.0,
            ];
        });

        $sorted = $entries->sort(function ($a, $b) {
            if ($a['hits'] !== $b['hits']) return $b['hits'] <=> $a['hits'];
            if ($a['tb_hits'] !== $b['tb_hits']) return $b['tb_hits'] <=> $a['tb_hits'];
            if ($a['tb_time'] !== $b['tb_time']) return $a['tb_time'] <=> $b['tb_time'];
            return $a['agg_time'] <=> $b['agg_time'];
        })->values();

        $leaderboard = $sorted->map(fn ($entry, $index) => [
            'rank' => $index + 1,
            'shooter_id' => $entry['shooter_id'],
            'name' => $entry['name'],
            'squad' => $entry['squad'],
            'division_id' => $entry['division_id'],
            'division' => $entry['division'],
            'hits' => $entry['hits'],
            'misses' => $entry['misses'],
            'not_taken' => $entry['not_taken'],
            'total_score' => $entry['hits'],
            'total_time' => round($entry['agg_time'], 2),
            'tb_hits' => $entry['tb_hits'],
            'tb_time' => round($entry['tb_time'], 2),
        ]);

        return response()->json([
            'match' => $this->matchMeta($match),
            'leaderboard' => $leaderboard,
        ]);
    }
}
