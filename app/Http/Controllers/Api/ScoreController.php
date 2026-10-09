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
use App\Rules\InIdSet;
use App\Services\NotificationService;
use App\Services\RoyalFlushShotStatusService;
use App\Services\ScoreAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScoreController extends Controller
{
    public function store(Request $request, ShootingMatch $match)
    {
        $user = $request->user();

        if (! $user->can('score', $match)) {
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

        $validTargetSetIds = $match->targetSets()->pluck('id');
        $shooterRule = new InIdSet($match->shooters()->pluck('shooters.id'));
        $gongRule = new InIdSet(Gong::whereIn('target_set_id', $validTargetSetIds)->pluck('id'));
        $targetSetRule = new InIdSet($validTargetSetIds);

        $validated = $request->validate([
            'scores' => ['sometimes', 'array'],
            'scores.*.shooter_id' => ['required', 'integer', $shooterRule],
            'scores.*.gong_id' => ['required', 'integer', $gongRule],
            'scores.*.is_hit' => ['required', 'boolean'],
            'scores.*.device_id' => ['required', 'string', 'max:255'],
            'scores.*.recorded_at' => ['required', 'date'],
            'deleted_scores' => ['sometimes', 'array'],
            'deleted_scores.*.shooter_id' => ['required', 'integer', $shooterRule],
            'deleted_scores.*.gong_id' => ['required', 'integer', $gongRule],
            'stage_times' => ['sometimes', 'array'],
            'stage_times.*.shooter_id' => ['required', 'integer', $shooterRule],
            'stage_times.*.target_set_id' => ['required', 'integer', $targetSetRule],
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

        $savedScores = collect();

        DB::transaction(function () use ($validated, $match, $user, $reason, $request, $savedScores) {
            $scoreRows = collect($validated['scores'] ?? [])->concat($validated['deleted_scores'] ?? []);
            $existingScores = $scoreRows->isEmpty() ? collect() : Score::query()
                ->whereIn('shooter_id', $scoreRows->pluck('shooter_id')->unique())
                ->whereIn('gong_id', $scoreRows->pluck('gong_id')->unique())
                ->get()
                ->keyBy(fn (Score $s) => "{$s->shooter_id}-{$s->gong_id}");

            foreach ($validated['deleted_scores'] ?? [] as $del) {
                if ($existing = $existingScores->pull("{$del['shooter_id']}-{$del['gong_id']}")) {
                    ScoreAuditService::logDeleted($match->id, $existing, $reason, $request);
                    $existing->delete();
                }
            }

            foreach ($validated['scores'] ?? [] as $scoreData) {
                $key = "{$scoreData['shooter_id']}-{$scoreData['gong_id']}";
                $existing = $existingScores->get($key);
                $oldValues = $existing?->toArray();

                $score = $existing ?? new Score([
                    'shooter_id' => $scoreData['shooter_id'],
                    'gong_id' => $scoreData['gong_id'],
                ]);
                $score->fill([
                    'is_hit' => $scoreData['is_hit'],
                    'recorded_by' => $user->id,
                    'device_id' => $scoreData['device_id'],
                    'recorded_at' => $scoreData['recorded_at'],
                    'synced_at' => now(),
                ])->save();
                $existingScores->put($key, $score);

                if ($oldValues) {
                    if ((bool) ($oldValues['is_hit'] ?? false) !== (bool) $scoreData['is_hit']) {
                        ScoreAuditService::logUpdated($match->id, $score, $oldValues, $reason, $request);
                    }
                } else {
                    ScoreAuditService::logCreated($match->id, $score, $request);
                }

                $savedScores->push($score);
            }

            $timeRows = collect($validated['stage_times'] ?? []);
            if ($timeRows->isEmpty()) {
                return;
            }

            $existingTimes = StageTime::query()
                ->whereIn('shooter_id', $timeRows->pluck('shooter_id')->unique())
                ->whereIn('target_set_id', $timeRows->pluck('target_set_id')->unique())
                ->get()
                ->keyBy(fn (StageTime $t) => "{$t->shooter_id}-{$t->target_set_id}");

            foreach ($timeRows as $timeData) {
                $key = "{$timeData['shooter_id']}-{$timeData['target_set_id']}";
                $existingTime = $existingTimes->get($key);
                $oldTimeValues = $existingTime?->toArray();

                $stageTime = $existingTime ?? new StageTime([
                    'shooter_id' => $timeData['shooter_id'],
                    'target_set_id' => $timeData['target_set_id'],
                ]);
                $stageTime->fill([
                    'time_seconds' => $timeData['time_seconds'],
                    'device_id' => $timeData['device_id'],
                    'recorded_at' => $timeData['recorded_at'],
                    'synced_at' => now(),
                ])->save();
                $existingTimes->put($key, $stageTime);

                if ($oldTimeValues) {
                    if ((float) ($oldTimeValues['time_seconds'] ?? 0) !== (float) $timeData['time_seconds']) {
                        ScoreAuditService::logUpdated($match->id, $stageTime, $oldTimeValues, $reason, $request);
                    }
                } else {
                    ScoreAuditService::logCreated($match->id, $stageTime, $request);
                }
            }
        });

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

    public function updateShooterStatus(ShootingMatch $match, Shooter $shooter, Request $request)
    {
        $user = $request->user();

        // AuthZ: previously this endpoint was only behind auth:sanctum, so ANY
        // logged-in user could flag any shooter as dq / no_show / withdrawn on
        // any match (a privilege-escalation / IDOR hole that also bypassed the
        // MD-only DisqualificationController). Status changes are a scoring
        // operation → range-officer bar; a `dq` here additionally requires MD
        // so it can't be used to route around the audited DQ flow.
        abort_unless($user->can('score', $match), 403, 'You are not authorized to manage shooters in this match.');

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

        if ($request->status === 'dq') {
            abort_unless($user->can('disqualify', $match), 403, 'Only match directors can disqualify a shooter.');
        }

        $shooter->update(['status' => $request->status]);

        return response()->json(['status' => $shooter->status]);
    }
}
