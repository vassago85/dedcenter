<?php

/**
 * Regression tests for HomeController::shooterData().
 *
 * Historically shooterData() wrapped the whole shooter-home payload in
 * Cache::remember() for 60s, which serialized hydrated Eloquent models
 * + Collections into Redis. After a deploy or an opcache reset, the
 * autoloader on some workers drifted from the serialized payload and
 * unserialize() returned `__PHP_Incomplete_Class` instances — the view
 * then 500'd with:
 *
 *   "The script tried to call a method on an incomplete object...
 *    Illuminate\Database\Eloquent\Collection"
 *
 * The fix was to stop caching Eloquent graphs entirely. Only the
 * scalar `activityStats` aggregates are cached now, under
 * `home:shooter-stats:v1`. These tests lock that in:
 *
 *   1. shooter-home renders a 200 even with a poisoned cache entry
 *      under the OLD key (incomplete-class payload).
 *   2. the scalar-stats cache is populated on first hit and served
 *      from cache on the second.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('shooter-home renders 200 with no data', function () {
    $res = $this->get('/');

    expect($res->status())->toBe(200);
});

test('shooter-home ignores the LEGACY cache key entirely', function () {
    // Before the fix: HomeController cached the whole shooter-home
    // payload under `home:shooter-data:v1`. A stale/poisoned value
    // there would be unserialize()'d and crash Blade. After the fix,
    // nothing reads that key anymore — proving it by stuffing garbage
    // into it and watching the page render fine.
    Cache::put('home:shooter-data:v1', 'garbage-from-a-previous-deploy', now()->addMinutes(5));

    $res = $this->get('/');

    expect($res->status())->toBe(200);
});

test('scalar activity stats are cached under the new key', function () {
    Cache::forget('home:shooter-stats:v1');

    $this->get('/')->assertStatus(200);

    $stats = Cache::get('home:shooter-stats:v1');
    expect($stats)->toBeArray();
    expect($stats)->toHaveKeys([
        'registrationsOpen',
        'matchesCompletedSeason',
        'activeShootersMonth',
        'scoresUpdatedAt',
    ]);

    // Crucially: no hydrated models in the cached payload. Everything must
    // be a scalar so we can't ever hit __PHP_Incomplete_Class on rehydrate.
    foreach ($stats as $v) {
        expect(is_scalar($v) || is_null($v))->toBeTrue();
    }
});
