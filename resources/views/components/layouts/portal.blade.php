@props(['organization' => null])
@php
    $org = $organization;
    if (! $org) {
        $orgSlug = request()->route('organization');
        $org = $orgSlug instanceof \App\Models\Organization
            ? $orgSlug
            : \App\Models\Organization::where('slug', $orgSlug)->first();
    }
    $primaryColor = $org?->primary_color ?? '#dc2626';
    $secondaryColor = $org?->secondary_color ?? '#1e293b';
    $orgName = $org?->name ?? 'DeadCenter';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.gtag')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? $orgName }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance

    <style>
        :root {
            --portal-primary: {{ $primaryColor }};
            --portal-secondary: {{ $secondaryColor }};
        }
        .portal-primary { color: var(--portal-primary); }
        .portal-bg-primary { background-color: var(--portal-primary); }
        .portal-bg-primary-hover:hover { background-color: color-mix(in srgb, var(--portal-primary) 85%, black); }
        .portal-border-primary { border-color: var(--portal-primary); }
        .portal-bg-secondary { background-color: var(--portal-secondary); }
        .portal-ring-primary:focus { --tw-ring-color: var(--portal-primary); }
    </style>
</head>
<body class="min-h-screen bg-app text-primary antialiased">

    {{-- Navigation --}}
    <nav class="sticky top-0 z-50 border-b border-white/10 backdrop-blur-xl" style="background-color: color-mix(in srgb, {{ $secondaryColor }} 95%, transparent);">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <div class="flex items-center gap-6">
                    <a href="{{ route('portal.home', $org) }}" class="flex items-center gap-3">
                        @if($org?->logo_path)
                            <img src="{{ Storage::url($org->logo_path) }}" alt="{{ $orgName }}" class="h-8 w-auto">
                        @endif
                        <span class="text-lg font-bold tracking-tight">{{ $orgName }}</span>
                    </a>

                    <div class="hidden sm:flex items-center gap-1">
                        <a href="{{ route('portal.home', $org) }}"
                           class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors {{ request()->routeIs('portal.home') ? 'portal-bg-primary text-primary' : 'text-secondary hover:text-primary hover:bg-white/10' }}">
                            Home
                        </a>
                        <a href="{{ route('portal.matches', $org) }}"
                           class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors {{ request()->routeIs('portal.matches*') ? 'portal-bg-primary text-primary' : 'text-secondary hover:text-primary hover:bg-white/10' }}">
                            Matches
                        </a>
                        <a href="{{ route('portal.leaderboard', $org) }}"
                           class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors {{ request()->routeIs('portal.leaderboard') ? 'portal-bg-primary text-primary' : 'text-secondary hover:text-primary hover:bg-white/10' }}">
                            Leaderboard
                        </a>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    @auth
                        <span class="text-xs text-muted hidden sm:inline">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-lg px-3 py-1.5 text-sm font-medium text-secondary hover:text-primary hover:bg-white/10 transition-colors">
                                Sign Out
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-secondary hover:text-primary hover:bg-white/10 transition-colors">
                            Sign In
                        </a>
                        <a href="{{ route('register') }}" class="portal-bg-primary portal-bg-primary-hover rounded-lg px-4 py-1.5 text-sm font-medium text-primary transition-colors">
                            Register
                        </a>
                    @endauth
                </div>
            </div>

            {{-- Mobile nav --}}
            <div class="flex sm:hidden gap-1 pb-2 -mx-1 overflow-x-auto">
                <a href="{{ route('portal.home', $org) }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium whitespace-nowrap transition-colors {{ request()->routeIs('portal.home') ? 'portal-bg-primary text-primary' : 'text-secondary hover:bg-white/10' }}">
                    Home
                </a>
                <a href="{{ route('portal.matches', $org) }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium whitespace-nowrap transition-colors {{ request()->routeIs('portal.matches*') ? 'portal-bg-primary text-primary' : 'text-secondary hover:bg-white/10' }}">
                    Matches
                </a>
                <a href="{{ route('portal.leaderboard', $org) }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium whitespace-nowrap transition-colors {{ request()->routeIs('portal.leaderboard') ? 'portal-bg-primary text-primary' : 'text-secondary hover:bg-white/10' }}">
                    Leaderboard
                </a>
            </div>
        </div>
    </nav>

    {{-- Page content --}}
    <main>
        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer class="border-t border-white/10 mt-16">
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-sm text-muted">&copy; {{ date('Y') }} {{ $orgName }}. Powered by DeadCenter.</p>
                <div class="flex gap-4 text-sm text-muted">
                    <a href="{{ route('portal.matches', $org) }}" class="hover:text-secondary transition-colors">Matches</a>
                    <a href="{{ route('portal.leaderboard', $org) }}" class="hover:text-secondary transition-colors">Leaderboard</a>
                </div>
            </div>
        </div>
    </footer>

    <flux:toast />
    @fluxScripts
    <x-pwa-nav />
</body>
</html>
