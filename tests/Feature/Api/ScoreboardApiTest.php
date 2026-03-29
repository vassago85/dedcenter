<?php

use App\Models\Gong;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;

it('returns ranked leaderboard for a match', function () {
    $match = ShootingMatch::factory()->active()->create();
    $ts = TargetSet::factory()->create(['match_id' => $match->id]);
    $gong = Gong::factory()->create(['target_set_id' => $ts->id, 'number' => 1, 'multiplier' => 2.00]);
    $squad = Squad::factory()->create(['match_id' => $match->id]);

    $shooter1 = Shooter::factory()->create(['squad_id' => $squad->id, 'name' => 'Alice']);
    $shooter2 = Shooter::factory()->create(['squad_id' => $squad->id, 'name' => 'Bob']);

    Score::factory()->create(['shooter_id' => $shooter1->id, 'gong_id' => $gong->id, 'is_hit' => true]);
    Score::factory()->create(['shooter_id' => $shooter2->id, 'gong_id' => $gong->id, 'is_hit' => false]);

    $response = $this->getJson("/api/matches/{$match->id}/scoreboard");

    $response->assertOk()
        ->assertJsonPath('match.id', $match->id)
        ->assertJsonCount(2, 'leaderboard')
        ->assertJsonPath('leaderboard.0.name', 'Alice')
        ->assertJsonPath('leaderboard.0.rank', 1)
        ->assertJsonPath('leaderboard.0.total_score', fn ($v) => abs((float) $v - 2.0) < 0.01)
        ->assertJsonPath('leaderboard.1.name', 'Bob')
        ->assertJsonPath('leaderboard.1.rank', 2)
        ->assertJsonPath('leaderboard.1.total_score', fn ($v) => (float) $v < 0.01);
});

it('returns empty leaderboard for match with no scores', function () {
    $match = ShootingMatch::factory()->active()->create();
    $squad = Squad::factory()->create(['match_id' => $match->id]);
    Shooter::factory()->create(['squad_id' => $squad->id]);

    $this->getJson("/api/matches/{$match->id}/scoreboard")
        ->assertOk()
        ->assertJsonCount(1, 'leaderboard')
        ->assertJsonPath('leaderboard.0.total_score', fn ($v) => (float) $v === 0.0);
});
