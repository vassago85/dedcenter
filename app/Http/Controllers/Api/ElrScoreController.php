<?php

namespace App\Http\Controllers\Api;

use App\Enums\ElrShotResult;
use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Models\ElrShot;
use App\Models\ElrTarget;
use App\Models\ElrTeamStageEntry;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Rules\InIdSet;
use App\Services\ScoreAuditService;
use App\Services\Scoring\ELRScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ElrScoreController extends Controller
{
    public function store(Request $request, ShootingMatch $match)
    {
        $user = $request->user();

        if (! $user->can('score', $match)) {
            return response()->json(['message' => 'You are not authorized to score this match.'], 403);
        }

        if ($match->status === MatchStatus::Completed) {
            return response()->json([
                'message' => 'Match already scored. Re-open the match to edit scores.',
                'status' => 'completed',
            ], 423);
        }

        $validated = $request->validate([
            'shots' => ['required', 'array', 'min:1'],
            'shots.*.shooter_id' => ['required', 'integer', new InIdSet($match->shooters()->pluck('shooters.id'))],
            'shots.*.elr_target_id' => ['required', 'integer', new InIdSet(
                ElrTarget::whereIn('elr_stage_id', $match->elrStages()->select('id'))->pluck('id')
            )],
            'shots.*.shot_number' => ['required', 'integer', 'min:1'],
            'shots.*.result' => ['required', 'string', Rule::in(['hit', 'miss', 'not_taken'])],
            'shots.*.device_id' => ['required', 'string', 'max:255'],
            'shots.*.recorded_at' => ['required', 'date'],
        ]);

        $isTeam = $match->elrEngagementMode()->isTeamSequence();
        $service = new ELRScoringService;

        $shots = collect($validated['shots']);
        $match->loadMissing('elrScoringProfile');
        $targets = ElrTarget::with('stage.scoringProfile')
            ->whereIn('id', $shots->pluck('elr_target_id')->unique())
            ->get()
            ->each(fn (ElrTarget $t) => $t->stage?->setRelation('match', $match))
            ->keyBy('id');

        $shotKey = fn ($shooterId, $targetId, $shotNumber) => "{$shooterId}-{$targetId}-{$shotNumber}";
        $existingShots = ElrShot::whereIn('shooter_id', $shots->pluck('shooter_id')->unique())
            ->whereIn('elr_target_id', $targets->keys())
            ->get()
            ->keyBy(fn (ElrShot $s) => $shotKey($s->shooter_id, $s->elr_target_id, $s->shot_number));

        // (shooterId => [targetId => ElrTarget]) pairs touched this batch,
        // so team-sequence scoring can recompute each affected gong once.
        $affected = [];

        $savedShots = DB::transaction(function () use ($validated, $targets, $existingShots, $shotKey, $isTeam, $user, $match, $request, $service, &$affected) {
            $savedShots = [];

            foreach ($validated['shots'] as $shotData) {
                $target = $targets->get($shotData['elr_target_id']);
                if (! $target) {
                    continue;
                }

                $result = ElrShotResult::from($shotData['result']);

                // For team gong-sequence matches the impact-based recompute below
                // owns points/multiplier; we just persist the raw result here.
                // Every other mode keeps the existing shot-number scoring.
                $values = [
                    'result' => $result,
                    'distance_at_score' => (int) $target->distance_m,
                    'recorded_by' => $user->id,
                    'device_id' => $shotData['device_id'],
                    'recorded_at' => $shotData['recorded_at'],
                    'synced_at' => now(),
                ];
                if (! $isTeam) {
                    $values['points_awarded'] = $result === ElrShotResult::Hit
                        ? $target->pointsForShot($shotData['shot_number'])
                        : 0;
                    $values['multiplier_at_score'] = $target->multiplierForShot($shotData['shot_number']);
                }

                $key = $shotKey($shotData['shooter_id'], $target->id, $shotData['shot_number']);
                $existingShot = $existingShots->get($key);
                $oldShotValues = $existingShot?->toArray();

                $shot = $existingShot ?? new ElrShot([
                    'shooter_id' => $shotData['shooter_id'],
                    'elr_target_id' => $target->id,
                    'shot_number' => $shotData['shot_number'],
                ]);
                $shot->fill($values)->save();
                $existingShots->put($key, $shot);

                if ($existingShot && $oldShotValues && ($oldShotValues['result'] ?? '') !== $shotData['result']) {
                    ScoreAuditService::logUpdated($match->id, $shot, $oldShotValues, null, $request);
                } elseif (! $existingShot) {
                    ScoreAuditService::logCreated($match->id, $shot, $request);
                }

                $affected[$shotData['shooter_id']][$target->id] = $target;
                $savedShots[] = $shot;
            }

            if ($isTeam) {
                foreach ($affected as $shooterId => $shooterTargets) {
                    foreach ($shooterTargets as $target) {
                        $service->recomputeTargetImpacts((int) $shooterId, $target);
                    }
                }

                $this->reopenAffectedTeamStages($match, $affected, $request);
            }

            return $savedShots;
        });

        // Team-sequence recompute may have changed points/impact on the
        // submitted shots, so re-read those once; other modes are current.
        if ($isTeam && $savedShots) {
            $fresh = ElrShot::whereIn('shooter_id', array_keys($affected))
                ->whereIn('elr_target_id', $targets->keys())
                ->get()
                ->keyBy(fn (ElrShot $s) => $shotKey($s->shooter_id, $s->elr_target_id, $s->shot_number));
            $savedShots = array_filter(array_map(
                fn (ElrShot $s) => $fresh->get($shotKey($s->shooter_id, $s->elr_target_id, $s->shot_number)),
                $savedShots,
            ));
        }

        $saved = array_values(array_map(fn (ElrShot $shot) => [
            'id' => $shot->id,
            'shooter_id' => $shot->shooter_id,
            'elr_target_id' => $shot->elr_target_id,
            'shot_number' => $shot->shot_number,
            'impact_number' => $shot->impact_number,
            'result' => $shot->result->value,
            'points_awarded' => (float) $shot->points_awarded,
        ], $savedShots));

        return response()->json(['data' => $saved]);
    }

    /**
     * Start / update / finish a team's turn at a stage (team gong-sequence
     * mode). Upserts the single (team x stage) lifecycle row used by the
     * countdown timer and stage rotation. Used to record started_at when a
     * team begins, completed_at + timed_out when it finishes or runs out of
     * time, and the first-shooter / firing-order recommendation.
     */
    public function teamStage(Request $request, ShootingMatch $match)
    {
        $user = $request->user();

        if (! $user->can('score', $match)) {
            return response()->json(['message' => 'You are not authorized to score this match.'], 403);
        }

        if ($match->status === MatchStatus::Completed) {
            return response()->json([
                'message' => 'Match already scored. Re-open the match to edit scores.',
                'status' => 'completed',
            ], 423);
        }

        $validTeamIds = $match->teams()->pluck('id')->toArray();
        $validStageIds = $match->elrStages()->pluck('id')->toArray();
        $validShooterIds = $match->shooters()->pluck('shooters.id')->toArray();
        $validSquadIds = $match->squads()->pluck('id')->toArray();

        $validated = $request->validate([
            'team_id' => ['required', 'integer', Rule::in($validTeamIds)],
            'elr_stage_id' => ['required', 'integer', Rule::in($validStageIds)],
            'squad_id' => ['nullable', 'integer', Rule::in($validSquadIds)],
            'first_shooter_id' => ['nullable', 'integer', Rule::in($validShooterIds)],
            'position' => ['nullable', 'integer', 'min:1'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'timed_out' => ['nullable', 'boolean'],
            // MD-captured reason for shooting past the team time limit. Free
            // text so the field can carry quick reasons ("Equipment", "Target
            // malfunction") or a longer note. Capped at the column length.
            'overtime_reason' => ['nullable', 'string', 'max:500'],
            'device_id' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = ElrTeamStageEntry::firstOrNew([
            'team_id' => $validated['team_id'],
            'elr_stage_id' => $validated['elr_stage_id'],
        ]);

        $wasCompleted = $entry->exists && $entry->completed_at !== null;

        foreach (['squad_id', 'first_shooter_id', 'position', 'started_at', 'device_id'] as $field) {
            if (array_key_exists($field, $validated) && $validated[$field] !== null) {
                $entry->{$field} = $validated[$field];
            }
        }
        // completed_at / timed_out / overtime_reason are explicitly settable
        // to null so a correction can reopen a finished entry or clear an
        // overtime reason after the fact.
        if (array_key_exists('completed_at', $validated)) {
            $entry->completed_at = $validated['completed_at'];
        }
        if (array_key_exists('timed_out', $validated)) {
            $entry->timed_out = (bool) $validated['timed_out'];
        }
        if (array_key_exists('overtime_reason', $validated)) {
            $entry->overtime_reason = $validated['overtime_reason'];
        }

        $entry->save();

        app(\App\Services\Scoring\ElrSquadTeamOrderService::class)->recordFromTeamStageEntry($entry);

        // Snapshot the team's per-stage scores when it finishes; clear them if
        // a correction reopens the entry. Rankings still compute live from
        // shots — these stored values are for record + exports.
        if ($entry->completed_at !== null) {
            $this->storeTeamStageScores($match, $entry);
        } elseif ($wasCompleted) {
            $entry->forceFill([
                'team_total_score' => null,
                'shooter_1_id' => null,
                'shooter_1_score' => null,
                'shooter_2_id' => null,
                'shooter_2_score' => null,
            ])->save();
        }

        // Reopening a finalized entry (completed -> not completed) is an audit
        // event so MDs can see a team's stage was edited after the fact.
        if ($wasCompleted && $entry->completed_at === null) {
            ScoreAuditService::log(
                $match->id,
                $entry,
                'reopened',
                ['completed_at' => $entry->getOriginal('completed_at')],
                ['completed_at' => null],
                null,
                $request,
            );
        }

        return response()->json([
            'data' => [
                'id' => $entry->id,
                'team_id' => $entry->team_id,
                'elr_stage_id' => $entry->elr_stage_id,
                'squad_id' => $entry->squad_id,
                'first_shooter_id' => $entry->first_shooter_id,
                'position' => $entry->position,
                'started_at' => $entry->started_at?->toIso8601String(),
                'completed_at' => $entry->completed_at?->toIso8601String(),
                'timed_out' => (bool) $entry->timed_out,
            ],
        ]);
    }

    /**
     * Compute and persist a team's per-stage snapshot scores from elr_shots:
     * each shooter's hit points on this stage's targets, and the team total.
     * shooter_1 follows the entry's first_shooter_id (S1) when set.
     */
    private function storeTeamStageScores(ShootingMatch $match, ElrTeamStageEntry $entry): void
    {
        $shooters = $match->shooters()
            ->where('shooters.team_id', $entry->team_id)
            ->get(['shooters.id', 'shooters.name']);

        // Order so shooter_1 is the designated first shooter for this stage.
        $ordered = $shooters->sortBy(fn ($s) => $s->id === $entry->first_shooter_id ? 0 : 1)->values();
        $s1 = $ordered->get(0);
        $s2 = $ordered->get(1);

        $points = ElrShot::whereIn('shooter_id', $ordered->take(2)->pluck('id'))
            ->whereIn('elr_target_id', ElrTarget::where('elr_stage_id', $entry->elr_stage_id)->select('id'))
            ->where('result', ElrShotResult::Hit)
            ->groupBy('shooter_id')
            ->selectRaw('shooter_id, SUM(points_awarded) as points')
            ->pluck('points', 'shooter_id');

        $s1Score = $s1 ? (float) ($points[$s1->id] ?? 0) : 0.0;
        $s2Score = $s2 ? (float) ($points[$s2->id] ?? 0) : 0.0;

        $entry->forceFill([
            'shooter_1_id' => $s1?->id,
            'shooter_1_score' => $s1Score,
            'shooter_2_id' => $s2?->id,
            'shooter_2_score' => $s2Score,
            'team_total_score' => round($s1Score + $s2Score, 2),
        ])->save();
    }

    /**
     * When a shot is corrected after a team finished a stage, clear that
     * (team x stage) entry's completion so the standings/timer reflect the
     * reopened state, and log it. Silent no-op when nothing was completed.
     */
    private function reopenAffectedTeamStages(ShootingMatch $match, array $affected, Request $request): void
    {
        $shooterIds = array_keys($affected);
        if (empty($shooterIds)) {
            return;
        }

        $teamByShooter = Shooter::whereIn('id', $shooterIds)->pluck('team_id', 'id');

        // Collect the distinct (team, stage) pairs this correction touched.
        $pairs = [];
        foreach ($affected as $shooterId => $targets) {
            $teamId = $teamByShooter[$shooterId] ?? null;
            if (! $teamId) {
                continue;
            }
            foreach ($targets as $target) {
                $stageId = $target->elr_stage_id;
                $pairs["{$teamId}-{$stageId}"] = [$teamId, $stageId];
            }
        }

        if (! $pairs) {
            return;
        }

        $entries = ElrTeamStageEntry::whereIn('team_id', array_column($pairs, 0))
            ->whereIn('elr_stage_id', array_column($pairs, 1))
            ->whereNotNull('completed_at')
            ->get()
            ->filter(fn ($e) => isset($pairs["{$e->team_id}-{$e->elr_stage_id}"]));

        foreach ($entries as $entry) {
            $old = ['completed_at' => $entry->completed_at?->toIso8601String()];
            $entry->completed_at = null;
            $entry->timed_out = false;
            $entry->save();

            ScoreAuditService::log(
                $match->id,
                $entry,
                'reopened',
                $old,
                ['completed_at' => null, 'reason' => 'shot corrected after completion'],
                null,
                $request,
            );
        }
    }
}
