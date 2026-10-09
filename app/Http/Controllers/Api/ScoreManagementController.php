<?php

namespace App\Http\Controllers\Api;

use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Models\CorrectionLog;
use App\Models\PrsShotScore;
use App\Models\PrsStageResult;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\StageTime;
use App\Models\TargetSet;
use App\Rules\InIdSet;
use App\Services\AchievementService;
use App\Services\NotificationService;
use App\Services\ScoreAuditService;
use App\Services\SquadScoreCorrectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ScoreManagementController extends Controller
{
    private const SCORING_APP_REASON = 'Corrected in scoring app';

    /**
     * Hand one shooter's PRS result on a stage to another shooter (the wrong
     * shooter was scored). Same contract as the hub's
     * POST stages/{stage}/reassign so the scoring SPA works on both.
     */
    public function reassignStage(Request $request, ShootingMatch $match, TargetSet $stage)
    {
        $this->authorizeMatchDirector($request, $match);
        abort_unless($stage->match_id === $match->id, 422, 'Stage does not belong to this match.');

        $validShooterIds = $match->shooters()->pluck('shooters.id')->all();

        $validated = $request->validate([
            'shooter_id' => ['required', 'integer', Rule::in($validShooterIds)],
            'new_shooter_id' => ['required', 'integer', Rule::in($validShooterIds), 'different:shooter_id'],
        ]);

        $from = (int) $validated['shooter_id'];
        $to = (int) $validated['new_shooter_id'];

        [$moved, $result] = DB::transaction(function () use ($request, $match, $stage, $from, $to) {
            $displaced = PrsStageResult::where('shooter_id', $to)->where('stage_id', $stage->id)->first();
            if ($displaced) {
                ScoreAuditService::logDeleted($match->id, $displaced, self::SCORING_APP_REASON, $request);
                $displaced->delete();
            }
            PrsShotScore::where('shooter_id', $to)->where('stage_id', $stage->id)->delete();

            $moved = PrsShotScore::where('shooter_id', $from)
                ->where('stage_id', $stage->id)
                ->update(['shooter_id' => $to]);

            $result = PrsStageResult::where('shooter_id', $from)->where('stage_id', $stage->id)->first();
            if ($result) {
                ScoreAuditService::logReassigned($match->id, $result, $from, $to, self::SCORING_APP_REASON, $request);
                $result->update(['shooter_id' => $to]);
            }

            CorrectionLog::create([
                'match_id' => $match->id,
                'stage_id' => $stage->id,
                'shooter_id' => $from,
                'action' => 'reassign',
                'details' => ['old_shooter_id' => $from, 'new_shooter_id' => $to, 'moved_scores' => $moved],
                'device_id' => $request->header('X-Device-Id'),
                'performed_at' => now(),
            ]);

            return [$moved, $result];
        });

        return response()->json([
            'success' => true,
            'moved_scores' => $moved,
            'shooterId' => $to,
            'stageId' => $stage->id,
            'hits' => $result?->hits ?? 0,
            'misses' => $result?->misses ?? 0,
            'notTaken' => $result?->not_taken ?? 0,
            'time' => $result?->official_time_seconds !== null ? (float) $result->official_time_seconds : null,
            'completedAt' => $result?->completed_at?->toIso8601String(),
        ]);
    }

    /**
     * Move a shooter's PRS result from one stage to another (scored on the
     * wrong stage). Same contract as the hub's POST stages/{stage}/move.
     */
    public function moveStage(Request $request, ShootingMatch $match, TargetSet $stage)
    {
        $this->authorizeMatchDirector($request, $match);
        abort_unless($stage->match_id === $match->id, 422, 'Stage does not belong to this match.');

        $validShooterIds = $match->shooters()->pluck('shooters.id')->all();
        $validStageIds = $match->targetSets()->pluck('id')->all();

        $validated = $request->validate([
            'shooter_id' => ['required', 'integer', Rule::in($validShooterIds)],
            'new_stage_id' => ['required', 'integer', Rule::in($validStageIds), Rule::notIn([$stage->id])],
        ]);

        $shooterId = (int) $validated['shooter_id'];
        $newStageId = (int) $validated['new_stage_id'];

        $moved = DB::transaction(function () use ($request, $match, $stage, $shooterId, $newStageId) {
            $displaced = PrsStageResult::where('shooter_id', $shooterId)->where('stage_id', $newStageId)->first();
            if ($displaced) {
                ScoreAuditService::logDeleted($match->id, $displaced, self::SCORING_APP_REASON, $request);
                $displaced->delete();
            }
            PrsShotScore::where('shooter_id', $shooterId)->where('stage_id', $newStageId)->delete();

            $moved = PrsShotScore::where('shooter_id', $shooterId)
                ->where('stage_id', $stage->id)
                ->update(['stage_id' => $newStageId]);

            $result = PrsStageResult::where('shooter_id', $shooterId)->where('stage_id', $stage->id)->first();
            if ($result) {
                ScoreAuditService::log(
                    $match->id,
                    $result,
                    'move_stage',
                    ['stage_id' => $stage->id],
                    ['stage_id' => $newStageId],
                    self::SCORING_APP_REASON,
                    $request,
                );
                $result->update(['stage_id' => $newStageId]);
            }

            CorrectionLog::create([
                'match_id' => $match->id,
                'stage_id' => $stage->id,
                'shooter_id' => $shooterId,
                'action' => 'move_stage',
                'details' => ['old_stage_id' => $stage->id, 'new_stage_id' => $newStageId, 'moved_scores' => $moved],
                'device_id' => $request->header('X-Device-Id'),
                'performed_at' => now(),
            ]);

            return $moved;
        });

        return response()->json([
            'success' => true,
            'moved_scores' => $moved,
        ]);
    }

    /**
     * Correction log for a match, newest first. Same shape as the hub's
     * GET correction-logs.
     */
    public function correctionLogs(Request $request, ShootingMatch $match)
    {
        $this->authorizeScorer($request, $match);

        return response()->json(
            CorrectionLog::where('match_id', $match->id)
                ->orderByDesc('performed_at')
                ->orderByDesc('id')
                ->get(['id', 'match_id', 'stage_id', 'shooter_id', 'action', 'details', 'device_id', 'performed_at'])
        );
    }

    /**
     * Store correction log entries for a match.
     */
    public function storeCorrectionLogs(Request $request, ShootingMatch $match)
    {
        // Correction-log writes are the RO's own audit trail of what they
        // just fixed on the day — RO-level bar (not a match-lifecycle op).
        $this->authorizeScorer($request, $match);

        $validated = $request->validate([
            'logs' => ['required', 'array', 'min:1'],
            'logs.*.action' => ['required', 'string', 'max:30'],
            'logs.*.stage_id' => ['required', 'integer', new InIdSet($match->targetSets()->pluck('id'))],
            'logs.*.shooter_id' => ['required', 'integer', new InIdSet($match->shooters()->pluck('shooters.id'))],
            'logs.*.details' => ['nullable', 'array'],
            'logs.*.device_id' => ['nullable', 'string'],
            'logs.*.performed_at' => ['nullable', 'date'],
        ]);

        // Bulk insert bypasses model casts, so encode/parse like the casts would.
        $now = now();
        CorrectionLog::insert(array_map(fn (array $entry) => [
            'match_id' => $match->id,
            'stage_id' => $entry['stage_id'],
            'shooter_id' => $entry['shooter_id'],
            'action' => $entry['action'],
            'details' => isset($entry['details']) ? json_encode($entry['details']) : null,
            'device_id' => $entry['device_id'] ?? null,
            'performed_at' => isset($entry['performed_at']) ? Carbon::parse($entry['performed_at']) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $validated['logs']));
        $created = count($validated['logs']);

        return response()->json([
            'message' => "{$created} correction log(s) created.",
            'count' => $created,
        ]);
    }

    public function completeMatch(Request $request, ShootingMatch $match)
    {
        $this->authorizeMatchDirector($request, $match);

        if ($match->status !== MatchStatus::Active) {
            return response()->json(['message' => 'Match is not active.'], 422);
        }

        $shooterIds = $match->shooters()->pluck('shooters.id');
        $scoredIds = Score::whereIn('shooter_id', $shooterIds)->distinct()->pluck('shooter_id');
        $unscoredCount = $shooterIds->diff($scoredIds)->count();

        if ($request->boolean('dry_run', false)) {
            return response()->json([
                'warnings' => $unscoredCount > 0
                    ? ["{$unscoredCount} shooter(s) have no scores recorded."]
                    : [],
                'total_shooters' => $shooterIds->count(),
                'scored_shooters' => $scoredIds->count(),
            ]);
        }

        $oldStatus = $match->status;
        $match->update(['status' => MatchStatus::Completed]);

        try {
            app(NotificationService::class)->onStatusChange($match, $oldStatus, MatchStatus::Completed);
        } catch (\Throwable $e) {
            Log::warning('Status notification dispatch failed', ['error' => $e->getMessage()]);
        }

        try {
            // Clean-slate re-evaluation: wipes badges stamped against this match
            // and re-awards from the CURRENT scores. Needed because corrections
            // (reshoot / reassign / move-stage) or a reopen→edit→complete cycle
            // can shift the podium and mid-match badges — additive evaluation
            // alone would leave stale awards.
            AchievementService::reevaluateForMatch($match);
        } catch (\Throwable $e) {
            Log::warning('Achievement evaluation failed', [
                'match_id' => $match->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Match completed.',
            'status' => 'completed',
        ]);
    }

    public function reopenMatch(Request $request, ShootingMatch $match)
    {
        $this->authorizeMatchDirector($request, $match);

        if ($match->status !== MatchStatus::Completed) {
            return response()->json([
                'message' => 'Only a completed match can be re-opened.',
                'status' => $match->status->value,
            ], 422);
        }

        $oldStatus = $match->status;
        $match->update(['status' => MatchStatus::Active]);

        try {
            app(NotificationService::class)->onStatusChange($match, $oldStatus, MatchStatus::Active);
        } catch (\Throwable $e) {
            Log::warning('Status notification dispatch failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'Match re-opened for editing.',
            'status' => 'active',
        ]);
    }

    /**
     * Side-bet buy-in roster for the scoring SPA. Returns every shooter on
     * the match with an `in_pot` flag so the MD can pick people in/out of
     * the pot without leaving the scoring app. MD-only.
     *
     * Supports `?since=<iso8601>` for delta polling: when provided we still
     * return the full shooters list (clients need it for new arrivals), but
     * include `changes_since` so the client can decide whether to short-
     * circuit a re-render, and a `server_time` echo so the next request can
     * pass that same value back as the cursor.
     */
    public function sideBetBuyIns(Request $request, ShootingMatch $match)
    {
        $this->authorizeMatchDirector($request, $match);

        if (! $match->side_bet_enabled) {
            return response()->json([
                'message' => 'Side bet is not enabled for this match.',
                'enabled' => false,
                'shooters' => [],
                'totals' => ['in' => 0, 'total' => 0],
                'locked' => false,
                'server_time' => now()->toIso8601String(),
                'changes_since' => false,
            ], 422);
        }

        $potRows = $match->sideBetShooters()
            ->get(['shooters.id', 'side_bet_shooters.updated_at as pivot_updated_at'])
            ->mapWithKeys(fn ($s) => [(int) $s->id => $s->pivot_updated_at]);

        $shooters = $match->shooters()
            ->with('squad:id,name')
            ->orderByRaw('LOWER(shooters.name) asc')
            ->get(['shooters.id', 'shooters.name', 'shooters.bib_number', 'shooters.squad_id', 'shooters.status'])
            ->map(fn ($s) => [
                'id' => (int) $s->id,
                'name' => $s->name,
                'bib_number' => $s->bib_number,
                'squad' => $s->squad?->name,
                'status' => $s->status,
                'in_pot' => $potRows->has((int) $s->id),
                'pot_updated_at' => optional($potRows->get((int) $s->id))?->toIso8601String(),
            ])
            ->values();

        $changesSince = false;
        if ($request->filled('since')) {
            try {
                $since = \Carbon\Carbon::parse($request->query('since'));
                $changesSince = $potRows->contains(
                    fn ($ts) => $ts && \Carbon\Carbon::parse($ts)->gt($since),
                );
            } catch (\Throwable) {
                $changesSince = true;
            }
        }

        return response()->json([
            'enabled' => true,
            'locked' => $match->status === MatchStatus::Completed,
            'shooters' => $shooters,
            'totals' => [
                'in' => $potRows->count(),
                'total' => $shooters->count(),
            ],
            'server_time' => now()->toIso8601String(),
            'changes_since' => $changesSince,
        ]);
    }

    /**
     * Toggle a single shooter in/out of the side-bet pot. Idempotent and
     * safe to retry — returns the resulting state so the SPA can confirm.
     * MD-only; locked once the match is completed.
     */
    public function toggleSideBetShooter(Request $request, ShootingMatch $match, Shooter $shooter)
    {
        $this->authorizeMatchDirector($request, $match);

        if (! $match->side_bet_enabled) {
            return response()->json([
                'message' => 'Side bet is not enabled for this match.',
            ], 422);
        }

        if ($match->status === MatchStatus::Completed) {
            return response()->json([
                'message' => 'Side-bet buy-in is locked once the match is completed.',
            ], 423);
        }

        // Confirm the shooter actually belongs to this match (catch IDs from
        // a different match before they pollute the pivot table).
        $belongs = $match->shooters()->whereKey($shooter->id)->exists();
        if (! $belongs) {
            return response()->json([
                'message' => 'Shooter does not belong to this match.',
            ], 404);
        }

        $explicit = $request->has('in') ? $request->boolean('in') : null;
        $currentlyIn = $match->sideBetShooters()->where('shooters.id', $shooter->id)->exists();

        $shouldBeIn = $explicit ?? ! $currentlyIn;
        $changed = false;

        if ($shouldBeIn && ! $currentlyIn) {
            $match->sideBetShooters()->attach($shooter->id, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $changed = true;
        } elseif (! $shouldBeIn && $currentlyIn) {
            $match->sideBetShooters()->detach($shooter->id);
            $changed = true;
        } elseif ($shouldBeIn && $currentlyIn) {
            // Idempotent toggle still bumps updated_at so observers polling
            // with ?since= can see the "MD confirmed in/out" event even
            // though the membership didn't flip.
            $match->sideBetShooters()->updateExistingPivot($shooter->id, [
                'updated_at' => now(),
            ]);
        }

        if ($changed) {
            ScoreAuditService::log(
                $match->id,
                $shooter,
                'side_bet_toggle',
                ['in_pot' => $currentlyIn],
                ['in_pot' => $shouldBeIn],
                $request->input('reason'),
                $request,
            );
        }

        $totalIn = $match->sideBetShooters()->count();
        $totalShooters = $match->shooters()->count();

        return response()->json([
            'shooter_id' => (int) $shooter->id,
            'in_pot' => $shouldBeIn,
            'changed' => $changed,
            'totals' => [
                'in' => $totalIn,
                'total' => $totalShooters,
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Correct a single shooter's scores on one stage in one round-trip.
     * Powers the "tap a row on the stage summary → fix this shooter"
     * UX on native, PWA, and web so the MD doesn't have to navigate
     * back into the whole scoring flow just to flip a single cell.
     *
     * Body shape (standard scoring):
     *   {
     *     "target_set_id": 12,
     *     "gong_states": { "<gong_id>": true|false|null, ... },
     *     "time_seconds": 32.5,            // optional, null clears
     *     "reason": "Score chair miscounted gong 3"
     *   }
     *
     * Body shape (PRS scoring):
     *   {
     *     "stage_id": 12,
     *     "shots": [{ "shot_number": 1, "result": "hit" }, ... ],
     *     "raw_time_seconds": 41.2,        // optional
     *     "reason": "Replayed video, shot 2 was a hit"
     *   }
     *
     * Honors the match-completed (HTTP 423) lock; clients must reopen
     * the match first. Returns the updated shooter state plus a
     * server_time echo so callers can sync clocks for delta polling.
     */
    public function correctSingleShooter(
        Request $request,
        ShootingMatch $match,
        Shooter $shooter,
        SquadScoreCorrectionService $service,
    ) {
        // Single-shooter correction is the "tap a row, fix the tap" modal
        // the RO uses during scoring — RO-level bar. Completing/reopening
        // the match still requires MD, so match-lifecycle stays protected.
        $this->authorizeScorer($request, $match);

        if ($match->status === MatchStatus::Completed) {
            return response()->json([
                'message' => 'Match already scored. Re-open the match to edit scores.',
                'status' => 'completed',
            ], 423);
        }

        $belongs = $match->shooters()->whereKey($shooter->id)->exists();
        if (! $belongs) {
            return response()->json([
                'message' => 'Shooter does not belong to this match.',
            ], 404);
        }

        if ($match->isPrs()) {
            return $this->correctPrsShooter($request, $match, $shooter);
        }

        return $this->correctStandardShooter($request, $match, $shooter, $service);
    }

    private function correctStandardShooter(
        Request $request,
        ShootingMatch $match,
        Shooter $shooter,
        SquadScoreCorrectionService $service,
    ) {
        $validTargetSetIds = $match->targetSets()->pluck('id')->toArray();

        $validated = $request->validate([
            'target_set_id' => ['required', 'integer', Rule::in($validTargetSetIds)],
            'gong_states' => ['required', 'array', 'min:1'],
            'gong_states.*' => ['nullable', 'boolean'],
            'time_seconds' => ['nullable', 'numeric', 'min:0'],
            'clear_time' => ['sometimes', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $targetSet = TargetSet::find($validated['target_set_id']);

        // Only accept gong_states that belong to the named target set —
        // stops a malformed payload from reaching across stages.
        $stageGongIds = $targetSet->gongs()->pluck('id')->all();
        $stageGongIds = array_flip($stageGongIds);
        $gongStates = [];
        foreach ($validated['gong_states'] as $gongId => $state) {
            $gongId = (int) $gongId;
            if (! isset($stageGongIds[$gongId])) {
                continue;
            }
            $gongStates[$gongId] = $state === null ? null : (bool) $state;
        }

        if (empty($gongStates)) {
            return response()->json([
                'message' => 'No valid gong cells were submitted for this stage.',
            ], 422);
        }

        $stats = $service->applyForShooter(
            $match,
            $shooter,
            $gongStates,
            $validated['reason'],
            (int) $request->user()->id,
        );

        $stageTimeState = null;
        if ($request->boolean('clear_time')) {
            $existing = StageTime::where('shooter_id', $shooter->id)
                ->where('target_set_id', $targetSet->id)
                ->first();
            if ($existing) {
                ScoreAuditService::logDeleted($match->id, $existing, $validated['reason'], $request);
                $existing->delete();
            }
            $stageTimeState = null;
        } elseif (array_key_exists('time_seconds', $validated) && $validated['time_seconds'] !== null) {
            $stageTime = StageTime::firstOrNew([
                'shooter_id' => $shooter->id,
                'target_set_id' => $targetSet->id,
            ]);
            $old = $stageTime->exists ? $stageTime->toArray() : null;

            $stageTime->fill([
                'time_seconds' => $validated['time_seconds'],
                'device_id' => $request->header('X-Device-Id', $request->input('device_id', 'correction')),
                'recorded_at' => now(),
            ])->save();

            if ($old !== null) {
                ScoreAuditService::logUpdated($match->id, $stageTime, $old, $validated['reason'], $request);
            } else {
                ScoreAuditService::logCreated($match->id, $stageTime, $request);
                ScoreAuditService::log(
                    $match->id,
                    $stageTime,
                    'correction',
                    null,
                    ['time_seconds' => (float) $validated['time_seconds']],
                    $validated['reason'],
                    $request,
                );
            }
            $stageTimeState = (float) $stageTime->time_seconds;
        }

        $currentScores = Score::where('shooter_id', $shooter->id)
            ->whereIn('gong_id', array_keys($stageGongIds))
            ->get(['id', 'gong_id', 'is_hit', 'recorded_at'])
            ->map(fn ($s) => [
                'id' => (int) $s->id,
                'gong_id' => (int) $s->gong_id,
                'is_hit' => (bool) $s->is_hit,
                'recorded_at' => optional($s->recorded_at)->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'message' => 'Shooter correction applied.',
            'stats' => $stats,
            'shooter_id' => (int) $shooter->id,
            'target_set_id' => (int) $targetSet->id,
            'stage_time_seconds' => $stageTimeState,
            'scores' => $currentScores,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    private function correctPrsShooter(Request $request, ShootingMatch $match, Shooter $shooter)
    {
        $validStageIds = $match->targetSets()->pluck('id')->toArray();

        $validated = $request->validate([
            'stage_id' => ['required', 'integer', Rule::in($validStageIds)],
            'shots' => ['required', 'array', 'min:1'],
            'shots.*.shot_number' => ['required', 'integer', 'min:1'],
            'shots.*.result' => ['required', 'string', Rule::in(['hit', 'miss', 'not_taken'])],
            'raw_time_seconds' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $stage = TargetSet::find($validated['stage_id']);

        $deviceId = $request->header('X-Device-Id', $request->input('device_id', 'correction'));
        $reason = $validated['reason'];
        $rawTime = $validated['raw_time_seconds'] ?? null;

        $stageResult = DB::transaction(function () use ($validated, $match, $stage, $shooter, $deviceId, $rawTime, $reason, $request) {
            $hits = 0;
            $misses = 0;
            $notTaken = 0;

            $existingShots = PrsShotScore::where('shooter_id', $shooter->id)
                ->where('stage_id', $stage->id)
                ->get()
                ->keyBy('shot_number');

            foreach ($validated['shots'] as $shot) {
                $existingShot = $existingShots->get($shot['shot_number']);
                $oldShotValues = $existingShot?->toArray();

                $savedShot = $existingShot ?? new PrsShotScore([
                    'shooter_id' => $shooter->id,
                    'stage_id' => $stage->id,
                    'shot_number' => $shot['shot_number'],
                ]);
                $savedShot->fill([
                    'match_id' => $match->id,
                    'result' => $shot['result'],
                    'device_id' => $deviceId,
                    'recorded_at' => now(),
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ])->save();
                $existingShots->put($shot['shot_number'], $savedShot);

                if ($existingShot && $oldShotValues && ($oldShotValues['result'] ?? '') !== $shot['result']) {
                    ScoreAuditService::logUpdated($match->id, $savedShot, $oldShotValues, $reason, $request);
                } elseif (! $existingShot) {
                    ScoreAuditService::logCreated($match->id, $savedShot, $request);
                    ScoreAuditService::log(
                        $match->id,
                        $savedShot,
                        'correction',
                        null,
                        ['result' => $shot['result']],
                        $reason,
                        $request,
                    );
                }

                match ($shot['result']) {
                    'hit' => $hits++,
                    'miss' => $misses++,
                    default => $notTaken++,
                };
            }

            // See PrsScoreController::store — official time = recorded raw
            // time capped at par. A miss is not a time-out, so we no longer
            // force non-clears to the par time (that flattened the tiebreaker).
            $officialTime = $rawTime;
            if ($officialTime !== null && $stage->par_time_seconds) {
                $officialTime = min($officialTime, (float) $stage->par_time_seconds);
            }

            $stageResult = PrsStageResult::firstOrNew([
                'shooter_id' => $shooter->id,
                'stage_id' => $stage->id,
            ]);
            $oldResultValues = $stageResult->exists ? $stageResult->toArray() : null;

            $stageResult->fill([
                'match_id' => $match->id,
                'hits' => $hits,
                'misses' => $misses,
                'not_taken' => $notTaken,
                'raw_time_seconds' => $rawTime,
                'official_time_seconds' => $officialTime,
                'completed_at' => now(),
                'completed_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ])->save();

            if ($oldResultValues) {
                ScoreAuditService::logUpdated($match->id, $stageResult, $oldResultValues, $reason, $request);
            } else {
                ScoreAuditService::logCreated($match->id, $stageResult, $request);
            }

            return $stageResult;
        });

        return response()->json([
            'message' => 'Shooter correction applied.',
            'shooter_id' => (int) $shooter->id,
            'stage_id' => (int) $stage->id,
            'stage_result' => [
                'hits' => $stageResult->hits,
                'misses' => $stageResult->misses,
                'not_taken' => $stageResult->not_taken,
                'raw_time_seconds' => $stageResult->raw_time_seconds ? (float) $stageResult->raw_time_seconds : null,
                'official_time_seconds' => $stageResult->official_time_seconds ? (float) $stageResult->official_time_seconds : null,
                'completed_at' => $stageResult->completed_at?->toIso8601String(),
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    private function authorizeMatchDirector(Request $request, ShootingMatch $match): void
    {
        abort_unless(
            $request->user()?->can('manage', $match),
            403,
            'Only the match director or admin can perform this action.',
        );
    }

    private function authorizeScorer(Request $request, ShootingMatch $match): void
    {
        abort_unless(
            $request->user()?->can('score', $match),
            403,
            'Only match staff can perform this action.',
        );
    }
}
