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

    <div x-data="{ sidebarOpen: false, adminOpen: false }" class="flex min-h-screen">
        <div x-cloak x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-200"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-200"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-40 bg-black/60 lg:hidden" @click="sidebarOpen = false"></div>

        <aside x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-border bg-sidebar transition-transform duration-200 lg:static lg:z-auto lg:translate-x-0">
            <div class="flex h-16 items-center border-b border-border px-6">
                <a href="/" class="group inline-flex min-h-[44px] items-center rounded-lg focus:outline-none focus:ring-2 focus:ring-accent">
                    <x-app-logo size="md" class="opacity-90 transition-opacity group-hover:opacity-100" />
                </a>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                @auth
                    @if($isOrgMode)
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
                <div class="flex-1"></div>
                @auth
                    @if($authUser->canScore())
                        <a href="https://{{ config('domains.app') }}/score" target="_blank" class="inline-flex min-h-[44px] flex-col items-start justify-center rounded-lg px-3 text-white transition-colors focus:outline-none focus:ring-2 focus:ring-accent" style="background:#ff2b2b;">
                            <span class="inline-flex items-center gap-2 text-sm font-semibold">
                                <x-icon name="play" class="h-4 w-4" />
                                Open scoring app
                            </span>
                            <span class="text-[10px] uppercase tracking-wider text-white/80">Separate window for tablet speed</span>
                        </a>
                    @endif
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

    <flux:toast />
    @fluxScripts
    <x-pwa-nav />
    <x-install-prompt />
</body>
</html>
