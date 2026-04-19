@props([
    'currentOrg',
])

{{-- Canonical org sidebar — 5 primary items. Results/Standings, Clubs and
     Portal sponsors move into a quieter "Secondary" group so the daily
     command structure stays legible. --}}
<div class="space-y-1">
    <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-muted">Organization</p>

    <a href="{{ route('org.dashboard', $currentOrg) }}" wire:navigate
       class="flex min-h-[44px] items-center gap-3 rounded-lg px-3 text-sm font-semibold transition-colors {{ request()->routeIs('org.dashboard') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Dashboard
    </a>
    <a href="{{ route('org.matches.index', $currentOrg) }}" wire:navigate
       class="flex min-h-[44px] items-center gap-3 rounded-lg px-3 text-sm font-semibold transition-colors {{ request()->routeIs('org.matches.*') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Matches
    </a>
    <a href="{{ route('org.registrations', $currentOrg) }}" wire:navigate
       class="flex min-h-[44px] items-center gap-3 rounded-lg px-3 text-sm font-semibold transition-colors {{ request()->routeIs('org.registrations') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Registrations
    </a>
    <a href="{{ route('org.admins', $currentOrg) }}" wire:navigate
       class="flex min-h-[44px] items-center gap-3 rounded-lg px-3 text-sm font-semibold transition-colors {{ request()->routeIs('org.admins') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Team
    </a>
    <a href="{{ route('org.settings', $currentOrg) }}" wire:navigate
       class="flex min-h-[44px] items-center gap-3 rounded-lg px-3 text-sm font-semibold transition-colors {{ request()->routeIs('org.settings') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Settings
    </a>

    @php
        $showSecondary = $currentOrg->isLeague() || $currentOrg->hasPortalAdRights();
    @endphp

    <div class="mt-3 border-t border-border pt-3">
        <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-muted">Secondary</p>
        <a href="{{ route('leaderboard', $currentOrg) }}" wire:navigate
           class="flex min-h-[44px] items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('leaderboard') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
            Results &amp; Standings
        </a>
        @if($currentOrg->isLeague())
            <a href="{{ route('org.clubs', $currentOrg) }}" wire:navigate
               class="flex min-h-[44px] items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('org.clubs') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
                Clubs
            </a>
        @endif
        @if($currentOrg->hasPortalAdRights())
            <a href="{{ route('org.portal-sponsors', $currentOrg) }}" wire:navigate
               class="flex min-h-[44px] items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('org.portal-sponsors') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
                Portal Sponsors
            </a>
        @endif
    </div>

    <div class="mt-2 border-t border-border pt-2">
        <a href="{{ route('dashboard') }}" wire:navigate
           class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium text-muted transition-colors hover:bg-surface-2/50 hover:text-primary">
            ← Back to Shooter Mode
        </a>
    </div>
</div>
