<?php

use App\Enums\PrsShotResult;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DisqualificationController;
use App\Http\Controllers\Api\ElrScoreController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\MemberMatchController;
use App\Http\Controllers\Api\PrsScoreController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\ScoreboardController;
use App\Http\Controllers\Api\ScoreController;
use App\Http\Controllers\Api\ScoreManagementController;
use App\Http\Controllers\Api\SeasonController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Middleware\EnforceDeviceLock;
use App\Models\PrsShotScore;
use App\Models\PrsStageResult;
use App\Models\Score;
use App\Models\ShootingMatch;
use App\Models\StageTime;
use App\Models\UserAchievement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);

Route::get('matches/{match}/scoreboard', [ScoreboardController::class, 'show']);

Route::get('matches/{match}/badges', function (ShootingMatch $match) {
    $badges = UserAchievement::where('match_id', $match->id)
        ->with('achievement:id,slug,label,description,category')
        ->get()
        ->groupBy('user_id')
        ->map(fn ($group) => $group->map(fn ($ua) => [
            'slug' => $ua->achievement->slug,
            'label' => $ua->achievement->label,
            'category' => $ua->achievement->category,
        ])->values());

    return response()->json(['badges' => $badges]);
});

Route::get('matches/{match}/prs-backfill', function (ShootingMatch $match) {
    if (! $match->isPrs()) {
        return response()->json(['message' => 'Not a PRS match'], 422);
    }

    $shots = PrsShotScore::where('match_id', $match->id)->get();
    $grouped = $shots->groupBy(fn ($s) => "{$s->shooter_id}-{$s->stage_id}");
    $created = 0;

    foreach ($grouped as $key => $stageShots) {
        [$shooterId, $stageId] = explode('-', $key);
        $existing = PrsStageResult::where('shooter_id', $shooterId)->where('stage_id', $stageId)->first();
        if ($existing) {
            continue;
        }

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

Route::get('seasons', [SeasonController::class, 'index']);
Route::get('seasons/{season}/standings', [SeasonController::class, 'standings']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', [AuthController::class, 'user']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::get('matches', [MatchController::class, 'index']);
    Route::get('matches/{match}', [MatchController::class, 'show']);

    Route::get('member/matches', [MemberMatchController::class, 'index']);

    Route::get('member/achievements', function (Request $request) {
        $badges = UserAchievement::where('user_id', $request->user()->id)
            ->with(['achievement:id,slug,label,description,category,scope,is_repeatable', 'match:id,name,date'])
            ->orderByDesc('awarded_at')
            ->get()
            ->map(fn ($ua) => [
                'id' => $ua->id,
                'slug' => $ua->achievement->slug,
                'label' => $ua->achievement->label,
                'description' => $ua->achievement->description,
                'category' => $ua->achievement->category,
                'match_name' => $ua->match?->name,
                'match_date' => $ua->match?->date?->toDateString(),
                'awarded_at' => $ua->awarded_at->toIso8601String(),
                'metadata' => $ua->metadata,
            ]);

        $grouped = [
            'repeatable' => $badges->where('category', 'repeatable')->values(),
            'lifetime' => $badges->where('category', 'lifetime')->values(),
            'match_special' => $badges->where('category', 'match_special')->values(),
        ];

        return response()->json([
            'achievements' => $grouped,
            'total_count' => $badges->count(),
        ]);
    });

    Route::post('matches/{match}/scores', [ScoreController::class, 'store']);
    Route::patch('matches/{match}/shooters/{shooter}/status', [ScoreController::class, 'updateShooterStatus']);
    Route::get('matches/{match}/shooters/{shooter}/royal-flush-status', [ScoreController::class, 'royalFlushStatus']);
    Route::post('matches/{match}/elr-shots', [ElrScoreController::class, 'store']);
    Route::get('matches/{match}/elr-progress', [ElrScoreController::class, 'progress']);

    Route::post('matches/{match}/stages/{stage}/score', [PrsScoreController::class, 'store'])->middleware(EnforceDeviceLock::class);
    Route::get('matches/{match}/stages/{stage}/scores', [PrsScoreController::class, 'show']);

    // Score management (MD only)
    Route::post('matches/{match}/scores/reassign', [ScoreManagementController::class, 'reassign']);
    Route::post('matches/{match}/scores/reshoot', [ScoreManagementController::class, 'reshoot']);
    Route::get('matches/{match}/audit-log', [ScoreManagementController::class, 'auditLog']);
    Route::post('matches/{match}/scores/publish', [ScoreManagementController::class, 'togglePublish']);
    Route::post('matches/{match}/scores/move-stage', [ScoreManagementController::class, 'moveStage']);
    Route::post('matches/{match}/correction-logs', [ScoreManagementController::class, 'storeCorrectionLogs']);
    Route::post('matches/{match}/complete', [ScoreManagementController::class, 'completeMatch']);
    Route::post('matches/{match}/reopen', [ScoreManagementController::class, 'reopenMatch']);

    // Disqualifications (MD only)
    Route::get('matches/{match}/disqualifications', [DisqualificationController::class, 'index']);
    Route::post('matches/{match}/disqualifications', [DisqualificationController::class, 'store']);
    Route::delete('matches/{match}/disqualifications/{disqualification}', [DisqualificationController::class, 'destroy']);

    Route::get('matches/{match}/scores/sync', [SyncController::class, 'scores']);

    Route::get('matches/{match}/prs-diagnostic', function (ShootingMatch $match) {
        $stageResults = PrsStageResult::where('match_id', $match->id)->get();
        $shotScores = PrsShotScore::where('match_id', $match->id)->count();
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
            'standard_scores_count' => Score::whereHas('shooter', fn ($q) => $q->whereHas('squad', fn ($sq) => $sq->where('match_id', $match->id)))->count(),
            'stage_times_count' => StageTime::whereHas('shooter', fn ($q) => $q->whereHas('squad', fn ($sq) => $sq->where('match_id', $match->id)))->count(),
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
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    });
});
