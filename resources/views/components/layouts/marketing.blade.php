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
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

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
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#08142b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="DeadCenter">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,900" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen antialiased overflow-x-hidden" style="background: linear-gradient(180deg, var(--lp-bg) 0%, var(--lp-bg-2) 100%); color: var(--lp-text);">

    {{-- Navigation (mobile menu uses native <details> — Alpine is not bundled on
         marketing pages). Breakpoint is `lg:` rather than `md:` because the
         shooter nav has 6 links ("Events / Organizations / Results / Standings /
         About / Advertise") — at the old `md:` cutoff (768px) they collided
         with the logo and the "Sign In / Get Started" pair, wrapping "Sign In"
         onto two lines and pushing "Get Started" past the viewport. Switching
         to `lg:` keeps the hamburger visible up to 1023px where the row
         finally has room to breathe. --}}
    <nav class="sticky top-0 z-50" style="background: rgba(7, 19, 39, 0.85); border-bottom: 1px solid var(--lp-border); backdrop-filter: blur(20px) saturate(1.4);">
        <div class="relative mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
            <a href="{{ $ctx === 'md' ? md_url('/') : shooter_url('/') }}" class="shrink-0 opacity-90 hover:opacity-100 transition-opacity">
                <x-app-logo size="md" variant="dark" />
            </a>

            {{-- Desktop nav — hidden until lg so the 6-item shooter nav has
                 room; gap tightens on smaller desktop widths so nothing gets
                 squeezed against the logo or the auth CTAs. --}}
            <div class="hidden lg:flex items-center gap-5 xl:gap-7 text-[13px] font-medium min-w-0">
                @if($ctx === 'md')
                    <a href="#features" class="shrink-0 transition-colors duration-200 hover:!text-white" style="color: var(--lp-text-muted);">Features</a>
                    <a href="#scoring-modes" class="shrink-0 transition-colors duration-200 hover:!text-white" style="color: var(--lp-text-muted);">Scoring Modes</a>
                    <a href="#how-it-works" class="shrink-0 transition-colors duration-200 hover:!text-white" style="color: var(--lp-text-muted);">How It Works</a>
                    <a href="#for-clubs" class="shrink-0 transition-colors duration-200 hover:!text-white" style="color: var(--lp-text-muted);">For Clubs</a>
                @else
                    <a href="{{ route('events') }}" class="shrink-0 transition-colors duration-200 hover:!text-white {{ request()->routeIs('events') ? '!text-white' : '' }}" style="color: var(--lp-text-muted);">Events</a>
                    <a href="{{ route('home') }}#organizations" class="shrink-0 transition-colors duration-200 hover:!text-white" style="color: var(--lp-text-muted);">Organizations</a>
                    <a href="{{ route('home') }}#results" class="shrink-0 transition-colors duration-200 hover:!text-white" style="color: var(--lp-text-muted);">Results</a>
                    <a href="{{ route('home') }}#standings" class="shrink-0 transition-colors duration-200 hover:!text-white" style="color: var(--lp-text-muted);">Standings</a>
                    <a href="{{ route('features') }}" class="shrink-0 transition-colors duration-200 hover:!text-white {{ request()->routeIs('features') ? '!text-white' : '' }}" style="color: var(--lp-text-muted);">About</a>
                    <a href="{{ route('advertise') }}" class="shrink-0 transition-colors duration-200 hover:!text-white {{ request()->routeIs('advertise') ? '!text-white' : '' }}" style="color: var(--lp-text-muted);">Advertise</a>
                @endif
            </div>

            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                @auth
                    <a href="{{ app_url('/dashboard') }}" class="lp-cta-nav inline-flex shrink-0 items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold whitespace-nowrap sm:px-5">
                        Dashboard
                    </a>
                @else
                    <a href="{{ app_url('/login') }}" class="hidden sm:inline-flex shrink-0 items-center whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:!text-white lp-nav-text-muted sm:px-4">
                        Sign In
                    </a>
                    <a href="{{ app_url('/register') }}" class="lp-cta-nav inline-flex shrink-0 items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold whitespace-nowrap sm:px-5">
                        Get Started
                    </a>
                @endauth

                {{-- Mobile menu: native disclosure (closed by default; no Alpine on this bundle) --}}
                <details class="marketing-nav-details lg:hidden relative">
                    <summary class="marketing-nav-summary list-none flex cursor-pointer items-center justify-center rounded-lg p-2 transition-colors hover:bg-white/10 outline-none ring-0" aria-label="Toggle menu">
                        <x-icon name="menu" class="marketing-nav-icon-open h-5 w-5 shrink-0" style="color: var(--lp-text-soft);" />
                        <x-icon name="x" class="marketing-nav-icon-close h-5 w-5 shrink-0" style="color: var(--lp-text-soft);" />
                    </summary>
                    <div class="marketing-mobile-panel absolute left-1/2 top-full z-[60] mt-0 w-screen max-w-[100vw] -translate-x-1/2 border-t px-6 py-4 shadow-[0_18px_48px_rgba(0,0,0,0.45)]" style="border-color: var(--lp-border); background: rgba(7, 19, 39, 0.97); backdrop-filter: blur(20px) saturate(1.4);">
                        <div class="mx-auto max-w-6xl space-y-1">
                            @if($ctx === 'md')
                                <a href="#features" class="marketing-mobile-link block rounded-lg px-3 py-2.5 text-[13px] font-medium transition-colors hover:bg-white/10" onclick="this.closest('details')?.removeAttribute('open')">Features</a>
                                <a href="#scoring-modes" class="marketing-mobile-link block rounded-lg px-3 py-2.5 text-[13px] font-medium transition-colors hover:bg-white/10" onclick="this.closest('details')?.removeAttribute('open')">Scoring Modes</a>
                                <a href="#how-it-works" class="marketing-mobile-link block rounded-lg px-3 py-2.5 text-[13px] font-medium transition-colors hover:bg-white/10" onclick="this.closest('details')?.removeAttribute('open')">How It Works</a>
                                <a href="#for-clubs" class="marketing-mobile-link block rounded-lg px-3 py-2.5 text-[13px] font-medium transition-colors hover:bg-white/10" onclick="this.closest('details')?.removeAttribute('open')">For Clubs</a>
                            @else
                                <a href="{{ route('events') }}" class="marketing-mobile-link block rounded-lg px-3 py-2.5 text-[13px] font-medium transition-colors hover:bg-white/10" onclick="this.closest('details')?.removeAttribute('open')">Events</a>
                                <a href="{{ route('home') }}#organizations" class="marketing-mobile-link block rounded-lg px-3 py-2.5 text-[13px] font-medium transition-colors hover:bg-white/10" onclick="this.closest('details')?.removeAttribute('open')">Organizations</a>
                                <a href="{{ route('home') }}#results" class="marketing-mobile-link block rounded-lg px-3 py-2.5 text-[13px] font-medium transition-colors hover:bg-white/10" onclick="this.closest('details')?.removeAttribute('open')">Results</a>
                                <a href="{{ route('home') }}#standings" class="marketing-mobile-link block rounded-lg px-3 py-2.5 text-[13px] font-medium transition-colors hover:bg-white/10" onclick="this.closest('details')?.removeAttribute('open')">Standings</a>
                                <a href="{{ route('features') }}" class="marketing-mobile-link block rounded-lg px-3 py-2.5 text-[13px] font-medium transition-colors hover:bg-white/10" onclick="this.closest('details')?.removeAttribute('open')">About</a>
                                <a href="{{ route('advertise') }}" class="marketing-mobile-link block rounded-lg px-3 py-2.5 text-[13px] font-medium transition-colors hover:bg-white/10" onclick="this.closest('details')?.removeAttribute('open')">Advertise</a>
                            @endif

                            <div class="mt-3 border-t pt-3 space-y-1" style="border-color: var(--lp-border);">
                                @auth
                                    <a href="{{ app_url('/dashboard') }}" class="lp-cta-nav block rounded-lg px-3 py-2.5 text-center text-[13px] font-semibold" onclick="this.closest('details')?.removeAttribute('open')">Dashboard</a>
                                @else
                                    <a href="{{ app_url('/login') }}" class="marketing-mobile-link block rounded-lg px-3 py-2.5 text-[13px] font-medium transition-colors hover:bg-white/10 sm:hidden" onclick="this.closest('details')?.removeAttribute('open')">Sign In</a>
                                    <a href="{{ app_url('/register') }}" class="lp-cta-nav block rounded-lg px-3 py-2.5 text-center text-[13px] font-semibold" onclick="this.closest('details')?.removeAttribute('open')">Register</a>
                                @endauth
                            </div>
                        </div>
                    </div>
                </details>
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
                        <a href="{{ shooter_url('/advertise') }}" class="hover:!text-white transition-colors">Advertise</a>
                    @else
                        <a href="{{ md_url('/') }}" class="hover:!text-white transition-colors">For Match Directors</a>
                        <a href="{{ route('advertise') }}" class="hover:!text-white transition-colors">Advertise</a>
                    @endif
                    <a href="{{ app_url('/login') }}" class="hover:!text-white transition-colors">Sign In</a>
                    <span style="opacity: 0.5;">deadcenter.co.za</span>
                </div>
            </div>
        </div>
    </footer>

    <flux:toast />
    @fluxScripts
    <x-pwa-nav />
    <x-install-prompt />
</body>
</html>
