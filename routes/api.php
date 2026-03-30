<?php

use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\ScoreboardController;
use App\Http\Controllers\Api\ScoreController;
use Illuminate\Support\Facades\Route;

Route::get('matches', [MatchController::class, 'index']);
Route::get('matches/{match}', [MatchController::class, 'show']);
Route::get('matches/{match}/scoreboard', [ScoreboardController::class, 'show']);

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
