{{-- Canonical platform-admin sidebar — 5 primary items. Members,
     Shooter Claims, Seasons, Contact Submissions, Homepage Editor collapse
     into a "Secondary" group so the daily admin view stays calm.

     The badge counts are computed fresh in render() (see the component), so
     they reflect the live DB on every re-render. We force a re-render on
     every wire:navigate via $wire.$refresh() — without it, navigating back to
     a cached page (e.g. Shooter Claims) restores the stale sidebar snapshot
     and the badge "sticks" at its old value. wire:poll.60s is a cross-tab
     safety net. This Alpine hook lives on the persisted layout component, so
     x-init runs once and the single listener survives every navigation. --}}
<div class="space-y-1"
     wire:poll.60s
     x-data
     x-init="document.addEventListener('livewire:navigated', () => $wire.$refresh())">
    <div class="px-3 pb-1">
        <p class="text-xs font-semibold uppercase tracking-wider text-muted">Platform Admin</p>
    </div>

    <a href="{{ route('admin.dashboard') }}" wire:navigate
       class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Overview
    </a>
    <a href="{{ route('admin.organizations') }}" wire:navigate
       class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold transition-colors {{ request()->routeIs('admin.organizations') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Organizations
        @if($pendingOrgs > 0)
            <span class="ml-auto inline-flex items-center justify-center rounded-full bg-amber-600 px-2 py-0.5 text-xs font-bold text-primary">{{ $pendingOrgs }}</span>
        @endif
    </a>
    <a href="{{ route('admin.matches.index') }}" wire:navigate
       class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold transition-colors {{ request()->routeIs('admin.matches.*') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Matches
    </a>
    <a href="{{ route('admin.advertising') }}" wire:navigate
       class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold transition-colors {{ request()->routeIs('admin.advertising') || request()->routeIs('admin.sponsors') || request()->routeIs('admin.sponsor-assignments') || request()->routeIs('admin.sponsor-info') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Revenue
    </a>
    <a href="{{ route('admin.settings') }}" wire:navigate
       class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold transition-colors {{ request()->routeIs('admin.settings') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Settings
    </a>

    <div class="mt-3 border-t border-border pt-3">
        <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-muted">Secondary</p>
        <a href="{{ route('admin.members') }}" wire:navigate
           class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('admin.members') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
            Members
        </a>
        <a href="{{ route('admin.shooter-claims') }}" wire:navigate
           class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('admin.shooter-claims') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
            Shooter Claims
            @if($pendingClaims > 0)
                <span class="ml-auto inline-flex items-center justify-center rounded-full bg-amber-600 px-2 py-0.5 text-xs font-bold text-primary">{{ $pendingClaims }}</span>
            @endif
        </a>
        <a href="{{ route('admin.registrations') }}" wire:navigate
           class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('admin.registrations') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
            All Registrations
        </a>
        <a href="{{ route('admin.seasons') }}" wire:navigate
           class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('admin.seasons') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
            Seasons
        </a>
        <a href="{{ route('admin.homepage') }}" wire:navigate
           class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('admin.homepage') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
            Homepage Editor
        </a>
        <a href="{{ route('admin.contact-submissions') }}" wire:navigate
           class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('admin.contact-submissions') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
            Contact Submissions
            @if($unreadContacts > 0)
                <span class="ml-auto rounded-full bg-amber-500 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $unreadContacts }}</span>
            @endif
        </a>
    </div>
</div>
