<?php

use App\Enums\MatchStatus;
use App\Models\ShootingMatch;
use App\Models\User;
use Database\Seeders\PeregrineAlma7Nov2026Seeder;
use Illuminate\Support\Facades\Hash;

it('creates the Alma 7 November match owned by JD Els', function () {
    $jd = User::factory()->create([
        'name' => 'Jd Els',
        'email' => 'Jd.els1989@gmail.com',
        'password' => Hash::make('password'),
        'role' => 'owner',
    ]);

    $this->seed(PeregrineAlma7Nov2026Seeder::class);

    $match = ShootingMatch::where('name', PeregrineAlma7Nov2026Seeder::MATCH_NAME)->first();
    expect($match)->not->toBeNull()
        ->and($match->created_by)->toBe($jd->id)
        ->and($match->date->toDateString())->toBe('2026-11-07')
        ->and($match->location)->toBe('Alma')
        ->and($match->status)->toBe(MatchStatus::Ready)
        ->and($match->scoring_type)->toBe('elr')
        ->and($match->team_event)->toBeTrue()
        ->and($match->team_size)->toBe(2)
        ->and($match->elr_distance_based_scoring)->toBeTrue();

    expect($match->shooters()->count())->toBe(66)
        ->and($match->squads()->count())->toBe(6)
        ->and($match->elrStages()->count())->toBe(5)
        ->and($match->divisions()->pluck('name')->sort()->values()->all())->toBe(['Major', 'Minor']);

    $youngGuns = $match->teams()->get()->filter(
        fn ($team) => strtolower($team->name) === 'young guns'
    );
    expect($youngGuns)->toHaveCount(1)
        ->and($youngGuns->first()->shooters)->toHaveCount(2);

    $jacos = $match->shooters()->where('shooters.name', 'Jaco Venter')->get();
    expect($jacos)->toHaveCount(2)
        ->and($jacos->pluck('user_id')->unique())->toHaveCount(2);

    $jdShooter = $match->shooters()->where('shooters.name', 'JD Els')->first();
    expect($jdShooter)->not->toBeNull()
        ->and($jdShooter->user_id)->toBe($jd->id)
        ->and($jdShooter->squad->name)->toBe('Squad 3')
        ->and($jdShooter->team->name)->toBe('Lights Out');

    $registration = $match->registrations()->where('user_id', $jd->id)->first();
    expect($registration)->not->toBeNull()
        ->and($registration->caliber)->toBe('338 Lapua Mag')
        ->and($registration->division_id)->toBe($jdShooter->match_division_id);

    $anton = $match->shooters()->where('shooters.name', 'Anton de Jager')->first();
    expect($anton)->not->toBeNull()
        ->and($anton->team_id)->toBeNull()
        ->and($anton->squad->name)->toBe('Unassigned');

    expect($match->squads()->where('name', 'Unassigned')->first()->shooters)->toHaveCount(3);

    $firstId = $match->id;
    $this->seed(PeregrineAlma7Nov2026Seeder::class);
    $again = ShootingMatch::where('name', PeregrineAlma7Nov2026Seeder::MATCH_NAME)->get();
    expect($again)->toHaveCount(1)
        ->and($again->first()->id)->toBe($firstId)
        ->and($again->first()->created_by)->toBe($jd->id)
        ->and($again->first()->shooters()->count())->toBe(66);
});

it('does not create the Alma match when JD Els has no account', function () {
    $this->seed(PeregrineAlma7Nov2026Seeder::class);

    expect(ShootingMatch::where('name', PeregrineAlma7Nov2026Seeder::MATCH_NAME)->exists())->toBeFalse();
});
