<?php

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
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create([
        'royal_flush_enabled' => true,
    ]);

    $this->match = ShootingMatch::factory()->create([
        'organization_id' => $this->org->id,
        'scoring_type' => 'standard',
        'royal_flush_enabled' => true,
        'side_bet_enabled' => true,
        'scores_published' => true,
        'status' => MatchStatus::Completed,
    ]);

    $this->squad = Squad::factory()->create(['match_id' => $this->match->id]);

    // Three distances, each with a small (2.0), medium (1.5) and large (1.0) gong.
    $this->targetSets = collect([300, 200, 100])->map(function ($d) {
        $ts = TargetSet::factory()->create(['match_id' => $this->match->id, 'distance_meters' => $d]);
        Gong::factory()->create(['target_set_id' => $ts->id, 'number' => 1, 'multiplier' => 2.00]);
        Gong::factory()->create(['target_set_id' => $ts->id, 'number' => 2, 'multiplier' => 1.50]);
        Gong::factory()->create(['target_set_id' => $ts->id, 'number' => 3, 'multiplier' => 1.00]);

        return $ts;
    });

    $this->alice = Shooter::factory()->create(['squad_id' => $this->squad->id, 'name' => 'Alice Ace']);
    $this->bob = Shooter::factory()->create(['squad_id' => $this->squad->id, 'name' => 'Bob Blank']);

    $this->match->sideBetShooters()->attach([$this->alice->id, $this->bob->id]);

    $flush = function (Shooter $shooter, TargetSet $ts) {
        foreach (Gong::where('target_set_id', $ts->id)->get() as $gong) {
            Score::create([
                'shooter_id' => $shooter->id,
                'gong_id' => $gong->id,
                'is_hit' => true,
                'device_id' => 'test',
                'recorded_at' => now(),
            ]);
        }
    };

    // Alice flushes 300m (furthest) -> wins the side bet on furthest-distance
    // tiebreak. Bob flushes 200m.
    $flush($this->alice, $this->targetSets[0]);
    $flush($this->bob, $this->targetSets[1]);
});

it('shows a public Side Bet tab on the scoreboard', function () {
    $this->get(route('scoreboard', $this->match))
        ->assertOk()
        ->assertSee('Side Bet')
        ->assertSee('Royal Flush');
});

it('surfaces the side bet winner and full standings publicly', function () {
    Volt::test('scoreboard', ['match' => $this->match])
        ->call('setTab', 'sidebet')
        ->assertSee('Side Bet Winner')
        ->assertSee('Alice Ace')
        ->assertSee('Bob Blank');
});

it('makes the Royal Flush distance filter prominent', function () {
    Volt::test('scoreboard', ['match' => $this->match])
        ->call('setTab', 'royalflush')
        ->assertSee('Filter flushes by distance')
        ->assertSee('All distances');
});

it('does not show the Side Bet tab when side bet is disabled', function () {
    $this->match->update(['side_bet_enabled' => false]);

    $html = $this->get(route('scoreboard', $this->match))->assertOk()->getContent();

    expect($html)->not->toContain("setTab('sidebet')");
});
