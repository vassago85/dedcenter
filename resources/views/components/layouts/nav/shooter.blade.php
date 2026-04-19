@props([
    'authUser',
    'userOrgs',
    'unreadNotifCount' => 0,
])

@php
    $primaryOrg = $userOrgs->first();
@endphp

{{-- Canonical shooter sidebar — 5 primary items. Notifications / Profile /
     Notification prefs live in the user menu (top-right). Secondary items
     (Standings, public homepage) move into a quieter bottom group. --}}
<div class="space-y-1">
    <div class="px-3 pb-1">
        <p class="text-xs font-semibold uppercase tracking-wider text-muted">Shooter</p>
    </div>

    <a href="{{ route('dashboard') }}" wire:navigate
       class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold transition-colors {{ request()->routeIs('dashboard') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Dashboard
    </a>
    <a href="{{ route('browse-events') }}" wire:navigate
       class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold transition-colors {{ request()->routeIs('browse-events') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Find a Match
    </a>
    <a href="{{ route('matches') }}" wire:navigate
       class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold transition-colors {{ request()->routeIs('matches') || request()->routeIs('matches.*') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        My Matches
        @if($unreadNotifCount > 0)
            <span class="ml-auto flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white" title="{{ $unreadNotifCount }} new notifications">{{ $unreadNotifCount > 99 ? '99+' : $unreadNotifCount }}</span>
        @endif
    </a>
    <a href="{{ route('events', ['tab' => 'my_events']) }}" wire:navigate
       class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold transition-colors {{ request()->routeIs('events') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Results
    </a>
    <a href="{{ route('equipment') }}" wire:navigate
       class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold transition-colors {{ request()->routeIs('equipment') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Equipment
    </a>

    {{-- Organizations — list your memberships so switching into org mode is
         always one click away. --}}
    <div class="mt-3 border-t border-border pt-3">
        <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-muted">Organizations</p>
        <a href="{{ route('organizations') }}" wire:navigate
           class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('organizations') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
            My Organizations
        </a>
        @if($primaryOrg)
            <a href="{{ route('leaderboard', $primaryOrg) }}" wire:navigate
               class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('leaderboard') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
                Standings
            </a>
        @endif
        @if($userOrgs->count() > 0)
            @foreach($userOrgs as $org)
                <a href="{{ route('org.dashboard', $org) }}" wire:navigate
                   class="flex min-h-[44px] items-center gap-2 rounded-lg px-3 text-sm font-medium text-secondary transition-colors hover:bg-surface-2/50 hover:text-primary">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-surface-2 text-xs font-bold uppercase">{{ substr($org->name, 0, 1) }}</span>
                    <span class="truncate">{{ $org->name }}</span>
                </a>
            @endforeach
        @else
            <a href="{{ route('organizations.create') }}" wire:navigate
               class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium text-secondary transition-colors hover:bg-surface-2/50 hover:text-primary">
                Start an Organization
            </a>
        @endif
    </div>
</div>
