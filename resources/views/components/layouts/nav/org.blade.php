@props([
    'currentOrg',
])

<div class="space-y-1">
    <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-muted">Organization Workspace</p>

    <a href="{{ route('org.dashboard', $currentOrg) }}"
       class="flex min-h-[44px] items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('org.dashboard') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Dashboard
    </a>
    <a href="{{ route('org.matches.index', $currentOrg) }}"
       class="flex min-h-[44px] items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('org.matches.*') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Matches
    </a>
    <a href="{{ route('org.registrations', $currentOrg) }}"
       class="flex min-h-[44px] items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('org.registrations') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Registrations
    </a>
    <a href="{{ route('org.admins', $currentOrg) }}"
       class="flex min-h-[44px] items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('org.admins') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Team
    </a>
    <a href="{{ route('leaderboard', $currentOrg) }}"
       class="flex min-h-[44px] items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('leaderboard') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Results & Standings
    </a>
    @if($currentOrg->isLeague())
        <a href="{{ route('org.clubs', $currentOrg) }}"
           class="flex min-h-[44px] items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('org.clubs') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
            Clubs
        </a>
    @endif
    <a href="{{ route('org.settings', $currentOrg) }}"
       class="flex min-h-[44px] items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('org.settings') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Organization Settings
    </a>

    <div class="mt-2 border-t border-border pt-2">
        <a href="{{ route('dashboard') }}"
           class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium text-secondary transition-colors hover:bg-surface-2/50 hover:text-primary">
            Back to Shooter Dashboard
        </a>
    </div>
</div>
