<?php

namespace App\Policies;

use App\Models\ShootingMatch;
use App\Models\User;

/**
 * Canonical authorization rules for match access. Introduced by the RBAC
 * audit as the source of truth so we stop duplicating the same
 * `isOwner || created_by || isOrgX($org)` snippet across every controller
 * and Volt component.
 *
 * Migration status (2026-08-02):
 *   - New routes / new code MUST use this policy via `$user->can(...)`,
 *     `Gate::allows(...)`, or route `can:` middleware.
 *   - Existing controllers (ScoreManagementController, DisqualificationController,
 *     MatchExportController, Api\ScoreController, Api\PrsScoreController,
 *     Api\ElrScoreController) still ship inline checks — those checks call
 *     the SAME underlying model helpers this policy delegates to, so they
 *     stay in sync. They will be migrated in a follow-up so a single edit
 *     to a policy method updates every consumer.
 *
 * Laravel 11 auto-discovers policies at `App\Policies\{Model}Policy`, so no
 * registration is required — `$user->can('score', $match)` just works.
 */
class ShootingMatchPolicy
{
    /**
     * Can the user see this match in the scoring API surface? Mirrors the
     * `ShootingMatch::scopeVisibleToScoringUser` filter, which is applied
     * by `MatchController::index/show` to block IDOR by direct id.
     */
    public function view(User $user, ShootingMatch $match): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($match->created_by === $user->id) {
            return true;
        }

        if ($match->organization_id
            && $user->organizations()->where('organizations.id', $match->organization_id)->exists()) {
            return true;
        }

        return $match->staff()->where('users.id', $user->id)->exists();
    }

    /**
     * Can the user record scores for this match? Range-officer bar — this
     * is the operational-during-scoring role. Matches the inline check in
     * `Api\ScoreController::store`, `Api\PrsScoreController::store`, and
     * `Api\ElrScoreController::store`.
     */
    public function score(User $user, ShootingMatch $match): bool
    {
        return $user->isAdmin()
            || $match->created_by === $user->id
            || ($match->organization !== null && $user->isOrgRangeOfficer($match->organization));
    }

    /**
     * Can the user apply a single-shooter score correction on the day?
     * Same bar as `score()` — this is an RO's own fix-up flow, not a
     * match-lifecycle action.
     */
    public function correct(User $user, ShootingMatch $match): bool
    {
        return $this->score($user, $match);
    }

    /**
     * Can the user perform destructive / match-lifecycle actions —
     * complete, reopen, reassign scores, reshoot a stage, publish, move a
     * scored stage, manage side-bet buy-ins? MATCH DIRECTOR bar. Prior to
     * the RBAC audit `authorizeMatchDirector` was actually checking
     * `isOrgAdmin` (= range officer), which silently let ROs complete /
     * reopen matches; this policy captures the corrected bar.
     */
    public function manage(User $user, ShootingMatch $match): bool
    {
        return $user->isAdmin()
            || $match->created_by === $user->id
            || ($match->organization !== null && $user->isOrgMatchDirector($match->organization));
    }

    /**
     * Can the user issue / revoke disqualifications? MD-only — same bar
     * as `manage()`. Mirrors `Api\DisqualificationController::authorizeMatchDirector`.
     */
    public function disqualify(User $user, ShootingMatch $match): bool
    {
        return $this->manage($user, $match);
    }

    /**
     * Can the user download match exports (CSV / PDF)? MD-only. Mirrors
     * `MatchExportController::authorizeExport`.
     */
    public function export(User $user, ShootingMatch $match): bool
    {
        return $this->manage($user, $match);
    }
}
