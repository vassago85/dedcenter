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
});
