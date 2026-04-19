<?php

namespace App\Services;

use App\Enums\PlacementKey;
use App\Models\ElrShot;
use App\Models\PrsShotScore;
use App\Models\PrsStageResult;
use App\Models\Score;
use App\Models\Setting;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\UserAchievement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MatchReportService
{
    public function generateReport(ShootingMatch $match, Shooter $shooter): array
    {
        $report = match ($match->scoring_type) {
            'prs' => $this->generatePrsReport($match, $shooter),
            'elr' => $this->generateElrReport($match, $shooter),
            default => $this->generateStandardReport($match, $shooter),
        };

        $report['sponsor'] = $this->sponsorData($match);

        return $report;
    }

    public function generatePdfBytes(ShootingMatch $match, Shooter $shooter): string
    {
        $report = $this->generateReport($match, $shooter);
        $renderer = app(PdfDocumentRenderer::class);

        return $renderer->generate('exports.pdf-match-report', ['report' => $report]);
    }

    private function sponsorData(ShootingMatch $match): ?array
    {
        if (! (bool) Setting::get('advertising_enabled', false)) {
            return null;
        }

        $resolver = app(SponsorPlacementResolver::class);
        $assignment = $resolver->resolve(PlacementKey::MatchResults, $match->id);

        if (! $assignment?->sponsor) {
            return null;
        }

        return [
            'name' => $assignment->sponsor->name,
            'logo_path' => $assignment->sponsor->logo_path,
        ];
    }

    public function getEmailableShooters(ShootingMatch $match): Collection
    {
        return Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->where('shooters.status', 'active')
            ->whereNotNull('shooters.user_id')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.id', 'shooters.user_id')
                    ->whereNotNull('users.email')
                    ->where('users.email', '!=', '');
            })
            ->select('shooters.*')
            ->get();
    }

    // ── Standard (relay-based) ─────────────────────────────────────────

    private function generateStandardReport(ShootingMatch $match, Shooter $shooter): array
    {
        $targetSets = $match->targetSets()
            ->orderBy('sort_order')
            ->with(['gongs' => fn ($q) => $q->orderBy('number')])
            ->get();

        $allGongs = $targetSets->flatMap->gongs;
        $allGongIds = $allGongs->pluck('id');
        $totalGongCount = $allGongs->count();

        $maxPossible = $targetSets->sum(function ($ts) {
            $mult = (float) ($ts->distance_multiplier ?? 1);
            return $ts->gongs->sum(fn ($g) => round($mult * $g->multiplier, 2));
        });

        $allShooters = $this->getActiveShooters($match);
        $shooterIds = $allShooters->pluck('id');

        $allScores = Score::query()
            ->whereIn('shooter_id', $shooterIds)
            ->whereIn('gong_id', $allGongIds)
            ->get();

        $scoresByShooter = $allScores->groupBy('shooter_id');

        // Build per-shooter totals for ranking and field stats
        $fieldTotals = $allShooters->map(function ($s) use ($scoresByShooter, $targetSets) {
            $scores = $scoresByShooter->get($s->id, collect())->keyBy('gong_id');
            $total = 0.0;
            $hits = 0;
            $misses = 0;

            foreach ($targetSets as $ts) {
                $mult = (float) ($ts->distance_multiplier ?? 1);
                foreach ($ts->gongs as $g) {
                    $score = $scores->get($g->id);
                    if ($score) {
                        if ($score->is_hit) {
                            $hits++;
                            $total += round($mult * $g->multiplier, 2);
                        } else {
                            $misses++;
                        }
                    }
                }
            }

            return [
                'id' => $s->id,
                'name' => $s->name,
                'total' => round($total, 2),
                'hits' => $hits,
                'misses' => $misses,
            ];
        });

        $ranked = $fieldTotals->sortByDesc('total')->values();
        $shooterRank = $ranked->search(fn ($e) => $e['id'] === $shooter->id);
        $rank = $shooterRank !== false ? $shooterRank + 1 : $ranked->count();

        // Current shooter's detailed stage breakdown
        $shooterScores = $scoresByShooter->get($shooter->id, collect())->keyBy('gong_id');
        $stages = [];
        $totalScore = 0.0;
        $totalHits = 0;
        $totalMisses = 0;
        $totalNoShots = 0;

        foreach ($targetSets as $ts) {
            $mult = (float) ($ts->distance_multiplier ?? 1);
            $stageHits = 0;
            $stageMisses = 0;
            $stageNoShots = 0;
            $stageScore = 0.0;
            $gongDetails = [];

            foreach ($ts->gongs as $g) {
                $score = $shooterScores->get($g->id);
                $points = round($mult * $g->multiplier, 2);

                if ($score) {
                    if ($score->is_hit) {
                        $stageHits++;
                        $stageScore += $points;
                        $gongDetails[] = ['number' => $g->number, 'label' => $g->label, 'result' => 'hit', 'points' => $points];
                    } else {
                        $stageMisses++;
                        $gongDetails[] = ['number' => $g->number, 'label' => $g->label, 'result' => 'miss', 'points' => 0];
                    }
                } else {
                    $stageNoShots++;
                    $gongDetails[] = ['number' => $g->number, 'label' => $g->label, 'result' => 'no_shot', 'points' => 0];
                }
            }

            $stageTargets = $ts->gongs->count();
            $stages[] = [
                'label' => $ts->label,
                'distance_meters' => $ts->distance_meters,
                'distance_multiplier' => $mult,
                'hits' => $stageHits,
                'misses' => $stageMisses,
                'no_shots' => $stageNoShots,
                'score' => round($stageScore, 2),
                'time' => null,
                'hit_rate' => $stageTargets > 0 ? round($stageHits / $stageTargets * 100, 1) : 0,
                'gongs' => $gongDetails,
            ];

            $totalScore += $stageScore;
            $totalHits += $stageHits;
            $totalMisses += $stageMisses;
            $totalNoShots += $stageNoShots;
        }

        $totalScore = round($totalScore, 2);

        // Build gong→distance lookup from already-loaded target sets
        $gongDistanceMap = [];
        foreach ($targetSets as $ts) {
            foreach ($ts->gongs as $g) {
                $gongDistanceMap[$g->id] = $ts->distance_meters;
            }
        }

        // Hardest / easiest gong across all shooters
        $gongHitRates = $this->standardGongHitRates($allScores, $allGongs, $shooterIds->count(), $gongDistanceMap);

        $fieldScores = $ranked->pluck('total');
        $fieldHitRates = $ranked->map(fn ($e) => $totalGongCount > 0 ? round($e['hits'] / $totalGongCount * 100, 1) : 0);

        return [
            'match' => $this->matchMeta($match, $allShooters->count()),
            'shooter' => $this->shooterMeta($shooter),
            'placement' => $this->placement($rank, $allShooters->count()),
            'summary' => [
                'total_score' => $totalScore,
                'max_possible' => round($maxPossible, 2),
                'hit_rate' => $totalGongCount > 0 ? round($totalHits / $totalGongCount * 100, 1) : 0,
                'hits' => $totalHits,
                'misses' => $totalMisses,
                'no_shots' => $totalNoShots,
                'total_targets' => $totalGongCount,
                'total_time' => null,
            ],
            'stages' => $stages,
            'best_stage' => $this->bestStage($stages),
            'worst_stage' => $this->worstStage($stages),
            'field_stats' => [
                'avg_score' => round($fieldScores->avg(), 1),
                'median_score' => round($this->median($fieldScores), 1),
                'avg_hit_rate' => round($fieldHitRates->avg(), 1),
                'top_score' => $fieldScores->max(),
                'winner_name' => $ranked->first()['name'] ?? null,
                'winner_score' => $ranked->first()['total'] ?? 0,
                'hardest_gong' => $gongHitRates['hardest'],
                'easiest_gong' => $gongHitRates['easiest'],
            ],
            'fun_facts' => $this->generateStandardFunFacts(
                $shooter, $rank, $allShooters->count(), $totalScore, $totalHits,
                $totalGongCount, $stages, $ranked, $gongHitRates, $shooterScores, $allGongs, $fieldHitRates, $gongDistanceMap, $targetSets
            ),
            'badges' => $this->shooterBadges($match, $shooter),
        ];
    }

    // ── PRS ─────────────────────────────────────────────────────────────

    private function generatePrsReport(ShootingMatch $match, Shooter $shooter): array
    {
        $targetSets = $match->targetSets()->orderBy('sort_order')
            ->with(['gongs' => fn ($q) => $q->orderBy('number')])
            ->get();

        $tiebreakerStage = $targetSets->firstWhere('is_tiebreaker', true);
        $allShooters = $this->getActiveShooters($match);
        $shooterIds = $allShooters->pluck('id');

        $hasNewResults = PrsStageResult::where('match_id', $match->id)->exists();

        $gongCountByTs = [];
        foreach ($targetSets as $ts) {
            $gongCountByTs[$ts->id] = $ts->gongs->count();
        }
        $totalTargets = array_sum($gongCountByTs);

        if ($hasNewResults) {
            return $this->prsReportNew($match, $shooter, $targetSets, $tiebreakerStage, $allShooters, $gongCountByTs, $totalTargets);
        }

        return $this->prsReportLegacy($match, $shooter, $targetSets, $tiebreakerStage, $allShooters, $gongCountByTs, $totalTargets);
    }

    private function prsReportNew(
        ShootingMatch $match, Shooter $shooter, Collection $targetSets,
        $tiebreakerStage, Collection $allShooters, array $gongCountByTs, int $totalTargets
    ): array {
        $shooterIds = $allShooters->pluck('id');
        $allResults = PrsStageResult::where('match_id', $match->id)->get()->groupBy('shooter_id');
        $allPrsShots = PrsShotScore::where('match_id', $match->id)
            ->orderBy('shot_number')
            ->get()
            ->groupBy(fn ($s) => "{$s->shooter_id}-{$s->stage_id}");

        // Build per-shooter totals for ranking
        $fieldEntries = $allShooters->map(function ($s) use ($allResults, $tiebreakerStage) {
            $results = $allResults->get($s->id, collect());
            $hits = $results->sum('hits');
            $misses = $results->sum('misses');
            $notTaken = $results->sum('not_taken');
            $aggTime = $results->whereNotNull('official_time_seconds')
                ->sum(fn ($r) => (float) $r->official_time_seconds);

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
                'id' => $s->id,
                'name' => $s->name,
                'hits' => $hits,
                'misses' => $misses,
                'not_taken' => $notTaken,
                'agg_time' => round($aggTime, 2),
                'tb_hits' => $tbHits,
                'tb_time' => $tbTime,
            ];
        });

        $ranked = $this->rankPrs($fieldEntries);

        $shooterRank = $ranked->search(fn ($e) => $e['id'] === $shooter->id);
        $rank = $shooterRank !== false ? $shooterRank + 1 : $ranked->count();

        $myEntry = $ranked->firstWhere('id', $shooter->id);

        // Detailed stage breakdown for this shooter
        $myResults = $allResults->get($shooter->id, collect());
        $stages = [];
        foreach ($targetSets as $ts) {
            $stageResult = $myResults->firstWhere('stage_id', $ts->id);
            $stageShots = $allPrsShots->get("{$shooter->id}-{$ts->id}", collect());

            $gongDetails = $stageShots->map(function ($s) {
                $result = $s->result instanceof \BackedEnum ? $s->result->value : (string) $s->result;
                return [
                    'number' => $s->shot_number,
                    'label' => "Shot {$s->shot_number}",
                    'result' => $result,
                    'points' => $result === 'hit' ? 1 : 0,
                ];
            })->values()->toArray();

            $expectedCount = $gongCountByTs[$ts->id] ?? 0;
            while (count($gongDetails) < $expectedCount) {
                $n = count($gongDetails) + 1;
                $gongDetails[] = ['number' => $n, 'label' => "Shot {$n}", 'result' => 'not_taken', 'points' => 0];
            }

            $stageHits = $stageResult ? $stageResult->hits : 0;
            $stageMisses = $stageResult ? $stageResult->misses : 0;
            $stageNotTaken = $stageResult ? $stageResult->not_taken : ($expectedCount - $stageHits - $stageMisses);

            $stages[] = [
                'label' => $ts->label,
                'distance_meters' => $ts->distance_meters,
                'distance_multiplier' => (float) ($ts->distance_multiplier ?? 1),
                'hits' => $stageHits,
                'misses' => $stageMisses,
                'no_shots' => max(0, $stageNotTaken),
                'score' => $stageHits,
                'time' => $stageResult && $stageResult->official_time_seconds
                    ? round((float) $stageResult->official_time_seconds, 2) : null,
                'hit_rate' => $expectedCount > 0 ? round($stageHits / $expectedCount * 100, 1) : 0,
                'gongs' => $gongDetails,
            ];
        }

        $totalHits = $myEntry['hits'] ?? 0;
        $totalMisses = $myEntry['misses'] ?? 0;
        $totalNotTaken = $myEntry['not_taken'] ?? 0;
        $totalTime = $myEntry['agg_time'] ?? 0;

        // Per-shot hit rates for hardest/easiest
        $shotHitRates = $this->prsShotHitRates($match, $targetSets, $shooterIds->count());

        $fieldHits = $ranked->pluck('hits');
        $fieldTimes = $ranked->pluck('agg_time');

        return [
            'match' => $this->matchMeta($match, $allShooters->count()),
            'shooter' => $this->shooterMeta($shooter),
            'placement' => $this->placement($rank, $allShooters->count()),
            'summary' => [
                'total_score' => $totalHits,
                'max_possible' => $totalTargets,
                'hit_rate' => $totalTargets > 0 ? round($totalHits / $totalTargets * 100, 1) : 0,
                'hits' => $totalHits,
                'misses' => $totalMisses,
                'no_shots' => $totalNotTaken,
                'total_targets' => $totalTargets,
                'total_time' => round($totalTime, 2),
            ],
            'stages' => $stages,
            'best_stage' => $this->bestStage($stages),
            'worst_stage' => $this->worstStage($stages),
            'field_stats' => [
                'avg_score' => round($fieldHits->avg(), 1),
                'median_score' => round($this->median($fieldHits), 1),
                'avg_hit_rate' => $totalTargets > 0 ? round($fieldHits->avg() / $totalTargets * 100, 1) : 0,
                'top_score' => $fieldHits->max(),
                'winner_name' => $ranked->first()['name'] ?? null,
                'winner_score' => $ranked->first()['hits'] ?? 0,
                'hardest_gong' => $shotHitRates['hardest'],
                'easiest_gong' => $shotHitRates['easiest'],
            ],
            'fun_facts' => $this->generatePrsFunFacts(
                $shooter, $rank, $allShooters->count(), $totalHits, $totalTargets,
                $totalTime, $stages, $ranked, $shotHitRates
            ),
            'badges' => $this->shooterBadges($match, $shooter),
        ];
    }

    private function prsReportLegacy(
        ShootingMatch $match, Shooter $shooter, Collection $targetSets,
        $tiebreakerStage, Collection $allShooters, array $gongCountByTs, int $totalTargets
    ): array {
        $shooterIds = $allShooters->pluck('id');
        $allGongIds = $targetSets->flatMap(fn ($ts) => $ts->gongs->pluck('id'));

        $allScores = Score::query()
            ->whereIn('shooter_id', $shooterIds)
            ->whereIn('gong_id', $allGongIds)
            ->get()
            ->keyBy(fn ($s) => "{$s->shooter_id}-{$s->gong_id}");

        $allStageTimes = DB::table('stage_times')
            ->whereIn('target_set_id', $targetSets->pluck('id'))
            ->get()
            ->groupBy('shooter_id');

        $tiebreakerStageId = $tiebreakerStage?->id;

        $fieldEntries = $allShooters->map(function ($s) use ($targetSets, $allScores, $allStageTimes, $tiebreakerStageId, $totalTargets) {
            $hits = 0;
            $misses = 0;
            $tbHits = 0;

            foreach ($targetSets as $ts) {
                $stageHits = 0;
                foreach ($ts->gongs as $g) {
                    $score = $allScores->get("{$s->id}-{$g->id}");
                    if ($score) {
                        if ($score->is_hit) { $hits++; $stageHits++; }
                        else { $misses++; }
                    }
                }
                if ($ts->id === $tiebreakerStageId) {
                    $tbHits = $stageHits;
                }
            }

            $times = $allStageTimes->get($s->id, collect());
            $aggTime = (float) $times->sum('time_seconds');
            $tbTime = $tiebreakerStageId
                ? (float) ($times->firstWhere('target_set_id', $tiebreakerStageId)?->time_seconds ?? 0)
                : 0.0;

            return [
                'id' => $s->id,
                'name' => $s->name,
                'hits' => $hits,
                'misses' => $misses,
                'not_taken' => $totalTargets - $hits - $misses,
                'agg_time' => round($aggTime, 2),
                'tb_hits' => $tbHits,
                'tb_time' => round($tbTime, 2),
            ];
        });

        $ranked = $this->rankPrs($fieldEntries);

        $shooterRank = $ranked->search(fn ($e) => $e['id'] === $shooter->id);
        $rank = $shooterRank !== false ? $shooterRank + 1 : $ranked->count();
        $myEntry = $ranked->firstWhere('id', $shooter->id);

        // Detailed stage breakdown
        $shooterTimes = $allStageTimes->get($shooter->id, collect());
        $stages = [];
        foreach ($targetSets as $ts) {
            $stageHits = 0;
            $stageMisses = 0;
            $stageNoShots = 0;
            $gongDetails = [];

            foreach ($ts->gongs as $g) {
                $score = $allScores->get("{$shooter->id}-{$g->id}");
                if ($score) {
                    if ($score->is_hit) {
                        $stageHits++;
                        $gongDetails[] = ['number' => $g->number, 'label' => $g->label, 'result' => 'hit', 'points' => 1];
                    } else {
                        $stageMisses++;
                        $gongDetails[] = ['number' => $g->number, 'label' => $g->label, 'result' => 'miss', 'points' => 0];
                    }
                } else {
                    $stageNoShots++;
                    $gongDetails[] = ['number' => $g->number, 'label' => $g->label, 'result' => 'not_taken', 'points' => 0];
                }
            }

            $expectedCount = $gongCountByTs[$ts->id] ?? 0;
            $stageTime = $shooterTimes->firstWhere('target_set_id', $ts->id);

            $stages[] = [
                'label' => $ts->label,
                'distance_meters' => $ts->distance_meters,
                'distance_multiplier' => (float) ($ts->distance_multiplier ?? 1),
                'hits' => $stageHits,
                'misses' => $stageMisses,
                'no_shots' => $stageNoShots,
                'score' => $stageHits,
                'time' => $stageTime ? round((float) $stageTime->time_seconds, 2) : null,
                'hit_rate' => $expectedCount > 0 ? round($stageHits / $expectedCount * 100, 1) : 0,
                'gongs' => $gongDetails,
            ];
        }

        $totalHits = $myEntry['hits'] ?? 0;
        $totalMisses = $myEntry['misses'] ?? 0;
        $totalNotTaken = $myEntry['not_taken'] ?? 0;
        $totalTime = $myEntry['agg_time'] ?? 0;

        $shotHitRates = $this->prsShotHitRatesLegacy($allScores, $targetSets, $shooterIds->count());

        $fieldHits = $ranked->pluck('hits');

        return [
            'match' => $this->matchMeta($match, $allShooters->count()),
            'shooter' => $this->shooterMeta($shooter),
            'placement' => $this->placement($rank, $allShooters->count()),
            'summary' => [
                'total_score' => $totalHits,
                'max_possible' => $totalTargets,
                'hit_rate' => $totalTargets > 0 ? round($totalHits / $totalTargets * 100, 1) : 0,
                'hits' => $totalHits,
                'misses' => $totalMisses,
                'no_shots' => $totalNotTaken,
                'total_targets' => $totalTargets,
                'total_time' => round($totalTime, 2),
            ],
            'stages' => $stages,
            'best_stage' => $this->bestStage($stages),
            'worst_stage' => $this->worstStage($stages),
            'field_stats' => [
                'avg_score' => round($fieldHits->avg(), 1),
                'median_score' => round($this->median($fieldHits), 1),
                'avg_hit_rate' => $totalTargets > 0 ? round($fieldHits->avg() / $totalTargets * 100, 1) : 0,
                'top_score' => $fieldHits->max(),
                'winner_name' => $ranked->first()['name'] ?? null,
                'winner_score' => $ranked->first()['hits'] ?? 0,
                'hardest_gong' => $shotHitRates['hardest'],
                'easiest_gong' => $shotHitRates['easiest'],
            ],
            'fun_facts' => $this->generatePrsFunFacts(
                $shooter, $rank, $allShooters->count(), $totalHits, $totalTargets,
                $totalTime, $stages, $ranked, $shotHitRates
            ),
            'badges' => $this->shooterBadges($match, $shooter),
        ];
    }

    private function rankPrs(Collection $entries): Collection
    {
        return $entries->sort(function ($a, $b) {
            if ($a['hits'] !== $b['hits']) return $b['hits'] <=> $a['hits'];
            if ($a['tb_hits'] !== $b['tb_hits']) return $b['tb_hits'] <=> $a['tb_hits'];

            $aTbTime = $a['tb_time'] ?? PHP_FLOAT_MAX;
            $bTbTime = $b['tb_time'] ?? PHP_FLOAT_MAX;
            if ($aTbTime !== $bTbTime) return $aTbTime <=> $bTbTime;

            return $a['agg_time'] <=> $b['agg_time'];
        })->values();
    }

    // ── ELR ─────────────────────────────────────────────────────────────

    private function generateElrReport(ShootingMatch $match, Shooter $shooter): array
    {
        $stages = $match->elrStages()
            ->with(['targets' => fn ($q) => $q->orderBy('sort_order'), 'scoringProfile'])
            ->get();

        $allTargetIds = $stages->flatMap(fn ($s) => $s->targets->pluck('id'))->toArray();
        $allShooters = $this->getActiveShooters($match);
        $shooterIds = $allShooters->pluck('id');

        $allShots = ElrShot::query()
            ->whereIn('shooter_id', $shooterIds)
            ->whereIn('elr_target_id', $allTargetIds)
            ->get()
            ->groupBy('shooter_id');

        $maxPossible = $stages->sum(function ($stage) {
            return $stage->targets->sum(fn ($t) => (float) $t->base_points);
        });

        $totalTargetCount = $stages->sum(fn ($s) => $s->targets->count());

        // Build field totals
        $fieldEntries = $allShooters->map(function ($s) use ($allShots, $stages) {
            $shooterShots = $allShots->get($s->id, collect());
            $shotsByTarget = $shooterShots->groupBy('elr_target_id');

            $totalPoints = 0.0;
            $totalHits = 0;
            $firstRoundHits = 0;
            $furthestHitM = 0;

            foreach ($stages as $stage) {
                foreach ($stage->targets as $target) {
                    $targetShots = $shotsByTarget->get($target->id, collect());
                    foreach ($targetShots as $shot) {
                        if ($shot->isHit()) {
                            $totalHits++;
                            $totalPoints += (float) $shot->points_awarded;
                            if ($shot->shot_number === 1) $firstRoundHits++;
                            if ($target->distance_m > $furthestHitM) $furthestHitM = $target->distance_m;
                        }
                    }
                }
            }

            return [
                'id' => $s->id,
                'name' => $s->name,
                'total_points' => round($totalPoints, 2),
                'total_hits' => $totalHits,
                'first_round_hits' => $firstRoundHits,
                'furthest_hit_m' => $furthestHitM,
            ];
        });

        $ranked = $fieldEntries->sort(function ($a, $b) {
            if ($a['total_points'] !== $b['total_points']) return $b['total_points'] <=> $a['total_points'];
            if ($a['furthest_hit_m'] !== $b['furthest_hit_m']) return $b['furthest_hit_m'] <=> $a['furthest_hit_m'];
            return $b['first_round_hits'] <=> $a['first_round_hits'];
        })->values();

        $shooterRank = $ranked->search(fn ($e) => $e['id'] === $shooter->id);
        $rank = $shooterRank !== false ? $shooterRank + 1 : $ranked->count();
        $myEntry = $ranked->firstWhere('id', $shooter->id);

        // Detailed stage breakdown for this shooter
        $myShots = $allShots->get($shooter->id, collect())->groupBy('elr_target_id');
        $stageDetails = [];
        $totalHits = 0;
        $totalMisses = 0;
        $totalNoShots = 0;
        $totalPoints = 0.0;
        $furthestHitM = 0;

        foreach ($stages as $stage) {
            $stageHits = 0;
            $stageMisses = 0;
            $stageNoShots = 0;
            $stagePoints = 0.0;
            $gongDetails = [];

            foreach ($stage->targets as $target) {
                $targetShots = $myShots->get($target->id, collect())->sortBy('shot_number');

                if ($targetShots->isEmpty()) {
                    $stageNoShots++;
                    $totalNoShots++;
                    $gongDetails[] = [
                        'number' => $target->sort_order ?? 1,
                        'label' => "{$target->name} ({$target->distance_m}m)",
                        'result' => 'no_shot',
                        'points' => 0,
                    ];
                    continue;
                }

                $targetHit = false;
                $targetPoints = 0.0;
                foreach ($targetShots as $shot) {
                    if ($shot->isHit()) {
                        $targetHit = true;
                        $targetPoints += (float) $shot->points_awarded;
                        if ($target->distance_m > $furthestHitM) $furthestHitM = $target->distance_m;
                    }
                }

                if ($targetHit) {
                    $stageHits++;
                    $stagePoints += $targetPoints;
                } else {
                    $stageMisses++;
                }

                $gongDetails[] = [
                    'number' => $target->sort_order ?? 1,
                    'label' => "{$target->name} ({$target->distance_m}m)",
                    'result' => $targetHit ? 'hit' : 'miss',
                    'points' => round($targetPoints, 2),
                ];
            }

            $stageTargetCount = $stage->targets->count();
            $stageDetails[] = [
                'label' => $stage->label,
                'distance_meters' => null,
                'distance_multiplier' => null,
                'hits' => $stageHits,
                'misses' => $stageMisses,
                'no_shots' => $stageNoShots,
                'score' => round($stagePoints, 2),
                'time' => null,
                'hit_rate' => $stageTargetCount > 0 ? round($stageHits / $stageTargetCount * 100, 1) : 0,
                'gongs' => $gongDetails,
            ];

            $totalHits += $stageHits;
            $totalMisses += $stageMisses;
            $totalPoints += $stagePoints;
        }

        $totalPoints = round($totalPoints, 2);

        // Target hit rates across field
        $targetHitRates = $this->elrTargetHitRates($allShots, $stages, $shooterIds->count());
        $fieldFurthest = $ranked->max('furthest_hit_m');

        $fieldPoints = $ranked->pluck('total_points');

        return [
            'match' => $this->matchMeta($match, $allShooters->count()),
            'shooter' => $this->shooterMeta($shooter),
            'placement' => $this->placement($rank, $allShooters->count()),
            'summary' => [
                'total_score' => $totalPoints,
                'max_possible' => round($maxPossible, 2),
                'hit_rate' => $totalTargetCount > 0 ? round($totalHits / $totalTargetCount * 100, 1) : 0,
                'hits' => $totalHits,
                'misses' => $totalMisses,
                'no_shots' => $totalNoShots,
                'total_targets' => $totalTargetCount,
                'total_time' => null,
            ],
            'stages' => $stageDetails,
            'best_stage' => $this->bestStage($stageDetails),
            'worst_stage' => $this->worstStage($stageDetails),
            'field_stats' => [
                'avg_score' => round($fieldPoints->avg(), 1),
                'median_score' => round($this->median($fieldPoints), 1),
                'avg_hit_rate' => $totalTargetCount > 0
                    ? round($ranked->avg('total_hits') / $totalTargetCount * 100, 1) : 0,
                'top_score' => $fieldPoints->max(),
                'winner_name' => $ranked->first()['name'] ?? null,
                'winner_score' => $ranked->first()['total_points'] ?? 0,
                'hardest_gong' => $targetHitRates['hardest'],
                'easiest_gong' => $targetHitRates['easiest'],
                'furthest_hit' => $fieldFurthest,
            ],
            'fun_facts' => $this->generateElrFunFacts(
                $shooter, $rank, $allShooters->count(), $totalPoints, $totalHits,
                $totalTargetCount, $furthestHitM, $stageDetails, $ranked, $targetHitRates, $fieldFurthest
            ),
            'badges' => $this->shooterBadges($match, $shooter),
        ];
    }

    // ── Shared helpers ──────────────────────────────────────────────────

    private function shooterBadges(ShootingMatch $match, Shooter $shooter): array
    {
        if (! $shooter->user_id) {
            return [];
        }

        return UserAchievement::where('match_id', $match->id)
            ->where('user_id', $shooter->user_id)
            ->with('achievement')
            ->orderBy('awarded_at')
            ->get()
            ->filter(fn ($ua) => $ua->achievement !== null)
            ->map(fn ($ua) => [
                'label' => $ua->achievement->label,
                'description' => $ua->achievement->description,
                'category' => $ua->achievement->category,
                'competition_type' => $ua->achievement->competition_type ?? 'prs',
                'stage' => $ua->stage?->label,
                'metadata' => $ua->metadata,
            ])
            ->values()
            ->toArray();
    }

    private function getActiveShooters(ShootingMatch $match): Collection
    {
        return Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->where('shooters.status', 'active')
            ->select('shooters.id', 'shooters.name', 'shooters.bib_number')
            ->get();
    }

    private function matchMeta(ShootingMatch $match, int $totalShooters): array
    {
        return [
            'name' => $match->name,
            'date' => $match->date?->toDateString(),
            'location' => $match->location,
            'scoring_type' => $match->scoring_type ?? 'standard',
            'total_shooters' => $totalShooters,
        ];
    }

    private function shooterMeta(Shooter $shooter): array
    {
        return [
            'name' => $shooter->name,
            'bib_number' => $shooter->bib_number,
            'division' => $shooter->division?->name,
            'squad' => $shooter->squad?->name,
        ];
    }

    private function placement(int $rank, int $total): array
    {
        $percentile = $total > 0 ? round(($rank / $total) * 100, 1) : 0;

        return [
            'rank' => $rank,
            'total' => $total,
            'percentile' => $percentile,
        ];
    }

    /**
     * Best / worst stage is chosen by raw points (score), matching how the
     * match is won. Ties are broken by hit-rate so a stage with fewer
     * targets but a cleaner run still wins over a sloppy higher-target one.
     * We pass through hits/total so the report can show "3/5 impacts"
     * instead of a percentage — impacts is the canonical language shooters
     * use, percentage is derived and confusing next to the points value.
     */
    private function bestStage(array $stages): ?array
    {
        if (empty($stages)) return null;

        $best = collect($stages)
            ->sort(function ($a, $b) {
                if ($a['score'] !== $b['score']) return $b['score'] <=> $a['score'];
                return ($b['hit_rate'] ?? 0) <=> ($a['hit_rate'] ?? 0);
            })
            ->first();

        return $this->stageHeadline($best);
    }

    private function worstStage(array $stages): ?array
    {
        if (empty($stages)) return null;

        $worst = collect($stages)
            ->sort(function ($a, $b) {
                if ($a['score'] !== $b['score']) return $a['score'] <=> $b['score'];
                return ($a['hit_rate'] ?? 0) <=> ($b['hit_rate'] ?? 0);
            })
            ->first();

        return $this->stageHeadline($worst);
    }

    private function stageHeadline(array $stage): array
    {
        $hits = (int) ($stage['hits'] ?? 0);
        $misses = (int) ($stage['misses'] ?? 0);
        $noShots = (int) ($stage['no_shots'] ?? 0);
        $targets = $hits + $misses + $noShots;

        return [
            'label' => $stage['label'],
            'hit_rate' => $stage['hit_rate'] ?? 0,
            'score' => $stage['score'],
            'hits' => $hits,
            'targets' => $targets,
        ];
    }

    private function median(Collection $values): float
    {
        $sorted = $values->sort()->values();
        $count = $sorted->count();

        if ($count === 0) return 0;
        if ($count % 2 === 0) {
            return ($sorted[$count / 2 - 1] + $sorted[$count / 2]) / 2;
        }

        return $sorted[intdiv($count, 2)];
    }

    // ── Gong / shot hit-rate analysis ───────────────────────────────────

    private function standardGongHitRates(Collection $allScores, Collection $allGongs, int $shooterCount, array $gongDistanceMap = []): array
    {
        if ($shooterCount === 0 || $allGongs->isEmpty()) {
            return ['hardest' => null, 'easiest' => null];
        }

        $hitsByGong = $allScores->where('is_hit', true)->groupBy('gong_id')
            ->map(fn ($group) => $group->count());

        $gongRates = $allGongs->map(function ($g) use ($hitsByGong, $shooterCount, $gongDistanceMap) {
            $hits = $hitsByGong->get($g->id, 0);
            return [
                'gong_id' => $g->id,
                'label' => $g->label,
                'distance' => $gongDistanceMap[$g->id] ?? null,
                'hit_rate' => round($hits / $shooterCount * 100, 1),
            ];
        });

        $hardest = $gongRates->sortBy('hit_rate')->first();
        $easiest = $gongRates->sortByDesc('hit_rate')->first();

        $formatGong = function ($g) {
            $label = $g['label'];
            if ($g['distance']) $label .= " at {$g['distance']}m";
            return ['label' => $label, 'hit_rate' => $g['hit_rate']];
        };

        return [
            'hardest' => $hardest ? $formatGong($hardest) : null,
            'easiest' => $easiest ? $formatGong($easiest) : null,
        ];
    }

    private function prsShotHitRates(ShootingMatch $match, Collection $targetSets, int $shooterCount): array
    {
        if ($shooterCount === 0) {
            return ['hardest' => null, 'easiest' => null];
        }

        $shots = PrsShotScore::where('match_id', $match->id)->get();

        $grouped = $shots->groupBy(fn ($s) => "{$s->stage_id}-{$s->shot_number}");

        $rates = $grouped->map(function ($group, $key) use ($shooterCount, $targetSets) {
            [$stageId, $shotNumber] = explode('-', $key);
            $hits = $group->filter(function ($s) {
                $result = $s->result instanceof \BackedEnum ? $s->result->value : (string) $s->result;
                return $result === 'hit';
            })->count();

            $stage = $targetSets->firstWhere('id', (int) $stageId);
            $label = ($stage ? $stage->label : "Stage {$stageId}") . " Shot {$shotNumber}";

            return [
                'label' => $label,
                'hit_rate' => round($hits / $shooterCount * 100, 1),
            ];
        });

        return [
            'hardest' => $rates->sortBy('hit_rate')->first(),
            'easiest' => $rates->sortByDesc('hit_rate')->first(),
        ];
    }

    private function prsShotHitRatesLegacy(Collection $allScores, Collection $targetSets, int $shooterCount): array
    {
        if ($shooterCount === 0 || $targetSets->isEmpty()) {
            return ['hardest' => null, 'easiest' => null];
        }

        $allGongs = $targetSets->flatMap->gongs;
        $hitsByGong = collect();
        foreach ($allScores as $key => $score) {
            if ($score->is_hit) {
                $gongId = $score->gong_id;
                $hitsByGong[$gongId] = ($hitsByGong[$gongId] ?? 0) + 1;
            }
        }

        $rates = $allGongs->map(function ($g) use ($hitsByGong, $shooterCount, $targetSets) {
            $hits = $hitsByGong[$g->id] ?? 0;
            $stage = $targetSets->firstWhere('id', $g->target_set_id);
            $label = ($stage ? $stage->label : '') . " #{$g->number}";

            return [
                'label' => trim($label),
                'hit_rate' => round($hits / $shooterCount * 100, 1),
            ];
        });

        return [
            'hardest' => $rates->sortBy('hit_rate')->first(),
            'easiest' => $rates->sortByDesc('hit_rate')->first(),
        ];
    }

    private function elrTargetHitRates(Collection $allShots, Collection $stages, int $shooterCount): array
    {
        if ($shooterCount === 0) {
            return ['hardest' => null, 'easiest' => null];
        }

        $allTargets = $stages->flatMap->targets;
        $flatShots = $allShots->flatten(1);
        $hitsByTarget = $flatShots->filter(fn ($s) => $s->isHit())->groupBy('elr_target_id')
            ->map(fn ($group) => $group->unique('shooter_id')->count());

        $rates = $allTargets->map(function ($t) use ($hitsByTarget, $shooterCount) {
            $hitters = $hitsByTarget->get($t->id, 0);
            return [
                'label' => "{$t->name} ({$t->distance_m}m)",
                'hit_rate' => round($hitters / $shooterCount * 100, 1),
            ];
        });

        return [
            'hardest' => $rates->sortBy('hit_rate')->first(),
            'easiest' => $rates->sortByDesc('hit_rate')->first(),
        ];
    }

    // ── Fun facts ───────────────────────────────────────────────────────

    private function generateStandardFunFacts(
        Shooter $shooter, int $rank, int $total, float $totalScore, int $totalHits,
        int $totalGongCount, array $stages, Collection $ranked, array $gongHitRates,
        Collection $shooterScores, Collection $allGongs, Collection $fieldHitRates,
        array $gongDistanceMap = [], ?Collection $targetSets = null
    ): array {
        $facts = [];
        $percentile = $total > 0 ? round(($rank / $total) * 100, 1) : 0;

        $facts[] = "You finished #{$rank} out of {$total} shooters (top {$percentile}%)";

        $hitRate = $totalGongCount > 0 ? round($totalHits / $totalGongCount * 100, 1) : 0;
        $avgHitRate = round($fieldHitRates->avg(), 1);
        $facts[] = "You hit {$totalHits} of {$totalGongCount} targets — {$hitRate}% hit rate"
            . ($hitRate > $avgHitRate ? " (above field average of {$avgHitRate}%)" : '');

        if (!empty($stages)) {
            $best = collect($stages)->sortByDesc('hit_rate')->first();
            $beatCount = $this->shootersBeatOnStage($best, $ranked, $stages);
            if ($beatCount > 0) {
                $beatPct = round($beatCount / max($total - 1, 1) * 100, 0);
                $facts[] = "Your best stage was {$best['label']} — you beat {$beatPct}% of shooters there";
            }
        }

        $winner = $ranked->first();
        if ($winner && $winner['id'] !== $shooter->id) {
            $diff = round($winner['total'] - $totalScore, 2);
            $facts[] = "The winner ({$winner['name']}) scored {$winner['total']} — {$diff} points ahead of you";
        }

        if ($gongHitRates['hardest']) {
            $hardest = $gongHitRates['hardest'];
            $shooterHitHardest = $this->didShooterHitGong($shooterScores, $allGongs, $hardest, $gongDistanceMap);
            $verb = $shooterHitHardest ? 'you nailed it' : 'you missed it';
            $facts[] = "The hardest gong was {$hardest['label']} ({$hardest['hit_rate']}% hit rate) — {$verb}";
        }

        $streak = $targetSets !== null
            ? $this->longestHitStreak($shooterScores, $targetSets)
            : $this->longestHitStreakFlat($shooterScores, $allGongs);
        if ($streak >= 3) {
            $facts[] = "Your longest hit streak was {$streak} consecutive hits on a single stage";
        }

        return array_slice($facts, 0, 6);
    }

    private function generatePrsFunFacts(
        Shooter $shooter, int $rank, int $total, int $totalHits, int $totalTargets,
        float $totalTime, array $stages, Collection $ranked, array $shotHitRates
    ): array {
        $facts = [];
        $percentile = $total > 0 ? round(($rank / $total) * 100, 1) : 0;

        $facts[] = "You finished #{$rank} out of {$total} shooters (top {$percentile}%)";

        $hitRate = $totalTargets > 0 ? round($totalHits / $totalTargets * 100, 1) : 0;
        $avgHits = round($ranked->avg('hits'), 1);
        $facts[] = "You scored {$totalHits}/{$totalTargets} hits — {$hitRate}% hit rate";

        if ($totalHits > $avgHits) {
            $facts[] = "You beat the field average of {$avgHits} hits";
        }

        if ($totalTime > 0) {
            $avgTime = round($ranked->avg('agg_time'), 1);
            if ($totalTime < $avgTime) {
                $facts[] = "Your total time of {$totalTime}s was faster than the field average of {$avgTime}s";
            }
        }

        if (!empty($stages)) {
            $best = collect($stages)->sortByDesc('hit_rate')->first();
            $facts[] = "Your best stage was {$best['label']} with {$best['hit_rate']}% hit rate";
        }

        if ($shotHitRates['hardest']) {
            $hardest = $shotHitRates['hardest'];
            $facts[] = "The hardest shot was {$hardest['label']} — only {$hardest['hit_rate']}% of shooters hit it";
        }

        $winner = $ranked->first();
        if ($winner && $winner['id'] !== $shooter->id) {
            $diff = $winner['hits'] - $totalHits;
            $facts[] = "The winner ({$winner['name']}) had {$winner['hits']} hits — {$diff} more than you";
        }

        return array_slice($facts, 0, 6);
    }

    private function generateElrFunFacts(
        Shooter $shooter, int $rank, int $total, float $totalPoints, int $totalHits,
        int $totalTargets, int $furthestHitM, array $stages, Collection $ranked,
        array $targetHitRates, int $fieldFurthest
    ): array {
        $facts = [];
        $percentile = $total > 0 ? round(($rank / $total) * 100, 1) : 0;

        $facts[] = "You finished #{$rank} out of {$total} shooters (top {$percentile}%)";

        $hitRate = $totalTargets > 0 ? round($totalHits / $totalTargets * 100, 1) : 0;
        $facts[] = "You scored {$totalPoints} points hitting {$totalHits} of {$totalTargets} targets ({$hitRate}%)";

        if ($furthestHitM > 0) {
            $facts[] = "Your furthest hit was at {$furthestHitM}m";
            if ($furthestHitM === $fieldFurthest) {
                $facts[] = "That ties for the furthest hit of the entire match!";
            }
        }

        $avgPoints = round($ranked->avg('total_points'), 1);
        if ($totalPoints > $avgPoints) {
            $facts[] = "You beat the field average of {$avgPoints} points";
        }

        if ($targetHitRates['hardest']) {
            $hardest = $targetHitRates['hardest'];
            $facts[] = "The hardest target was {$hardest['label']} — only {$hardest['hit_rate']}% hit it";
        }

        $winner = $ranked->first();
        if ($winner && $winner['id'] !== $shooter->id) {
            $diff = round($winner['total_points'] - $totalPoints, 2);
            $facts[] = "The winner ({$winner['name']}) scored {$winner['total_points']} — {$diff} points ahead";
        }

        return array_slice($facts, 0, 6);
    }

    // ── Fun-fact sub-helpers ────────────────────────────────────────────

    private function shootersBeatOnStage(array $bestStage, Collection $ranked, array $stages): int
    {
        $stageLabel = $bestStage['label'];
        $myScore = $bestStage['score'];
        $count = 0;

        // We don't have per-shooter-per-stage data in the ranked collection,
        // so we approximate using the shooter's score vs their proportional stage contribution.
        // For a simpler metric, count those with a lower total.
        foreach ($ranked as $entry) {
            if ($entry['total'] < $myScore) $count++;
        }

        return $count;
    }

    private function didShooterHitGong(Collection $shooterScores, Collection $allGongs, array $hardest, array $gongDistanceMap = []): bool
    {
        $label = $hardest['label'];

        foreach ($allGongs as $g) {
            $gongLabel = $g->label;
            $distance = $gongDistanceMap[$g->id] ?? null;
            if ($distance) $gongLabel .= " at {$distance}m";

            if ($gongLabel === $label) {
                $score = $shooterScores->get($g->id);
                return $score && $score->is_hit;
            }
        }

        return false;
    }

    /**
     * Longest run of consecutive hits constrained to a single stage (target set).
     * The streak does NOT carry across distance/stage boundaries — e.g. a hit
     * on the last 400 m gong followed by a hit on the first 500 m gong is NOT
     * a streak of 2, it's two independent streaks of 1.
     */
    private function longestHitStreak(Collection $shooterScores, Collection $targetSets): int
    {
        $maxStreak = 0;

        foreach ($targetSets as $ts) {
            $currentStreak = 0;

            $gongs = $ts->gongs ?? collect();
            foreach ($gongs as $g) {
                $score = $shooterScores->get($g->id);
                if ($score && $score->is_hit) {
                    $currentStreak++;
                    $maxStreak = max($maxStreak, $currentStreak);
                } else {
                    $currentStreak = 0;
                }
            }
        }

        return $maxStreak;
    }

    /**
     * Fallback used only when target sets are not threaded through to the fun-facts
     * generator. Iterates the flat gong list with no stage boundaries.
     */
    private function longestHitStreakFlat(Collection $shooterScores, Collection $allGongs): int
    {
        $maxStreak = 0;
        $currentStreak = 0;

        foreach ($allGongs as $g) {
            $score = $shooterScores->get($g->id);
            if ($score && $score->is_hit) {
                $currentStreak++;
                $maxStreak = max($maxStreak, $currentStreak);
            } else {
                $currentStreak = 0;
            }
        }

        return $maxStreak;
    }
}
