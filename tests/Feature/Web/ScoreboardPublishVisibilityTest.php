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

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->org = Organization::factory()->create(['created_by' => $this->owner->id]);
    $this->org->admins()->attach($this->owner->id, ['is_owner' => true]);

    $this->match = ShootingMatch::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->owner->id,
        'scoring_type' => 'standard',
        'status' => MatchStatus::Completed,
        'scores_published' => false,
    ]);

    $squad = Squad::factory()->create(['match_id' => $this->match->id]);
    $ts = TargetSet::factory()->create(['match_id' => $this->match->id, 'distance_meters' => 300]);
    $gong = Gong::factory()->create(['target_set_id' => $ts->id, 'number' => 1, 'multiplier' => 1.00]);
    $this->shooter = Shooter::factory()->create(['squad_id' => $squad->id, 'name' => 'Zed Zephyr']);
    Score::create([
        'shooter_id' => $this->shooter->id,
        'gong_id' => $gong->id,
        'is_hit' => true,
        'device_id' => 'test',
        'recorded_at' => now(),
    ]);
});

it('hides scores from the public when not published', function () {
    $this->get(route('scoreboard', $this->match))
        ->assertOk()
        ->assertSee('Results not published yet')
        ->assertSee('Zed Zephyr') // entrant list is still fine to show
        ->assertDontSee('Detailed Breakdown'); // the results tabs are gated away
});

it('lets a match director preview hidden scores with a banner', function () {
    $this->actingAs($this->owner)
        ->get(route('scoreboard', $this->match))
        ->assertOk()
        ->assertSee('scores are hidden from the public')
        ->assertSee('Zed Zephyr')
        ->assertDontSee('Results not published yet');
});

it('shows scores publicly once published', function () {
    $this->match->update(['scores_published' => true]);

    $this->get(route('scoreboard', $this->match))
        ->assertOk()
        ->assertSee('Zed Zephyr')
        ->assertDontSee('Results not published yet');
});
