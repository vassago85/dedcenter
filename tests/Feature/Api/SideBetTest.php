<?php

use App\Models\Gong;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->match = ShootingMatch::factory()->active()->create([
        'scoring_type' => 'standard',
        'side_bet_enabled' => true,
    ]);

    $this->squad = Squad::factory()->create(['match_id' => $this->match->id]);

    // 3 target sets at different distances
    $this->ts300 = TargetSet::factory()->create(['match_id' => $this->match->id, 'distance_meters' => 300]);
    $this->ts200 = TargetSet::factory()->create(['match_id' => $this->match->id, 'distance_meters' => 200]);
    $this->ts100 = TargetSet::factory()->create(['match_id' => $this->match->id, 'distance_meters' => 100]);

    // Each target set has 3 gongs: 0.5 MOA (2.0x = smallest), 1 MOA (1.5x), 2 MOA (1.0x)
    foreach ([$this->ts300, $this->ts200, $this->ts100] as $ts) {
        Gong::factory()->create(['target_set_id' => $ts->id, 'number' => 1, 'label' => '0.5 MOA', 'multiplier' => 2.00]);
        Gong::factory()->create(['target_set_id' => $ts->id, 'number' => 2, 'label' => '1.0 MOA', 'multiplier' => 1.50]);
        Gong::factory()->create(['target_set_id' => $ts->id, 'number' => 3, 'label' => '2.0 MOA', 'multiplier' => 1.00]);
    }

    $this->shooterA = Shooter::factory()->create(['squad_id' => $this->squad->id, 'name' => 'Alice']);
    $this->shooterB = Shooter::factory()->create(['squad_id' => $this->squad->id, 'name' => 'Bob']);
    $this->shooterC = Shooter::factory()->create(['squad_id' => $this->squad->id, 'name' => 'Charlie']);
});

function smallGong(TargetSet $ts): Gong
{
    return Gong::where('target_set_id', $ts->id)->orderByDesc('multiplier')->first();
}

function mediumGong(TargetSet $ts): Gong
{
    return Gong::where('target_set_id', $ts->id)->orderByDesc('multiplier')->skip(1)->first();
}

function hit(Shooter $shooter, Gong $gong): void
{
    Score::create([
        'shooter_id' => $shooter->id,
        'gong_id' => $gong->id,
        'is_hit' => true,
        'device_id' => 'test',
        'recorded_at' => now(),
    ]);
}

it('returns side bet leaderboard when enabled', function () {
    hit($this->shooterA, smallGong($this->ts300));

    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");

    $response->assertOk()
        ->assertJsonPath('match.side_bet_enabled', true)
        ->assertJsonStructure(['side_bet' => [['rank', 'shooter_id', 'name', 'squad', 'small_gong_hits', 'distances_hit']]]);
});

it('does not return side bet when disabled', function () {
    $this->match->update(['side_bet_enabled' => false]);

    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");

    $response->assertOk()
        ->assertJsonMissing(['side_bet']);
});

it('ranks by most small gong hits', function () {
    // Alice: 2 small gong hits
    hit($this->shooterA, smallGong($this->ts300));
    hit($this->shooterA, smallGong($this->ts200));

    // Bob: 1 small gong hit
    hit($this->shooterB, smallGong($this->ts100));

    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");
    $sideBet = $response->json('side_bet');

    expect($sideBet[0]['name'])->toBe('Alice');
    expect($sideBet[0]['small_gong_hits'])->toBe(2);
    expect($sideBet[1]['name'])->toBe('Bob');
    expect($sideBet[1]['small_gong_hits'])->toBe(1);
});

it('breaks ties by furthest distance', function () {
    // Alice: small gong at 300m, 200m
    hit($this->shooterA, smallGong($this->ts300));
    hit($this->shooterA, smallGong($this->ts200));

    // Bob: small gong at 300m, 100m
    hit($this->shooterB, smallGong($this->ts300));
    hit($this->shooterB, smallGong($this->ts100));

    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");
    $sideBet = $response->json('side_bet');

    // Both have 2 small gong hits. Alice hit at 300+200, Bob at 300+100. Alice wins.
    expect($sideBet[0]['name'])->toBe('Alice');
    expect($sideBet[0]['distances_hit'])->toBe([300, 200]);
    expect($sideBet[1]['name'])->toBe('Bob');
    expect($sideBet[1]['distances_hit'])->toBe([300, 100]);
});

it('cascades to second smallest gong when fully tied', function () {
    // Both hit the small gong at same distances
    hit($this->shooterA, smallGong($this->ts300));
    hit($this->shooterB, smallGong($this->ts300));

    // Alice also hits the medium gong at 300m and 200m
    hit($this->shooterA, mediumGong($this->ts300));
    hit($this->shooterA, mediumGong($this->ts200));

    // Bob hits the medium gong at 100m only
    hit($this->shooterB, mediumGong($this->ts100));

    $response = $this->getJson("/api/matches/{$this->match->id}/scoreboard");
    $sideBet = $response->json('side_bet');

    // Tied on small gong (1 each at 300m). Cascade to medium gong: Alice=2, Bob=1. Alice wins.
    expect($sideBet[0]['name'])->toBe('Alice');
    expect($sideBet[1]['name'])->toBe('Bob');
});

it('handles single target set with no distance tiebreaker', function () {
    $singleMatch = ShootingMatch::factory()->active()->create([
        'scoring_type' => 'standard',
        'side_bet_enabled' => true,
    ]);
    $squad = Squad::factory()->create(['match_id' => $singleMatch->id]);
    $ts = TargetSet::factory()->create(['match_id' => $singleMatch->id, 'distance_meters' => 500]);
    $small = Gong::factory()->create(['target_set_id' => $ts->id, 'number' => 1, 'multiplier' => 2.00]);

    $alice = Shooter::factory()->create(['squad_id' => $squad->id, 'name' => 'Alice']);
    $bob = Shooter::factory()->create(['squad_id' => $squad->id, 'name' => 'Bob']);

    hit($alice, $small);

    $response = $this->getJson("/api/matches/{$singleMatch->id}/scoreboard");
    $sideBet = $response->json('side_bet');

    expect($sideBet[0]['name'])->toBe('Alice');
    expect($sideBet[0]['small_gong_hits'])->toBe(1);
    expect($sideBet[1]['name'])->toBe('Bob');
    expect($sideBet[1]['small_gong_hits'])->toBe(0);
});

it('does not return side bet for prs matches', function () {
    $prsMatch = ShootingMatch::factory()->active()->create([
        'scoring_type' => 'prs',
        'side_bet_enabled' => true,
    ]);

    $response = $this->getJson("/api/matches/{$prsMatch->id}/scoreboard");

    $response->assertOk()
        ->assertJsonMissing(['side_bet']);
});
