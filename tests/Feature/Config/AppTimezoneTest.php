<?php

/**
 * Regression: DeadCenter operates exclusively in South Africa. Every
 * match time, registration window, and results timestamp is spoken
 * about in SAST. The app clock must be Africa/Johannesburg so that
 * Carbon::now(), DB timestamps, and scheduled jobs all speak SAST
 * without any per-view `->tz(...)` conversion.
 *
 * Previously config/app.php was hardcoded to 'UTC' which silently
 * overrode the APP_TIMEZONE=Africa/Johannesburg env var baked into
 * every Docker container. This locks in the fix.
 */

test('application timezone is Africa/Johannesburg', function () {
    expect(config('app.timezone'))->toBe('Africa/Johannesburg');
});

test('Carbon::now() returns a SAST timestamp', function () {
    $now = now();

    expect($now->timezoneName)->toBe('Africa/Johannesburg');
    // South Africa does not observe DST — always UTC+02:00.
    expect($now->getOffset())->toBe(7200);
});
