@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'ogImage' => null,
    'ogType' => 'website',
    'schema' => null,
])

@php
    $ctx = $domainContext ?? domain_context();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth">
<head>
    @include('partials.gtag')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <x-seo-meta
        :title="$title ?? ($ctx === 'md' ? 'DeadCenter — Shooting Match Scoring Software' : 'DeadCenter — Precision Shooting Scoring Platform')"
        :description="$description ?? ($ctx === 'md'
            ? 'Set up matches, manage scoring, and publish results. A modern competition management platform for match directors, clubs, and federations in South Africa.'
            : 'Find shooting competitions, track results, view standings, and connect with clubs across South Africa. Free to use.')"
        :canonical="$canonical"
        :og-image="$ogImage"
        :og-type="$ogType"
        :schema="$schema"
    />

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,900" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen antialiased" style="background: linear-gradient(180deg, var(--lp-bg) 0%, var(--lp-bg-2) 100%); color: var(--lp-text);">

    {{-- Navigation --}}
    <nav x-data="{ mobileOpen: false }" class="sticky top-0 z-50" style="background: rgba(7, 19, 39, 0.85); border-bottom: 1px solid var(--lp-border); backdrop-filter: blur(20px) saturate(1.4);">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <a href="{{ $ctx === 'md' ? md_url('/') : shooter_url('/') }}" class="opacity-90 hover:opacity-100 transition-opacity">
                <x-app-logo size="md" variant="dark" />
            </a>

            {{-- Desktop nav --}}
            <div class="hidden md:flex items-center gap-7 text-[13px] font-medium">
                @if($ctx === 'md')
                    <a href="#features" class="transition-colors duration-200 hover:!text-white" style="color: var(--lp-text-muted);">Features</a>
                    <a href="#scoring-modes" class="transition-colors duration-200 hover:!text-white" style="color: var(--lp-text-muted);">Scoring Modes</a>
                    <a href="#how-it-works" class="transition-colors duration-200 hover:!text-white" style="color: var(--lp-text-muted);">How It Works</a>
                    <a href="#for-clubs" class="transition-colors duration-200 hover:!text-white" style="color: var(--lp-text-muted);">For Clubs</a>
                @else
                    <a href="#events" class="transition-colors duration-200 hover:!text-white" style="color: var(--lp-text-muted);">Events</a>
                    <a href="#results" class="transition-colors duration-200 hover:!text-white" style="color: var(--lp-text-muted);">Results</a>
                    <a href="#standings" class="transition-colors duration-200 hover:!text-white" style="color: var(--lp-text-muted);">Standings</a>
                    <a href="#disciplines" class="transition-colors duration-200 hover:!text-white" style="color: var(--lp-text-muted);">Disciplines</a>
                    <a href="{{ route('features') }}" class="transition-colors duration-200 hover:!text-white {{ request()->routeIs('features') ? '!text-white' : '' }}" style="color: var(--lp-text-muted);">About</a>
                @endif
            </div>

            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ app_url('/dashboard') }}" class="rounded-lg px-5 py-2 text-sm font-semibold text-white transition-colors" style="background: var(--lp-red);" onmouseover="this.style.background='var(--lp-red-hover)'" onmouseout="this.style.background='var(--lp-red)'">
                        Dashboard
                    </a>
                @else
                    <a href="{{ app_url('/login') }}" class="hidden sm:inline-block rounded-lg px-4 py-2 text-sm font-medium transition-colors hover:!text-white" style="color: var(--lp-text-soft);">
                        Sign In
                    </a>
                    <a href="{{ app_url('/register') }}" class="rounded-lg px-5 py-2 text-sm font-semibold text-white transition-colors" style="background: var(--lp-red);" onmouseover="this.style.background='var(--lp-red-hover)'" onmouseout="this.style.background='var(--lp-red)'">
                        Get Started
                    </a>
                @endauth

                {{-- Mobile hamburger --}}
                <button @click="mobileOpen = !mobileOpen" class="md:hidden ml-1 p-2 rounded-lg transition-colors hover:bg-white/10" aria-label="Toggle menu">
                    <svg x-show="!mobileOpen" class="h-5 w-5" style="color: var(--lp-text-soft);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                    <svg x-show="mobileOpen" x-cloak class="h-5 w-5" style="color: var(--lp-text-soft);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- Mobile nav panel --}}
        <div x-show="mobileOpen" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="md:hidden border-t px-6 py-4 space-y-1" style="border-color: var(--lp-border); background: rgba(7, 19, 39, 0.95);">
            @if($ctx === 'md')
                <a href="#features" @click="mobileOpen = false" class="block rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:bg-white/10" style="color: var(--lp-text-soft);">Features</a>
                <a href="#scoring-modes" @click="mobileOpen = false" class="block rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:bg-white/10" style="color: var(--lp-text-soft);">Scoring Modes</a>
                <a href="#how-it-works" @click="mobileOpen = false" class="block rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:bg-white/10" style="color: var(--lp-text-soft);">How It Works</a>
                <a href="#for-clubs" @click="mobileOpen = false" class="block rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:bg-white/10" style="color: var(--lp-text-soft);">For Clubs</a>
            @else
                <a href="#events" @click="mobileOpen = false" class="block rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:bg-white/10" style="color: var(--lp-text-soft);">Events</a>
                <a href="#results" @click="mobileOpen = false" class="block rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:bg-white/10" style="color: var(--lp-text-soft);">Results</a>
                <a href="#standings" @click="mobileOpen = false" class="block rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:bg-white/10" style="color: var(--lp-text-soft);">Standings</a>
                <a href="#disciplines" @click="mobileOpen = false" class="block rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:bg-white/10" style="color: var(--lp-text-soft);">Disciplines</a>
                <a href="{{ route('features') }}" class="block rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:bg-white/10" style="color: var(--lp-text-soft);">About</a>
            @endif

            <div class="pt-2 border-t" style="border-color: var(--lp-border);">
                @guest
                    <a href="{{ app_url('/login') }}" class="block rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:bg-white/10" style="color: var(--lp-text-soft);">Sign In</a>
                    <a href="{{ app_url('/register') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold" style="color: var(--lp-red);">Register</a>
                @endguest
            </div>
        </div>
    </nav>

    {{ $slot }}

    {{-- Footer --}}
    <footer style="border-top: 1px solid var(--lp-border); background: var(--lp-bg);">
        <div class="mx-auto max-w-6xl px-6 py-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2 opacity-60">
                    <x-app-logo size="sm" variant="dark" />
                    <span class="text-xs" style="color: var(--lp-text-muted);">&copy; {{ date('Y') }}</span>
                </div>

                <div class="flex items-center gap-6 text-xs" style="color: var(--lp-text-muted);">
                    @if($ctx === 'md')
                        <a href="{{ shooter_url('/') }}" class="hover:!text-white transition-colors">Shooter Portal</a>
                    @else
                        <a href="{{ md_url('/') }}" class="hover:!text-white transition-colors">For Match Directors</a>
                    @endif
                    <a href="{{ app_url('/login') }}" class="hover:!text-white transition-colors">Sign In</a>
                    <span style="opacity: 0.5;">deadcenter.co.za</span>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
