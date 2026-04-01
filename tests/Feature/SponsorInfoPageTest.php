<?php

use App\Models\Setting;

it('returns 404 without valid token', function () {
    $this->get('/sponsor-info/invalid-token')->assertNotFound();
});

it('returns 404 when no token is configured', function () {
    Setting::set('sponsor_info_access_token', null);
    $this->get('/sponsor-info/anything')->assertNotFound();
});

it('returns 200 with correct token', function () {
    Setting::set('sponsor_info_access_token', 'test-token-123');
    $this->get('/sponsor-info/test-token-123')->assertOk();
});

it('includes noindex meta tag', function () {
    Setting::set('sponsor_info_access_token', 'noindex-test');
    $response = $this->get('/sponsor-info/noindex-test');
    $response->assertOk();
    $response->assertSee('noindex, nofollow', false);
});

it('renders content sections from settings', function () {
    Setting::set('sponsor_info_access_token', 'content-test');
    Setting::set('sponsor_info_overview', 'DeadCenter is a competition platform.');
    Setting::set('sponsor_info_tiers', 'Tier 1: Match Presence');

    $response = $this->get('/sponsor-info/content-test');
    $response->assertOk();
    $response->assertSee('DeadCenter is a competition platform.');
    $response->assertSee('Tier 1: Match Presence');
});
