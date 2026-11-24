<?php

namespace App\Policies;

use App\Models\ShootingMatch;
use App\Models\User;

/**
 * One place for match access. Call `$user->can('score', $match)` and friends.
 *
 *   view      org member, match staff, the creator, or a platform admin
 *   score     org director and above, match staff for this event, or the creator
 *   squad     same people as score — walk-ins and firing order
 *   manage    org director and above, this event's match director, or the creator
 *   maintain  match director and above, not merely the creator — repair tools
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

        if ($this->createdThisMatch($user, $match)) {
            return true;
        }

        if ($match->organization_id
            && $user->organizations()->where('organizations.id', $match->organization_id)->exists()) {
            return true;
        }

        return $match->staff()->where('users.id', $user->id)->exists();
    }

    /**
     * Record scores, correct a shooter, or change shooter status.
     * Org directors cover every match in their organization. A range
     * officer only covers matches they are staffed on.
     */
    public function score(User $user, ShootingMatch $match): bool
    {
        return $user->isAdmin()
            || $this->createdThisMatch($user, $match)
            || ($match->organization !== null && $user->isOrgMatchDirector($match->organization))
            || $match->staff()->where('users.id', $user->id)->exists();
    }

    /** Walk-ins, squad moves, and firing order. Same people as score(). */
    public function squad(User $user, ShootingMatch $match): bool
    {
        return $this->score($user, $match);
    }

    /** Fix a score on the day. Same people as score(). */
    public function correct(User $user, ShootingMatch $match): bool
    {
        return $this->score($user, $match);
    }

    /**
     * Complete, reopen, reassign, publish, side bets, DQ, and exports.
     * Match director and above, plus the person who created the match.
     */
    public function manage(User $user, ShootingMatch $match): bool
    {
        return $user->isAdmin()
            || $this->createdThisMatch($user, $match)
            || ($match->organization !== null && $user->isOrgMatchDirector($match->organization))
            || $match->staff()->where('users.id', $user->id)->where('match_staff.role', 'match_director')->exists();
    }

    public function disqualify(User $user, ShootingMatch $match): bool
    {
        return $this->manage($user, $match);
    }

    public function export(User $user, ShootingMatch $match): bool
    {
        return $this->manage($user, $match);
    }

    /**
     * Repair tools (PRS backfill / diagnostic). Org match directors and
     * platform admins only — creating the match is not enough.
     */
    public function maintain(User $user, ShootingMatch $match): bool
    {
        return $user->isAdmin()
            || ($match->organization !== null && $user->isOrgMatchDirector($match->organization));
    }

    private function createdThisMatch(User $user, ShootingMatch $match): bool
    {
        return $match->created_by !== null
            && (int) $match->created_by === (int) $user->id;
    }
}
