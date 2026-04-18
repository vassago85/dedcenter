<?php

namespace App\Http\Controllers\Api;

use App\Enums\PrsShotResult;
use App\Http\Controllers\Controller;
use App\Models\CorrectionLog;
use App\Models\ElrShot;
use App\Models\PrsShotScore;
use App\Models\PrsStageResult;
use App\Models\Score;
use App\Models\ScoreAuditLog;
use App\Models\ShootingMatch;
use App\Services\ScoreAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

            if (!$model) continue;

            $oldShooterId = $model->shooter_id;
            if ($oldShooterId === $validated['new_shooter_id']) continue;

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
            'message' => count($reassigned) . ' score(s) reassigned.',
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

            if (!$model) continue;

            $model->update([
                'is_reshoot' => true,
                'reshoot_reason' => $validated['reason'],
            ]);

            ScoreAuditService::logReshoot($match->id, $model, $validated['reason'], $request);

            $marked[] = $model->getKey();
        }

        return response()->json([
            'message' => count($marked) . ' score(s) marked as reshoot.',
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
                \App\Services\AchievementService::evaluateMatchCompletion($match);
                if ($match->royal_flush_enabled) {
                    \App\Services\AchievementService::evaluateRoyalFlushCompletion($match);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Achievement evaluation failed', ['error' => $e->getMessage()]);
            }

            if ($match->status === \App\Enums\MatchStatus::Completed) {
                \App\Jobs\SendPostMatchNotifications::dispatch($match)->delay(now()->addHour());
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

        if ($match->status !== \App\Enums\MatchStatus::Active) {
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
        $match->update(['status' => \App\Enums\MatchStatus::Completed]);

        try {
            app(\App\Services\NotificationService::class)->onStatusChange($match, $oldStatus, \App\Enums\MatchStatus::Completed);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Status notification dispatch failed', ['error' => $e->getMessage()]);
        }

        try {
            \App\Services\AchievementService::evaluateMatchCompletion($match);
            if ($match->royal_flush_enabled) {
                \App\Services\AchievementService::evaluateRoyalFlushCompletion($match);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Achievement evaluation failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'Match completed.',
            'status' => 'completed',
        ]);
    }

    private function authorizeMatchDirector(Request $request, ShootingMatch $match): void
    {
        $user = $request->user();

        $authorized = $user->isOwner()
            || $match->created_by === $user->id
            || ($match->organization && $user->isOrgAdmin($match->organization));

        if (!$authorized) {
            abort(403, 'Only the match director or admin can perform this action.');
        }
    }
}
