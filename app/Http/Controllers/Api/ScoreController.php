<?php

namespace App\Http\Controllers\Api;

use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ScoreResource;
use App\Models\Gong;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\StageTime;
use App\Models\TargetSet;
use App\Services\NotificationService;
use App\Services\ScoreAuditService;
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

        // Auto-promote a Ready match to Active the moment anything is captured
        // (scores OR stage times). Ready means "tablets loaded, waiting to
        // start"; as soon as the first shot lands the match is live.
        $isCapturingSomething = ! empty($validated['scores'])
            || ! empty($validated['deleted_scores'])
            || ! empty($validated['stage_times']);

        if ($isCapturingSomething && $match->status === MatchStatus::Ready) {
            $oldStatus = $match->status;
            $match->update(['status' => MatchStatus::Active]);
            app(NotificationService::class)->onStatusChange($match, $oldStatus, MatchStatus::Active);
        }

        if (! empty($validated['deleted_scores'])) {
            foreach ($validated['deleted_scores'] as $del) {
                $existing = Score::where('shooter_id', $del['shooter_id'])
                    ->where('gong_id', $del['gong_id'])
                    ->first();
                if ($existing) {
                    ScoreAuditService::logDeleted($match->id, $existing, null, $request);
                    $existing->delete();
                }
            }
        }

        $savedScores = collect();

        foreach ($validated['scores'] ?? [] as $scoreData) {
            $existing = Score::where('shooter_id', $scoreData['shooter_id'])
                ->where('gong_id', $scoreData['gong_id'])
                ->first();

            $oldValues = $existing?->toArray();

            $score = Score::updateOrCreate(
                [
                    'shooter_id' => $scoreData['shooter_id'],
                    'gong_id' => $scoreData['gong_id'],
                ],
                [
                    'is_hit' => $scoreData['is_hit'],
                    'recorded_by' => $user->id,
                    'device_id' => $scoreData['device_id'],
                    'recorded_at' => $scoreData['recorded_at'],
                    'synced_at' => now(),
                ]
            );

            if ($existing && $oldValues) {
                if ((bool) ($oldValues['is_hit'] ?? false) !== (bool) $scoreData['is_hit']) {
                    ScoreAuditService::logUpdated($match->id, $score, $oldValues, null, $request);
                }
            } else {
                ScoreAuditService::logCreated($match->id, $score, $request);
            }

            $savedScores->push($score);
        }

        if (! empty($validated['stage_times'])) {
            foreach ($validated['stage_times'] as $timeData) {
                $existingTime = StageTime::where('shooter_id', $timeData['shooter_id'])
                    ->where('target_set_id', $timeData['target_set_id'])
                    ->first();
                $oldTimeValues = $existingTime?->toArray();

                $stageTime = StageTime::updateOrCreate(
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

                if ($existingTime && $oldTimeValues) {
                    if ((float) ($oldTimeValues['time_seconds'] ?? 0) !== (float) $timeData['time_seconds']) {
                        ScoreAuditService::logUpdated($match->id, $stageTime, $oldTimeValues, null, $request);
                    }
                } else {
                    ScoreAuditService::logCreated($match->id, $stageTime, $request);
                }
            }
        }

        return ScoreResource::collection($savedScores);
    }

    public function updateShooterStatus(ShootingMatch $match, Shooter $shooter, Request $request)
    {
        $matchShooterIds = $match->shooters()->pluck('shooters.id');
        if (!$matchShooterIds->contains($shooter->id)) {
            abort(404);
        }

        // no_show is the match-day equivalent of "absent for their relay" —
        // the scoring app must be able to flag it from the field so the
        // shooter is excluded from rankings + field stats (see
        // MatchStandingsService::NON_RANKED_STATUSES). Without this,
        // absent shooters remained 'active' and got scored as all-misses.
        $request->validate(['status' => 'required|in:active,withdrawn,dq,no_show']);
        $shooter->update(['status' => $request->status]);

        return response()->json(['status' => $shooter->status]);
    }
}
