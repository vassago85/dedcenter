<?php

use App\Models\Organization;

it('only lists portal pages for organizations whose portal is public', function () {
    $public = Organization::factory()->create(['status' => 'active', 'portal_enabled' => true]);
    $hidden = Organization::factory()->create(['status' => 'active', 'portal_enabled' => false]);

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)->toContain(route('portal.home', $public))
        ->and($xml)->not->toContain(route('portal.home', $hidden))
        ->and($xml)->toContain(route('leaderboard', $hidden))
        ->and($xml)->toContain(url('/events'));
});
