<?php

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
            'slug'  => 'shooter',
            'label' => 'Shooter mode',
            'url'   => route('dashboard'),
        ];

        $ownedOrg = $user->ownedOrganizations()->first();
        if ($ownedOrg) {
            $modes[] = [
                'slug'  => 'org',
                'label' => 'Organization mode',
                'url'   => route('org.dashboard', ['organization' => $ownedOrg->slug]),
            ];
        }

        if ($user->isAdmin()) {
            $modes[] = [
                'slug'  => 'admin',
                'label' => 'Platform admin mode',
                'url'   => route('admin.dashboard'),
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

        $modes  = user_available_modes($user);
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
