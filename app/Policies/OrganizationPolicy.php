<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

/**
 * Canonical authorization rules for organization-scoped actions.
 * Introduced by the RBAC audit; see the docblock on
 * `ShootingMatchPolicy` for the wider migration plan.
 *
 * The three bars used across the app:
 *   - RO   → can access the org area (matches, roster, team roster view).
 *            Matches the `org.admin` middleware alias which resolves to
 *            `isOrgRangeOfficer`.
 *   - MD   → can manage the org's matches, settings, and lifecycle.
 *   - OWNER→ can manage the org's admin team (add / remove staff, toggle
 *            roles). Prior to the RBAC audit the team page's mutating
 *            Livewire actions had NO server-side owner check, so any
 *            range officer could self-promote to Match Director; this
 *            policy captures the corrected bar.
 */
class OrganizationPolicy
{
    /**
     * Can the user access this organization's admin area at all?
     * Range-officer bar (same as the `org.admin` route middleware).
     */
    public function view(User $user, Organization $organization): bool
    {
        return $user->isOrgRangeOfficer($organization);
    }

    /**
     * Can the user manage the org's matches, registrations, settings,
     * and other operational tooling? MD bar — settings changes and match
     * CRUD are heavier than day-of scoring.
     */
    public function manage(User $user, Organization $organization): bool
    {
        return $user->isOrgMatchDirector($organization);
    }

    /**
     * Can the user manage the org's admin TEAM — invite/remove staff,
     * toggle role pivots? OWNER bar only. Everything else in the org
     * area is fine at MD or RO level; touching the team is the one
     * place a non-owner should not go.
     */
    public function manageTeam(User $user, Organization $organization): bool
    {
        return $user->isOrgOwner($organization);
    }
}
