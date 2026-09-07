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

/*
|--------------------------------------------------------------------------
| Stale-tab hardening on `transitionStatus()`.
|--------------------------------------------------------------------------
| A Match Control Center page snapshots $match into Livewire's client
| state at mount(). A tab left open in the background stays frozen at
| that snapshot even if another admin (or another tab) advances the
| match. Without a DB refresh in transitionStatus(), a stale-tab click
| lands the guard check against the *snapshotted* status — either
| firing a phantom transition (silent no-op) or tripping the guard with
| the cryptic old "Invalid status transition" toast for reasons the
| operator can't diagnose.
|
| These tests pin the two hardenings on the trait:
|   1. transitionStatus() calls $match->refresh() before validating.
|   2. Same-state click (stale tab → target that DB already reached) is
|      a friendly no-op, not a guard rejection.
*/

it('refreshes stale in-memory status from the DB before validating a transition', function () {
    $host = makeMatchHost(MatchStatus::Ready);

    // Another admin (or another tab) advances the match past Ready.
    // The host's in-memory $match still reads Ready — the classic
    // stale-tab scenario.
    ShootingMatch::query()->where('id', $host->match->id)->update([
        'status' => MatchStatus::Completed->value,
    ]);
    expect($host->match->status)->toBe(MatchStatus::Ready); // still stale in memory.

    // Ask to un-complete (Completed -> Active). Under the stale
    // in-memory view Ready cannot reach Active directly *without*
    // going through SquaddingClosed first — this is a legal move only
    // once the trait notices the DB is already Completed. Without the
    // refresh, this click would trip the guard.
    $host->transitionStatus(MatchStatus::Active->value);

    // The refresh pulled the current DB value in, then the legal
    // Completed -> Active reopen path fired cleanly.
    expect($host->match->fresh()->status)->toBe(MatchStatus::Active);
});

it('handles a stale-tab click on the current status as a friendly no-op, not "Invalid"', function () {
    $host = makeMatchHost(MatchStatus::Active);

    // Another admin completes the match. Host's in-memory $match is
    // still Active — the "stale tab clicks Complete after someone
    // else already completed the match" scenario the user reported.
    ShootingMatch::query()->where('id', $host->match->id)->update([
        'status' => MatchStatus::Completed->value,
    ]);

    // Same-status transition. The old code tripped the guard with a
    // cryptic "Invalid status transition" toast because in-memory
    // Active can't reach Completed without the password modal, and
    // even fresh-Completed can't reach Completed. New code detects
    // "you're already there" and refreshes the local state.
    $host->transitionStatus(MatchStatus::Completed->value);

    // Status unchanged (already Completed on the DB), but the host's
    // local model got rehydrated so the stepper will render fresh on
    // the next paint.
    expect($host->match->fresh()->status)->toBe(MatchStatus::Completed);
    expect($host->match->status)->toBe(MatchStatus::Completed);
});

it('still rejects a genuinely-illegal transition with a helpful message after refresh', function () {
    // Draft -> Completed is not a legal transition even after refresh.
    // Trait must still refuse it — the refresh hardening does not
    // widen the transition graph, it only closes the stale-tab loophole.
    $host = makeMatchHost(MatchStatus::Draft);

    $host->transitionStatus(MatchStatus::Completed->value);

    expect($host->match->fresh()->status)->toBe(MatchStatus::Draft);
});
