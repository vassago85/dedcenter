<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DeadCenter — Multi-Discipline Shooting Scoring Platform</title>
    <meta name="description" content="A modern scoring platform for shooting sports. Capture scores offline on tablets, sync across devices, and publish live results for shooters, spectators, and organizers.">
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
                <a href="{{ route('scoring') }}" class="hover:text-primary transition-colors duration-200">Scoring</a>
                <a href="{{ route('offline') }}" class="hover:text-primary transition-colors duration-200">Offline</a>
                <a href="{{ route('setup') }}" class="hover:text-primary transition-colors duration-200">Setup</a>
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

        <div class="pointer-events-none absolute inset-y-0 right-0 hidden lg:block w-[45%] overflow-hidden" aria-hidden="true">
            <div class="absolute top-24 right-12 xl:right-24 space-y-3 opacity-[0.07]">
                <div class="w-72 rounded-xl border border-primary/20 bg-primary/5 p-4 backdrop-blur-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <div class="h-2 w-20 rounded bg-primary/40"></div>
                        <div class="h-2 w-8 rounded bg-accent/60"></div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3"><div class="h-2 w-2 rounded-full bg-accent"></div><div class="h-1.5 w-32 rounded bg-primary/30"></div><div class="ml-auto h-1.5 w-8 rounded bg-primary/20"></div></div>
                        <div class="flex items-center gap-3"><div class="h-2 w-2 rounded-full bg-primary/30"></div><div class="h-1.5 w-28 rounded bg-primary/20"></div><div class="ml-auto h-1.5 w-8 rounded bg-primary/15"></div></div>
                        <div class="flex items-center gap-3"><div class="h-2 w-2 rounded-full bg-primary/30"></div><div class="h-1.5 w-24 rounded bg-primary/15"></div><div class="ml-auto h-1.5 w-8 rounded bg-primary/10"></div></div>
                        <div class="flex items-center gap-3"><div class="h-2 w-2 rounded-full bg-primary/20"></div><div class="h-1.5 w-30 rounded bg-primary/10"></div><div class="ml-auto h-1.5 w-8 rounded bg-primary/10"></div></div>
                    </div>
                </div>
                <div class="ml-8 w-64 rounded-xl border border-primary/20 bg-primary/5 p-4 backdrop-blur-sm">
                    <div class="mb-3 h-2 w-16 rounded bg-primary/30"></div>
                    <div class="grid grid-cols-5 gap-1.5">
                        <div class="h-5 rounded bg-green-500/30"></div>
                        <div class="h-5 rounded bg-green-500/30"></div>
                        <div class="h-5 rounded bg-accent/25"></div>
                        <div class="h-5 rounded bg-green-500/30"></div>
                        <div class="h-5 rounded bg-primary/10"></div>
                        <div class="h-5 rounded bg-green-500/30"></div>
                        <div class="h-5 rounded bg-green-500/30"></div>
                        <div class="h-5 rounded bg-green-500/30"></div>
                        <div class="h-5 rounded bg-accent/25"></div>
                        <div class="h-5 rounded bg-green-500/30"></div>
                    </div>
                </div>
                <div class="w-72 rounded-xl border border-primary/20 bg-primary/5 p-4 backdrop-blur-sm">
                    <div class="flex items-center justify-between">
                        <div class="text-center"><div class="h-3 w-6 mx-auto rounded bg-accent/40 mb-1"></div><div class="h-1.5 w-10 rounded bg-primary/20"></div></div>
                        <div class="text-center"><div class="h-3 w-8 mx-auto rounded bg-primary/25 mb-1"></div><div class="h-1.5 w-10 rounded bg-primary/20"></div></div>
                        <div class="text-center"><div class="h-3 w-5 mx-auto rounded bg-primary/20 mb-1"></div><div class="h-1.5 w-10 rounded bg-primary/20"></div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="relative mx-auto max-w-6xl px-6 pt-28 pb-20 sm:pt-36 sm:pb-28 lg:pt-44 lg:pb-32">
            <div class="mx-auto max-w-2xl text-center">
                <div class="mb-8 inline-flex items-center gap-2 rounded-full border border-border/50 bg-surface/30 px-4 py-1.5 text-[13px] font-medium text-muted backdrop-blur-sm">
                    <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
                    Multi-Discipline Shooting Scoring
                </div>

                <h1 class="text-[2.75rem] font-black leading-[1.08] tracking-tight sm:text-6xl lg:text-7xl">
                    Run Every Match.<br>
                    Score Every <span class="text-accent">Shot.</span>
                </h1>

                <p class="mx-auto mt-7 max-w-lg text-[1.05rem] leading-relaxed text-muted">
                    A modern scoring platform for shooting sports. Capture scores offline on tablets, sync across devices, and publish live results for shooters, spectators, and organizers.
                </p>

                <div class="mt-10 flex flex-col items-center gap-3.5 sm:flex-row sm:justify-center">
                    @auth
                        <a href="{{ route('dashboard') }}"
                           class="group relative inline-flex items-center justify-center rounded-xl bg-accent px-8 py-3.5 text-[15px] font-semibold text-primary shadow-[0_1px_2px_rgba(0,0,0,0.3),0_8px_24px_rgba(255,43,43,0.2)] transition-all duration-200 hover:bg-accent-hover hover:shadow-[0_1px_2px_rgba(0,0,0,0.3),0_12px_32px_rgba(255,43,43,0.35)]">
                            Go to Dashboard
                        </a>
                    @else
                        <a href="{{ route('register') }}"
                           class="group relative inline-flex items-center justify-center rounded-xl bg-accent px-8 py-3.5 text-[15px] font-semibold text-primary shadow-[0_1px_2px_rgba(0,0,0,0.3),0_8px_24px_rgba(255,43,43,0.2)] transition-all duration-200 hover:bg-accent-hover hover:shadow-[0_1px_2px_rgba(0,0,0,0.3),0_12px_32px_rgba(255,43,43,0.35)]">
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
                        Built for clubs and federations
                    </div>
                </div>
            </div>
        </div>

        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-border/50 to-transparent"></div>
    </section>

    <section class="border-t border-border bg-surface/50">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-12 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">Built for the Range</h2>
                <p class="mt-3 text-muted max-w-xl mx-auto">Everything you need to run a smooth match &mdash; from setup to final standings.</p>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-border bg-surface p-6">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-amber-600/10">
                        <svg class="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" /></svg>
                    </div>
                    <h3 class="mb-1 font-semibold text-primary">Offline-First Scoring</h3>
                    <p class="text-sm text-muted leading-relaxed">No signal at the range? Scores save locally and sync when connectivity returns.</p>
                </div>
                <div class="rounded-xl border border-border bg-surface p-6">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-accent/10">
                        <svg class="h-5 w-5 text-accent" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" /></svg>
                    </div>
                    <h3 class="mb-1 font-semibold text-primary">Live Scoreboards</h3>
                    <p class="text-sm text-muted leading-relaxed">TV scoreboard and mobile-friendly live page with QR code sharing.</p>
                </div>
                <div class="rounded-xl border border-border bg-surface p-6">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500/10">
                        <svg class="h-5 w-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" /></svg>
                    </div>
                    <h3 class="mb-1 font-semibold text-primary">PRS &amp; Standard Scoring</h3>
                    <p class="text-sm text-muted leading-relaxed">Hit/Miss/Not Taken for PRS. Gong multipliers for standard matches.</p>
                </div>
                <div class="rounded-xl border border-border bg-surface p-6">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600/10">
                        <svg class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" /></svg>
                    </div>
                    <h3 class="mb-1 font-semibold text-primary">Divisions &amp; Categories</h3>
                    <p class="text-sm text-muted leading-relaxed">Equipment classes and demographic groups for leaderboards.</p>
                </div>
                <div class="rounded-xl border border-border bg-surface p-6">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-green-600/10">
                        <svg class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                    </div>
                    <h3 class="mb-1 font-semibold text-primary">Leagues &amp; Clubs</h3>
                    <p class="text-sm text-muted leading-relaxed">Season leaderboards with best-of-N scoring across matches.</p>
                </div>
                <div class="rounded-xl border border-border bg-surface p-6">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600/10">
                        <svg class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" /></svg>
                    </div>
                    <h3 class="mb-1 font-semibold text-primary">Multi-Device Sync</h3>
                    <p class="text-sm text-muted leading-relaxed">Multiple ROs score simultaneously, merging in real-time.</p>
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
        <div class="mx-auto max-w-6xl px-6 py-20 text-center">
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
