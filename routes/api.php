<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ElrScoreController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\PrsScoreController;
use App\Http\Controllers\Api\ScoreboardController;
use App\Http\Controllers\Api\ScoreController;
use App\Http\Controllers\Api\ScoreManagementController;
use App\Http\Controllers\Api\SeasonController;
use App\Http\Middleware\EnforceDeviceLock;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);

Route::get('matches/{match}/scoreboard', [ScoreboardController::class, 'show']);

Route::get('seasons', [SeasonController::class, 'index']);
Route::get('seasons/{season}/standings', [SeasonController::class, 'standings']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', [AuthController::class, 'user']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::get('matches', [MatchController::class, 'index']);
    Route::get('matches/{match}', [MatchController::class, 'show']);

    Route::post('matches/{match}/scores', [ScoreController::class, 'store']);
    Route::patch('matches/{match}/shooters/{shooter}/status', [ScoreController::class, 'updateShooterStatus']);
    Route::post('matches/{match}/elr-shots', [ElrScoreController::class, 'store']);
    Route::get('matches/{match}/elr-progress', [ElrScoreController::class, 'progress']);

    Route::post('matches/{match}/stages/{stage}/score', [PrsScoreController::class, 'store'])->middleware(EnforceDeviceLock::class);
    Route::get('matches/{match}/stages/{stage}/scores', [PrsScoreController::class, 'show']);

    // Score management (MD only)
    Route::post('matches/{match}/scores/reassign', [ScoreManagementController::class, 'reassign']);
    Route::post('matches/{match}/scores/reshoot', [ScoreManagementController::class, 'reshoot']);
    Route::get('matches/{match}/audit-log', [ScoreManagementController::class, 'auditLog']);
    Route::post('matches/{match}/scores/publish', [ScoreManagementController::class, 'togglePublish']);

    Route::get('matches/{match}/scores/sync', [\App\Http\Controllers\Api\SyncController::class, 'scores']);

    Route::get('matches/{match}/prs-diagnostic', function (App\Models\ShootingMatch $match) {
        $stageResults = App\Models\PrsStageResult::where('match_id', $match->id)->get();
        $shotScores = App\Models\PrsShotScore::where('match_id', $match->id)->count();
        $stages = $match->targetSets()->get(['id', 'label', 'is_timed_stage', 'total_shots']);
        $gongCounts = $stages->mapWithKeys(fn ($s) => [$s->id => $s->gongs()->count()]);

        return response()->json([
            'match_id' => $match->id,
            'scoring_type' => $match->scoring_type,
            'is_prs' => $match->isPrs(),
            'stages' => $stages->map(fn ($s) => [
                'id' => $s->id,
                'label' => $s->label,
                'is_timed' => $s->is_timed_stage,
                'total_shots' => $s->total_shots,
                'gong_count' => $gongCounts[$s->id] ?? 0,
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
            'standard_scores_count' => App\Models\Score::whereHas('shooter', fn ($q) => $q->whereHas('squad', fn ($sq) => $sq->where('match_id', $match->id)))->count(),
            'stage_times_count' => App\Models\StageTime::whereHas('shooter', fn ($q) => $q->whereHas('squad', fn ($sq) => $sq->where('match_id', $match->id)))->count(),
        ]);
    });

    Route::post('push/subscribe', [\App\Http\Controllers\Api\PushSubscriptionController::class, 'subscribe']);
    Route::delete('push/unsubscribe', [\App\Http\Controllers\Api\PushSubscriptionController::class, 'unsubscribe']);

    Route::get('notifications', function (\Illuminate\Http\Request $request) {
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
    Route::post('notifications/{id}/read', function (\Illuminate\Http\Request $request, string $id) {
        $request->user()->notifications()->where('id', $id)->first()?->markAsRead();
        return response()->json(['success' => true]);
    });
    Route::post('notifications/read-all', function (\Illuminate\Http\Request $request) {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['success' => true]);
    });
});
