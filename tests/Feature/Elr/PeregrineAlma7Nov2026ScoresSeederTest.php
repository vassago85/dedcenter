<?php

use App\Enums\ElrShotResult;
use App\Models\ElrShot;
use App\Models\ShootingMatch;
use App\Models\User;
use Database\Seeders\PeregrineAlma7Nov2026ScoresSeeder;
use Database\Seeders\PeregrineAlma7Nov2026Seeder;
use Illuminate\Support\Facades\Hash;

it('records the readable squad 3 paper scores and leaves unclear cells blank', function () {
    User::factory()->create([
        'name' => 'JD Els',
        'email' => 'jd.els1989@gmail.com',
        'password' => Hash::make('password'),
        'role' => 'owner',
    ]);

    $this->seed(PeregrineAlma7Nov2026Seeder::class);
    $this->seed(PeregrineAlma7Nov2026ScoresSeeder::class);

    $match = ShootingMatch::where('name', PeregrineAlma7Nov2026Seeder::MATCH_NAME)->first();
    $warrior = $match->elrStages()->where('label', 'Warrior')->first();
    $targets = $warrior->targets()->orderBy('sort_order')->get();

    $carl = $match->shooters()->where('shooters.name', 'Carl Louw')->first();
    $carlT1 = ElrShot::where('shooter_id', $carl->id)->where('elr_target_id', $targets[0]->id)->orderBy('shot_number')->get();
    expect($carlT1)->toHaveCount(3)
        ->and($carlT1[0]->result)->toBe(ElrShotResult::Miss)
        ->and($carlT1[1]->result)->toBe(ElrShotResult::Hit)
        ->and($carlT1[1]->impact_number)->toBe(1)
        ->and((float) $carlT1[1]->points_awarded)->toBe(891.0)
        ->and($carlT1[2]->impact_number)->toBe(2);

    $danie = $match->shooters()->where('shooters.name', 'Danie Koch')->first();
    expect(ElrShot::where('shooter_id', $danie->id)->where('elr_target_id', $targets[0]->id)->exists())->toBeFalse()
        ->and(ElrShot::where('shooter_id', $danie->id)->where('elr_target_id', $targets[1]->id)->count())->toBe(3);

    $herman = $match->shooters()->where('shooters.name', 'Herman Boshoff')->first();
    expect(ElrShot::where('shooter_id', $herman->id)->where('elr_target_id', $targets[3]->id)->where('shot_number', 1)->exists())->toBeFalse()
        ->and(ElrShot::where('shooter_id', $herman->id)->where('elr_target_id', $targets[3]->id)->where('shot_number', 2)->value('result'))->toBe(ElrShotResult::Hit);

    $brothers = $match->elrStages()->where('label', 'Brothers Arms')->first();
    expect(ElrShot::where('shooter_id', $carl->id)->whereIn('elr_target_id', $brothers->targets()->pluck('id'))->exists())->toBeFalse();

    $integrix = $match->elrStages()->where('label', 'Integrix')->first();
    $integrixTargets = $integrix->targets()->orderBy('sort_order')->get();
    $danieIntegrixFar = ElrShot::where('shooter_id', $danie->id)->where('elr_target_id', $integrixTargets[3]->id)->count();
    expect($danieIntegrixFar)->toBe(0);
});
