<?php

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use Illuminate\Support\Carbon;

it('creates a match with default draft status', function () {
    $match = ShootingMatch::factory()->create();

    expect($match->status)->toBe(MatchStatus::Draft);
    expect($match->is_active)->toBeFalse();
    expect($match->is_completed)->toBeFalse();
});

it('casts date as Carbon instance', function () {
    $match = ShootingMatch::factory()->create(['date' => '2026-06-15']);

    expect($match->date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($match->date->format('Y-m-d'))->toBe('2026-06-15');
});

it('has many target sets', function () {
    $match = ShootingMatch::factory()->create();
    TargetSet::factory()->count(3)->create(['match_id' => $match->id]);

    expect($match->targetSets)->toHaveCount(3);
});

it('has many squads', function () {
    $match = ShootingMatch::factory()->create();
    Squad::factory()->count(2)->create(['match_id' => $match->id]);

    expect($match->squads)->toHaveCount(2);
});

it('has shooters through squads', function () {
    $match = ShootingMatch::factory()->create();
    $squad = Squad::factory()->create(['match_id' => $match->id]);
    Shooter::factory()->count(5)->create(['squad_id' => $squad->id]);

    expect($match->shooters)->toHaveCount(5);
    expect($match->total_shooters)->toBe(5);
});

it('reports active status correctly', function () {
    $match = ShootingMatch::factory()->active()->create();

    expect($match->is_active)->toBeTrue();
    expect($match->is_completed)->toBeFalse();
});

it('reports completed status correctly', function () {
    $match = ShootingMatch::factory()->completed()->create();

    expect($match->is_active)->toBeFalse();
    expect($match->is_completed)->toBeTrue();
});

it('scopes active live today to active status and today date only', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', config('app.timezone')));

    $today = ShootingMatch::factory()->active()->create(['date' => '2026-04-10']);
    $yesterday = ShootingMatch::factory()->active()->create(['date' => '2026-04-09']);
    ShootingMatch::factory()->create(['status' => MatchStatus::Draft, 'date' => '2026-04-10']);

    $ids = ShootingMatch::activeLiveToday()->pluck('id')->all();

    expect($ids)->toContain($today->id)
        ->not->toContain($yesterday->id);

    Carbon::setTestNow();
});

it('cascades delete to target sets and gongs', function () {
    $match = ShootingMatch::factory()->create();
    $ts = TargetSet::factory()->create(['match_id' => $match->id]);
    Gong::factory()->count(3)->create(['target_set_id' => $ts->id]);

    expect(Gong::count())->toBe(3);

    $match->delete();

    expect(TargetSet::count())->toBe(0);
    expect(Gong::count())->toBe(0);
});
