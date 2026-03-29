<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScoreResource;
use App\Models\Gong;
use App\Models\Score;
use App\Models\ShootingMatch;
use App\Models\StageTime;
use App\Models\TargetSet;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScoreController extends Controller
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
        $validTargetSetIds = $match->targetSets()->pluck('id')->toArray();
        $validGongIds = Gong::whereIn('target_set_id', $validTargetSetIds)
            ->pluck('id')
            ->toArray();

        $validated = $request->validate([
            'scores' => ['sometimes', 'array'],
            'scores.*.shooter_id' => ['required', 'integer', Rule::in($validShooterIds)],
            'scores.*.gong_id' => ['required', 'integer', Rule::in($validGongIds)],
            'scores.*.is_hit' => ['required', 'boolean'],
            'scores.*.device_id' => ['required', 'string', 'max:255'],
            'scores.*.recorded_at' => ['required', 'date'],
            'deleted_scores' => ['sometimes', 'array'],
            'deleted_scores.*.shooter_id' => ['required', 'integer', Rule::in($validShooterIds)],
            'deleted_scores.*.gong_id' => ['required', 'integer', Rule::in($validGongIds)],
            'stage_times' => ['sometimes', 'array'],
            'stage_times.*.shooter_id' => ['required', 'integer', Rule::in($validShooterIds)],
            'stage_times.*.target_set_id' => ['required', 'integer', Rule::in($validTargetSetIds)],
            'stage_times.*.time_seconds' => ['required', 'numeric', 'min:0'],
            'stage_times.*.device_id' => ['required', 'string', 'max:255'],
            'stage_times.*.recorded_at' => ['required', 'date'],
        ]);

        if (! empty($validated['deleted_scores'])) {
            foreach ($validated['deleted_scores'] as $del) {
                Score::where('shooter_id', $del['shooter_id'])
                    ->where('gong_id', $del['gong_id'])
                    ->delete();
            }
        }

        $savedScores = collect();

        foreach ($validated['scores'] ?? [] as $scoreData) {
            $savedScores->push(Score::updateOrCreate(
                [
                    'shooter_id' => $scoreData['shooter_id'],
                    'gong_id' => $scoreData['gong_id'],
                ],
                [
                    'is_hit' => $scoreData['is_hit'],
                    'device_id' => $scoreData['device_id'],
                    'recorded_at' => $scoreData['recorded_at'],
                    'synced_at' => now(),
                ]
            ));
        }

        if (! empty($validated['stage_times'])) {
            foreach ($validated['stage_times'] as $timeData) {
                StageTime::updateOrCreate(
                    [
                        'shooter_id' => $timeData['shooter_id'],
                        'target_set_id' => $timeData['target_set_id'],
                    ],
                    [
                        'time_seconds' => $timeData['time_seconds'],
                        'device_id' => $timeData['device_id'],
                        'recorded_at' => $timeData['recorded_at'],
                        'synced_at' => now(),
                    ]
                );
            }
        }

        return ScoreResource::collection($savedScores);
    }
}
