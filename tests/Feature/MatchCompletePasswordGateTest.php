<?php

use App\Concerns\HandlesMatchLifecycleTransitions;
use App\Enums\MatchStatus;
use App\Models\ShootingMatch;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Password gate on "Mark match Completed"
|--------------------------------------------------------------------------
| Completing a match locks scores, awards achievements and fires post-
| match emails — high-stakes enough that a stray click on the lifecycle
| stepper can't be allowed to do it. The Match Control Center routes
| every Completed transition through `confirmCompleteMatch()` after a
| password challenge in the modal hosted by `<x-match-control-shell>`.
|
| These tests exercise the trait against an anonymous Volt-shaped host
| so we lock in the gate behaviour without standing up the full
| Livewire stack.
*/

function makeMatchHost(MatchStatus $status = MatchStatus::Active): object
{
    $host = new class {
        use HandlesMatchLifecycleTransitions;
        public ShootingMatch $match;
    };

    $owner = User::factory()->create([
        'role' => 'owner',
        'password' => Hash::make('correct-horse-battery-staple'),
    ]);

    $host->match = ShootingMatch::factory()->create([
        'created_by' => $owner->id,
        'status' => $status,
    ]);

    Auth::login($owner);

    return $host;
}

it('does NOT transition Active to Completed on a direct transitionStatus call (gate kicks in)', function () {
    $host = makeMatchHost(MatchStatus::Active);

    $host->transitionStatus(MatchStatus::Completed->value);

    expect($host->match->fresh()->status)->toBe(MatchStatus::Active);
});

it('refuses confirmCompleteMatch when password is empty', function () {
    $host = makeMatchHost(MatchStatus::Active);
    $host->completeMatchPassword = '';

    $host->confirmCompleteMatch();

    expect($host->match->fresh()->status)->toBe(MatchStatus::Active);
    expect($host->completeMatchPasswordError)->toBe('Password incorrect. Try again.');
});

it('refuses confirmCompleteMatch when password is wrong', function () {
    $host = makeMatchHost(MatchStatus::Active);
    $host->completeMatchPassword = 'definitely-not-the-password';

    $host->confirmCompleteMatch();

    expect($host->match->fresh()->status)->toBe(MatchStatus::Active);
    expect($host->completeMatchPasswordError)->toBe('Password incorrect. Try again.');
});

it('completes the match and clears the password buffer when the password is correct', function () {
    $host = makeMatchHost(MatchStatus::Active);
    $host->completeMatchPassword = 'correct-horse-battery-staple';

    $host->confirmCompleteMatch();

    expect($host->match->fresh()->status)->toBe(MatchStatus::Completed);
    expect($host->completeMatchPassword)->toBe('');
    expect($host->completeMatchPasswordError)->toBe('');
});

it('still permits non-Completed transitions without a password (gate is Completed-only)', function () {
    $host = makeMatchHost(MatchStatus::SquaddingClosed);

    $host->transitionStatus(MatchStatus::Ready->value);

    expect($host->match->fresh()->status)->toBe(MatchStatus::Ready);
});

it('lets the Completed -> Active reopen path through without a password', function () {
    $host = makeMatchHost(MatchStatus::Completed);

    $host->transitionStatus(MatchStatus::Active->value);

    expect($host->match->fresh()->status)->toBe(MatchStatus::Active);
});
