<?php

/**
 * Reproduce the 500 reported on
 * /org/royal-flush-1/matches/15/side-bet-report
 *
 * Both the org-scoped and admin-scoped side-bet-report Volt components
 * should render cleanly for a Royal Flush match with side bets enabled.
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
        'scoring_type' => 'royal_flush',
        'royal_flush_enabled' => true,
        'side_bet_enabled' => true,
    ]);

    // Real-world shape: multiple distances, multiple gongs per distance,
    // multiple shooters registered for the side bet, scored hits on the
    // smallest gongs so the cascading tiebreaker path actually runs.
    $ts400 = TargetSet::create([
        'match_id' => $this->match->id,
        'label' => '400m', 'distance_meters' => 400, 'distance_multiplier' => 4.0, 'sort_order' => 1,
    ]);
    $ts500 = TargetSet::create([
        'match_id' => $this->match->id,
        'label' => '500m', 'distance_meters' => 500, 'distance_multiplier' => 5.0, 'sort_order' => 2,
    ]);
    $g400_small = Gong::create(['target_set_id' => $ts400->id, 'number' => 1, 'label' => 'G1', 'multiplier' => '2.00']);
    $g400_big   = Gong::create(['target_set_id' => $ts400->id, 'number' => 2, 'label' => 'G2', 'multiplier' => '1.00']);
    $g500_small = Gong::create(['target_set_id' => $ts500->id, 'number' => 1, 'label' => 'G1', 'multiplier' => '2.00']);

    $this->squad = Squad::create(['match_id' => $this->match->id, 'name' => 'Alpha']);

    $a = Shooter::factory()->create(['squad_id' => $this->squad->id, 'name' => 'Alice']);
    $b = Shooter::factory()->create(['squad_id' => $this->squad->id, 'name' => 'Bob']);

    \DB::table('side_bet_shooters')->insert([
        ['match_id' => $this->match->id, 'shooter_id' => $a->id, 'created_at' => now()],
        ['match_id' => $this->match->id, 'shooter_id' => $b->id, 'created_at' => now()],
    ]);

    // Alice: both small-gong hits. Bob: one small-gong hit + one big.
    // Forces describeTiebreaker to fire.
    \DB::table('scores')->insert([
        ['shooter_id' => $a->id, 'gong_id' => $g400_small->id, 'is_hit' => true, 'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['shooter_id' => $a->id, 'gong_id' => $g500_small->id, 'is_hit' => true, 'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['shooter_id' => $b->id, 'gong_id' => $g400_small->id, 'is_hit' => true, 'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['shooter_id' => $b->id, 'gong_id' => $g400_big->id,   'is_hit' => true, 'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('org side-bet-report renders without 500', function () {
    $this->withoutExceptionHandling();

    $res = $this->actingAs($this->admin)
        ->get(route('org.matches.side-bet-report', [
            'organization' => $this->org,
            'match' => $this->match,
        ]));

    expect($res->status())->toBe(200);
});

test('admin side-bet-report renders without 500', function () {
    $this->withoutExceptionHandling();

    $res = $this->actingAs($this->admin)
        ->get(route('admin.matches.side-bet-report', [
            'match' => $this->match,
        ]));

    expect($res->status())->toBe(200);
});
