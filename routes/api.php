<?php

use App\Enums\PrsShotResult;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DisqualificationController;
use App\Http\Controllers\Api\ElrRankingController;
use App\Http\Controllers\Api\ElrScoreController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\MemberMatchController;
use App\Http\Controllers\Api\PrsScoreController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\ScoreboardController;
use App\Http\Controllers\Api\ScoreController;
use App\Http\Controllers\Api\ScoreManagementController;
use App\Http\Controllers\Api\ShooterManagementController;
use App\Http\Controllers\Api\SeasonController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Middleware\EnforceDeviceLock;
use App\Models\PrsShotScore;
use App\Models\PrsStageResult;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\StageTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Throttle credential submission to blunt password brute-forcing / spraying.
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::get('matches/{match}/scoreboard', [ScoreboardController::class, 'show']);
Route::get('matches/{match}/elr-rankings', [ElrRankingController::class, 'show']);

Route::get('seasons', [SeasonController::class, 'index']);
Route::get('seasons/{season}/standings', [SeasonController::class, 'standings']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', [AuthController::class, 'user']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('auth/verify-password', [AuthController::class, 'verifyPassword'])->middleware('throttle:10,1');

    Route::get('matches', [MatchController::class, 'index']);
    Route::get('matches/{match}', [MatchController::class, 'show']);

    Route::get('member/matches', [MemberMatchController::class, 'index']);

    // ── Scoring surface ──────────────────────────────────────────────
    // Every route here is driven exclusively by the scoring app. Enforce the
    // Sanctum `scoring` ability so the short-lived web scoring token (minted
    // with `['scoring']` by the /score route) is the only credential class
    // that reaches score data — and, conversely, so a leaked scoring token
    // can't be replayed against member endpoints. Full-access tokens (`*`,
    // issued by the native /login flow) satisfy `ability:scoring` as well.
    Route::middleware('ability:scoring')->group(function () {
        Route::post('matches/{match}/scores', [ScoreController::class, 'store']);
        Route::patch('matches/{match}/shooters/{shooter}/status', [ScoreController::class, 'updateShooterStatus']);
        Route::post('matches/{match}/elr-shots', [ElrScoreController::class, 'store']);
        Route::post('matches/{match}/elr-team-stage', [ElrScoreController::class, 'teamStage']);

        Route::post('matches/{match}/stages/{stage}/score', [PrsScoreController::class, 'store'])->middleware(EnforceDeviceLock::class);
        Route::get('matches/{match}/stages/{stage}/scores', [PrsScoreController::class, 'show']);

        // PRS corrections — same paths and payloads as the Android hub.
        Route::post('matches/{match}/stages/{stage}/reassign', [ScoreManagementController::class, 'reassignStage']);
        Route::post('matches/{match}/stages/{stage}/move', [ScoreManagementController::class, 'moveStage']);
        Route::get('matches/{match}/correction-logs', [ScoreManagementController::class, 'correctionLogs']);
        Route::post('matches/{match}/correction-logs', [ScoreManagementController::class, 'storeCorrectionLogs']);
        Route::post('matches/{match}/complete', [ScoreManagementController::class, 'completeMatch']);
        Route::post('matches/{match}/reopen', [ScoreManagementController::class, 'reopenMatch']);
        // Single-shooter correction: powers the inline "tap a row on the
        // stage summary → fix this shooter" modal across native, PWA, and
        // web. Both standard and PRS scoring routed through here.
        Route::post('matches/{match}/shooters/{shooter}/correct', [ScoreManagementController::class, 'correctSingleShooter']);

        // Walk-ins, squad moves, and firing order for Manage Shooters.
        Route::post('matches/{match}/shooters', [ShooterManagementController::class, 'store']);
        Route::patch('matches/{match}/shooters/{shooter}/squad', [ShooterManagementController::class, 'move']);
        Route::patch('matches/{match}/shooters/{shooter}/order', [ShooterManagementController::class, 'reorder']);

        // Side-bet buy-in management (MD only) — drives the scoring-app's
        // Buy-Ins sub-tab so the MD can add/remove shooters from the pot
        // without leaving the scoring SPA.
        Route::get('matches/{match}/side-bet/buy-ins', [ScoreManagementController::class, 'sideBetBuyIns']);
        Route::post('matches/{match}/side-bet/toggle/{shooter}', [ScoreManagementController::class, 'toggleSideBetShooter']);

        // Disqualifications (MD only)
        Route::get('matches/{match}/disqualifications', [DisqualificationController::class, 'index']);
        Route::post('matches/{match}/disqualifications', [DisqualificationController::class, 'store']);
        Route::delete('matches/{match}/disqualifications/{disqualification}', [DisqualificationController::class, 'destroy']);

        Route::get('matches/{match}/scores/sync', [SyncController::class, 'scores']);
    });

    // Diagnostic + one-shot repair endpoints. Both are match-director-only
    // maintenance tools (previously prs-backfill was public + a GET, so
    // anyone could mutate scoring data by hitting the URL). Kept inline so
    // the auth check lives next to the handler.
    $requireMatchDirector = function (Request $request, ShootingMatch $match): void {
        abort_unless($request->user()?->can('maintain', $match), 403, 'Match director only.');
    };

    Route::post('matches/{match}/prs-backfill', function (Request $request, ShootingMatch $match) use ($requireMatchDirector) {
        $requireMatchDirector($request, $match);

        if (! $match->isPrs()) {
            return response()->json(['message' => 'Not a PRS match'], 422);
        }

        $shots = PrsShotScore::where('match_id', $match->id)->get();
        $grouped = $shots->groupBy(fn ($s) => "{$s->shooter_id}-{$s->stage_id}");
        $existing = PrsStageResult::whereIn('stage_id', $shots->pluck('stage_id')->unique())
            ->get(['shooter_id', 'stage_id'])
            ->mapWithKeys(fn ($r) => ["{$r->shooter_id}-{$r->stage_id}" => true]);
        $created = 0;

        foreach ($grouped as $key => $stageShots) {
            if ($existing->has($key)) {
                continue;
            }
            [$shooterId, $stageId] = explode('-', $key);

            $hits = $stageShots->where('result', PrsShotResult::Hit)->count();
            $misses = $stageShots->where('result', PrsShotResult::Miss)->count();
            $notTaken = $stageShots->where('result', PrsShotResult::NotTaken)->count();

            PrsStageResult::create([
                'match_id' => $match->id,
                'shooter_id' => (int) $shooterId,
                'stage_id' => (int) $stageId,
                'hits' => $hits,
                'misses' => $misses,
                'not_taken' => $notTaken,
                'completed_at' => $stageShots->first()->recorded_at,
            ]);
            $created++;
        }

        return response()->json(['message' => "Backfilled $created missing PrsStageResult records"]);
    });

    Route::get('matches/{match}/prs-diagnostic', function (Request $request, ShootingMatch $match) use ($requireMatchDirector) {
        $requireMatchDirector($request, $match);

        $stageResults = PrsStageResult::where('match_id', $match->id)->get();
        $shotScores = PrsShotScore::where('match_id', $match->id)->count();
        $stages = $match->targetSets()->select(['id', 'label', 'is_timed_stage', 'total_shots'])->withCount('gongs')->get();
        $matchShooterIds = Shooter::whereIn('squad_id', $match->squads()->select('id'))->select('id');

        return response()->json([
            'match_id' => $match->id,
            'scoring_type' => $match->scoring_type,
            'is_prs' => $match->isPrs(),
            'stages' => $stages->map(fn ($s) => [
                'id' => $s->id,
                'label' => $s->label,
                'is_timed' => $s->is_timed_stage,
                'total_shots' => $s->total_shots,
                'gong_count' => $s->gongs_count,
            ]),
            'prs_stage_results' => $stageResults->map(fn ($r) => [
                'shooter_id' => $r->shooter_id,
                'stage_id' => $r->stage_id,
                'hits' => $r->hits,
                'misses' => $r->misses,
                'time' => $r->raw_time_seconds,
                'updated_at' => $r->updated_at?->toIso8601String(),
            ]),
            'total_prs_shot_scores' => $shotScores,
            'standard_scores_count' => Score::whereIn('shooter_id', $matchShooterIds)->count(),
            'stage_times_count' => StageTime::whereIn('shooter_id', $matchShooterIds)->count(),
        ]);
    });

    Route::post('push/subscribe', [PushSubscriptionController::class, 'subscribe']);
    Route::delete('push/unsubscribe', [PushSubscriptionController::class, 'unsubscribe']);

    Route::get('notifications', function (Request $request) {
        return response()->json([
            'notifications' => $request->user()->notifications()->latest()->take(30)->get()->map(fn ($n) => [
                'id' => $n->id,
                'type' => class_basename($n->type),
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
            ]),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    });
    Route::post('notifications/{id}/read', function (Request $request, string $id) {
        $request->user()->notifications()->where('id', $id)->first()?->markAsRead();

        return response()->json(['success' => true]);
    });
    Route::post('notifications/read-all', function (Request $request) {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    });
});
