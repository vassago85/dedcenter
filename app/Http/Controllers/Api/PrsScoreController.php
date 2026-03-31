<?php

namespace App\Http\Controllers\Api;

use App\Enums\PrsShotResult;
use App\Http\Controllers\Controller;
use App\Models\PrsShotScore;
use App\Models\PrsStageResult;
use App\Models\ShootingMatch;
use App\Models\TargetSet;
use App\Services\ScoreAuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrsScoreController extends Controller
{
    public function store(Request $request, ShootingMatch $match, TargetSet $stage)
    {
        $user = $request->user();

        $canScore = $user->isOwner()
            || $match->created_by === $user->id
            || ($match->organization && $user->isOrgRangeOfficer($match->organization));

        if (! $canScore) {
            return response()->json(['message' => 'You are not authorized to score this match.'], 403);
        }

        if (! $match->isPrs()) {
            return response()->json(['message' => 'This match is not a PRS match.'], 422);
        }

        if ($stage->match_id !== $match->id) {
            return response()->json(['message' => 'Stage does not belong to this match.'], 422);
        }

        $validShooterIds = $match->shooters()->pluck('shooters.id')->toArray();
        $validSquadIds = $match->squads()->pluck('id')->toArray();

        $timeRequired = $stage->is_timed_stage || $stage->is_tiebreaker;
        $expectedShots = $stage->total_shots ?? $stage->gongs()->count();

        $validated = $request->validate([
            'shooter_id' => ['required', 'integer', Rule::in($validShooterIds)],
            'squad_id' => ['required', 'integer', Rule::in($validSquadIds)],
            'raw_time_seconds' => [$timeRequired ? 'required' : 'nullable', 'numeric', 'min:0'],
            'shots' => ['required', 'array', $expectedShots > 0 ? "size:{$expectedShots}" : 'min:1'],
            'shots.*.shot_number' => ['required', 'integer', 'min:1'],
            'shots.*.result' => ['required', 'string', Rule::in(['hit', 'miss', 'not_taken'])],
        ]);

        $deviceId = $request->header('X-Device-Id', $request->input('device_id'));

        $hits = 0;
        $misses = 0;
        $notTaken = 0;

        foreach ($validated['shots'] as $shot) {
            $existingShot = PrsShotScore::where('shooter_id', $validated['shooter_id'])
                ->where('stage_id', $stage->id)
                ->where('shot_number', $shot['shot_number'])
                ->first();
            $oldShotValues = $existingShot?->toArray();

            $savedShot = PrsShotScore::updateOrCreate(
                [
                    'shooter_id' => $validated['shooter_id'],
                    'stage_id' => $stage->id,
                    'shot_number' => $shot['shot_number'],
                ],
                [
                    'match_id' => $match->id,
                    'result' => $shot['result'],
                    'device_id' => $deviceId,
                    'recorded_at' => now(),
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]
            );

            if ($existingShot && $oldShotValues && ($oldShotValues['result'] ?? '') !== $shot['result']) {
                ScoreAuditService::logUpdated($match->id, $savedShot, $oldShotValues, null, $request);
            } elseif (!$existingShot) {
                ScoreAuditService::logCreated($match->id, $savedShot, $request);
            }

            match ($shot['result']) {
                'hit' => $hits++,
                'miss' => $misses++,
                default => $notTaken++,
            };
        }

        $officialTime = $validated['raw_time_seconds'] ?? null;
        if ($officialTime !== null && $stage->par_time_seconds) {
            $allHit = $hits === ($stage->total_shots ?? 0);
            if (! $allHit) {
                $officialTime = max($officialTime, (float) $stage->par_time_seconds);
            }
        }

        $existingResult = PrsStageResult::where('shooter_id', $validated['shooter_id'])
            ->where('stage_id', $stage->id)
            ->first();
        $oldResultValues = $existingResult?->toArray();

        $stageResult = PrsStageResult::updateOrCreate(
            [
                'shooter_id' => $validated['shooter_id'],
                'stage_id' => $stage->id,
            ],
            [
                'match_id' => $match->id,
                'hits' => $hits,
                'misses' => $misses,
                'not_taken' => $notTaken,
                'raw_time_seconds' => $validated['raw_time_seconds'],
                'official_time_seconds' => $officialTime,
                'completed_at' => now(),
                'completed_by' => $user->id,
                'updated_by' => $user->id,
            ]
        );

        if ($existingResult && $oldResultValues) {
            ScoreAuditService::logUpdated($match->id, $stageResult, $oldResultValues, null, $request);
        } else {
            ScoreAuditService::logCreated($match->id, $stageResult, $request);
        }

        return response()->json([
            'message' => 'Stage score saved.',
            'stage_result' => [
                'shooter_id' => $stageResult->shooter_id,
                'stage_id' => $stageResult->stage_id,
                'hits' => $stageResult->hits,
                'misses' => $stageResult->misses,
                'not_taken' => $stageResult->not_taken,
                'raw_time_seconds' => $stageResult->raw_time_seconds ? (float) $stageResult->raw_time_seconds : null,
                'official_time_seconds' => $stageResult->official_time_seconds ? (float) $stageResult->official_time_seconds : null,
                'completed_at' => $stageResult->completed_at?->toIso8601String(),
            ],
        ]);
    }

    public function show(Request $request, ShootingMatch $match, TargetSet $stage)
    {
        if ($stage->match_id !== $match->id) {
            return response()->json(['message' => 'Stage does not belong to this match.'], 422);
        }

        $results = PrsStageResult::where('match_id', $match->id)
            ->where('stage_id', $stage->id)
            ->get()
            ->map(fn ($r) => [
                'shooter_id' => $r->shooter_id,
                'hits' => $r->hits,
                'misses' => $r->misses,
                'not_taken' => $r->not_taken,
                'raw_time_seconds' => $r->raw_time_seconds ? (float) $r->raw_time_seconds : null,
                'official_time_seconds' => $r->official_time_seconds ? (float) $r->official_time_seconds : null,
                'completed_at' => $r->completed_at?->toIso8601String(),
            ]);

        $shots = PrsShotScore::where('match_id', $match->id)
            ->where('stage_id', $stage->id)
            ->orderBy('shooter_id')
            ->orderBy('shot_number')
            ->get()
            ->groupBy('shooter_id')
            ->map(fn ($group) => $group->map(fn ($s) => [
                'shot_number' => $s->shot_number,
                'result' => $s->result->value,
            ])->values());

        return response()->json([
            'stage_id' => $stage->id,
            'results' => $results,
            'shots' => $shots,
        ]);
    }
}
