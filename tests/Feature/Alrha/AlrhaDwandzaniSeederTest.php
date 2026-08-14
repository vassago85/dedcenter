<?php

use App\Enums\AlrhaClass;
use App\Enums\MatchStatus;
use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('seeds the Dwandzani ALRHA match with Tiaan Alberts as match director', function () {
    $this->artisan('match:seed-alrha-dwandzani')
        ->assertSuccessful();

    $tiaan = User::where('email', 'tiaan.alberts@deadcenter.co.za')->first();
    expect($tiaan)->not->toBeNull()
        ->and($tiaan->name)->toBe('Tiaan Alberts');

    $org = Organization::where('slug', 'alrha')->first();
    expect($org)->not->toBeNull()
        ->and($org->name)->toBe('ALRHA');
    expect($org->admins()->where('users.id', $tiaan->id)->wherePivot('is_match_director', true)->exists())->toBeTrue();

    $match = ShootingMatch::where('name', 'ALRHA — Dwandzani (15 August 2026)')->first();
    expect($match)->not->toBeNull()
        ->and($match->scoring_type)->toBe('alrha')
        ->and($match->location)->toBe('Dwandzani Shooting Range')
        ->and($match->status)->toBe(MatchStatus::SquaddingOpen)
        ->and($match->created_by)->toBe($tiaan->id)
        ->and($match->team_event)->toBeTrue()
        ->and($match->team_size)->toBe(2)
        ->and($match->alrha_class)->toBeNull();

    expect($match->staff()->where('users.id', $tiaan->id)->wherePivot('role', 'match_director')->exists())->toBeTrue();

    expect($match->squads()->count())->toBe(4);
    expect($match->shooters()->count())->toBe(90);
    expect($match->shooters()->where('alrha_class', AlrhaClass::Varmint)->count())->toBe(54);
    expect($match->shooters()->where('alrha_class', AlrhaClass::Hunters)->count())->toBe(36);
    expect($match->teams()->count())->toBe(18);

    $monica = $match->shooters()->where('shooters.name', 'Monica Makkink')->first();
    expect($monica)->not->toBeNull()
        ->and($monica->alrha_class)->toBe(AlrhaClass::Varmint)
        ->and($monica->gong_position)->toBe(1)
        ->and($monica->squad->name)->toBe('Relay 1')
        ->and($monica->bib_number)->toBe('V-01');

    $vixens = $match->teams()->where('name', 'Victory Vixens')->first();
    expect($vixens)->not->toBeNull();
    expect($vixens->shooters()->pluck('name')->sort()->values()->all())
        ->toBe(['Chantal Buys', 'Lizett Nel']);

    $titan = $match->shooters()->where('shooters.name', 'Christiaan Alberts')->first();
    expect($titan)->not->toBeNull()
        ->and($titan->alrha_class)->toBe(AlrhaClass::Hunters)
        ->and($titan->team->name)->toBe('Team 1/2 TiTaN')
        ->and($titan->squad->name)->toBe('Relay 3')
        ->and($titan->gong_position)->toBe(3);

    $louise = $match->shooters()->where('shooters.name', 'Louise Kamp')->get();
    expect($louise)->toHaveCount(2);
    expect($louise->pluck('alrha_class')->map->value->sort()->values()->all())
        ->toBe(['hunters', 'varmint']);

    $andre = $match->shooters()->where('shooters.name', 'Andre Smith')->get();
    expect($andre)->toHaveCount(2);
    expect($andre->pluck('bib_number')->sort()->values()->all())->toBe(['H-06A', 'H-06B']);

    expect($match->elrStages()->count())->toBe(6);
    expect($match->elrStages()->where('alrha_class', AlrhaClass::Varmint)->count())->toBe(3);
    expect($match->elrStages()->where('alrha_class', AlrhaClass::Hunters)->count())->toBe(3);
});

it('is idempotent and can rebuild the roster with --fresh', function () {
    $this->artisan('match:seed-alrha-dwandzani')->assertSuccessful();
    $match = ShootingMatch::where('name', 'ALRHA — Dwandzani (15 August 2026)')->first();
    $firstId = $match->id;
    $firstCount = $match->shooters()->count();

    $this->artisan('match:seed-alrha-dwandzani')->assertSuccessful();
    $match->refresh();
    expect($match->id)->toBe($firstId)
        ->and($match->shooters()->count())->toBe($firstCount);

    $this->artisan('match:seed-alrha-dwandzani', ['--fresh' => true])->assertSuccessful();
    expect($match->fresh()->shooters()->count())->toBe(90);
});

it('uses an existing match director when --md is passed', function () {
    $md = User::factory()->create([
        'name' => 'Tiaan Alberts',
        'email' => 'tiaan@example.com',
        'password' => Hash::make('secret'),
        'role' => 'shooter',
    ]);

    $this->artisan('match:seed-alrha-dwandzani', ['--md' => 'tiaan@example.com'])
        ->assertSuccessful();

    $match = ShootingMatch::where('name', 'ALRHA — Dwandzani (15 August 2026)')->first();
    expect($match->created_by)->toBe($md->id);
    expect(User::where('email', 'tiaan.alberts@deadcenter.co.za')->exists())->toBeFalse();
});
