<?php

namespace App\Services\Scoring;

use App\Enums\ElrShotResult;
use App\Models\ElrShot;
use App\Models\ElrStage;
use App\Models\ElrTarget;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use Illuminate\Support\Facades\DB;

class ELRScoringService implements ScoringEngineInterface
{
    public function calculateStandings(ShootingMatch $match, array $filters = []): array
    {
        $stages = $match->elrStages()
            ->with(['targets' => fn ($q) => $q->orderBy('sort_order'), 'scoringProfile'])
            ->get();

        $allTargetIds = $stages->flatMap(fn ($s) => $s->targets->pluck('id'))->toArray();

        $shooters = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->select('shooters.id', 'shooters.name', 'shooters.bib_number', 'shooters.status', 'squads.name as squad_name')
            ->get();

        if ($shooters->isEmpty() || empty($allTargetIds)) {
            return [
                'match' => $this->matchMeta($match),
                'stages' => $this->serializeStages($stages),
                'standings' => [],
            ];
        }

        $allShots = ElrShot::query()
            ->whereIn('shooter_id', $shooters->pluck('id'))
            ->whereIn('elr_target_id', $allTargetIds)
            ->get()
            ->groupBy('shooter_id');

        $standings = $shooters->map(function ($shooter) use ($allShots, $stages) {
            $shooterShots = $allShots->get($shooter->id, collect());
            $shotsByTarget = $shooterShots->groupBy('elr_target_id');

            $totalPoints = 0;
            $totalHits = 0;
            $firstRoundHits = 0;
            $secondRoundHits = 0;
            $furthestHitM = 0;
            $stageResults = [];

            foreach ($stages as $stage) {
                $stagePoints = 0;
                $stageTargets = [];

                foreach ($stage->targets as $target) {
                    $targetShots = $shotsByTarget->get($target->id, collect())->sortBy('shot_number');
                    $targetResult = [];

                    foreach ($targetShots as $shot) {
                        $targetResult[] = [
                            'shot_number' => $shot->shot_number,
                            'result' => $shot->result->value,
                            'points' => (float) $shot->points_awarded,
                        ];

                        if ($shot->isHit()) {
                            $totalHits++;
                            $stagePoints += (float) $shot->points_awarded;
                            $totalPoints += (float) $shot->points_awarded;

                            if ($shot->shot_number === 1) {
                                $firstRoundHits++;
                            } elseif ($shot->shot_number === 2) {
                                $secondRoundHits++;
                            }

                            if ($target->distance_m > $furthestHitM) {
                                $furthestHitM = $target->distance_m;
                            }
                        }
                    }

                    $stageTargets[] = [
                        'target_id' => $target->id,
                        'name' => $target->name,
                        'distance_m' => $target->distance_m,
                        'base_points' => (float) $target->base_points,
                        'max_shots' => $target->max_shots,
                        'shots' => $targetResult,
                    ];
                }

                $stageResults[] = [
                    'stage_id' => $stage->id,
                    'label' => $stage->label,
                    'stage_type' => $stage->stage_type->value,
                    'points' => round($stagePoints, 2),
                    'targets' => $stageTargets,
                ];
            }

            return [
                'id' => $shooter->id,
                'name' => $shooter->name,
                'bib_number' => $shooter->bib_number,
                'squad_name' => $shooter->squad_name,
                'status' => $shooter->status ?? 'active',
                'total_points' => round($totalPoints, 2),
                'total_hits' => $totalHits,
                'first_round_hits' => $firstRoundHits,
                'second_round_hits' => $secondRoundHits,
                'furthest_hit_m' => $furthestHitM,
                'stages' => $stageResults,
            ];
        });

        $sorted = $standings
            ->sortByDesc('total_points')
            ->sortByDesc('furthest_hit_m')
            ->sortByDesc('total_points')
            ->values();

        // Proper sort: points desc, then furthest hit desc as tiebreaker
        $sorted = $standings->sort(function ($a, $b) {
            if ($a['total_points'] !== $b['total_points']) {
                return $b['total_points'] <=> $a['total_points'];
            }
            if ($a['furthest_hit_m'] !== $b['furthest_hit_m']) {
                return $b['furthest_hit_m'] <=> $a['furthest_hit_m'];
            }
            return $b['first_round_hits'] <=> $a['first_round_hits'];
        })->values();

        $topScore = $sorted->first()['total_points'] ?? 0;

        $ranked = $sorted->map(function ($entry, $index) use ($topScore) {
            $entry['rank'] = $index + 1;
            $entry['normalized_score'] = $topScore > 0
                ? round(($entry['total_points'] / $topScore) * 100, 1)
                : 0;
            return $entry;
        });

        return [
            'match' => $this->matchMeta($match),
            'stages' => $this->serializeStages($stages),
            'standings' => $ranked->toArray(),
        ];
    }

    private function matchMeta(ShootingMatch $match): array
    {
        return [
            'name' => $match->name,
            'date' => $match->date?->toDateString(),
            'location' => $match->location,
            'scoring_type' => 'elr',
        ];
    }

    private function serializeStages($stages): array
    {
        return $stages->map(fn ($s) => [
            'id' => $s->id,
            'label' => $s->label,
            'stage_type' => $s->stage_type->value,
            'targets' => $s->targets->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'distance_m' => $t->distance_m,
                'base_points' => (float) $t->base_points,
                'max_shots' => $t->max_shots,
                'must_hit_to_advance' => $t->must_hit_to_advance,
            ]),
        ])->toArray();
    }

    /**
     * Validate and record a single ELR shot, computing points from profile.
     */
    public function recordShot(
        Shooter $shooter,
        ElrTarget $target,
        int $shotNumber,
        ElrShotResult $result,
        ?int $recordedBy = null,
        ?string $deviceId = null,
    ): ElrShot {
        $pointsAwarded = 0;

        if ($result === ElrShotResult::Hit) {
            $pointsAwarded = $target->pointsForShot($shotNumber);
        }

        return ElrShot::updateOrCreate(
            [
                'shooter_id' => $shooter->id,
                'elr_target_id' => $target->id,
                'shot_number' => $shotNumber,
            ],
            [
                'result' => $result,
                'points_awarded' => $pointsAwarded,
                'recorded_by' => $recordedBy,
                'device_id' => $deviceId,
                'recorded_at' => now(),
                'synced_at' => now(),
            ]
        );
    }

    /**
     * Return the current progression state for a shooter in an ELR stage.
     * For ladder stages, indicates which target is currently unlocked.
     */
    public function getStageProgress(ElrStage $stage, Shooter $shooter): array
    {
        $targets = $stage->targets;
        $shots = ElrShot::where('shooter_id', $shooter->id)
            ->whereIn('elr_target_id', $targets->pluck('id'))
            ->get()
            ->groupBy('elr_target_id');

        $progress = [];
        $ladderBlocked = false;

        foreach ($targets as $target) {
            $targetShots = $shots->get($target->id, collect());
            $hitAchieved = $targetShots->contains(fn ($s) => $s->isHit());
            $shotsTaken = $targetShots->count();
            $maxedOut = $shotsTaken >= $target->max_shots;

            $status = 'pending';
            if ($hitAchieved) {
                $status = 'hit';
            } elseif ($maxedOut) {
                $status = 'exhausted';
            } elseif ($shotsTaken > 0) {
                $status = 'in_progress';
            }

            $locked = $ladderBlocked && $stage->isLadder();

            $progress[] = [
                'target_id' => $target->id,
                'name' => $target->name,
                'distance_m' => $target->distance_m,
                'base_points' => (float) $target->base_points,
                'max_shots' => $target->max_shots,
                'shots_taken' => $shotsTaken,
                'hit' => $hitAchieved,
                'status' => $locked ? 'locked' : $status,
                'locked' => $locked,
            ];

            if ($stage->isLadder() && $target->must_hit_to_advance && ! $hitAchieved) {
                $ladderBlocked = true;
            }
        }

        return $progress;
    }
}
