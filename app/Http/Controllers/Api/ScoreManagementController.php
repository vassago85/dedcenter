<?php

namespace App\Http\Controllers\Api;

use App\Enums\MatchStatus;
use App\Enums\PrsShotResult;
use App\Http\Controllers\Controller;
use App\Jobs\SendPostMatchNotifications;
use App\Models\CorrectionLog;
use App\Models\ElrShot;
use App\Models\PrsShotScore;
use App\Models\PrsStageResult;
use App\Models\Score;
use App\Models\ScoreAuditLog;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\StageTime;
use App\Models\TargetSet;
use App\Services\AchievementService;
use App\Services\NotificationService;
use App\Services\ScoreAuditService;
use App\Services\SquadScoreCorrectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ScoreManagementController extends Controller
{
    /**
     * Reassign score(s) from one shooter to another.
     */
    public function reassign(Request $request, ShootingMatch $match)
    {
        $this->authorizeMatchDirector($request, $match);

        $validShooterIds = $match->shooters()->pluck('shooters.id')->toArray();

        $validated = $request->validate([
            'score_type' => ['required', Rule::in(['standard', 'prs', 'elr'])],
            'score_ids' => ['required', 'array', 'min:1'],
            'score_ids.*' => ['required', 'integer'],
            'new_shooter_id' => ['required', 'integer', Rule::in($validShooterIds)],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $reassigned = [];

        foreach ($validated['score_ids'] as $scoreId) {
            $model = match ($validated['score_type']) {
                'standard' => Score::find($scoreId),
                'prs' => PrsStageResult::where('match_id', $match->id)->find($scoreId),
                'elr' => ElrShot::find($scoreId),
                default => null,
            };

            if (! $model) {
                continue;
            }

            $oldShooterId = $model->shooter_id;
            if ($oldShooterId === $validated['new_shooter_id']) {
                continue;
            }

            ScoreAuditService::logReassigned(
                $match->id,
                $model,
                $oldShooterId,
                $validated['new_shooter_id'],
                $validated['reason'],
                $request,
            );

            $model->update(['shooter_id' => $validated['new_shooter_id']]);

            if ($validated['score_type'] === 'prs') {
                PrsShotScore::where('match_id', $match->id)
                    ->where('stage_id', $model->stage_id)
                    ->where('shooter_id', $oldShooterId)
                    ->update(['shooter_id' => $validated['new_shooter_id']]);
            }

            $reassigned[] = $model->getKey();
        }

        return response()->json([
            'message' => count($reassigned).' score(s) reassigned.',
            'reassigned_ids' => $reassigned,
        ]);
    }

    /**
     * Mark score(s) as a reshoot with a mandatory reason.
     */
    public function reshoot(Request $request, ShootingMatch $match)
    {
        $this->authorizeMatchDirector($request, $match);

        $validated = $request->validate([
            'score_type' => ['required', Rule::in(['standard', 'prs_stage', 'prs_shot', 'elr'])],
            'score_ids' => ['required', 'array', 'min:1'],
            'score_ids.*' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $marked = [];

        foreach ($validated['score_ids'] as $scoreId) {
            $model = match ($validated['score_type']) {
                'standard' => Score::find($scoreId),
                'prs_stage' => PrsStageResult::where('match_id', $match->id)->find($scoreId),
                'prs_shot' => PrsShotScore::where('match_id', $match->id)->find($scoreId),
                'elr' => ElrShot::find($scoreId),
                default => null,
            };

            if (! $model) {
                continue;
            }

            $model->update([
                'is_reshoot' => true,
                'reshoot_reason' => $validated['reason'],
            ]);

            ScoreAuditService::logReshoot($match->id, $model, $validated['reason'], $request);

            $marked[] = $model->getKey();
        }

        return response()->json([
            'message' => count($marked).' score(s) marked as reshoot.',
            'reshoot_ids' => $marked,
        ]);
    }

    /**
     * Return audit log entries for a match.
     */
    public function auditLog(Request $request, ShootingMatch $match)
    {
        $this->authorizeMatchDirector($request, $match);

        $query = ScoreAuditLog::where('match_id', $match->id)
            ->with('user:id,name,email')
            ->orderByDesc('created_at');

        if ($request->has('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->has('shooter_id')) {
            $shooterId = (int) $request->query('shooter_id');
            $query->where(function ($q) use ($shooterId) {
                $q->whereJsonContains('old_values->shooter_id', $shooterId)
                    ->orWhereJsonContains('new_values->shooter_id', $shooterId);
            });
        }

        $logs = $query->paginate(50);

        return response()->json($logs);
    }

    /**
     * Toggle scores_published for a match.
     */
    public function togglePublish(Request $request, ShootingMatch $match)
    {
        $this->authorizeMatchDirector($request, $match);

        $validated = $request->validate([
            'published' => ['required', 'boolean'],
        ]);

        $match->update(['scores_published' => $validated['published']]);

        if ($validated['published']) {
            try {
                // Clean rebuild so the published leaderboard, podiums, and
                // per-shooter badge lists always match the final scores even
                // if the MD corrected something and re-published.
                AchievementService::reevaluateForMatch($match);
            } catch (\Throwable $e) {
                Log::warning('Achievement evaluation failed', [
                    'match_id' => $match->id,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($match->status === MatchStatus::Completed) {
                SendPostMatchNotifications::dispatch($match)->delay(now()->addHour());
            }
        }

        return response()->json([
            'message' => $validated['published'] ? 'Scores are now published.' : 'Scores are now hidden from public.',
            'scores_published' => (bool) $match->scores_published,
        ]);
    }

    /**
     * Move a shooter's PRS scores from one stage to another.
     */
    public function moveStage(Request $request, ShootingMatch $match)
    {
        $this->authorizeMatchDirector($request, $match);

        $validShooterIds = $match->shooters()->pluck('shooters.id')->toArray();
        $validStageIds = $match->targetSets()->pluck('id')->toArray();

        $validated = $request->validate([
            'score_type' => ['required', Rule::in(['prs'])],
            'shooter_id' => ['required', 'integer', Rule::in($validShooterIds)],
            'old_stage_id' => ['required', 'integer', Rule::in($validStageIds)],
            'new_stage_id' => ['required', 'integer', Rule::in($validStageIds), 'different:old_stage_id'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $count = 0;

        DB::transaction(function () use ($match, $validated, $request, &$count) {
            $count = PrsShotScore::where('match_id', $match->id)
                ->where('shooter_id', $validated['shooter_id'])
                ->where('stage_id', $validated['old_stage_id'])
                ->update(['stage_id' => $validated['new_stage_id']]);

            $oldResult = PrsStageResult::where('match_id', $match->id)
                ->where('shooter_id', $validated['shooter_id'])
                ->where('stage_id', $validated['old_stage_id'])
                ->first();

            if ($oldResult) {
                ScoreAuditService::logDeleted($match->id, $oldResult, $validated['reason'], $request);
                $oldResult->delete();
            }

            $movedShots = PrsShotScore::where('match_id', $match->id)
                ->where('shooter_id', $validated['shooter_id'])
                ->where('stage_id', $validated['new_stage_id'])
                ->get();

            if ($movedShots->isNotEmpty()) {
                $newResult = PrsStageResult::create([
                    'match_id' => $match->id,
                    'shooter_id' => $validated['shooter_id'],
                    'stage_id' => $validated['new_stage_id'],
                    'hits' => $movedShots->where('result', PrsShotResult::Hit)->count(),
                    'misses' => $movedShots->where('result', PrsShotResult::Miss)->count(),
                    'not_taken' => $movedShots->where('result', PrsShotResult::NotTaken)->count(),
                    'completed_at' => now(),
                ]);

                ScoreAuditService::log(
                    $match->id,
                    $newResult,
                    'move_stage',
                    ['stage_id' => $validated['old_stage_id']],
                    ['stage_id' => $validated['new_stage_id']],
                    $validated['reason'],
                    $request,
                );
            }
        });

        return response()->json([
            'message' => "{$count} shot(s) moved to new stage.",
            'count' => $count,
        ]);
    }

    /**
     * Store correction log entries for a match.
     */
    public function storeCorrectionLogs(Request $request, ShootingMatch $match)
    {
        $this->authorizeMatchDirector($request, $match);

        $validShooterIds = $match->shooters()->pluck('shooters.id')->toArray();
        $validStageIds = $match->targetSets()->pluck('id')->toArray();

        $validated = $request->validate([
            'logs' => ['required', 'array', 'min:1'],
            'logs.*.action' => ['required', 'string', 'max:30'],
            'logs.*.stage_id' => ['required', 'integer', Rule::in($validStageIds)],
            'logs.*.shooter_id' => ['required', 'integer', Rule::in($validShooterIds)],
            'logs.*.details' => ['nullable', 'array'],
            'logs.*.device_id' => ['nullable', 'string'],
            'logs.*.performed_at' => ['nullable', 'date'],
        ]);

        $created = 0;

        foreach ($validated['logs'] as $entry) {
            CorrectionLog::create([
                'match_id' => $match->id,
                'stage_id' => $entry['stage_id'],
                'shooter_id' => $entry['shooter_id'],
                'action' => $entry['action'],
                'details' => $entry['details'] ?? null,
                'device_id' => $entry['device_id'] ?? null,
                'performed_at' => $entry['performed_at'] ?? null,
            ]);
            $created++;
        }

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
        $this->authorizeMatchDirector($request, $match);

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
            $existing = StageTime::where('shooter_id', $shooter->id)
                ->where('target_set_id', $targetSet->id)
                ->first();
            $old = $existing?->toArray();

            $stageTime = StageTime::updateOrCreate(
                [
                    'shooter_id' => $shooter->id,
                    'target_set_id' => $targetSet->id,
                ],
                [
                    'time_seconds' => $validated['time_seconds'],
                    'device_id' => $request->header('X-Device-Id', $request->input('device_id', 'correction')),
                    'recorded_at' => now(),
                ],
            );

            if ($existing) {
                ScoreAuditService::logUpdated($match->id, $stageTime, $old ?? [], $validated['reason'], $request);
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

            foreach ($validated['shots'] as $shot) {
                $existingShot = PrsShotScore::where('shooter_id', $shooter->id)
                    ->where('stage_id', $stage->id)
                    ->where('shot_number', $shot['shot_number'])
                    ->first();
                $oldShotValues = $existingShot?->toArray();

                $savedShot = PrsShotScore::updateOrCreate(
                    [
                        'shooter_id' => $shooter->id,
                        'stage_id' => $stage->id,
                        'shot_number' => $shot['shot_number'],
                    ],
                    [
                        'match_id' => $match->id,
                        'result' => $shot['result'],
                        'device_id' => $deviceId,
                        'recorded_at' => now(),
                        'created_by' => $request->user()->id,
                        'updated_by' => $request->user()->id,
                    ],
                );

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

            $officialTime = $rawTime;
            if ($officialTime !== null && $stage->par_time_seconds) {
                $allHit = $hits === ($stage->total_shots ?? 0);
                if (! $allHit) {
                    $officialTime = max($officialTime, (float) $stage->par_time_seconds);
                }
            }

            $existingResult = PrsStageResult::where('shooter_id', $shooter->id)
                ->where('stage_id', $stage->id)
                ->first();
            $oldResultValues = $existingResult?->toArray();

            $stageResult = PrsStageResult::updateOrCreate(
                [
                    'shooter_id' => $shooter->id,
                    'stage_id' => $stage->id,
                ],
                [
                    'match_id' => $match->id,
                    'hits' => $hits,
                    'misses' => $misses,
                    'not_taken' => $notTaken,
                    'raw_time_seconds' => $rawTime,
                    'official_time_seconds' => $officialTime,
                    'completed_at' => now(),
                    'completed_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ],
            );

            if ($existingResult && $oldResultValues) {
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
        $user = $request->user();

        $authorized = $user->isOwner()
            || $match->created_by === $user->id
            || ($match->organization && $user->isOrgAdmin($match->organization));

        if (! $authorized) {
            abort(403, 'Only the match director or admin can perform this action.');
        }
    }
}
