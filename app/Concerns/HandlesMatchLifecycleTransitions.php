<?php

namespace App\Concerns;

use App\Enums\MatchStatus;
use App\Models\ShootingMatch;
use App\Services\AchievementService;
use App\Services\NotificationService;
use Flux\Flux;
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
    public function transitionStatus(string $target): void
    {
        $targetStatus = MatchStatus::from($target);

        if (! $this->match->status->canTransitionTo($targetStatus)) {
            Flux::toast('Invalid status transition.', variant: 'danger');
            return;
        }

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
            Flux::toast('Match reopened. Achievements already awarded stay in place.', variant: 'success');
        } else {
            Flux::toast("Match status changed to {$targetStatus->label()}.", variant: 'success');
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
        Flux::toast('Match reopened.', variant: 'success');
    }
}
