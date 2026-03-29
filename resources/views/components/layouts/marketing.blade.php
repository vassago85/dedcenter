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
<body class="min-h-screen bg-app text-primary antialiased">

    <nav class="sticky top-0 z-50 border-b border-border/50 bg-app/80 backdrop-blur-xl">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <a href="/" class="group opacity-90 hover:opacity-100 transition-opacity">
                <x-app-logo size="md" />
            </a>
            <div class="hidden md:flex items-center gap-7 text-[13px] font-medium text-muted">
                <a href="{{ route('features') }}" class="hover:text-primary transition-colors duration-200 {{ request()->routeIs('features') ? 'text-primary' : '' }}">Features</a>
                <a href="{{ route('scoring') }}" class="hover:text-primary transition-colors duration-200 {{ request()->routeIs('scoring') ? 'text-primary' : '' }}">Scoring</a>
                <a href="{{ route('offline') }}" class="hover:text-primary transition-colors duration-200 {{ request()->routeIs('offline') ? 'text-primary' : '' }}">Offline</a>
                <a href="{{ route('setup') }}" class="hover:text-primary transition-colors duration-200 {{ request()->routeIs('setup') ? 'text-primary' : '' }}">Setup</a>
            </div>
            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-lg bg-accent px-5 py-2 text-sm font-semibold text-primary transition-colors hover:bg-accent-hover">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-muted transition-colors hover:text-primary">
                        Sign In
                    </a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-accent px-5 py-2 text-sm font-semibold text-primary transition-colors hover:bg-accent-hover">
                        Get Started
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    {{ $slot }}

    <footer class="border-t border-border/50 bg-app">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
            <div class="flex items-center gap-2">
                <x-app-logo size="sm" class="opacity-40" />
                <span class="text-xs text-muted/60">&copy; {{ date('Y') }}</span>
            </div>
            <span class="text-xs text-muted/60">deadcenter.co.za</span>
        </div>
    </footer>

</body>
</html>
