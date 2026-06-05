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
use App\Services\ScoreAuditService;
use App\Services\Scoring\ELRScoringService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ElrScoreController extends Controller
{
    public function store(Request $request, ShootingMatch $match)
    {
        $user = $request->user();

        $canScore = $user->isOwner()
            || $match->created_by === $user->id
            || ($match->organization && $user->isOrgRangeOfficer($match->organization));

        if (! $canScore) {
            return response()->json(['message' => 'You are not authorized to score this match.'], 403);
        }

        if ($match->status === MatchStatus::Completed) {
            return response()->json([
                'message' => 'Match already scored. Re-open the match to edit scores.',
                'status' => 'completed',
            ], 423);
        }

        $validShooterIds = $match->shooters()->pluck('shooters.id')->toArray();
        $validTargetIds = ElrTarget::query()
            ->whereIn('elr_stage_id', $match->elrStages()->pluck('id'))
            ->pluck('id')
            ->toArray();

        $validated = $request->validate([
            'shots' => ['required', 'array', 'min:1'],
            'shots.*.shooter_id' => ['required', 'integer', Rule::in($validShooterIds)],
            'shots.*.elr_target_id' => ['required', 'integer', Rule::in($validTargetIds)],
            'shots.*.shot_number' => ['required', 'integer', 'min:1'],
            'shots.*.result' => ['required', 'string', Rule::in(['hit', 'miss', 'not_taken'])],
            'shots.*.device_id' => ['required', 'string', 'max:255'],
            'shots.*.recorded_at' => ['required', 'date'],
        ]);

        $isTeam = $match->elrEngagementMode()->isTeamSequence();
        $service = new ELRScoringService;

        $saved = [];
        // (shooterId => [targetId => ElrTarget]) pairs touched this batch,
        // so team-sequence scoring can recompute each affected gong once.
        $affected = [];
        $submittedKeys = [];

        foreach ($validated['shots'] as $shotData) {
            $target = ElrTarget::with('stage.scoringProfile', 'stage.match.elrScoringProfile')->find($shotData['elr_target_id']);
            if (! $target) {
                continue;
            }

            $result = ElrShotResult::from($shotData['result']);

            // For team gong-sequence matches the impact-based recompute below
            // owns points/multiplier; we just persist the raw result here.
            // Every other mode keeps the existing shot-number scoring.
            $pointsAwarded = 0;
            $multiplier = $target->multiplierForShot($shotData['shot_number']);
            if (! $isTeam && $result === ElrShotResult::Hit) {
                $pointsAwarded = $target->pointsForShot($shotData['shot_number']);
            }

            $existingShot = ElrShot::where('shooter_id', $shotData['shooter_id'])
                ->where('elr_target_id', $shotData['elr_target_id'])
                ->where('shot_number', $shotData['shot_number'])
                ->first();
            $oldShotValues = $existingShot?->toArray();

            $values = [
                'result' => $result,
                'distance_at_score' => (int) $target->distance_m,
                'recorded_by' => $user->id,
                'device_id' => $shotData['device_id'],
                'recorded_at' => $shotData['recorded_at'],
                'synced_at' => now(),
            ];
            if (! $isTeam) {
                $values['points_awarded'] = $pointsAwarded;
                $values['multiplier_at_score'] = $multiplier;
            }

            $shot = ElrShot::updateOrCreate(
                [
                    'shooter_id' => $shotData['shooter_id'],
                    'elr_target_id' => $shotData['elr_target_id'],
                    'shot_number' => $shotData['shot_number'],
                ],
                $values,
            );

            if ($existingShot && $oldShotValues && ($oldShotValues['result'] ?? '') !== $shotData['result']) {
                ScoreAuditService::logUpdated($match->id, $shot, $oldShotValues, null, $request);
            } elseif (! $existingShot) {
                ScoreAuditService::logCreated($match->id, $shot, $request);
            }

            $affected[$shotData['shooter_id']][$target->id] = $target;
            $submittedKeys[] = [$shotData['shooter_id'], $target->id, $shotData['shot_number']];
        }

        if ($isTeam) {
            foreach ($affected as $shooterId => $targets) {
                foreach ($targets as $target) {
                    $service->recomputeTargetImpacts((int) $shooterId, $target);
                }
            }

            $this->reopenAffectedTeamStages($match, $affected, $request);
        }

        // Build the response from the freshest stored values (team-sequence
        // recompute may have changed points/impact on the submitted shots).
        foreach ($submittedKeys as [$shooterId, $targetId, $shotNumber]) {
            $shot = ElrShot::where('shooter_id', $shooterId)
                ->where('elr_target_id', $targetId)
                ->where('shot_number', $shotNumber)
                ->first();
            if (! $shot) {
                continue;
            }

            $saved[] = [
                'id' => $shot->id,
                'shooter_id' => $shot->shooter_id,
                'elr_target_id' => $shot->elr_target_id,
                'shot_number' => $shot->shot_number,
                'impact_number' => $shot->impact_number,
                'result' => $shot->result->value,
                'points_awarded' => (float) $shot->points_awarded,
            ];
        }

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

        $canScore = $user->isOwner()
            || $match->created_by === $user->id
            || ($match->organization && $user->isOrgRangeOfficer($match->organization));

        if (! $canScore) {
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
        // completed_at / timed_out are explicitly settable to null so a
        // correction can reopen a finished entry.
        if (array_key_exists('completed_at', $validated)) {
            $entry->completed_at = $validated['completed_at'];
        }
        if (array_key_exists('timed_out', $validated)) {
            $entry->timed_out = (bool) $validated['timed_out'];
        }

        $entry->save();

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
        $stageTargetIds = ElrTarget::where('elr_stage_id', $entry->elr_stage_id)->pluck('id');

        $shooters = Shooter::whereHas('squad', fn ($q) => $q->where('match_id', $match->id))
            ->where('team_id', $entry->team_id)
            ->get(['id', 'name']);

        $scoreFor = fn (int $shooterId): float => (float) ElrShot::where('shooter_id', $shooterId)
            ->whereIn('elr_target_id', $stageTargetIds)
            ->where('result', ElrShotResult::Hit)
            ->sum('points_awarded');

        // Order so shooter_1 is the designated first shooter for this stage.
        $ordered = $shooters->sortBy(fn ($s) => $s->id === $entry->first_shooter_id ? 0 : 1)->values();
        $s1 = $ordered->get(0);
        $s2 = $ordered->get(1);

        $s1Score = $s1 ? $scoreFor($s1->id) : 0.0;
        $s2Score = $s2 ? $scoreFor($s2->id) : 0.0;

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

        foreach ($pairs as [$teamId, $stageId]) {
            $entry = ElrTeamStageEntry::where('team_id', $teamId)
                ->where('elr_stage_id', $stageId)
                ->whereNotNull('completed_at')
                ->first();
            if (! $entry) {
                continue;
            }

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

    public function progress(Request $request, ShootingMatch $match)
    {
        $shooterId = $request->query('shooter_id');
        if (! $shooterId) {
            return response()->json(['message' => 'shooter_id required'], 422);
        }

        $shooter = $match->shooters()->where('shooters.id', $shooterId)->first();
        if (! $shooter) {
            return response()->json(['message' => 'Shooter not found in this match'], 404);
        }

        $service = new ELRScoringService;
        $stages = $match->elrStages()->with(['targets', 'scoringProfile'])->get();

        $progress = $stages->map(fn ($stage) => [
            'stage_id' => $stage->id,
            'label' => $stage->label,
            'stage_type' => $stage->stage_type->value,
            'targets' => $service->getStageProgress($stage, $shooter),
        ]);

        return response()->json(['data' => $progress]);
    }
}
