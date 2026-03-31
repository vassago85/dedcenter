<?php

use App\Http\Controllers\MatchExportController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// ══════════════════════════════════════════════════
// Auth (guest)
// ══════════════════════════════════════════════════

Volt::route('/login', 'auth.login')->name('login');
Volt::route('/register', 'auth.register')->name('register');

// ══════════════════════════════════════════════════
// Public landing — domain-aware
// ══════════════════════════════════════════════════

Route::get('/', [\App\Http\Controllers\HomeController::class, '__invoke'])->name('home');

// ══════════════════════════════════════════════════
// Public marketing pages
// ══════════════════════════════════════════════════

Volt::route('/features', 'features')->name('features');
Volt::route('/scoring', 'scoring-info')->name('scoring');
Volt::route('/offline', 'offline')->name('offline');
Volt::route('/setup', 'setup')->name('setup');

Route::get('/sitemap.xml', \App\Http\Controllers\SitemapController::class)->name('sitemap');

Route::get('/app-login', [\App\Http\Controllers\Api\AuthController::class, 'tokenLogin'])->name('app.login');

Route::get('/score/{any?}', function () {
    $user = auth()->user();
    $user->tokens()->where('name', 'scoring-session')->delete();
    $token = $user->createToken('scoring-session')->plainTextToken;

    return view('scoring', ['apiToken' => $token]);
})->where('any', '.*')->middleware('auth')->name('score');

Volt::route('/scoreboard/{match}', 'scoreboard')->name('scoreboard');
Volt::route('/live/{match}', 'live')->name('live');
Volt::route('/leaderboard/{organization}', 'leaderboard')->name('leaderboard');

// ── Public Portal (white-label org pages) ──
Route::prefix('p/{organization}')->name('portal.')->group(function () {
    Volt::route('/', 'portal.landing')->name('home');
    Volt::route('/matches', 'portal.matches')->name('matches');
    Volt::route('/matches/{match}', 'portal.match-detail')->name('matches.show');
    Volt::route('/leaderboard', 'portal.leaderboard')->name('leaderboard');
});

// ══════════════════════════════════════════════════
// Member (auth)
// ══════════════════════════════════════════════════

Route::middleware('auth')->group(function () {
    Volt::route('/dashboard', 'member.dashboard')->name('dashboard');
    Volt::route('/matches', 'member.matches')->name('matches');
    Volt::route('/matches/{match}', 'member.match-detail')->name('matches.show');
    Volt::route('/organizations', 'member.organizations')->name('organizations');
    Volt::route('/organizations/create', 'member.organization-create')->name('organizations.create');
    Volt::route('/settings', 'member.settings')->name('settings');
});

// ══════════════════════════════════════════════════
// Organization Admin (auth + org.admin)
// ══════════════════════════════════════════════════

Route::middleware(['auth', 'org.admin'])->prefix('org/{organization}')->name('org.')->group(function () {
    Volt::route('/dashboard', 'org.dashboard')->name('dashboard');
    Volt::route('/matches', 'org.matches.index')->name('matches.index');
    Volt::route('/matches/create', 'org.matches.edit')->name('matches.create');
    Volt::route('/matches/{match}', 'org.matches.edit')->name('matches.edit');
    Volt::route('/matches/{match}/squadding', 'org.matches.squadding')->name('matches.squadding');
    Route::get('/matches/{match}/export/standings', [MatchExportController::class, 'standings'])->name('matches.export.standings');
    Route::get('/matches/{match}/export/detailed', [MatchExportController::class, 'detailed'])->name('matches.export.detailed');
    Volt::route('/registrations', 'org.registrations')->name('registrations');
    Volt::route('/admins', 'org.admins')->name('admins');
    Volt::route('/clubs', 'org.clubs')->name('clubs');
    Volt::route('/settings', 'org.settings')->name('settings');
});

// ══════════════════════════════════════════════════
// Site Admin (auth + admin)
// ══════════════════════════════════════════════════

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('/dashboard', 'admin.dashboard')->name('dashboard');
    Volt::route('/organizations', 'admin.organizations')->name('organizations');
    Volt::route('/matches', 'admin.matches.index')->name('matches.index');
    Volt::route('/matches/create', 'admin.matches.edit')->name('matches.create');
    Volt::route('/matches/{match}', 'admin.matches.edit')->name('matches.edit');
    Volt::route('/matches/{match}/squadding', 'admin.matches.squadding')->name('matches.squadding');
    Route::get('/matches/{match}/export/standings', [MatchExportController::class, 'standings'])->name('matches.export.standings');
    Route::get('/matches/{match}/export/detailed', [MatchExportController::class, 'detailed'])->name('matches.export.detailed');
    Volt::route('/registrations', 'admin.registrations')->name('registrations');
    Volt::route('/seasons', 'admin.seasons')->name('seasons');
    Volt::route('/homepage', 'admin.homepage')->name('homepage');
    Volt::route('/settings', 'admin.settings')->name('settings');
});

// ══════════════════════════════════════════════════
// Logout
// ══════════════════════════════════════════════════

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('logout')->middleware('auth');
