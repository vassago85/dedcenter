<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DeadCenter — Precision Shooting Scoring Platform</title>
    <meta name="description" content="A modern scoring platform for precision shooting sports. Capture scores offline, sync across devices, and publish live results.">
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
                <a href="{{ route('features') }}" class="hover:text-primary transition-colors duration-200">Features</a>
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

    <section class="relative isolate overflow-hidden">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute inset-0 bg-gradient-to-b from-accent/5 via-transparent to-transparent"></div>
            <div class="absolute top-0 left-1/2 -translate-x-1/2 h-[600px] w-[900px] rounded-full bg-accent/[0.03] blur-[120px]"></div>
        </div>

        <div class="relative mx-auto max-w-6xl px-6 pt-28 pb-20 sm:pt-36 sm:pb-28 lg:pt-44 lg:pb-32">
            <div class="mx-auto max-w-2xl text-center">
                <div class="mb-8 inline-flex items-center gap-2 rounded-full border border-border/50 bg-surface/30 px-4 py-1.5 text-[13px] font-medium text-muted backdrop-blur-sm">
                    <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
                    Precision Shooting Scoring
                </div>

                <h1 class="text-[2.75rem] font-black leading-[1.08] tracking-tight sm:text-6xl lg:text-7xl">
                    Run Every Match.<br>
                    Score Every <span class="text-accent">Shot.</span>
                </h1>

                <p class="mx-auto mt-7 max-w-lg text-[1.05rem] leading-relaxed text-muted">
                    A modern scoring platform for precision shooting. Capture scores offline on tablets, sync across devices, and publish live results.
                </p>

                <div class="mt-10 flex flex-col items-center gap-3.5 sm:flex-row sm:justify-center">
                    @auth
                        <a href="{{ route('dashboard') }}"
                           class="group relative inline-flex items-center justify-center rounded-xl bg-accent px-8 py-3.5 text-[15px] font-semibold text-primary shadow-[0_1px_2px_rgba(0,0,0,0.3),0_8px_24px_rgba(255,43,43,0.2)] transition-all duration-200 hover:bg-accent-hover">
                            Go to Dashboard
                        </a>
                    @else
                        <a href="{{ route('register') }}"
                           class="group relative inline-flex items-center justify-center rounded-xl bg-accent px-8 py-3.5 text-[15px] font-semibold text-primary shadow-[0_1px_2px_rgba(0,0,0,0.3),0_8px_24px_rgba(255,43,43,0.2)] transition-all duration-200 hover:bg-accent-hover">
                            Get Started Free
                        </a>
                        <a href="{{ route('login') }}"
                           class="inline-flex items-center justify-center rounded-xl border border-border/50 bg-surface/30 px-8 py-3.5 text-[15px] font-semibold text-secondary backdrop-blur-sm transition-all duration-200 hover:border-border hover:bg-surface/60 hover:text-primary">
                            Sign In
                        </a>
                    @endauth
                </div>

                <div class="mt-14 flex flex-col items-center gap-6 sm:flex-row sm:justify-center sm:gap-10">
                    <div class="flex items-center gap-2.5 text-[13px] text-muted">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-surface">
                            <span class="h-1.5 w-1.5 rounded-full bg-accent/70"></span>
                        </span>
                        Offline scoring on tablets
                    </div>
                    <div class="flex items-center gap-2.5 text-[13px] text-muted">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-surface">
                            <span class="h-1.5 w-1.5 rounded-full bg-accent/70"></span>
                        </span>
                        Live synced leaderboards
                    </div>
                    <div class="flex items-center gap-2.5 text-[13px] text-muted">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-surface">
                            <span class="h-1.5 w-1.5 rounded-full bg-accent/70"></span>
                        </span>
                        Built for clubs & leagues
                    </div>
                </div>
            </div>
        </div>

        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-border/50 to-transparent"></div>
    </section>

    <section class="border-t border-border">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-12 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">Built for the Range</h2>
                <p class="mt-3 text-muted max-w-xl mx-auto">Everything you need to run a smooth match.</p>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-border bg-surface p-6">
                    <h3 class="mb-1 font-semibold text-primary">Offline-First Scoring</h3>
                    <p class="text-sm text-muted">Scores save locally and sync when connectivity returns.</p>
                </div>
                <div class="rounded-xl border border-border bg-surface p-6">
                    <h3 class="mb-1 font-semibold text-primary">Live Scoreboards</h3>
                    <p class="text-sm text-muted">TV display and mobile live page with QR code sharing.</p>
                </div>
                <div class="rounded-xl border border-border bg-surface p-6">
                    <h3 class="mb-1 font-semibold text-primary">PRS & Standard Scoring</h3>
                    <p class="text-sm text-muted">Hit/Miss/Not Taken for PRS. Gong multipliers for standard.</p>
                </div>
                <div class="rounded-xl border border-border bg-surface p-6">
                    <h3 class="mb-1 font-semibold text-primary">Divisions & Categories</h3>
                    <p class="text-sm text-muted">Equipment classes and demographic groups for leaderboards.</p>
                </div>
                <div class="rounded-xl border border-border bg-surface p-6">
                    <h3 class="mb-1 font-semibold text-primary">Leagues & Clubs</h3>
                    <p class="text-sm text-muted">Season leaderboards with best-of-N scoring.</p>
                </div>
                <div class="rounded-xl border border-border bg-surface p-6">
                    <h3 class="mb-1 font-semibold text-primary">Multi-Device Sync</h3>
                    <p class="text-sm text-muted">Multiple ROs score simultaneously, merging in real-time.</p>
                </div>
            </div>
            <div class="mt-10 text-center">
                <a href="{{ route('features') }}" class="text-sm font-medium text-accent hover:text-accent-hover transition-colors">
                    View all features &rarr;
                </a>
            </div>
        </div>
    </section>

    <section class="border-t border-border">
        <div class="mx-auto max-w-6xl px-6 py-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">Ready to Score?</h2>
            <p class="mx-auto mt-3 max-w-md text-muted">Set up your first match in minutes. Free to use.</p>
            <div class="mt-8 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-xl bg-accent px-8 py-3.5 text-lg font-bold text-primary shadow-lg shadow-accent/20 transition-all hover:bg-accent-hover">
                        Go to Dashboard
                    </a>
                @else
                    <a href="{{ route('register') }}" class="rounded-xl bg-accent px-8 py-3.5 text-lg font-bold text-primary shadow-lg shadow-accent/20 transition-all hover:bg-accent-hover">
                        Get Started Free
                    </a>
                    <a href="{{ route('login') }}" class="rounded-xl border border-border px-8 py-3.5 text-lg font-semibold text-primary transition-colors hover:bg-surface">
                        Sign In
                    </a>
                @endauth
            </div>
        </div>
    </section>

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
