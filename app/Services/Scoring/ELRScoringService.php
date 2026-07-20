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
    /**
     * Build ELR standings for a match.
     *
     * Supports an optional `division` filter (passed as either a division id
     * or a name) so the scoreboard, exports, and series aggregator can rank
     * Minor and Major separately for matches that run multiple divisions.
     * Ranks are always assigned within the filtered set so a Minor podium
     * never inherits a Major shooter's #1 from the global list.
     */
    public function calculateStandings(ShootingMatch $match, array $filters = [], bool $completedOnly = false): array
    {
        $stages = $match->elrStages()
            ->with(['targets' => fn ($q) => $q->orderBy('sort_order'), 'scoringProfile'])
            ->orderBy('sort_order')
            ->get();

        $allTargetIds = $stages->flatMap(fn ($s) => $s->targets->pluck('id'))->toArray();

        // When true, only stages a shooter's team has completed count toward
        // totals (team gong-sequence rankings). ElrRankingService passes true;
        // scoreboard/season/CSV standings pass false explicitly.
        $completedByTeam = [];
        if ($completedOnly) {
            \App\Models\ElrTeamStageEntry::query()
                ->whereHas('stage', fn ($q) => $q->where('match_id', $match->id))
                ->whereNotNull('completed_at')
                ->get(['team_id', 'elr_stage_id'])
                ->each(function ($entry) use (&$completedByTeam) {
                    $completedByTeam[$entry->team_id][$entry->elr_stage_id] = true;
                });
        }

        $shooterQuery = Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->leftJoin('match_divisions', 'shooters.match_division_id', '=', 'match_divisions.id')
            ->leftJoin('teams', 'shooters.team_id', '=', 'teams.id')
            // match_registrations.caliber is the canonical source for shooter
            // caliber (see MatchExportController). Join on user_id so the
            // leaderboard can show "300 PRC" / ".408 CT" without a per-row
            // lookup. shooters without a registration (walk-ins) get null,
            // which the UI renders as a blank cell.
            ->leftJoin('match_registrations', function ($join) use ($match) {
                $join->on('match_registrations.user_id', '=', 'shooters.user_id')
                    ->where('match_registrations.match_id', '=', $match->id);
            })
            ->where('squads.match_id', $match->id)
            ->select(
                'shooters.id',
                'shooters.user_id',
                'shooters.name',
                'shooters.bib_number',
                'shooters.status',
                'shooters.match_division_id',
                'shooters.team_id',
                'shooters.is_coached',
                'shooters.gong_position',
                'squads.name as squad_name',
                'teams.name as team_name',
                'match_divisions.name as division_name',
                'match_registrations.caliber as caliber',
            );

        $divisionFilter = $filters['division'] ?? null;
        if ($divisionFilter !== null && $divisionFilter !== '') {
            // Accept either an id or a name so the controller can pass through
            // whatever query string the API client sent.
            if (is_numeric($divisionFilter)) {
                $shooterQuery->where('shooters.match_division_id', (int) $divisionFilter);
            } else {
                $shooterQuery->where('match_divisions.name', $divisionFilter);
            }
        }

        $shooters = $shooterQuery->get();

        if ($shooters->isEmpty() || empty($allTargetIds)) {
            return [
                'match' => $this->matchMeta($match),
                'stages' => $this->serializeStages($stages),
                'standings' => [],
                'divisions' => $this->serializeDivisions($match),
                'active_division' => $divisionFilter,
            ];
        }

        // Per-division target whitelist (Peregrine: Minor=T1-T3, Major=T2-T4).
        // Lazy-loaded; only populated for divisions that have explicit pivot
        // rows. Divisions with no rows mean "no restriction" \u2014 see
        // MatchDivision::elrTargets() docstring.
        $divisionTargetWhitelist = $this->resolveDivisionTargetWhitelist($shooters);

        $allShots = ElrShot::query()
            ->whereIn('shooter_id', $shooters->pluck('id'))
            ->whereIn('elr_target_id', $allTargetIds)
            ->get()
            ->groupBy('shooter_id');

        // Category slug lookup: (shooter_id => [slug, ...]) built with a
        // single pivot query so ALRHA (and any category-driven prize
        // table) can filter without n+1 lookups per shooter.
        $categoryLookup = [];
        DB::table('match_category_shooter')
            ->join('match_categories', 'match_categories.id', '=', 'match_category_shooter.match_category_id')
            ->whereIn('match_category_shooter.shooter_id', $shooters->pluck('id'))
            ->get(['match_category_shooter.shooter_id', 'match_categories.slug'])
            ->each(function ($row) use (&$categoryLookup) {
                $slug = strtolower((string) $row->slug);
                if ($slug === '') {
                    return;
                }
                $categoryLookup[(int) $row->shooter_id][] = $slug;
            });

        $standings = $shooters->map(function ($shooter) use ($allShots, $stages, $divisionTargetWhitelist, $completedOnly, $completedByTeam, $categoryLookup) {
            $shooterShots = $allShots->get($shooter->id, collect());
            $shotsByTarget = $shooterShots->groupBy('elr_target_id');

            $allowedTargetIds = $shooter->match_division_id !== null
                ? ($divisionTargetWhitelist[$shooter->match_division_id] ?? null)
                : null;

            $totalPoints = 0;
            $totalHits = 0;
            $shotsFired = 0;
            $firstRoundHits = 0;
            $secondRoundHits = 0;
            $furthestHitM = 0;
            $furthestFirstRoundHitM = 0;
            $stageResults = [];

            foreach ($stages as $stage) {
                if ($completedOnly && $shooter->team_id) {
                    if (! isset($completedByTeam[$shooter->team_id][$stage->id])) {
                        $stageResults[] = [
                            'stage_id' => $stage->id,
                            'label' => $stage->label,
                            'stage_type' => $stage->stage_type->value,
                            'points' => 0.0,
                            'targets' => [],
                            'completed' => false,
                        ];
                        continue;
                    }
                }

                $stagePoints = 0;
                $stageTargets = [];

                foreach ($stage->targets as $target) {
                    // Skip targets this shooter's division doesn't engage.
                    // Whitelist is null => no restriction => include all.
                    if ($allowedTargetIds !== null && ! isset($allowedTargetIds[$target->id])) {
                        continue;
                    }

                    $targetShots = $shotsByTarget->get($target->id, collect())->sortBy('shot_number');
                    $targetResult = [];

                    foreach ($targetShots as $shot) {
                        // Only Hit and Miss count toward shots-fired for
                        // hit-rate %. NotTaken means "round skipped" and is
                        // excluded from both numerator and denominator.
                        if ($shot->result !== ElrShotResult::NotTaken) {
                            $shotsFired++;
                        }

                        $targetResult[] = [
                            'shot_number' => $shot->shot_number,
                            'result' => $shot->result->value,
                            'points' => (float) $shot->points_awarded,
                        ];

                        if ($shot->isHit()) {
                            $totalHits++;
                            $stagePoints += (float) $shot->points_awarded;
                            $totalPoints += (float) $shot->points_awarded;

                            // Furthest impact uses the snapshotted distance
                            // where present so changing target distances
                            // mid-match doesn't retroactively change history.
                            $hitDistance = $shot->distance_at_score !== null
                                ? (int) $shot->distance_at_score
                                : (int) $target->distance_m;

                            if ($shot->shot_number === 1) {
                                $firstRoundHits++;
                                if ($hitDistance > $furthestFirstRoundHitM) {
                                    $furthestFirstRoundHitM = $hitDistance;
                                }
                            } elseif ($shot->shot_number === 2) {
                                $secondRoundHits++;
                            }

                            if ($hitDistance > $furthestHitM) {
                                $furthestHitM = $hitDistance;
                            }
                        }
                    }

                    $stageTargets[] = [
                        'target_id' => $target->id,
                        'name' => $target->name,
                        'distance_m' => $target->distance_m,
                        'base_points' => (float) $target->base_points,
                        'max_shots' => $target->max_shots,
                        'is_cold_bore' => (bool) $target->is_cold_bore,
                        'alrha_block' => $target->alrha_block,
                        'shots' => $targetResult,
                    ];
                }

                $stageResults[] = [
                    'stage_id' => $stage->id,
                    'label' => $stage->label,
                    'stage_type' => $stage->stage_type->value,
                    'points' => round($stagePoints, 2),
                    'targets' => $stageTargets,
                    'completed' => ! $completedOnly || ! $shooter->team_id || isset($completedByTeam[$shooter->team_id][$stage->id]),
                ];
            }

            $hitRatePct = $shotsFired > 0
                ? round(($totalHits / $shotsFired) * 100, 1)
                : 0.0;

            return [
                'id' => $shooter->id,
                'user_id' => $shooter->user_id,
                'name' => $shooter->name,
                'bib_number' => $shooter->bib_number,
                'squad_name' => $shooter->squad_name,
                'team_id' => $shooter->team_id ? (int) $shooter->team_id : null,
                'team' => $shooter->team_name,
                'caliber' => $shooter->caliber,
                'division_id' => $shooter->match_division_id ? (int) $shooter->match_division_id : null,
                'division' => $shooter->division_name,
                'status' => $shooter->status ?? 'active',
                'is_coached' => (bool) ($shooter->is_coached ?? false),
                'gong_position' => $shooter->gong_position ? (int) $shooter->gong_position : null,
                'category_slugs' => $categoryLookup[$shooter->id] ?? [],
                'total_points' => round($totalPoints, 2),
                'total_hits' => $totalHits,
                'shots_fired' => $shotsFired,
                'hit_rate_pct' => $hitRatePct,
                'first_round_hits' => $firstRoundHits,
                'second_round_hits' => $secondRoundHits,
                'furthest_hit_m' => $furthestHitM,
                'furthest_first_round_hit_m' => $furthestFirstRoundHitM,
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
            'teams' => $this->buildTeamStandings($match, $ranked),
            'divisions' => $this->serializeDivisions($match),
            'active_division' => $divisionFilter,
        ];
    }

    /**
     * Per-team leaderboard for team gong-sequence matches. Returns [] for
     * every other mode so the scoreboard can hide the team tab cleanly.
     * Built from the already-ranked individual standings so it costs no
     * extra queries beyond loading team names/divisions.
     *
     * @param  \Illuminate\Support\Collection  $standings  ranked shooter rows
     */
    private function buildTeamStandings(ShootingMatch $match, \Illuminate\Support\Collection $standings): array
    {
        if (! $match->elrEngagementMode()->isTeamSequence()) {
            return [];
        }

        $teamMeta = $match->teams()->with('shooters.division')->get()->keyBy('id');

        $teams = $standings
            ->filter(fn ($s) => ! empty($s['team_id']))
            ->groupBy('team_id')
            ->map(function ($members, $teamId) use ($teamMeta) {
                $members = $members->sortByDesc('total_points')->values();
                $meta = $teamMeta->get((int) $teamId);

                return [
                    'team_id' => (int) $teamId,
                    'team' => $meta?->name ?? ($members[0]['team'] ?? null),
                    'division_category' => $meta?->divisionCategoryLabel(),
                    'team_total_score' => round($members->sum('total_points'), 2),
                    'team_total_hits' => $members->sum('total_hits'),
                    'shooter_1_id' => $members[0]['id'] ?? null,
                    'shooter_1_name' => $members[0]['name'] ?? null,
                    'shooter_1_score' => $members[0]['total_points'] ?? 0,
                    'shooter_2_id' => $members[1]['id'] ?? null,
                    'shooter_2_name' => $members[1]['name'] ?? null,
                    'shooter_2_score' => $members[1]['total_points'] ?? 0,
                    'members' => $members->map(fn ($m) => [
                        'id' => $m['id'],
                        'name' => $m['name'],
                        'division' => $m['division'],
                        'total_points' => $m['total_points'],
                    ])->all(),
                ];
            })
            ->sortByDesc('team_total_score')
            ->values();

        return $teams->map(function ($team, $index) {
            $team['rank'] = $index + 1;

            return $team;
        })->all();
    }

    /**
     * List a match's divisions for the API payload so the Vue scoreboard can
     * render a chip filter without needing a second round trip.
     */
    private function serializeDivisions(ShootingMatch $match): array
    {
        return $match->divisions()
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->map(fn ($d) => ['id' => (int) $d->id, 'name' => $d->name])
            ->all();
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
                'is_cold_bore' => (bool) $t->is_cold_bore,
                'alrha_block' => $t->alrha_block,
            ]),
        ])->toArray();
    }

    /**
     * Build a per-division allowed-target map for this match's shooters.
     *
     * Shape: `[ match_division_id => [ elr_target_id => true, ... ], ... ]`
     * \u2014 the inner array is keyed by id for O(1) lookup in the per-shot loop.
     * Divisions without any pivot rows are deliberately omitted so the
     * caller falls back to "include every target".
     */
    private function resolveDivisionTargetWhitelist(\Illuminate\Support\Collection $shooters): array
    {
        $divisionIds = $shooters
            ->pluck('match_division_id')
            ->filter()
            ->unique()
            ->values();

        if ($divisionIds->isEmpty()) {
            return [];
        }

        $rows = DB::table('elr_division_targets')
            ->whereIn('match_division_id', $divisionIds)
            ->get(['match_division_id', 'elr_target_id']);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->match_division_id][(int) $row->elr_target_id] = true;
        }

        return $map;
    }

    /**
     * Validate and record a single ELR shot, computing points from profile
     * and snapshotting the distance + multiplier in effect at this moment.
     *
     * The snapshot is what makes historical scores immutable: even if a
     * match director later edits a target's distance or the profile's
     * shot-1 multiplier, the points already on the leaderboard came from
     * THESE numbers and stay reproducible.
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
        $multiplier = $target->multiplierForShot($shotNumber);

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
                'distance_at_score' => (int) $target->distance_m,
                'multiplier_at_score' => $multiplier,
                'recorded_by' => $recordedBy,
                'device_id' => $deviceId,
                'recorded_at' => now(),
                'synced_at' => now(),
            ]
        );
    }

    /**
     * Recompute impact-based points for every shot a shooter has on a single
     * gong (team gong-sequence mode). Hits are numbered 1..N in shot order
     * and take the multiplier for that IMPACT; misses get impact_number null
     * and zero points so they never consume a multiplier slot. Idempotent —
     * safe to call after any correction to the gong.
     *
     * Only rows whose impact_number / points / multiplier actually change are
     * saved, so the audit trail isn't polluted with no-op writes.
     */
    public function recomputeTargetImpacts(int $shooterId, ElrTarget $target): void
    {
        $shots = ElrShot::where('shooter_id', $shooterId)
            ->where('elr_target_id', $target->id)
            ->orderBy('shot_number')
            ->get();

        $impact = 0;
        foreach ($shots as $shot) {
            if ($shot->result === ElrShotResult::Hit) {
                $impact++;
                $shot->impact_number = $impact;
                $shot->multiplier_at_score = $target->multiplierForShot($impact);
                $shot->points_awarded = $target->pointsForImpact($impact);
            } else {
                $shot->impact_number = null;
                $shot->multiplier_at_score = 0;
                $shot->points_awarded = 0;
            }

            if ($shot->isDirty()) {
                $shot->save();
            }
        }
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
