<?php

/**
 * Regression test: the MatchExportController methods are shared between
 * three route groups:
 *
 *   1. org/{organization}/matches/{match}/export/...      (org.admin)
 *   2. admin/matches/{match}/export/...                   (admin)
 *   3. scoreboard/{match}/export/...                      (public)
 *
 * The org group passes an Organization as the first route parameter, but
 * the other two don't. If the controller signatures don't accept a
 * nullable leading Organization, Laravel's ControllerDispatcher passes
 * the slug string into the ShootingMatch arg and triggers a TypeError
 * (a 500). This file locks that in for all affected endpoints.
 *
 * See error in prod: "Argument #1 ($match) must be of type ShootingMatch,
 * string given" when hitting /org/royal-flush-1/matches/15/export/...
 */

use App\Models\Gong;
use App\Models\Organization;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->org = Organization::factory()->create(['created_by' => $this->admin->id]);
    $this->org->admins()->attach($this->admin->id, ['is_owner' => true]);

    $this->match = ShootingMatch::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->admin->id,
        'scoring_type' => 'standard',
    ]);

    $ts = TargetSet::create([
        'match_id' => $this->match->id,
        'label' => '400m',
        'distance_meters' => 400,
        'distance_multiplier' => 4.0,
        'sort_order' => 1,
    ]);
    Gong::create(['target_set_id' => $ts->id, 'number' => 1, 'label' => 'G1', 'multiplier' => '1.00']);

    $this->squad = Squad::create(['match_id' => $this->match->id, 'name' => 'Alpha']);
    $this->shooter = Shooter::factory()->create([
        'squad_id' => $this->squad->id,
        'name' => 'Test Shooter',
    ]);
});

test('org pdf-standings route resolves without TypeError', function () {
    $res = $this->actingAs($this->admin)
        ->get(route('org.matches.export.pdf-standings', [
            'organization' => $this->org,
            'match' => $this->match,
        ]));

    // The renderer may fail (Gotenberg not running in CI), but what we
    // care about is that the controller dispatcher successfully bound
    // both route params. That means NOT a 500 TypeError.
    expect($res->status())->not->toBe(500);
});

test('org pdf-full-match-report route resolves without TypeError', function () {
    $res = $this->actingAs($this->admin)
        ->get(route('org.matches.export.pdf-executive-summary', [
            'organization' => $this->org,
            'match' => $this->match,
        ]));

    expect($res->status())->not->toBe(500);
});

test('org pdf-shooter-report route resolves without TypeError', function () {
    $res = $this->actingAs($this->admin)
        ->get(route('org.matches.export.pdf-shooter-report', [
            'organization' => $this->org,
            'match' => $this->match,
            'shooter' => $this->shooter,
        ]));

    expect($res->status())->not->toBe(500);
});

test('admin pdf-shooter-report route still resolves without TypeError', function () {
    $res = $this->actingAs($this->admin)
        ->get(route('admin.matches.export.pdf-shooter-report', [
            'match' => $this->match,
            'shooter' => $this->shooter,
        ]));

    expect($res->status())->not->toBe(500);
});

test('public scoreboard standings CSV export still resolves', function () {
    $res = $this->actingAs($this->admin)
        ->get(route('scoreboard.export.standings', ['match' => $this->match]));

    expect($res->status())->not->toBe(500);
});

test('org export rejects cross-org match (tamper guard)', function () {
    // Different org — user is NOT an admin of it, but they ARE a site
    // admin so authorizeExport() lets them through. The ensureOrgMatch
    // check must still 404 because the match doesn't belong to that org.
    $otherOrg = Organization::factory()->create(['created_by' => $this->admin->id]);

    $res = $this->actingAs($this->admin)
        ->get(route('org.matches.export.pdf-standings', [
            'organization' => $otherOrg,
            'match' => $this->match,
        ]));

    expect($res->status())->toBe(404);
});
