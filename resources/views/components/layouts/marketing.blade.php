<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'DeadCenter — Precision Shooting Scoring Platform' }}</title>
    <meta name="description" content="{{ $description ?? 'A modern scoring platform for shooting sports. Capture scores offline on tablets, sync across devices, and publish live results.' }}">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,900" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen antialiased" style="background: linear-gradient(180deg, var(--lp-bg) 0%, var(--lp-bg-2) 100%); color: var(--lp-text);">

    <nav class="sticky top-0 z-50" style="background: rgba(7, 19, 39, 0.85); border-bottom: 1px solid var(--lp-border); backdrop-filter: blur(20px) saturate(1.4);">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <a href="/" class="opacity-90 hover:opacity-100 transition-opacity">
                <x-app-logo size="md" variant="dark" />
            </a>
            <div class="hidden md:flex items-center gap-7 text-[13px] font-medium">
                <a href="{{ route('features') }}" class="transition-colors duration-200 hover:!text-white {{ request()->routeIs('features') ? '!text-white' : '' }}" style="color: var(--lp-text-muted);">Features</a>
                <a href="{{ route('scoring') }}" class="transition-colors duration-200 hover:!text-white {{ request()->routeIs('scoring') ? '!text-white' : '' }}" style="color: var(--lp-text-muted);">Scoring</a>
                <a href="{{ route('offline') }}" class="transition-colors duration-200 hover:!text-white {{ request()->routeIs('offline') ? '!text-white' : '' }}" style="color: var(--lp-text-muted);">Offline</a>
                <a href="{{ route('setup') }}" class="transition-colors duration-200 hover:!text-white {{ request()->routeIs('setup') ? '!text-white' : '' }}" style="color: var(--lp-text-muted);">Setup</a>
            </div>
            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-lg px-5 py-2 text-sm font-semibold text-white transition-colors" style="background: var(--lp-red);" onmouseover="this.style.background='var(--lp-red-hover)'" onmouseout="this.style.background='var(--lp-red)'">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg px-4 py-2 text-sm font-medium transition-colors hover:!text-white" style="color: var(--lp-text-soft);">
                        Sign In
                    </a>
                    <a href="{{ route('register') }}" class="rounded-lg px-5 py-2 text-sm font-semibold text-white transition-colors" style="background: var(--lp-red);" onmouseover="this.style.background='var(--lp-red-hover)'" onmouseout="this.style.background='var(--lp-red)'">
                        Get Started
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    {{ $slot }}

    <footer style="border-top: 1px solid var(--lp-border); background: var(--lp-bg);">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
            <div class="flex items-center gap-2 opacity-40">
                <x-app-logo size="sm" variant="dark" />
                <span class="text-xs" style="color: var(--lp-text-muted);">&copy; {{ date('Y') }}</span>
            </div>
            <span class="text-xs" style="color: var(--lp-text-muted); opacity: 0.6;">deadcenter.co.za</span>
        </div>
    </footer>

</body>
</html>
