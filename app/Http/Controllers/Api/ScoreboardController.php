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
        if (! $match->scoresArePublic()) {
            $user = $request->user();
            $canView = $user && ($user->isOwner() || $match->created_by === $user->id
                || ($match->organization && $user->isOrgRangeOfficer($match->organization)));

            if (! $canView) {
                return response()->json([
                    'match' => [
                        'id' => $match->id,
                        'name' => $match->name,
                        'scoring_type' => $match->scoring_type ?? 'standard',
                        'scores_published' => false,
                    ],
                    'message' => 'Scores for this match have not been published yet.',
                    'leaderboard' => [],
                ]);
            }
        }

        if ($match->isElr()) {
            return $this->elrScoreboard($match);
        }

        if ($match->isPrs()) {
            return $this->prsScoreboard($match, $request);
        }

        if ($request->boolean('detailed')) {
            return $this->detailedScoreboard($match, $request);
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

    private function prsTargetSetsPayload($targetSets): array
    {
        return $targetSets->map(fn ($ts) => [
            'id' => $ts->id,
            'label' => $ts->label,
            'is_tiebreaker' => (bool) $ts->is_tiebreaker,
            'is_timed_stage' => (bool) $ts->is_timed_stage,
            'gong_count' => DB::table('gongs')->where('target_set_id', $ts->id)->count(),
        ])->values()->toArray();
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

        $user = $request->user();
        $isMd = $user && ($user->isOwner()
            || $match->created_by === $user->id
            || ($match->organization && $user->isOrgRangeOfficer($match->organization)));

        $response['match']['is_md'] = $isMd;

        if ($match->side_bet_enabled) {
            $response['match']['side_bet_enabled'] = true;
            if ($isMd) {
                $response['side_bet'] = $this->sideBetLeaderboard($match, $catShooterIds, $divisionFilter);
            }
        }

        if ($match->royal_flush_enabled) {
            $response['match']['royal_flush_enabled'] = true;
            $response['royal_flush'] = $this->royalFlushLeaderboard($match, $catShooterIds, $divisionFilter);
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

        $sideBetIds = DB::table('side_bet_shooters')
            ->where('match_id', $match->id)
            ->pluck('shooter_id')
            ->toArray();

        $shooterQuery = DB::table('shooters')
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.id', 'shooters.name', 'squads.name as squad');

        if (!empty($sideBetIds)) {
            $shooterQuery->whereIn('shooters.id', $sideBetIds);
        } else {
            return [];
        }

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

        $totalScores = DB::table('scores')
            ->join('gongs', 'scores.gong_id', '=', 'gongs.id')
            ->join('target_sets', 'gongs.target_set_id', '=', 'target_sets.id')
            ->whereIn('scores.shooter_id', $shooterIds)
            ->where('scores.is_hit', true)
            ->groupBy('scores.shooter_id')
            ->select('scores.shooter_id')
            ->selectRaw('COALESCE(SUM(COALESCE(target_sets.distance_multiplier, 1) * gongs.multiplier), 0) as total_score')
            ->pluck('total_score', 'scores.shooter_id')
            ->toArray();

        $profiles = [];
        foreach ($shooters as $s) {
            $profiles[$s->id] = [
                'shooter_id' => $s->id,
                'name' => $s->name,
                'squad' => $s->squad,
                'total_score' => round((float) ($totalScores[$s->id] ?? 0), 2),
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
            return $b['total_score'] <=> $a['total_score'];
        });

        $result = [];
        foreach ($profiles as $index => $p) {
            $result[] = [
                'rank' => $index + 1,
                'shooter_id' => $p['shooter_id'],
                'name' => $p['name'],
                'squad' => $p['squad'],
                'small_gong_hits' => $p['ranks'][0]['count'] ?? 0,
                'distances_hit' => $p['ranks'][0]['distances'] ?? [],
                'total_score' => $p['total_score'],
            ];
        }

        return $result;
    }

    /**
     * Royal Flush: a shooter who hits ALL gongs at a given distance earns a flush for that distance.
     * Ranked by flush count (desc), then furthest flushed distance, then total match score.
     */
    private function royalFlushLeaderboard(ShootingMatch $match, ?array $catShooterIds, ?string $divisionFilter): array
    {
        $targetSets = $match->targetSets()
            ->orderByDesc('distance_meters')
            ->with(['gongs'])
            ->get();

        if ($targetSets->isEmpty()) {
            return [];
        }

        $gongCountByTs = [];
        $gongIdsByTs = [];
        foreach ($targetSets as $ts) {
            $gongCountByTs[$ts->id] = $ts->gongs->count();
            $gongIdsByTs[$ts->id] = $ts->gongs->pluck('id')->toArray();
        }

        $allGongIds = $targetSets->flatMap(fn ($ts) => $ts->gongs->pluck('id'))->toArray();
        if (empty($allGongIds)) {
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
            ->whereIn('scores.gong_id', $allGongIds)
            ->whereIn('scores.shooter_id', $shooterIds)
            ->where('scores.is_hit', true)
            ->select('scores.shooter_id', 'scores.gong_id')
            ->get();

        $gongToTs = [];
        foreach ($targetSets as $ts) {
            foreach ($ts->gongs as $g) {
                $gongToTs[$g->id] = $ts->id;
            }
        }

        $hitCountByShooterTs = [];
        foreach ($hits as $hit) {
            $tsId = $gongToTs[$hit->gong_id] ?? null;
            if ($tsId === null) continue;
            $hitCountByShooterTs[$hit->shooter_id][$tsId] =
                ($hitCountByShooterTs[$hit->shooter_id][$tsId] ?? 0) + 1;
        }

        $tsDistances = $targetSets->pluck('distance_meters', 'id')->toArray();

        $totalScores = DB::table('scores')
            ->join('gongs', 'scores.gong_id', '=', 'gongs.id')
            ->join('target_sets', 'gongs.target_set_id', '=', 'target_sets.id')
            ->whereIn('scores.shooter_id', $shooterIds)
            ->where('scores.is_hit', true)
            ->groupBy('scores.shooter_id')
            ->select('scores.shooter_id')
            ->selectRaw('COALESCE(SUM(COALESCE(target_sets.distance_multiplier, 1) * gongs.multiplier), 0) as total_score')
            ->pluck('total_score', 'scores.shooter_id')
            ->toArray();

        $profiles = [];
        foreach ($shooters as $s) {
            $flushDistances = [];
            foreach ($targetSets as $ts) {
                $hitsAtTs = $hitCountByShooterTs[$s->id][$ts->id] ?? 0;
                if ($gongCountByTs[$ts->id] > 0 && $hitsAtTs >= $gongCountByTs[$ts->id]) {
                    $flushDistances[] = (int) $ts->distance_meters;
                }
            }

            $profiles[] = [
                'shooter_id' => $s->id,
                'name' => $s->name,
                'squad' => $s->squad,
                'flush_count' => count($flushDistances),
                'flush_distances' => $flushDistances,
                'total_score' => round((float) ($totalScores[$s->id] ?? 0), 2),
            ];
        }

        usort($profiles, function ($a, $b) {
            if ($a['flush_count'] !== $b['flush_count']) {
                return $b['flush_count'] <=> $a['flush_count'];
            }
            $aMax = !empty($a['flush_distances']) ? max($a['flush_distances']) : 0;
            $bMax = !empty($b['flush_distances']) ? max($b['flush_distances']) : 0;
            if ($aMax !== $bMax) {
                return $bMax <=> $aMax;
            }
            return $b['total_score'] <=> $a['total_score'];
        });

        return array_map(fn ($p, $i) => array_merge($p, ['rank' => $i + 1]), $profiles, array_keys($profiles));
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
        $targetSets = $match->targetSets()->orderBy('sort_order')->get();
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

        $gongsByTs = [];
        foreach ($targetSets as $ts) {
            $gongsByTs[$ts->id] = DB::table('gongs')->where('target_set_id', $ts->id)->orderBy('number')->get();
        }
        $allGongIds = collect($gongsByTs)->flatMap(fn ($g) => $g->pluck('id'));
        $allScores = Score::query()
            ->whereIn('gong_id', $allGongIds)
            ->get()
            ->keyBy(fn ($s) => "{$s->shooter_id}-{$s->gong_id}");

        $entries = $shooters->map(function ($shooter) use ($allResults, $tiebreakerStage, $targetSets, $divisionNames, $divisionIds, $gongsByTs, $allScores) {
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

            $stages = [];
            foreach ($targetSets as $ts) {
                $stageResult = $results->firstWhere('stage_id', $ts->id);
                $shots = [];
                foreach ($gongsByTs[$ts->id] ?? [] as $gong) {
                    $score = $allScores->get("{$sid}-{$gong->id}");
                    if ($score) {
                        $shots[] = $score->is_hit ? 'hit' : 'miss';
                    } else {
                        $shots[] = 'not_taken';
                    }
                }
                $stages[$ts->id] = [
                    'hits' => $stageResult ? $stageResult->hits : 0,
                    'misses' => $stageResult ? $stageResult->misses : 0,
                    'shots' => $shots,
                    'time' => $stageResult && $stageResult->official_time_seconds ? round((float) $stageResult->official_time_seconds, 2) : null,
                ];
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
                'stages' => $stages,
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
            'stages' => $entry['stages'],
        ]);

        return response()->json([
            'match' => $this->matchMeta($match),
            'target_sets' => $this->prsTargetSetsPayload($targetSets),
            'leaderboard' => $leaderboard,
        ]);
    }

    private function prsScoreboardLegacy(ShootingMatch $match, ?string $divisionFilter, ?array $catShooterIds, array $divisionNames, array $divisionIds)
    {
        $targetSets = $match->targetSets()->orderBy('sort_order')->get();
        $targetSetIds = $targetSets->pluck('id');

        $gongsByTs = [];
        foreach ($targetSets as $ts) {
            $gongsByTs[$ts->id] = DB::table('gongs')->where('target_set_id', $ts->id)->orderBy('number')->get();
        }

        $totalTargets = collect($gongsByTs)->sum(fn ($g) => $g->count());
        $tiebreakerStage = $targetSets->firstWhere('is_tiebreaker', true);
        $tiebreakerStageId = $tiebreakerStage?->id;

        $allStageTimes = StageTime::query()
            ->whereIn('target_set_id', $targetSetIds)
            ->get();
        $stageTimeMap = $allStageTimes->groupBy('shooter_id');

        $allScores = Score::query()
            ->whereIn('gong_id', collect($gongsByTs)->flatMap(fn ($g) => $g->pluck('id')))
            ->get()
            ->keyBy(fn ($s) => "{$s->shooter_id}-{$s->gong_id}");

        $query = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id);

        if ($divisionFilter) {
            $query->where('shooters.match_division_id', $divisionFilter);
        }
        if ($catShooterIds !== null) {
            $query->whereIn('shooters.id', $catShooterIds);
        }

        $shooters = $query->select('shooters.id as shooter_id', 'shooters.name', 'squads.name as squad')->get();

        $entries = $shooters->map(function ($shooter) use ($targetSets, $gongsByTs, $allScores, $stageTimeMap, $tiebreakerStageId, $divisionNames, $divisionIds, $totalTargets) {
            $sid = (int) $shooter->shooter_id;
            $totalHits = 0;
            $totalMisses = 0;
            $tbHits = 0;
            $stages = [];

            foreach ($targetSets as $ts) {
                $stageHits = 0;
                $stageMisses = 0;
                $shots = [];
                foreach ($gongsByTs[$ts->id] as $gong) {
                    $score = $allScores->get("{$sid}-{$gong->id}");
                    if ($score) {
                        if ($score->is_hit) { $stageHits++; $totalHits++; $shots[] = 'hit'; }
                        else { $stageMisses++; $totalMisses++; $shots[] = 'miss'; }
                    } else {
                        $shots[] = 'not_taken';
                    }
                }
                if ($ts->id === $tiebreakerStageId) {
                    $tbHits = $stageHits;
                }
                $shooterStageTimes = $stageTimeMap->get($sid, collect());
                $stageTime = $shooterStageTimes->firstWhere('target_set_id', $ts->id);
                $stages[$ts->id] = [
                    'hits' => $stageHits,
                    'misses' => $stageMisses,
                    'shots' => $shots,
                    'time' => $stageTime ? round((float) $stageTime->time_seconds, 2) : null,
                ];
            }

            $shooterTimes = $stageTimeMap->get($sid, collect());
            $aggTime = (float) $shooterTimes->sum('time_seconds');
            $tbTime = $tiebreakerStageId
                ? (float) ($shooterTimes->firstWhere('target_set_id', $tiebreakerStageId)?->time_seconds ?? 0)
                : 0.0;

            return [
                'shooter_id' => $sid,
                'name' => $shooter->name,
                'squad' => $shooter->squad,
                'division_id' => $divisionIds[$sid] ?? null,
                'division' => $divisionNames[$sid] ?? null,
                'hits' => $totalHits,
                'misses' => $totalMisses,
                'not_taken' => $totalTargets - $totalHits - $totalMisses,
                'agg_time' => round($aggTime, 2),
                'tb_hits' => $tbHits,
                'tb_time' => round($tbTime, 2),
                'stages' => $stages,
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
            'stages' => $entry['stages'],
        ]);

        return response()->json([
            'match' => $this->matchMeta($match),
            'target_sets' => $this->prsTargetSetsPayload($targetSets),
            'leaderboard' => $leaderboard,
        ]);
    }
}
