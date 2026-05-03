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
use App\Services\NotificationService;
use App\Services\RoyalFlushShotStatusService;
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

        // Lock writes on a Completed match. Prevents a stale offline tablet
        // from syncing scores into a match that has already been finalised,
        // badges awarded, and post-match emails sent. MDs must explicitly
        // re-open the match (Completed → Active) to accept corrections.
        if ($match->status === MatchStatus::Completed) {
            return response()->json([
                'message' => 'Match already scored. Re-open the match to edit scores.',
                'status' => 'completed',
            ], 423);
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
            // Optional batch-wide correction note. The scoring app sends
            // this when the SO is explicitly fixing a previously-synced
            // result rather than scoring fresh. Persisted onto every
            // score_audit_logs.reason for the mutated rows in this batch
            // so the MD's "Recent Corrections" feed has the why, not just
            // the what. We don't enforce required-ness here because old
            // clients shouldn't break — server-side enforcement happens
            // below, only when the batch actually contains a mutation
            // that warrants a note (delete OR is_hit flip on an existing
            // row OR stage time change).
            'correction_reason' => ['sometimes', 'nullable', 'string', 'min:3', 'max:500'],
        ]);

        $reason = isset($validated['correction_reason']) && $validated['correction_reason'] !== ''
            ? trim($validated['correction_reason'])
            : null;

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
                    ScoreAuditService::logDeleted($match->id, $existing, $reason, $request);
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
                    ScoreAuditService::logUpdated($match->id, $score, $oldValues, $reason, $request);
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
                        ScoreAuditService::logUpdated($match->id, $stageTime, $oldTimeValues, $reason, $request);
                    }
                } else {
                    ScoreAuditService::logCreated($match->id, $stageTime, $request);
                }
            }
        }

        // Enrich the response with Royal-Flush "armed" status for every
        // shooter touched by this batch, so the scoring app can show the
        // "ROYAL FLUSH SHOT" banner before the next tap at that distance
        // without a second round-trip. Backwards compatible: existing
        // callers that just read `data` (the ScoreResource collection) are
        // unaffected — royal_flush is an added sibling key.
        $touchedShooterIds = array_values(array_unique(array_merge(
            $savedScores->pluck('shooter_id')->all(),
            collect($validated['deleted_scores'] ?? [])->pluck('shooter_id')->all(),
        )));

        $rfStatus = [];
        if (! empty($touchedShooterIds)) {
            $touchedShooters = Shooter::whereIn('id', $touchedShooterIds)->get();
            $rfStatus = app(RoyalFlushShotStatusService::class)
                ->forShooters($match, $touchedShooters)
                ->all();
        }

        return ScoreResource::collection($savedScores)
            ->additional(['royal_flush' => $rfStatus]);
    }

    /**
     * GET /api/matches/{match}/shooters/{shooter}/royal-flush-status
     *
     * Stateless "is the next shot the Royal Flush shot?" query the scoring
     * app (or the web scoring pad) calls before rendering the hit/miss
     * buttons for a shooter at a distance. Returns the full per-distance
     * breakdown; the banner trigger is `royal_flush_shot === true`.
     */
    public function royalFlushStatus(ShootingMatch $match, Shooter $shooter, RoyalFlushShotStatusService $service)
    {
        if (! $match->shooters()->whereKey($shooter->id)->exists()) {
            abort(404);
        }

        return response()->json($service->forShooter($match, $shooter));
    }

    public function updateShooterStatus(ShootingMatch $match, Shooter $shooter, Request $request)
    {
        $matchShooterIds = $match->shooters()->pluck('shooters.id');
        if (! $matchShooterIds->contains($shooter->id)) {
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
