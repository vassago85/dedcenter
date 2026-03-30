<?php

use App\Http\Controllers\Api\ElrScoreController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\ScoreboardController;
use App\Http\Controllers\Api\ScoreController;
use App\Http\Controllers\Api\SeasonController;
use Illuminate\Support\Facades\Route;

Route::get('matches', [MatchController::class, 'index']);
Route::get('matches/{match}', [MatchController::class, 'show']);
Route::get('matches/{match}/scoreboard', [ScoreboardController::class, 'show']);

Route::get('seasons', [SeasonController::class, 'index']);
Route::get('seasons/{season}/standings', [SeasonController::class, 'standings']);

Route::post('matches/{match}/scores', [ScoreController::class, 'store'])
    ->middleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        'auth',
    ]);

Route::patch('matches/{match}/shooters/{shooter}/status', [ScoreController::class, 'updateShooterStatus'])
    ->middleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        'auth',
    ]);

Route::post('matches/{match}/elr-shots', [ElrScoreController::class, 'store'])
    ->middleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        'auth',
    ]);

Route::get('matches/{match}/elr-progress', [ElrScoreController::class, 'progress'])
    ->middleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        'auth',
    ]);
