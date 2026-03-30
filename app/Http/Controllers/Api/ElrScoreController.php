<?php

namespace App\Http\Controllers\Api;

use App\Enums\ElrShotResult;
use App\Http\Controllers\Controller;
use App\Models\ElrShot;
use App\Models\ElrTarget;
use App\Models\ShootingMatch;
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

        $service = new ELRScoringService();
        $saved = [];

        foreach ($validated['shots'] as $shotData) {
            $target = ElrTarget::with('stage.scoringProfile', 'stage.match.elrScoringProfile')->find($shotData['elr_target_id']);
            if (! $target) continue;

            $result = ElrShotResult::from($shotData['result']);
            $pointsAwarded = 0;

            if ($result === ElrShotResult::Hit) {
                $pointsAwarded = $target->pointsForShot($shotData['shot_number']);
            }

            $shot = ElrShot::updateOrCreate(
                [
                    'shooter_id' => $shotData['shooter_id'],
                    'elr_target_id' => $shotData['elr_target_id'],
                    'shot_number' => $shotData['shot_number'],
                ],
                [
                    'result' => $result,
                    'points_awarded' => $pointsAwarded,
                    'recorded_by' => $user->id,
                    'device_id' => $shotData['device_id'],
                    'recorded_at' => $shotData['recorded_at'],
                    'synced_at' => now(),
                ]
            );

            $saved[] = [
                'id' => $shot->id,
                'shooter_id' => $shot->shooter_id,
                'elr_target_id' => $shot->elr_target_id,
                'shot_number' => $shot->shot_number,
                'result' => $shot->result->value,
                'points_awarded' => (float) $shot->points_awarded,
            ];
        }

        return response()->json(['data' => $saved]);
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

        $service = new ELRScoringService();
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
