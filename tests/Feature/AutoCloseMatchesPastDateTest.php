<?php

use App\Enums\MatchStatus;
use App\Models\ShootingMatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

afterEach(function () {
    Carbon::setTestNow();
});

it('completes active matches whose scheduled date is before today', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', config('app.timezone')));

    $past = ShootingMatch::factory()->active()->create(['date' => '2026-04-08']);
    $today = ShootingMatch::factory()->active()->create(['date' => '2026-04-10']);
    $future = ShootingMatch::factory()->active()->create(['date' => '2026-04-15']);
    $draftOld = ShootingMatch::factory()->create(['status' => MatchStatus::Draft, 'date' => '2026-04-01']);
    $alreadyDone = ShootingMatch::factory()->completed()->create(['date' => '2026-04-01']);
    $squaddingPast = ShootingMatch::factory()->create([
        'status' => MatchStatus::SquaddingOpen,
        'date' => '2026-04-05',
    ]);

    Artisan::call('matches:auto-close-past-date');

    expect($past->fresh()->status)->toBe(MatchStatus::Completed)
        ->and($today->fresh()->status)->toBe(MatchStatus::Active)
        ->and($future->fresh()->status)->toBe(MatchStatus::Active)
        ->and($draftOld->fresh()->status)->toBe(MatchStatus::Draft)
        ->and($alreadyDone->fresh()->status)->toBe(MatchStatus::Completed)
        ->and($squaddingPast->fresh()->status)->toBe(MatchStatus::Completed);
});
