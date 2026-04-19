<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.gtag')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if(config('services.webpush.public_key'))
        <meta name="vapid-public-key" content="{{ config('services.webpush.public_key') }}">
    @endif
    <title>{{ $title ?? 'DeadCenter' }}</title>
    {{-- Block Grammarly and similar browser extensions from injecting DOM nodes.
         Their hidden elements break Livewire's morph engine with
         "Cannot read properties of null (reading 'before')". --}}
    <meta name="grammarly" content="false">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#08142b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="DeadCenter">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen bg-app text-base text-primary antialiased" data-gramm="false" data-gramm_editor="false" data-enable-grammarly="false">
    @php
        $currentOrg = request()->route('organization');
        if ($currentOrg && ! $currentOrg instanceof \App\Models\Organization) {
            $currentOrg = \App\Models\Organization::where('slug', $currentOrg)->first();
        }
        $leaderboardOrg = request()->route('organization');
        if ($leaderboardOrg && ! $leaderboardOrg instanceof \App\Models\Organization) {
            $leaderboardOrg = \App\Models\Organization::where('slug', $leaderboardOrg)->first();
        }
        $authUser = auth()->user();
        $userOrgs = $authUser ? $authUser->organizations : collect();
        $unreadNotifCount = $authUser?->unreadNotifications()->count() ?? 0;
        $isOrgMode = request()->routeIs('org.*') && $currentOrg;
        $isPlatformMode = request()->routeIs('admin.*');

        // Sticky org mode: if the user clicked "Results & Standings" from the
        // org sidebar (/leaderboard/{organization}), keep them in Org Mode as
        // long as they actually belong to that org — otherwise the page looks
        // like they were thrown back into Shooter Mode.
        if (! $isOrgMode && request()->routeIs('leaderboard') && $leaderboardOrg && $authUser) {
            $belongsToOrg = $userOrgs->contains(fn ($o) => $o->id === $leaderboardOrg->id);
            if ($belongsToOrg || $authUser->isAdmin()) {
                $isOrgMode = true;
                $currentOrg = $leaderboardOrg;
            }
        }

        $contextMode = 'shooter';
        $contextModeLabel = 'Shooter Mode';
        $contextLabel = 'Your match-day companion.';
        $contextExitUrl = null;
        $contextExitLabel = null;

        if ($isOrgMode) {
            $contextMode = 'org';
            $contextModeLabel = $currentOrg->name . ' Admin Mode';
            $contextLabel = 'Operate matches, registrations, and team for your organization.';
            $contextExitUrl = route('dashboard');
            $contextExitLabel = 'Back to Shooter Dashboard';
        } elseif ($isPlatformMode) {
            $contextMode = 'platform';
            $contextModeLabel = 'Platform Admin Mode';
            $contextLabel = 'Platform-level operations and controls.';
            $contextExitUrl = route('dashboard');
            $contextExitLabel = 'Back to Shooter Dashboard';
        } elseif (request()->routeIs('leaderboard') && $leaderboardOrg) {
            $contextLabel = 'Viewing standings for ' . $leaderboardOrg->name . '.';
        }
    @endphp

    @guest
        {{-- Anonymous viewers: drop the whole sidebar shell and render a
             slim top bar so the scoreboard / event detail / past-results
             still read as "public" without feeling logged-out-broken. --}}
        <div class="flex min-h-screen flex-col">
            <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-border bg-app/95 px-4 backdrop-blur lg:px-8">
                <a href="/" wire:navigate class="group inline-flex min-h-[44px] items-center rounded-lg focus:outline-none focus:ring-2 focus:ring-accent">
                    <x-app-logo size="md" class="opacity-90 transition-opacity group-hover:opacity-100" />
                </a>
                <div class="flex-1"></div>
                <a href="{{ route('login') }}" wire:navigate class="inline-flex min-h-[40px] items-center rounded-lg border border-border bg-surface px-3 text-sm font-semibold text-secondary transition-colors hover:border-accent hover:text-accent">Sign In</a>
                <a href="{{ route('register') }}" wire:navigate class="inline-flex min-h-[40px] items-center rounded-lg bg-accent px-3 text-sm font-semibold text-white transition-colors hover:bg-accent/90">Register</a>
            </header>
            <main class="min-w-0 flex-1 overflow-x-hidden px-4 py-6 lg:px-8">
                <div class="min-w-0">{{ $slot }}</div>
            </main>
            <flux:toast />
            @fluxScripts
            <x-pwa-nav />
        </div>
    @else
    @php
        $availableModes = user_available_modes($authUser);
        $activeModeSlug = session('active_mode')
            ?? ($isPlatformMode ? 'admin' : ($isOrgMode ? 'org' : 'shooter'));
    @endphp
    <div x-data="{ sidebarOpen: false, adminOpen: false, userMenu: false, modeMenu: false }" class="flex min-h-screen">
        <div x-cloak x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-200"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-200"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-40 bg-black/60 lg:hidden" @click="sidebarOpen = false"></div>

        <aside x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-border bg-sidebar transition-transform duration-200 lg:static lg:z-auto lg:translate-x-0">
            <div class="flex h-16 items-center border-b border-border px-6">
                <a href="{{ route('dashboard') }}" wire:navigate class="group inline-flex min-h-[44px] items-center rounded-lg focus:outline-none focus:ring-2 focus:ring-accent">
                    <x-app-logo size="md" class="opacity-90 transition-opacity group-hover:opacity-100" />
                </a>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                @auth
                    @if($isPlatformMode)
                        {{-- Platform Admin Mode owns the whole sidebar. Role
                             switching happens via the top-right mode menu. --}}
                        <x-layouts.nav.platform-admin />
                    @elseif($isOrgMode)
                        <x-layouts.nav.org :current-org="$currentOrg" />
                    @else
                        <x-layouts.nav.shooter :auth-user="$authUser" :user-orgs="$userOrgs" :unread-notif-count="$unreadNotifCount" />
                        @if($authUser->isOwner())
                            <div class="mt-2 border-t border-border pt-3">
                                <button type="button" @click="adminOpen = !adminOpen" class="flex min-h-[44px] w-full items-center justify-between rounded-lg px-3 text-xs font-semibold uppercase tracking-wider text-muted transition-colors hover:text-secondary focus:outline-none focus:ring-2 focus:ring-accent">
                                    Platform admin tools
                                    <x-icon name="chevron-down" class="h-3.5 w-3.5 transition-transform duration-200" x-bind:class="adminOpen && 'rotate-180'" />
                                </button>
                                <div x-show="adminOpen" x-collapse x-cloak class="mt-2 space-y-1">
                                    <x-layouts.nav.platform-admin />
                                </div>
                            </div>
                        @endif
                    @endif
                @endauth
            </nav>

            <div class="space-y-2 border-t border-border px-4 py-3">
                @auth
                    <div class="flex items-center gap-2 px-2">
                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-surface-2 text-xs font-bold text-primary">{{ strtoupper(substr($authUser->name, 0, 1)) }}</div>
                        <div class="min-w-0 flex-1"><span class="block truncate text-xs text-secondary">{{ $authUser->name }}</span></div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex min-h-[44px] w-full items-center gap-2 rounded-lg px-3 text-sm text-muted transition-colors hover:bg-surface-2 hover:text-primary focus:outline-none focus:ring-2 focus:ring-accent">
                            <x-icon name="log-out" class="h-4 w-4" />
                            Sign Out
                        </button>
                    </form>
                @endauth
                <p class="px-2 text-xs text-muted">&copy; {{ date('Y') }} <span class="font-semibold"><span class="text-muted">DEAD</span><span class="text-accent/50">CENTER</span></span></p>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col lg:ml-0">
            <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-border bg-app/95 px-4 backdrop-blur lg:px-8">
                <button type="button" @click="sidebarOpen = true" class="flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg text-muted transition-colors hover:text-primary focus:outline-none focus:ring-2 focus:ring-accent lg:hidden">
                    <x-icon name="menu" class="h-6 w-6" />
                </button>
                {{-- Context title: which mode the user is operating in. Matches
                     the §3A UX-standard rule "the top bar always answers where
                     am I right now?". Page H1 remains in <x-app-page-header>. --}}
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-primary lg:text-base">{{ $contextModeLabel }}</p>
                    @if($isOrgMode && $currentOrg)
                        <p class="truncate text-[11px] font-medium uppercase tracking-wider text-muted">{{ ucfirst($currentOrg->type ?? 'organization') }}</p>
                    @endif
                </div>
                @auth
                    @if($authUser->canScore())
                        {{-- External target (scoring SPA in a new window). Never wire:navigate this. --}}
                        <a href="https://{{ config('domains.app') }}/score" target="_blank" rel="noopener" class="inline-flex min-h-[44px] flex-col items-start justify-center rounded-lg px-3 text-white transition-colors focus:outline-none focus:ring-2 focus:ring-accent" style="background:#ff2b2b;">
                            <span class="inline-flex items-center gap-2 text-sm font-semibold">
                                <x-icon name="play" class="h-4 w-4" />
                                Open scoring app
                            </span>
                            <span class="text-[10px] uppercase tracking-wider text-white/80">Separate window for tablet speed</span>
                        </a>
                    @endif

                    {{-- Mode switcher: only shown when the user has more than one mode --}}
                    @if(count($availableModes) > 1)
                        <div class="relative" @click.outside="modeMenu = false">
                            <button type="button" @click="modeMenu = !modeMenu"
                                class="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-secondary transition-colors hover:border-accent hover:text-primary focus:outline-none focus:ring-2 focus:ring-accent">
                                <span class="h-1.5 w-1.5 rounded-full bg-accent node-pulse"></span>
                                @php
                                    $activeLabel = collect($availableModes)->firstWhere('slug', $activeModeSlug)['label'] ?? 'Shooter mode';
                                @endphp
                                <span class="hidden sm:inline">{{ $activeLabel }}</span>
                                <span class="sm:hidden">Mode</span>
                                <x-icon name="chevron-down" class="h-3.5 w-3.5" />
                            </button>
                            <div x-show="modeMenu" x-cloak x-transition
                                class="absolute right-0 z-40 mt-2 w-60 overflow-hidden rounded-xl border border-border bg-surface shadow-xl shadow-black/40">
                                <div class="border-b border-border/70 px-4 py-2">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-muted">Switch mode</p>
                                </div>
                                <div class="py-1">
                                    @foreach($availableModes as $mode)
                                        <form method="POST" action="{{ route('mode.switch') }}">
                                            @csrf
                                            <input type="hidden" name="mode" value="{{ $mode['slug'] }}">
                                            <button type="submit"
                                                class="flex w-full items-center justify-between gap-2 px-4 py-2 text-left text-sm text-secondary transition-colors hover:bg-surface-2 hover:text-primary {{ $mode['slug'] === $activeModeSlug ? 'bg-surface-2 text-primary' : '' }}">
                                                <span>{{ $mode['label'] }}</span>
                                                @if($mode['slug'] === $activeModeSlug)
                                                    <span class="inline-flex h-4 items-center rounded-full bg-accent/20 px-2 text-[10px] font-semibold uppercase tracking-wider text-accent">Active</span>
                                                @endif
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- User menu --}}
                    <div class="relative" @click.outside="userMenu = false">
                        <button type="button" @click="userMenu = !userMenu"
                            class="inline-flex min-h-[40px] items-center gap-2 rounded-lg p-1.5 transition-colors hover:bg-surface focus:outline-none focus:ring-2 focus:ring-accent">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-surface-2 to-surface text-xs font-semibold text-primary ring-1 ring-border">
                                {{ strtoupper(substr($authUser->name, 0, 1)) }}{{ strtoupper(substr(strstr($authUser->name, ' ') ?: '', 1, 1)) }}
                            </span>
                            <span class="hidden md:flex flex-col items-start leading-tight">
                                <span class="truncate max-w-[140px] text-sm font-semibold text-primary">{{ $authUser->name }}</span>
                                <span class="truncate max-w-[140px] text-[10px] font-medium uppercase tracking-wider text-muted">
                                    {{ $authUser->isOwner() ? 'Owner · Developer' : ($authUser->isMatchDirector() ? 'Match Director' : 'Shooter') }}
                                </span>
                            </span>
                            <x-icon name="chevron-down" class="hidden md:block h-3.5 w-3.5 text-muted" />
                        </button>
                        <div x-show="userMenu" x-cloak x-transition
                            class="absolute right-0 z-40 mt-2 w-64 overflow-hidden rounded-xl border border-border bg-surface shadow-xl shadow-black/40">
                            <div class="border-b border-border/70 px-4 py-3">
                                <p class="truncate text-sm font-semibold text-primary">{{ $authUser->name }}</p>
                                <p class="truncate text-xs text-muted">{{ $authUser->email }}</p>
                            </div>
                            <div class="py-1">
                                <a href="{{ route('notifications') }}" wire:navigate class="flex items-center gap-2 px-4 py-2 text-sm text-secondary transition-colors hover:bg-surface-2 hover:text-primary">
                                    <x-icon name="bell" class="h-4 w-4" />
                                    Notifications
                                    @if($unreadNotifCount > 0)
                                        <span class="ml-auto flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $unreadNotifCount > 99 ? '99+' : $unreadNotifCount }}</span>
                                    @endif
                                </a>
                                <a href="{{ route('settings') }}" wire:navigate class="flex items-center gap-2 px-4 py-2 text-sm text-secondary transition-colors hover:bg-surface-2 hover:text-primary">
                                    <x-icon name="settings" class="h-4 w-4" />
                                    Account &amp; profile
                                </a>
                                <a href="{{ route('settings.notifications') }}" wire:navigate class="flex items-center gap-2 px-4 py-2 text-sm text-secondary transition-colors hover:bg-surface-2 hover:text-primary">
                                    <x-icon name="bell" class="h-4 w-4" />
                                    Notification preferences
                                </a>
                                <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2 px-4 py-2 text-sm text-secondary transition-colors hover:bg-surface-2 hover:text-primary">
                                    <x-icon name="house" class="h-4 w-4" />
                                    Public homepage
                                </a>
                                <div class="my-1 border-t border-border/70"></div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-secondary transition-colors hover:bg-surface-2 hover:text-primary">
                                        <x-icon name="log-out" class="h-4 w-4" />
                                        Sign out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endauth
            </header>

            @auth
                <x-app-context-bar :mode="$contextMode" :mode-label="$contextModeLabel" :context-label="$contextLabel" :exit-url="$contextExitUrl" :exit-label="$contextExitLabel" />
            @endauth

            <main class="min-w-0 flex-1 overflow-x-hidden px-4 py-6 lg:px-8">
                <div class="min-w-0">{{ $slot }}</div>
            </main>
        </div>
    </div>

    {{-- Developer toolbar: visible only to site-owner role. Pinned to the
         bottom so the dev tools don't eat into the sidebar nav. Keeps the
         dashboard feeling calm for shooters while giving the owner an
         always-available escape hatch. --}}
    @if($authUser->isOwner())
        <div class="fixed inset-x-0 bottom-0 z-[90] border-t border-border bg-gradient-to-r from-sidebar via-app to-sidebar text-secondary shadow-[0_-4px_20px_-8px_rgba(0,0,0,0.6)]">
            <div class="flex flex-wrap items-center gap-4 px-4 py-2 text-xs">
                <span class="inline-flex items-center gap-2 font-semibold uppercase tracking-[0.2em] text-accent">
                    <span class="h-1.5 w-1.5 rounded-full bg-accent node-pulse"></span>
                    Owner · Developer
                </span>
                <a href="{{ route('admin.dashboard') }}" wire:navigate class="inline-flex items-center gap-1 rounded-md border border-border/60 px-2 py-1 transition-colors hover:border-accent hover:text-primary">
                    <x-icon name="gauge" class="h-3.5 w-3.5" />
                    Admin
                </a>
                <a href="{{ route('admin.shooter-claims') }}" wire:navigate class="inline-flex items-center gap-1 rounded-md border border-border/60 px-2 py-1 transition-colors hover:border-accent hover:text-primary">
                    <x-icon name="user-check" class="h-3.5 w-3.5" />
                    Claims
                </a>
                @if(\Illuminate\Support\Facades\Route::has('telescope'))
                    <a href="/telescope" target="_blank" class="inline-flex items-center gap-1 rounded-md border border-border/60 px-2 py-1 transition-colors hover:border-accent hover:text-primary">
                        <x-icon name="terminal" class="h-3.5 w-3.5" />
                        Telescope
                    </a>
                @endif
                <span class="ml-auto hidden text-[10px] uppercase tracking-[0.25em] text-muted md:inline">v{{ config('app.version', '1.0') }} · {{ app()->environment() }}</span>
            </div>
        </div>
        <div class="h-10"></div>
    @endif

    <flux:toast />
    @fluxScripts
    <x-pwa-nav />
    <x-install-prompt />
    @endguest
</body>
</html>
