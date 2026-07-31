<?php

/**
 * Regression: MatchReportController::preview()/send() are shared between
 * the org group (prefix `org/{organization}`) and the admin group (prefix
 * `admin`). Both methods take a positional `$orgOrAdmin` argument BETWEEN
 * `$request` and `$match`. The org route fills it from the `{organization}`
 * segment; the admin route has no such segment, so without an explicit
 * `->defaults('orgOrAdmin', 'admin')` Laravel invokes the method with 2
 * args instead of 3 → ArgumentCountError → 500.
 *
 * Prod error this locks in: "Too few arguments to function
 * App\Http\Controllers\MatchReportController::preview(), 2 passed ... and
 * exactly 3 expected" when hitting /admin/matches/{match}/report/preview.
 */

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\Organization;
use App\Models\Score;
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
        'status' => MatchStatus::Completed,
    ]);

    $ts = TargetSet::create([
        'match_id' => $this->match->id,
        'label' => '400m',
        'distance_meters' => 400,
        'distance_multiplier' => 1.0,
        'sort_order' => 1,
    ]);
    $gong = Gong::create(['target_set_id' => $ts->id, 'number' => 1, 'label' => 'G1', 'multiplier' => '1.00']);

    $this->squad = Squad::create(['match_id' => $this->match->id, 'name' => 'Alpha', 'sort_order' => 1]);
    $this->shooter = Shooter::factory()->create([
        'squad_id' => $this->squad->id,
        'name' => 'Test Shooter',
        'status' => 'active',
    ]);
    Score::create([
        'shooter_id' => $this->shooter->id,
        'gong_id' => $gong->id,
        'is_hit' => true,
        'recorded_at' => now(),
    ]);
});

test('admin report preview resolves the $orgOrAdmin arg (no ArgumentCountError)', function () {
    $res = $this->actingAs($this->admin)
        ->get(route('admin.matches.report.preview', ['match' => $this->match]));

    expect($res->status())->not->toBe(500);
    $res->assertOk();
});

test('org report preview still resolves after the admin-route fix', function () {
    $res = $this->actingAs($this->admin)
        ->get(route('org.matches.report.preview', [
            'organization' => $this->org,
            'match' => $this->match,
        ]));

    expect($res->status())->not->toBe(500);
    $res->assertOk();
});

test('admin report send resolves the $orgOrAdmin arg (no ArgumentCountError)', function () {
    $res = $this->actingAs($this->admin)
        ->post(route('admin.matches.report.send', ['match' => $this->match]));

    // send() redirects back with a flash message — the regression is only
    // that it no longer 500s on the missing positional $orgOrAdmin arg.
    expect($res->status())->not->toBe(500);
});
