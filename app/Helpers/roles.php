<?php

use App\Models\Organization;
use App\Models\User;

if (! function_exists('user_available_modes')) {
    /**
     * Return the list of modes this user can operate in, in priority order.
     * Each mode has a slug, label, and URL. Shooter is the universal baseline.
     *
     * @return array<int, array{slug: string, label: string, url: string, current_ok: bool}>
     */
    function user_available_modes(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $modes = [];

        $modes[] = [
            'slug' => 'shooter',
            'label' => 'Shooter mode',
            'url' => route('dashboard'),
        ];

        $operates = fn ($pivot) => (bool) $pivot->is_owner
            || (bool) $pivot->is_match_director
            || (bool) $pivot->is_range_officer;
        $organizerOrg = $user->relationLoaded('organizations')
            ? $user->organizations->first(fn ($o) => $operates($o->pivot))
            : $user->organizations()->where(function ($q) {
                $q->where('organization_admins.is_owner', true)
                    ->orWhere('organization_admins.is_match_director', true)
                    ->orWhere('organization_admins.is_range_officer', true);
            })->first();
        if ($organizerOrg) {
            $modes[] = [
                'slug' => 'org',
                'label' => 'Organizer',
                'url' => route('org.dashboard', ['organization' => $organizerOrg->slug]),
            ];
        }

        if ($user->isAdmin()) {
            $modes[] = [
                'slug' => 'admin',
                'label' => 'Platform admin mode',
                'url' => route('admin.dashboard'),
            ];
        }

        return $modes;
    }
}

if (! function_exists('user_home_path')) {
    /**
     * Post-login / "home" URL for a user. Respects the currently active mode
     * (set by the mode switcher) and falls back to the highest-privilege
     * mode available to them.
     */
    function user_home_path(?User $user): string
    {
        if (! $user) {
            return route('login');
        }

        $modes = user_available_modes($user);
        $active = session('active_mode');

        if ($active) {
            foreach ($modes as $mode) {
                if ($mode['slug'] === $active) {
                    return $mode['url'];
                }
            }
        }

        $fallbackPriority = ['admin', 'org', 'shooter'];
        foreach ($fallbackPriority as $slug) {
            foreach ($modes as $mode) {
                if ($mode['slug'] === $slug) {
                    return $mode['url'];
                }
            }
        }

        return route('dashboard');
    }
}

if (! function_exists('user_can_enter_mode')) {
    function user_can_enter_mode(?User $user, string $mode): bool
    {
        foreach (user_available_modes($user) as $m) {
            if ($m['slug'] === $mode) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('user_can_operate_org')) {
    /**
     * Range officer and above on this organization. Site admins pass because
     * they already bypass the org middleware.
     */
    function user_can_operate_org(?User $user, Organization $organization): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $pivot = $organization->pivot;

        if ($pivot && ($pivot->is_owner || $pivot->is_match_director || $pivot->is_range_officer)) {
            return true;
        }

        return $user->isOrgRangeOfficer($organization);
    }
}

if (! function_exists('user_org_destination')) {
    /**
     * Where a sidebar or dashboard link for this org should go.
     * Operators land in the organizer workspace. Everyone else lands on
     * the public portal when it is on, otherwise the public leaderboard.
     */
    function user_org_destination(?User $user, Organization $organization): string
    {
        if (user_can_operate_org($user, $organization)) {
            return route('org.dashboard', $organization);
        }

        if ($organization->portal_enabled) {
            return route('portal.home', $organization);
        }

        return route('leaderboard', $organization);
    }
}

if (! function_exists('org_membership_label')) {
    /**
     * Highest org badge. Owner implies Director implies Range Officer.
     * A person with only the shooter flag is a member, not a role.
     */
    function org_membership_label(object $pivot): string
    {
        if ($pivot->is_owner) {
            return 'Owner';
        }

        if ($pivot->is_match_director) {
            return 'Director';
        }

        if ($pivot->is_range_officer) {
            return 'Range Officer';
        }

        return 'Member';
    }
}
