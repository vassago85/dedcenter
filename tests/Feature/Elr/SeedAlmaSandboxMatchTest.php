<?php

use App\Console\Commands\SeedAlmaSandboxMatch;
use App\Enums\MatchStatus;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates the sandbox match for JD Els with Minor/Major, five stations, and twelve shooters', function () {
    $jd = User::factory()->create([
        'name' => 'Jd Els',
        'email' => 'Jd.els1989@gmail.com',
        'password' => Hash::make('password'),
        'role' => 'owner',
    ]);

    $this->artisan('match:seed-alma-sandbox')->assertSuccessful();

    $match = ShootingMatch::where('name', SeedAlmaSandboxMatch::MATCH_NAME)->first();
    expect($match)->not->toBeNull()
        ->and($match->created_by)->toBe($jd->id)
        ->and($match->status)->toBe(MatchStatus::Ready)
        ->and($match->scoring_type)->toBe('elr')
        ->and($match->team_event)->toBeTrue()
        ->and($match->divisions()->pluck('name')->sort()->values()->all())->toBe(['Major', 'Minor'])
        ->and($match->elrStages()->count())->toBe(5)
        ->and($match->shooters()->count())->toBe(12)
        ->and($match->teams()->count())->toBe(6);
});

it('is safe to re-run and --fresh resets edits', function () {
    User::factory()->create([
        'name' => 'Jd Els',
        'email' => 'Jd.els1989@gmail.com',
        'password' => Hash::make('password'),
        'role' => 'owner',
    ]);

    $this->artisan('match:seed-alma-sandbox')->assertSuccessful();
    $match = ShootingMatch::where('name', SeedAlmaSandboxMatch::MATCH_NAME)->first();
    $originalId = $match->id;

    $sandboxShooter = $match->shooters()->where('shooters.name', 'Sandbox Shooter One')->first();
    $sandboxShooter->update(['name' => 'Renamed Shooter']);

    $this->artisan('match:seed-alma-sandbox')->assertSuccessful();
    expect(ShootingMatch::where('name', SeedAlmaSandboxMatch::MATCH_NAME)->count())->toBe(1);
    // Re-run keeps the renamed shooter — update only pulls in the row the seeder owns per user_id.
    expect(Shooter::find($sandboxShooter->id)->name)->toBe('Sandbox Shooter One');

    Shooter::find($sandboxShooter->id)->update(['name' => 'Renamed Again']);

    $this->artisan('match:seed-alma-sandbox', ['--fresh' => true])->assertSuccessful();
    $match->refresh();
    expect($match->id)->toBe($originalId)
        ->and($match->shooters()->count())->toBe(12)
        ->and($match->shooters()->where('shooters.name', 'Renamed Again')->exists())->toBeFalse()
        ->and($match->shooters()->where('shooters.name', 'Sandbox Shooter One')->exists())->toBeTrue();
});

it('refuses when JD Els does not have an account', function () {
    $this->artisan('match:seed-alma-sandbox')->assertFailed();
    expect(ShootingMatch::where('name', SeedAlmaSandboxMatch::MATCH_NAME)->exists())->toBeFalse();
});
