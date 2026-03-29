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
<body class="min-h-screen bg-[#0a0a0f] text-white antialiased">

    {{-- Nav --}}
    <nav class="sticky top-0 z-50 border-b border-white/[0.06] bg-[#0a0a0f]/80 backdrop-blur-xl">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <a href="/" class="group opacity-90 hover:opacity-100 transition-opacity">
                <x-app-logo size="md" />
            </a>
            <div class="hidden md:flex items-center gap-7 text-[13px] font-medium text-slate-500">
                <a href="#features" class="hover:text-white transition-colors duration-200">Features</a>
                <a href="#scoring-modes" class="hover:text-white transition-colors duration-200">Scoring</a>
                <a href="#offline" class="hover:text-white transition-colors duration-200">Offline</a>
                <a href="#setup" class="hover:text-white transition-colors duration-200">Setup</a>
            </div>
            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-500">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-400 transition-colors hover:text-white">
                        Sign In
                    </a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-500">
                        Get Started
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    {{-- Hero --}}
    <section class="relative isolate overflow-hidden">
        {{-- Background treatment --}}
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute inset-0 bg-gradient-to-b from-red-950/10 via-transparent to-transparent"></div>
            <div class="absolute top-0 left-1/2 -translate-x-1/2 h-[600px] w-[900px] rounded-full bg-red-600/[0.04] blur-[120px]"></div>
        </div>

        {{-- Abstract data visual (right-side ambient element) --}}
        <div class="pointer-events-none absolute inset-y-0 right-0 hidden lg:block w-[45%] overflow-hidden" aria-hidden="true">
            <div class="absolute top-24 right-12 xl:right-24 space-y-3 opacity-[0.07]">
                {{-- Leaderboard card --}}
                <div class="w-72 rounded-xl border border-white/20 bg-white/5 p-4 backdrop-blur-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <div class="h-2 w-20 rounded bg-white/40"></div>
                        <div class="h-2 w-8 rounded bg-red-500/60"></div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3"><div class="h-2 w-2 rounded-full bg-red-500"></div><div class="h-1.5 w-32 rounded bg-white/30"></div><div class="ml-auto h-1.5 w-8 rounded bg-white/20"></div></div>
                        <div class="flex items-center gap-3"><div class="h-2 w-2 rounded-full bg-white/30"></div><div class="h-1.5 w-28 rounded bg-white/20"></div><div class="ml-auto h-1.5 w-8 rounded bg-white/15"></div></div>
                        <div class="flex items-center gap-3"><div class="h-2 w-2 rounded-full bg-white/30"></div><div class="h-1.5 w-24 rounded bg-white/15"></div><div class="ml-auto h-1.5 w-8 rounded bg-white/10"></div></div>
                        <div class="flex items-center gap-3"><div class="h-2 w-2 rounded-full bg-white/20"></div><div class="h-1.5 w-30 rounded bg-white/10"></div><div class="ml-auto h-1.5 w-8 rounded bg-white/10"></div></div>
                    </div>
                </div>
                {{-- Score grid card --}}
                <div class="ml-8 w-64 rounded-xl border border-white/20 bg-white/5 p-4 backdrop-blur-sm">
                    <div class="mb-3 h-2 w-16 rounded bg-white/30"></div>
                    <div class="grid grid-cols-5 gap-1.5">
                        <div class="h-5 rounded bg-green-500/30"></div>
                        <div class="h-5 rounded bg-green-500/30"></div>
                        <div class="h-5 rounded bg-red-500/25"></div>
                        <div class="h-5 rounded bg-green-500/30"></div>
                        <div class="h-5 rounded bg-white/10"></div>
                        <div class="h-5 rounded bg-green-500/30"></div>
                        <div class="h-5 rounded bg-green-500/30"></div>
                        <div class="h-5 rounded bg-green-500/30"></div>
                        <div class="h-5 rounded bg-red-500/25"></div>
                        <div class="h-5 rounded bg-green-500/30"></div>
                    </div>
                </div>
                {{-- Stats strip --}}
                <div class="w-72 rounded-xl border border-white/20 bg-white/5 p-4 backdrop-blur-sm">
                    <div class="flex items-center justify-between">
                        <div class="text-center"><div class="h-3 w-6 mx-auto rounded bg-red-500/40 mb-1"></div><div class="h-1.5 w-10 rounded bg-white/20"></div></div>
                        <div class="text-center"><div class="h-3 w-8 mx-auto rounded bg-white/25 mb-1"></div><div class="h-1.5 w-10 rounded bg-white/20"></div></div>
                        <div class="text-center"><div class="h-3 w-5 mx-auto rounded bg-white/20 mb-1"></div><div class="h-1.5 w-10 rounded bg-white/20"></div></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Hero content --}}
        <div class="relative mx-auto max-w-6xl px-6 pt-28 pb-20 sm:pt-36 sm:pb-28 lg:pt-44 lg:pb-32">
            <div class="mx-auto max-w-2xl text-center">

                {{-- Eyebrow pill --}}
                <div class="mb-8 inline-flex items-center gap-2 rounded-full border border-white/[0.08] bg-white/[0.03] px-4 py-1.5 text-[13px] font-medium text-slate-400 backdrop-blur-sm">
                    <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                    Multi-Discipline Shooting Scoring
                </div>

                {{-- Headline --}}
                <h1 class="text-[2.75rem] font-black leading-[1.08] tracking-tight sm:text-6xl lg:text-7xl">
                    Run Every Match.<br>
                    Score Every <span class="text-red-500">Shot.</span>
                </h1>

                {{-- Supporting text --}}
                <p class="mx-auto mt-7 max-w-lg text-[1.05rem] leading-relaxed text-slate-400">
                    A modern scoring platform for shooting sports. Capture scores offline, sync across devices, and publish live results for shooters, spectators, and organizers.
                </p>

                {{-- CTA row --}}
                <div class="mt-10 flex flex-col items-center gap-3.5 sm:flex-row sm:justify-center">
                    @auth
                        <a href="{{ route('dashboard') }}"
                           class="group relative inline-flex items-center justify-center rounded-xl bg-red-600 px-8 py-3.5 text-[15px] font-semibold text-white shadow-[0_1px_2px_rgba(0,0,0,0.3),0_8px_24px_rgba(220,38,38,0.25)] transition-all duration-200 hover:bg-red-500 hover:shadow-[0_1px_2px_rgba(0,0,0,0.3),0_12px_32px_rgba(220,38,38,0.35)]">
                            Go to Dashboard
                        </a>
                    @else
                        <a href="{{ route('register') }}"
                           class="group relative inline-flex items-center justify-center rounded-xl bg-red-600 px-8 py-3.5 text-[15px] font-semibold text-white shadow-[0_1px_2px_rgba(0,0,0,0.3),0_8px_24px_rgba(220,38,38,0.25)] transition-all duration-200 hover:bg-red-500 hover:shadow-[0_1px_2px_rgba(0,0,0,0.3),0_12px_32px_rgba(220,38,38,0.35)]">
                            Get Started Free
                        </a>
                        <a href="{{ route('login') }}"
                           class="inline-flex items-center justify-center rounded-xl border border-white/[0.1] bg-white/[0.03] px-8 py-3.5 text-[15px] font-semibold text-slate-300 backdrop-blur-sm transition-all duration-200 hover:border-white/[0.18] hover:bg-white/[0.06] hover:text-white">
                            Sign In
                        </a>
                    @endauth
                </div>

                {{-- Feature row --}}
                <div class="mt-14 flex flex-col items-center gap-6 sm:flex-row sm:justify-center sm:gap-10">
                    <div class="flex items-center gap-2.5 text-[13px] text-slate-500">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-white/[0.06]">
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500/70"></span>
                        </span>
                        Offline scoring on tablets
                    </div>
                    <div class="flex items-center gap-2.5 text-[13px] text-slate-500">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-white/[0.06]">
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500/70"></span>
                        </span>
                        Live synced leaderboards
                    </div>
                    <div class="flex items-center gap-2.5 text-[13px] text-slate-500">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-white/[0.06]">
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500/70"></span>
                        </span>
                        Built for clubs and federations
                    </div>
                </div>

            </div>
        </div>

        {{-- Bottom fade --}}
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-white/[0.06] to-transparent"></div>
    </section>

    {{-- Features Grid --}}
    <section id="features" class="border-t border-slate-800 bg-slate-900/50">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-16 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">Built for the Range</h2>
                <p class="mt-3 text-slate-400 max-w-xl mx-auto">Everything you need to run a smooth match &mdash; from setup to final standings.</p>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">

                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-8">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-amber-600/10">
                        <svg class="h-6 w-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold">Offline-First Scoring</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">No signal at the range? No problem. Scores are saved locally on the device and sync automatically when connectivity returns.</p>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-8">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-red-600/10">
                        <svg class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold">Live Scoreboards</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">TV scoreboard for the range, plus a mobile-friendly live page spectators can open by scanning a QR code. Auto-refreshes every 10 seconds.</p>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-8">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/10">
                        <svg class="h-6 w-6 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold">PRS Scoring</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">Hit / Miss / Shot Not Taken buttons for each target. Timed stages with smart decimal input, tiebreaker stage support, and par time auto-fill.</p>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-8">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600/10">
                        <svg class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold">Divisions &amp; Categories</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">Split shooters by equipment class (Open, Factory, Limited) and demographics (Overall, Ladies, Junior, Senior). Filter scoreboards by either axis.</p>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-8">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-green-600/10">
                        <svg class="h-6 w-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold">Leagues &amp; Clubs</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">Create a league, add clubs underneath it. Season leaderboards aggregate scores across matches with best-of-N scoring.</p>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-8">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-purple-600/10">
                        <svg class="h-6 w-6 text-purple-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold">Match Director Control</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">Only the person who created a match can edit or delete it. Multiple admins per club, but each match director owns their match.</p>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-8">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-green-600/10">
                        <svg class="h-6 w-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold">QR Code Sharing</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">A QR code is generated for every active match. Print it or show it on screen &mdash; spectators scan it and get the live scoreboard on their phone.</p>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-8">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600/10">
                        <svg class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold">Multi-Device Sync</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">Multiple Range Officers can score simultaneously on different tablets. All scores merge on the server and appear on the scoreboard in real-time.</p>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-8">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-600/10">
                        <svg class="h-6 w-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold">Match Registration &amp; Fees</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">Shooters register for matches online. Set entry fees, approve/reject registrations, and track payments. Free matches are supported too.</p>
                </div>

                <div class="rounded-2xl border border-amber-800/30 bg-slate-900 p-8 ring-1 ring-amber-600/10">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-amber-600/10">
                        <svg class="h-6 w-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0 1 16.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.003 6.003 0 0 1-3.77 1.522m0 0a6.003 6.003 0 0 1-3.77-1.522" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold">Side Bet (Royal Flush)</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">Optional side competition for round-robin matches. The winner is whoever hits the most smallest gongs. Ties break by furthest distance, then cascade to the next gong size.</p>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-8">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-red-600/10">
                        <svg class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold">Scoring Security &amp; Squad Lock</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">Only authorized users can submit scores. The Match Director logs into each tablet and locks it to a specific squad to prevent accidental edits on other squads.</p>
                </div>

            </div>
        </div>
    </section>

    {{-- Scoring Modes --}}
    <section id="scoring-modes" class="border-t border-slate-800">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-16 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">Two Scoring Modes</h2>
                <p class="mt-3 text-slate-400 max-w-xl mx-auto">Choose the right format for your match.</p>
            </div>
            <div class="grid gap-8 lg:grid-cols-2">

                {{-- Standard --}}
                <div class="rounded-2xl border border-slate-700 bg-slate-900 p-8 lg:p-10">
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-red-600/10 border border-red-800/30 px-4 py-1.5 text-sm font-semibold text-red-400">
                        Standard Scoring
                    </div>
                    <h3 class="mb-3 text-xl font-bold">Gong Multiplier System</h3>
                    <p class="mb-6 text-sm text-slate-400 leading-relaxed">
                        Each gong has a point multiplier based on size and difficulty. Shooters rotate through gongs in relay order.
                        Range Officers tap HIT or MISS for each shooter at each gong. The scorer auto-advances through the relay sequence.
                    </p>
                    <ul class="space-y-2 text-sm text-slate-400">
                        <li class="flex items-start gap-2">
                            <span class="mt-1 text-green-500">&#10003;</span>
                            Gongs with custom multipliers (e.g. 2.5 MOA = 1.0x, 0.5 MOA = 2.0x)
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-1 text-green-500">&#10003;</span>
                            Round-robin relay scoring flow
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-1 text-green-500">&#10003;</span>
                            Score = sum of multipliers for successful hits
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-1 text-green-500">&#10003;</span>
                            Quick-add presets: 5 standard MOA targets
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-1 text-amber-500">&#10003;</span>
                            Optional <strong class="text-amber-400">Side Bet</strong>: rank by smallest gong hits with distance tiebreaker
                        </li>
                    </ul>
                </div>

                {{-- PRS --}}
                <div class="rounded-2xl border border-amber-800/30 bg-slate-900 p-8 lg:p-10 ring-1 ring-amber-600/10">
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-amber-600/10 border border-amber-800/30 px-4 py-1.5 text-sm font-semibold text-amber-400">
                        PRS Scoring
                    </div>
                    <h3 class="mb-3 text-xl font-bold">Hit / Miss / Shot Not Taken</h3>
                    <p class="mb-6 text-sm text-slate-400 leading-relaxed">
                        Each shooter completes an entire stage at once. Every target has three state buttons: <strong class="text-green-400">Hit</strong>,
                        <strong class="text-red-400">Miss</strong>, or <strong class="text-amber-400">Shot Not Taken</strong> (the default).
                        If a shooter runs out of time, remaining targets stay as "shot not taken."
                    </p>
                    <ul class="space-y-2 text-sm text-slate-400">
                        <li class="flex items-start gap-2">
                            <span class="mt-1 text-green-500">&#10003;</span>
                            Three-button scoring per target (Hit / Miss / Not Taken)
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-1 text-green-500">&#10003;</span>
                            Timed stages with app timer or smart manual input
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-1 text-green-500">&#10003;</span>
                            Tiebreaker stage: impacts first, then time
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-1 text-green-500">&#10003;</span>
                            Par time auto-fill when not all targets are engaged
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-1 text-green-500">&#10003;</span>
                            Smart time input: type "105" and it reads as 1.05 seconds
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </section>

    {{-- Divisions & Categories --}}
    <section class="border-t border-slate-800 bg-slate-900/50">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-16 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">Divisions &amp; Categories</h2>
                <p class="mt-3 text-slate-400 max-w-2xl mx-auto">Two independent axes for slicing leaderboards. Together they form a matrix so you can view standings for any combination.</p>
            </div>
            <div class="grid gap-8 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-700 bg-slate-900 p-8">
                    <h3 class="mb-1 text-lg font-bold text-red-400">Divisions</h3>
                    <p class="mb-4 text-xs text-slate-500 uppercase tracking-wider">What gear class are you competing in?</p>
                    <p class="mb-4 text-sm text-slate-400 leading-relaxed">
                        Divisions classify competitors by equipment. Each shooter selects <strong class="text-white">one division</strong> per match (single-select).
                    </p>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3 rounded-lg bg-slate-800/50 px-4 py-2">
                            <span class="rounded bg-red-600/20 px-2 py-0.5 text-xs font-bold text-red-400">Open</span>
                            <span class="text-sm text-slate-400">Unrestricted equipment</span>
                        </div>
                        <div class="flex items-center gap-3 rounded-lg bg-slate-800/50 px-4 py-2">
                            <span class="rounded bg-red-600/20 px-2 py-0.5 text-xs font-bold text-red-400">Factory</span>
                            <span class="text-sm text-slate-400">Factory-stock rifle, no mods</span>
                        </div>
                        <div class="flex items-center gap-3 rounded-lg bg-slate-800/50 px-4 py-2">
                            <span class="rounded bg-red-600/20 px-2 py-0.5 text-xs font-bold text-red-400">Limited</span>
                            <span class="text-sm text-slate-400">Limited modifications allowed</span>
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-slate-600">Presets included or create your own (e.g. Minor / Major by calibre).</p>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-900 p-8">
                    <h3 class="mb-1 text-lg font-bold text-blue-400">Categories</h3>
                    <p class="mb-4 text-xs text-slate-500 uppercase tracking-wider">What demographic group(s) do you belong to?</p>
                    <p class="mb-4 text-sm text-slate-400 leading-relaxed">
                        Categories classify competitors by who they are. A shooter can belong to <strong class="text-white">multiple categories</strong> (multi-select).
                        A single score appears in all matching category leaderboards.
                    </p>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3 rounded-lg bg-slate-800/50 px-4 py-2">
                            <span class="rounded bg-blue-600/20 px-2 py-0.5 text-xs font-bold text-blue-400">Overall</span>
                            <span class="text-sm text-slate-400">All shooters &mdash; the default</span>
                        </div>
                        <div class="flex items-center gap-3 rounded-lg bg-slate-800/50 px-4 py-2">
                            <span class="rounded bg-blue-600/20 px-2 py-0.5 text-xs font-bold text-blue-400">Ladies</span>
                            <span class="text-sm text-slate-400">Female shooters</span>
                        </div>
                        <div class="flex items-center gap-3 rounded-lg bg-slate-800/50 px-4 py-2">
                            <span class="rounded bg-blue-600/20 px-2 py-0.5 text-xs font-bold text-blue-400">Junior</span>
                            <span class="text-sm text-slate-400">Under 21 (centrefire) / Under 18 (rimfire) as of 1 Jan</span>
                        </div>
                        <div class="flex items-center gap-3 rounded-lg bg-slate-800/50 px-4 py-2">
                            <span class="rounded bg-blue-600/20 px-2 py-0.5 text-xs font-bold text-blue-400">Senior</span>
                            <span class="text-sm text-slate-400">55+</span>
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-slate-600">Standard presets included or create your own.</p>
                </div>
            </div>
            <div class="mt-10 rounded-2xl border border-slate-700 bg-slate-800/50 p-6 lg:p-8">
                <h4 class="mb-3 text-center font-semibold text-white">Leaderboard Matrix</h4>
                <p class="mb-5 text-center text-sm text-slate-400">Filter by division, category, or both.</p>
                <div class="overflow-x-auto">
                    <table class="mx-auto text-sm">
                        <thead>
                            <tr>
                                <th class="px-4 py-2"></th>
                                <th class="px-4 py-2 text-center text-red-400 font-semibold">Open</th>
                                <th class="px-4 py-2 text-center text-red-400 font-semibold">Factory</th>
                                <th class="px-4 py-2 text-center text-red-400 font-semibold">Limited</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(['Overall', 'Ladies', 'Junior', 'Senior'] as $cat)
                                <tr>
                                    <td class="px-4 py-2 text-blue-400 font-semibold">{{ $cat }}</td>
                                    @for($i = 0; $i < 3; $i++)
                                        <td class="px-4 py-2 text-center"><span class="inline-block h-4 w-4 rounded bg-green-600/30 text-[10px] leading-4 text-green-400">&#10003;</span></td>
                                    @endfor
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    {{-- How Offline Scoring Works --}}
    <section id="offline" class="border-t border-slate-800">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-16 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">How Offline Scoring Works</h2>
                <p class="mt-3 text-slate-400 max-w-2xl mx-auto">Ranges often have poor or no mobile signal. DeadCenter is built from the ground up to handle this.</p>
            </div>
            <div class="grid gap-8 lg:grid-cols-2">
                <div class="space-y-6">
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-full bg-amber-600 text-sm font-black">1</div>
                        <div>
                            <h4 class="font-semibold text-white">Load the match while online</h4>
                            <p class="mt-1 text-sm text-slate-400">Before heading to the range, open the scoring app and select your match. The full match data (target sets, gongs, squads, shooters, existing scores) is downloaded and cached locally in the browser&rsquo;s IndexedDB.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-full bg-amber-600 text-sm font-black">2</div>
                        <div>
                            <h4 class="font-semibold text-white">Score offline &mdash; everything saves locally</h4>
                            <p class="mt-1 text-sm text-slate-400">Every tap of Hit, Miss, or Shot Not Taken is instantly saved to the device&rsquo;s local database. Stage times are saved locally too. The app works exactly the same whether you&rsquo;re online or offline &mdash; no loading spinners, no errors.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-full bg-amber-600 text-sm font-black">3</div>
                        <div>
                            <h4 class="font-semibold text-white">Auto-sync when signal returns</h4>
                            <p class="mt-1 text-sm text-slate-400">A background sync runs every 15 seconds. When connectivity is detected, all unsynced scores and stage times are batched and sent to the server in a single API call. The server uses upsert logic so duplicate submissions are harmless.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-full bg-amber-600 text-sm font-black">4</div>
                        <div>
                            <h4 class="font-semibold text-white">Scoreboards update live</h4>
                            <p class="mt-1 text-sm text-slate-400">Once scores reach the server, the TV scoreboard and mobile live page pick them up on their next 10-second refresh cycle. Spectators see results appear in real-time as devices sync.</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-900 p-8">
                    <h4 class="mb-4 font-semibold text-white">Under the Hood</h4>
                    <div class="space-y-4 text-sm text-slate-400">
                        <div class="rounded-lg bg-slate-800 p-4">
                            <p class="mb-1 text-xs font-bold text-amber-400 uppercase tracking-wider">Local Storage</p>
                            <p>Scores are persisted in <strong class="text-white">IndexedDB</strong> via Dexie.js. Each score is keyed by (shooterId, gongId) so re-tapping the same target overwrites rather than duplicates.</p>
                        </div>
                        <div class="rounded-lg bg-slate-800 p-4">
                            <p class="mb-1 text-xs font-bold text-amber-400 uppercase tracking-wider">Sync Protocol</p>
                            <p>The sync payload includes <strong class="text-white">scores</strong>, <strong class="text-white">stage_times</strong>, and <strong class="text-white">deleted_scores</strong> (for Shot Not Taken reversals). The server processes deletions first, then upserts.</p>
                        </div>
                        <div class="rounded-lg bg-slate-800 p-4">
                            <p class="mb-1 text-xs font-bold text-amber-400 uppercase tracking-wider">Conflict Resolution</p>
                            <p>Last-write-wins. Each score carries a <strong class="text-white">device_id</strong> and <strong class="text-white">recorded_at</strong> timestamp. Multiple devices can score different stages simultaneously without conflict.</p>
                        </div>
                        <div class="rounded-lg bg-slate-800 p-4">
                            <p class="mb-1 text-xs font-bold text-amber-400 uppercase tracking-wider">Pending Counter</p>
                            <p>A badge in the scoring app header shows how many unsynced items exist. Tap "Sync" to force an immediate upload, or let it happen automatically.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Setup Guide --}}
    <section id="setup" class="border-t border-slate-800 bg-slate-900/50">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-16 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">Setting Up Your First Match</h2>
                <p class="mt-3 text-slate-400 max-w-xl mx-auto">A step-by-step guide from account creation to live scoring.</p>
            </div>
            <div class="mx-auto max-w-3xl space-y-8">

                <div class="flex gap-5">
                    <div class="flex-shrink-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-600 text-sm font-black">1</div>
                    </div>
                    <div>
                        <h4 class="font-semibold text-lg text-white">Create an account &amp; organisation</h4>
                        <p class="mt-1 text-sm text-slate-400 leading-relaxed">
                            Register for a free account, then create your club or league from the "Organisations" page.
                            Your organisation is submitted for approval &mdash; once approved, you&rsquo;re the owner and can invite other admins.
                        </p>
                    </div>
                </div>

                <div class="flex gap-5">
                    <div class="flex-shrink-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-600 text-sm font-black">2</div>
                    </div>
                    <div>
                        <h4 class="font-semibold text-lg text-white">Create a match</h4>
                        <p class="mt-1 text-sm text-slate-400 leading-relaxed">
                            Go to your org dashboard, click <strong class="text-white">New Match</strong>, and fill in the name, date, location, and entry fee.
                            Choose <strong class="text-white">Standard</strong> for multiplier-based scoring or <strong class="text-white">PRS</strong> for hit/miss/timed scoring.
                        </p>
                    </div>
                </div>

                <div class="flex gap-5">
                    <div class="flex-shrink-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-600 text-sm font-black">3</div>
                    </div>
                    <div>
                        <h4 class="font-semibold text-lg text-white">Set up divisions &amp; categories (optional)</h4>
                        <p class="mt-1 text-sm text-slate-400 leading-relaxed">
                            Add divisions for equipment classes (use the Open/Factory/Limited or Minor/Major presets, or create your own).
                            Add categories for demographics (use the standard preset for Overall/Ladies/Junior/Senior, or create custom ones).
                            Both are optional &mdash; without them, all shooters compete in a single pool.
                        </p>
                    </div>
                </div>

                <div class="flex gap-5">
                    <div class="flex-shrink-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-600 text-sm font-black">4</div>
                    </div>
                    <div>
                        <h4 class="font-semibold text-lg text-white">Add target sets &amp; targets</h4>
                        <p class="mt-1 text-sm text-slate-400 leading-relaxed">
                            Add one target set per stage (e.g. "100m", "200m"). Inside each, add targets (gongs) with multipliers for standard, or use the
                            quick-add PRS preset buttons (5, 8, or 10 targets at 1pt each). For PRS, you can set a <strong class="text-white">par time</strong> per stage
                            and designate one stage as the <strong class="text-white">tiebreaker</strong> (impacts first, then time).
                        </p>
                    </div>
                </div>

                <div class="flex gap-5">
                    <div class="flex-shrink-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-600 text-sm font-black">5</div>
                    </div>
                    <div>
                        <h4 class="font-semibold text-lg text-white">Add squads &amp; shooters</h4>
                        <p class="mt-1 text-sm text-slate-400 leading-relaxed">
                            Create squads (e.g. "Squad A", "Squad B") and add shooters to each. Assign each shooter a division (single-select dropdown)
                            and categories (multi-select checkboxes). Bib numbers are optional.
                        </p>
                    </div>
                </div>

                <div class="flex gap-5">
                    <div class="flex-shrink-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-600 text-sm font-black">6</div>
                    </div>
                    <div>
                        <h4 class="font-semibold text-lg text-white">Start the match &amp; share the live link</h4>
                        <p class="mt-1 text-sm text-slate-400 leading-relaxed">
                            Click <strong class="text-white">Start Match</strong>. A QR code and shareable link appear &mdash; spectators scan it to follow live on their phones.
                            Put the TV scoreboard URL on a big screen at the range if you have one.
                        </p>
                    </div>
                </div>

                <div class="flex gap-5">
                    <div class="flex-shrink-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-600 text-sm font-black">7</div>
                    </div>
                    <div>
                        <h4 class="font-semibold text-lg text-white">Score on tablets</h4>
                        <p class="mt-1 text-sm text-slate-400 leading-relaxed">
                            Range Officers open <strong class="text-white">/score</strong> on their tablets, select the active match, and start scoring.
                            For Standard: tap HIT/MISS per gong per shooter in relay order.
                            For PRS: tap Hit/Miss/Not Taken per target for each shooter per stage, enter the stage time, and complete the stage.
                            Everything works offline &mdash; scores sync when signal is available.
                        </p>
                    </div>
                </div>

                <div class="flex gap-5">
                    <div class="flex-shrink-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-600 text-sm font-black">8</div>
                    </div>
                    <div>
                        <h4 class="font-semibold text-lg text-white">Complete the match</h4>
                        <p class="mt-1 text-sm text-slate-400 leading-relaxed">
                            Once all stages are done and scores are synced, click <strong class="text-white">Complete Match</strong>. The match moves to completed status.
                            Scores count toward the season leaderboard. You can reopen a match if needed.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="border-t border-slate-800">
        <div class="mx-auto max-w-6xl px-6 py-20 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">Ready to Score?</h2>
            <p class="mx-auto mt-3 max-w-md text-slate-400">Set up your first match in minutes. Free to use.</p>
            <div class="mt-8 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-xl bg-red-600 px-8 py-3.5 text-lg font-bold text-white shadow-lg shadow-red-600/25 transition-all hover:bg-red-700">
                        Go to Dashboard
                    </a>
                @else
                    <a href="{{ route('register') }}" class="rounded-xl bg-red-600 px-8 py-3.5 text-lg font-bold text-white shadow-lg shadow-red-600/25 transition-all hover:bg-red-700">
                        Get Started Free
                    </a>
                    <a href="{{ route('login') }}" class="rounded-xl border border-slate-700 px-8 py-3.5 text-lg font-semibold text-white transition-colors hover:bg-slate-800">
                        Sign In
                    </a>
                @endauth
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-white/[0.06] bg-[#0a0a0f]">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
            <div class="flex items-center gap-2">
                <x-app-logo size="sm" class="opacity-40" />
                <span class="text-xs text-slate-600">&copy; {{ date('Y') }}</span>
            </div>
            <span class="text-xs text-slate-600">deadcenter.co.za</span>
        </div>
    </footer>

</body>
</html>
