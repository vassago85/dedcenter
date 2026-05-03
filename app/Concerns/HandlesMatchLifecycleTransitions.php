<?php

namespace App\Concerns;

use App\Enums\MatchStatus;
use App\Models\ShootingMatch;
use App\Services\AchievementService;
use App\Services\NotificationService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Lifecycle transition helpers for Volt match-admin pages.
 *
 * Lifted out of `pages/org/matches/edit.blade.php` so EVERY page in the
 * Match Control Center (Overview, Setup, Squadding, Scoring, Reports)
 * can host the lifecycle stepper at the top — clicking a future stage
 * fires the transition without the user having to navigate to Setup
 * first to find the stepper buttons. The `wire:click` / `wire:confirm`
 * markup lives in `<x-match-progress>`; this trait provides the methods
 * those directives bind to.
 *
 * Behaviour preserved byte-for-byte from the original inline copy:
 *   - Validates the transition against `MatchStatus::canTransitionTo`.
 *   - Notifies via `NotificationService::onStatusChange`.
 *   - On forward transition into Completed, runs achievement evaluation
 *     (skipped on re-entry — evaluators are idempotent on slug+shooter).
 *   - On entry to RegistrationClosed, calls `cleanUpPreRegistrations`
 *     IF the consuming class implements that hook (currently only the
 *     org Setup page does — the other pages don't host pre-registration
 *     state, so the hook is opt-in via `method_exists` rather than a
 *     hard interface requirement).
 *   - Toasts the result with the un-completed-vs-changed wording the
 *     original implementation used.
 *
 * Consuming Volt pages need only:
 *   use App\Concerns\HandlesMatchLifecycleTransitions;
 *   public ShootingMatch $match;
 *
 * The `<x-match-progress :match="$match" />` component then wires the
 * stepper buttons to `transitionStatus(string $target)` automatically.
 *
 * @property ShootingMatch $match
 */
trait HandlesMatchLifecycleTransitions
{
    /**
     * Password input bound to the "Complete Match" confirmation modal.
     * Cleared after every successful transition so a stale value can't
     * leak into a subsequent attempt or get picked up by a screenshot.
     */
    public string $completeMatchPassword = '';

    /** Inline validation error shown under the password input. */
    public string $completeMatchPasswordError = '';

    public function transitionStatus(string $target): void
    {
        $targetStatus = MatchStatus::from($target);

        if (! $this->match->status->canTransitionTo($targetStatus)) {
            $this->safeToast('Invalid status transition.', 'danger');
            return;
        }

        // High-stakes gate: completing a match locks scores, awards
        // achievements and fires a wave of post-match emails. Route
        // every path that lands on Completed through a password
        // challenge so a stray click on the lifecycle stepper or a
        // confirm-dialog double-tap can't accidentally finalise a
        // live match. The trusted path back through `confirmCompleteMatch`
        // calls `performTransition()` directly to skip this gate after
        // the password has validated.
        if ($targetStatus === MatchStatus::Completed && $this->match->status !== MatchStatus::Completed) {
            $this->requestCompleteConfirmation();
            return;
        }

        $this->performTransition($targetStatus);
    }

    /** Open the "Complete Match" password modal. */
    public function requestCompleteConfirmation(): void
    {
        $this->completeMatchPassword = '';
        $this->completeMatchPasswordError = '';
        $this->safeModalShow('complete-match-password');
    }

    /**
     * Modal submit — validate the current user's password, then run the
     * Completed transition through the trusted internal path. Wrong
     * password keeps the modal open with an inline error; success
     * closes the modal and lets the normal toast / notification flow
     * fire.
     */
    public function confirmCompleteMatch(): void
    {
        $user = Auth::user();
        $password = $this->completeMatchPassword;

        if (! $user || ! is_string($user->password) || $user->password === '' || ! Hash::check($password, $user->password)) {
            $this->completeMatchPasswordError = 'Password incorrect. Try again.';
            return;
        }

        $this->completeMatchPassword = '';
        $this->completeMatchPasswordError = '';
        $this->safeModalClose('complete-match-password');

        $this->performTransition(MatchStatus::Completed);
    }

    /**
     * Trusted internal transition runner — assumed to have already
     * cleared any preconditions (graph validity, password challenge).
     * The original `transitionStatus()` body lives here verbatim so
     * non-Completed transitions still go through identical code.
     */
    protected function performTransition(MatchStatus $targetStatus): void
    {
        $oldStatus = $this->match->status;
        $isUncomplete = $oldStatus === MatchStatus::Completed && $targetStatus === MatchStatus::Active;

        $this->match->update(['status' => $targetStatus]);

        try {
            app(NotificationService::class)->onStatusChange($this->match, $oldStatus, $targetStatus);
        } catch (\Throwable $e) {
            Log::warning('Status notification dispatch failed', ['error' => $e->getMessage()]);
        }

        // Pre-registration cleanup is only relevant on the page that owns
        // the registration list (the Setup page). Other pages can ignore
        // it — they don't have the data loaded anyway. `method_exists`
        // keeps the trait usable everywhere without forcing each page to
        // implement the hook.
        if ($targetStatus === MatchStatus::RegistrationClosed && method_exists($this, 'cleanUpPreRegistrations')) {
            $this->cleanUpPreRegistrations();
        }

        // Achievement evaluation on a fresh entry into Completed only.
        // Re-completing (after an un-complete + re-complete round-trip) is
        // a no-op because the evaluators are idempotent on slug+shooter,
        // but we skip the call entirely to avoid the wasted DB scan.
        if ($targetStatus === MatchStatus::Completed && $oldStatus !== MatchStatus::Completed) {
            try {
                AchievementService::evaluateMatchCompletion($this->match);
                if ($this->match->royal_flush_enabled) {
                    AchievementService::evaluateRoyalFlushCompletion($this->match);
                }
            } catch (\Throwable $e) {
                Log::warning('Achievement evaluation failed', ['error' => $e->getMessage()]);
            }
        }

        if ($isUncomplete) {
            $this->safeToast('Match reopened. Achievements already awarded stay in place.', 'success');
        } else {
            $this->safeToast("Match status changed to {$targetStatus->label()}.", 'success');
        }
    }

    /**
     * Direct-jump back to Active from any state. Bypasses the transition
     * graph — used by the "Reopen Match" affordance on the Reports tab
     * for a Completed match (the in-graph path goes Completed → Active
     * already, but this is the named entry point for the UI to bind to).
     */
    public function reopenMatch(): void
    {
        $this->match->update(['status' => MatchStatus::Active]);
        $this->safeToast('Match reopened.', 'success');
    }

    // ── Flux side-effect helpers ─────────────────────────────────────────
    //
    // Flux::toast / Flux::modal both reach into the current Livewire
    // component to dispatch browser events. In any context that isn't
    // a live Livewire request (unit tests, queued jobs, console
    // commands), `livewire()->current()` is null and these calls
    // explode with a `Call to a member function dispatch() on false`.
    //
    // Wrap them so the trait's lifecycle behaviour (status change,
    // notifications, achievements) survives in those contexts — the
    // toast is purely UX feedback, never load-bearing logic, and
    // silently dropping it is preferable to crashing the transition.

    private function safeToast(string $message, string $variant = 'success'): void
    {
        try {
            Flux::toast($message, variant: $variant);
        } catch (\Throwable $e) {
            // No Livewire context — caller is a test or background job.
        }
    }

    private function safeModalShow(string $name): void
    {
        try {
            Flux::modal($name)->show();
        } catch (\Throwable $e) {
            // No Livewire context — caller is a test or background job.
        }
    }

    private function safeModalClose(string $name): void
    {
        try {
            Flux::modal($name)->close();
        } catch (\Throwable $e) {
            // No Livewire context — caller is a test or background job.
        }
    }
}
