<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\BadgeGalleryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MatchBookController;
use App\Http\Controllers\MatchExportController;
use App\Http\Controllers\MatchReportController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SponsorInfoController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// ══════════════════════════════════════════════════
// Auth (guest)
// ══════════════════════════════════════════════════

Volt::route('/login', 'auth.login')->name('login');
Volt::route('/register', 'auth.register')->name('register');
Volt::route('/verify-email', 'auth.verify-email')->middleware('auth')->name('verification.notice');
Volt::route('/forgot-password', 'auth.forgot-password')->middleware('guest')->name('password.request');
Volt::route('/reset-password/{token}', 'auth.reset-password')->middleware('guest')->name('password.reset');

// ══════════════════════════════════════════════════
// Public landing — domain-aware
// ══════════════════════════════════════════════════

Route::get('/', [HomeController::class, '__invoke'])->name('home');

// ══════════════════════════════════════════════════
// Public marketing pages
// ══════════════════════════════════════════════════

Volt::route('/features', 'features')->name('features');
Volt::route('/scoring', 'scoring-info')->name('scoring');
Volt::route('/sponsorships', 'sponsorships')->name('sponsorships');
Volt::route('/advertise', 'advertise')->name('advertise');
Volt::route('/sponsor-marketplace', 'sponsor-marketplace')->name('sponsor-marketplace');
Volt::route('/offline', 'offline')->name('offline');
Volt::route('/setup', 'setup')->name('setup');
Volt::route('/privacy', 'privacy')->name('privacy');
Volt::route('/terms', 'terms')->name('terms');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// ── Private Sponsor Info (token-protected, not in navigation) ──
Route::get('/sponsor-info/{token}', [SponsorInfoController::class, 'show'])->name('sponsor-info.show');

Route::get('/app-login', [AuthController::class, 'tokenLogin'])->name('app.login');

Route::get('/score/{any?}', function () {
    $user = auth()->user();
    $user->tokens()->where('name', 'scoring-session')->delete();
    $token = $user->createToken('scoring-session')->plainTextToken;

    return view('scoring', ['apiToken' => $token]);
})->where('any', '.*')->middleware(['auth', 'verified'])->name('score');

Volt::route('/scoreboard/{match}', 'scoreboard')->name('scoreboard');
Route::middleware('auth')->group(function () {
    Route::get('/scoreboard/{match}/export/standings', [MatchExportController::class, 'standings'])->name('scoreboard.export.standings');
    Route::get('/scoreboard/{match}/export/detailed', [MatchExportController::class, 'detailed'])->name('scoreboard.export.detailed');
});
Volt::route('/live/{match}', 'live')->name('live');
Route::get('/badges-preview', BadgeGalleryController::class)->name('badges.preview');
Volt::route('/events', 'events')->name('events');
Volt::route('/events/{match}', 'event-detail')->name('events.show');
Volt::route('/shooters/{user}', 'shooter-profile')->name('shooter.profile');
Volt::route('/leaderboard/{organization}', 'leaderboard')->name('leaderboard');

// ── Public Portal (white-label org pages; requires paid entitlement + org toggle) ──
Route::middleware('org.portal')->prefix('p/{organization}')->name('portal.')->group(function () {
    Volt::route('/', 'portal.landing')->name('home');
    Volt::route('/matches', 'portal.matches')->name('matches');
    Volt::route('/matches/{match}', 'portal.match-detail')->name('matches.show');
    Volt::route('/leaderboard', 'portal.leaderboard')->name('leaderboard');
});

// ══════════════════════════════════════════════════
// Member (auth)
// ══════════════════════════════════════════════════

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('/welcome', 'member.welcome')->name('welcome');
    Volt::route('/dashboard', 'member.dashboard')->name('dashboard');
    Volt::route('/matches', 'member.matches')->name('matches');
    Volt::route('/matches/{match}', 'member.match-detail')->name('matches.show');
    Volt::route('/matches/{match}/squadding', 'member.match-squadding')->name('matches.squadding');
    Volt::route('/browse-events', 'member.browse-events')->name('browse-events');
    Volt::route('/equipment', 'member.equipment')->name('equipment');
    Volt::route('/organizations', 'member.organizations')->name('organizations');
    Volt::route('/organizations/create', 'member.organization-create')->name('organizations.create');
    Volt::route('/settings', 'member.settings')->name('settings');
    Volt::route('/notifications', 'member.notifications')->name('notifications');
    Volt::route('/settings/notifications', 'member.notification-settings')->name('settings.notifications');
    Volt::route('/events/{match}/register', 'member.register-for-match')->name('events.register');
    Route::get('/matches/{match}/report/download', [MatchReportController::class, 'download'])->name('matches.report.download');
});

// ══════════════════════════════════════════════════
// Organization Admin (auth + org.admin)
// ══════════════════════════════════════════════════

Route::middleware(['auth', 'verified', 'org.admin'])->prefix('org/{organization}')->name('org.')->group(function () {
    Volt::route('/dashboard', 'org.dashboard')->name('dashboard');
    Volt::route('/matches', 'org.matches.index')->name('matches.index');
    Volt::route('/matches/create', 'org.matches.edit')->name('matches.create');
    Volt::route('/matches/{match}', 'org.matches.edit')->name('matches.edit');
    Volt::route('/matches/{match}/squadding', 'org.matches.squadding')->name('matches.squadding');
    Route::get('/matches/{match}/export/standings', [MatchExportController::class, 'standings'])->name('matches.export.standings');
    Route::get('/matches/{match}/export/detailed', [MatchExportController::class, 'detailed'])->name('matches.export.detailed');
    Route::get('/matches/{match}/export/pdf-standings', [MatchExportController::class, 'pdfStandings'])->name('matches.export.pdf-standings');
    Route::get('/matches/{match}/export/pdf-detailed', [MatchExportController::class, 'pdfDetailed'])->name('matches.export.pdf-detailed');
    Volt::route('/matches/{match}/side-bet-report', 'org.matches.side-bet-report')->name('matches.side-bet-report');

    // ── Match Reports ──
    Route::get('/matches/{match}/report/preview', [MatchReportController::class, 'preview'])->name('matches.report.preview');
    Route::post('/matches/{match}/report/send', [MatchReportController::class, 'send'])->name('matches.report.send');

    // ── Match Book (gated — see config/deadcenter.php) ──
    Route::middleware('matchbook.enabled')->group(function () {
        Route::get('/matches/{match}/matchbook', [MatchBookController::class, 'show'])->name('matches.matchbook.show');
        Volt::route('/matches/{match}/matchbook/edit', 'org.matches.matchbook')->name('matches.matchbook.edit');
        Route::get('/matches/{match}/matchbook/preview', [MatchBookController::class, 'preview'])->name('matches.matchbook.preview');
        Route::get('/matches/{match}/matchbook/download', [MatchBookController::class, 'download'])->name('matches.matchbook.download');
    });

    Volt::route('/registrations', 'org.registrations')->name('registrations');
    Volt::route('/admins', 'org.admins')->name('admins');
    Volt::route('/clubs', 'org.clubs')->name('clubs');
    Volt::route('/settings', 'org.settings')->name('settings');
    Volt::route('/portal-sponsors', 'org.portal-sponsors')->name('portal-sponsors');
});

// ══════════════════════════════════════════════════
// Site Admin (auth + admin)
// ══════════════════════════════════════════════════

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('/dashboard', 'admin.dashboard')->name('dashboard');
    Volt::route('/organizations', 'admin.organizations')->name('organizations');
    Volt::route('/members', 'admin.members')->name('members');
    Volt::route('/matches', 'admin.matches.index')->name('matches.index');
    Volt::route('/matches/create', 'admin.matches.edit')->name('matches.create');
    Volt::route('/matches/{match}', 'admin.matches.edit')->name('matches.edit');
    Volt::route('/matches/{match}/squadding', 'admin.matches.squadding')->name('matches.squadding');
    Route::get('/matches/{match}/export/standings', [MatchExportController::class, 'standings'])->name('matches.export.standings');
    Route::get('/matches/{match}/export/detailed', [MatchExportController::class, 'detailed'])->name('matches.export.detailed');
    Route::get('/matches/{match}/export/pdf-standings', [MatchExportController::class, 'pdfStandings'])->name('matches.export.pdf-standings');
    Route::get('/matches/{match}/export/pdf-detailed', [MatchExportController::class, 'pdfDetailed'])->name('matches.export.pdf-detailed');
    Volt::route('/matches/{match}/side-bet-report', 'admin.matches.side-bet-report')->name('matches.side-bet-report');

    // ── Match Reports ──
    Route::get('/matches/{match}/report/preview', [MatchReportController::class, 'preview'])->name('matches.report.preview');
    Route::post('/matches/{match}/report/send', [MatchReportController::class, 'send'])->name('matches.report.send');

    // ── Match Book (gated — see config/deadcenter.php) ──
    Route::middleware('matchbook.enabled')->group(function () {
        Route::get('/matches/{match}/matchbook', [MatchBookController::class, 'show'])->name('matches.matchbook.show');
        Volt::route('/matches/{match}/matchbook/edit', 'admin.matches.matchbook')->name('matches.matchbook.edit');
        Route::get('/matches/{match}/matchbook/preview', [MatchBookController::class, 'preview'])->name('matches.matchbook.preview');
        Route::get('/matches/{match}/matchbook/download', [MatchBookController::class, 'download'])->name('matches.matchbook.download');
    });

    // ── Brands & Advertising ──
    Volt::route('/sponsors', 'admin.sponsors')->name('sponsors');
    Volt::route('/advertising', 'admin.advertising')->name('advertising');
    Volt::route('/sponsor-assignments', 'admin.sponsor-assignments')->name('sponsor-assignments');
    Volt::route('/sponsor-info', 'admin.sponsor-info')->name('sponsor-info');
    Volt::route('/contact-submissions', 'admin.contact-submissions')->name('contact-submissions');

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
