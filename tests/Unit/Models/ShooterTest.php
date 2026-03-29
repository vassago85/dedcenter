<?php

use App\Models\Gong;
use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;

it('belongs to a squad', function () {
    $shooter = Shooter::factory()->create();

    expect($shooter->squad)->toBeInstanceOf(Squad::class);
});

it('has many scores', function () {
    $match = ShootingMatch::factory()->create();
    $ts = TargetSet::factory()->create(['match_id' => $match->id]);
    $gong1 = Gong::factory()->create(['target_set_id' => $ts->id, 'number' => 1]);
    $gong2 = Gong::factory()->create(['target_set_id' => $ts->id, 'number' => 2]);
    $squad = Squad::factory()->create(['match_id' => $match->id]);
    $shooter = Shooter::factory()->create(['squad_id' => $squad->id]);

    Score::factory()->create(['shooter_id' => $shooter->id, 'gong_id' => $gong1->id, 'is_hit' => true]);
    Score::factory()->create(['shooter_id' => $shooter->id, 'gong_id' => $gong2->id, 'is_hit' => false]);

    expect($shooter->scores)->toHaveCount(2);
});

it('calculates total score from hit multipliers', function () {
    $match = ShootingMatch::factory()->create();
    $ts = TargetSet::factory()->create(['match_id' => $match->id]);
    $gong1 = Gong::factory()->create(['target_set_id' => $ts->id, 'number' => 1, 'multiplier' => 2.00]);
    $gong2 = Gong::factory()->create(['target_set_id' => $ts->id, 'number' => 2, 'multiplier' => 3.00]);
    $gong3 = Gong::factory()->create(['target_set_id' => $ts->id, 'number' => 3, 'multiplier' => 1.50]);
    $squad = Squad::factory()->create(['match_id' => $match->id]);
    $shooter = Shooter::factory()->create(['squad_id' => $squad->id]);

    Score::factory()->create(['shooter_id' => $shooter->id, 'gong_id' => $gong1->id, 'is_hit' => true]);
    Score::factory()->create(['shooter_id' => $shooter->id, 'gong_id' => $gong2->id, 'is_hit' => true]);
    Score::factory()->create(['shooter_id' => $shooter->id, 'gong_id' => $gong3->id, 'is_hit' => false]);

    expect($shooter->total_score)->toBe(5.0);
    expect($shooter->hit_count)->toBe(2);
    expect($shooter->miss_count)->toBe(1);
});
