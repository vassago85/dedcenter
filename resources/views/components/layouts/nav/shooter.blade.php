@props([
    'authUser',
    'userOrgs',
    'unreadNotifCount' => 0,
])

@php
    $primaryOrg = $userOrgs->first();
@endphp

<div class="space-y-1">
    <div class="px-3 pb-1">
        <p class="text-xs font-semibold uppercase tracking-wider text-muted">Shooter</p>
    </div>

    <div class="px-3 pt-1">
        <p class="text-[10px] font-semibold uppercase tracking-wider text-muted/70">Primary</p>
    </div>
    <a href="{{ route('dashboard') }}" class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold transition-colors {{ request()->routeIs('dashboard') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">Dashboard</a>
    <a href="{{ route('browse-events') }}" class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold transition-colors {{ request()->routeIs('browse-events') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">Find a Match</a>
    <a href="{{ route('matches') }}" class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold transition-colors {{ request()->routeIs('matches') || request()->routeIs('matches.*') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">My Matches</a>

    <div class="px-3 pt-2">
        <p class="text-[10px] font-semibold uppercase tracking-wider text-muted/70">Results</p>
    </div>
    <a href="{{ route('events', ['tab' => 'past']) }}" class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('events') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">Recent Results</a>
    <a href="{{ $primaryOrg ? route('leaderboard', $primaryOrg) : route('organizations') }}" class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('leaderboard') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Standings
        @if(! $primaryOrg)
            <span class="ml-auto text-[10px] text-muted">Join an org</span>
        @endif
    </a>

    <div class="px-3 pt-2">
        <p class="text-[10px] font-semibold uppercase tracking-wider text-muted/70">Tools</p>
    </div>
    <a href="{{ route('equipment') }}" class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('equipment') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">My Rifles & Loads</a>
    <a href="{{ route('notifications') }}" class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('notifications') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">
        Notifications
        @if($unreadNotifCount > 0)
            <span class="ml-auto flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $unreadNotifCount > 99 ? '99+' : $unreadNotifCount }}</span>
        @endif
    </a>
    <a href="{{ route('settings') }}" class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('settings') && ! request()->routeIs('settings.notifications') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">Profile</a>
    <a href="{{ route('settings.notifications') }}" class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('settings.notifications') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">Notification Preferences</a>
    <a href="{{ route('home') }}" class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium text-secondary transition-colors hover:bg-surface-2/50 hover:text-primary">Homepage</a>

    <div class="mt-2 border-t border-border pt-2">
        <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-muted">Organizations</p>
        <a href="{{ route('organizations') }}" class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition-colors {{ request()->routeIs('organizations') ? 'bg-surface-2 text-primary' : 'text-secondary hover:bg-surface-2/50 hover:text-primary' }}">My Organizations</a>
        @if($userOrgs->count() > 0)
            @foreach($userOrgs as $org)
                <a href="{{ route('org.dashboard', $org) }}" class="flex min-h-[44px] items-center gap-2 rounded-lg px-3 text-sm font-medium text-secondary transition-colors hover:bg-surface-2/50 hover:text-primary">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-surface-2 text-xs font-bold uppercase">{{ substr($org->name, 0, 1) }}</span>
                    <span class="truncate">{{ $org->name }}</span>
                </a>
            @endforeach
        @else
            <a href="{{ route('organizations.create') }}" class="flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium text-secondary transition-colors hover:bg-surface-2/50 hover:text-primary">Start an Organization</a>
        @endif
    </div>
</div>
