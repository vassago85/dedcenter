<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\TargetSet;
use App\Models\Gong;
use App\Models\Squad;
use App\Models\Shooter;
use App\Models\Score;
use App\Models\User;
use App\Enums\MatchStatus;

it('shows leaderboard page for an organization', function () {
    $org = Organization::factory()->create();

    $this->get("/leaderboard/{$org->slug}")
        ->assertOk();
});

it('shows empty state when no completed matches', function () {
    $org = Organization::factory()->create();

    $this->get("/leaderboard/{$org->slug}")
        ->assertOk()
        ->assertSee('No completed matches with scores yet.');
});

it('calculates leaderboard correctly', function () {
    $org = Organization::factory()->competition()->withBestOf(2)->create();

    $match1 = ShootingMatch::factory()->create([
        'organization_id' => $org->id,
        'status' => MatchStatus::Completed,
    ]);
    $match2 = ShootingMatch::factory()->create([
        'organization_id' => $org->id,
        'status' => MatchStatus::Completed,
    ]);
    $match3 = ShootingMatch::factory()->create([
        'organization_id' => $org->id,
        'status' => MatchStatus::Completed,
    ]);

    $user = User::factory()->create();

    // Set up targets for each match
    foreach ([$match1, $match2, $match3] as $match) {
        $ts = TargetSet::factory()->create(['match_id' => $match->id]);
        $gong = Gong::create([
            'target_set_id' => $ts->id,
            'number' => 1,
            'label' => 'Test',
            'multiplier' => 1.00,
        ]);

        $squad = Squad::create(['match_id' => $match->id, 'name' => 'Default', 'sort_order' => 1]);
        $shooter = Shooter::create([
            'squad_id' => $squad->id,
            'name' => $user->name,
            'user_id' => $user->id,
            'sort_order' => 1,
        ]);

        Score::create([
            'shooter_id' => $shooter->id,
            'gong_id' => $gong->id,
            'is_hit' => true,
            'recorded_at' => now(),
        ]);
    }

    // Match 1: 1.00, Match 2: 1.00, Match 3: 1.00
    // Best of 2 = 2.00

    $this->get("/leaderboard/{$org->slug}")
        ->assertOk()
        ->assertSee($user->name)
        ->assertSee('2.00');
});

it('includes child org matches in league leaderboard', function () {
    $league = Organization::factory()->league()->create();
    $club = Organization::factory()->club()->create(['parent_id' => $league->id]);

    $match = ShootingMatch::factory()->create([
        'organization_id' => $club->id,
        'status' => MatchStatus::Completed,
    ]);

    $ts = TargetSet::factory()->create(['match_id' => $match->id]);
    $gong = Gong::create([
        'target_set_id' => $ts->id,
        'number' => 1,
        'label' => 'Test',
        'multiplier' => 5.00,
    ]);

    $squad = Squad::create(['match_id' => $match->id, 'name' => 'Default', 'sort_order' => 1]);
    $shooter = Shooter::create([
        'squad_id' => $squad->id,
        'name' => 'Club Shooter',
        'sort_order' => 1,
    ]);

    Score::create([
        'shooter_id' => $shooter->id,
        'gong_id' => $gong->id,
        'is_hit' => true,
        'recorded_at' => now(),
    ]);

    $this->get("/leaderboard/{$league->slug}")
        ->assertOk()
        ->assertSee('Club Shooter')
        ->assertSee('5.00');
});
