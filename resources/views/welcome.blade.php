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
<body class="min-h-screen antialiased" style="background: linear-gradient(180deg, var(--lp-bg) 0%, var(--lp-bg-2) 100%); color: var(--lp-text);">

    <nav class="sticky top-0 z-50" style="background: rgba(7, 19, 39, 0.85); border-bottom: 1px solid var(--lp-border); backdrop-filter: blur(20px) saturate(1.4);">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <a href="/" class="opacity-90 hover:opacity-100 transition-opacity">
                <x-app-logo size="md" variant="dark" />
            </a>
            <div class="hidden md:flex items-center gap-7 text-[13px] font-medium" style="color: var(--lp-text-muted);">
                <a href="{{ route('features') }}" class="transition-colors duration-200 hover:!text-white" style="color: var(--lp-text-muted);">Features</a>
                <a href="{{ route('scoring') }}" class="transition-colors duration-200 hover:!text-white" style="color: var(--lp-text-muted);">Scoring</a>
                <a href="{{ route('offline') }}" class="transition-colors duration-200 hover:!text-white" style="color: var(--lp-text-muted);">Offline</a>
                <a href="{{ route('setup') }}" class="transition-colors duration-200 hover:!text-white" style="color: var(--lp-text-muted);">Setup</a>
            </div>
            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ app_url('/dashboard') }}" class="rounded-lg px-5 py-2 text-sm font-semibold text-white transition-colors" style="background: var(--lp-red);" onmouseover="this.style.background='var(--lp-red-hover)'" onmouseout="this.style.background='var(--lp-red)'">
                        Dashboard
                    </a>
                @else
                    <a href="{{ app_url('/login') }}" class="rounded-lg px-4 py-2 text-sm font-medium transition-colors hover:!text-white" style="color: var(--lp-text-soft);">
                        Sign In
                    </a>
                    <a href="{{ app_url('/register') }}" class="rounded-lg px-5 py-2 text-sm font-semibold text-white transition-colors" style="background: var(--lp-red);" onmouseover="this.style.background='var(--lp-red-hover)'" onmouseout="this.style.background='var(--lp-red)'">
                        Get Started
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    {{-- Hero --}}
    <section class="relative isolate overflow-hidden">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute inset-0" style="background: radial-gradient(ellipse 70% 50% at 50% 0%, rgba(225, 6, 0, 0.06), transparent 70%);"></div>
            <div class="absolute top-0 left-1/2 -translate-x-1/2 h-[600px] w-[900px] rounded-full blur-[120px]" style="background: rgba(225, 6, 0, 0.03);"></div>
        </div>

        {{-- Ambient data visuals --}}
        <div class="pointer-events-none absolute inset-y-0 right-0 hidden lg:block w-[45%] overflow-hidden" aria-hidden="true">
            <div class="absolute top-24 right-12 xl:right-24 space-y-3 opacity-[0.07]">
                <div class="w-72 rounded-xl p-4 backdrop-blur-sm" style="border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.05);">
                    <div class="mb-3 flex items-center justify-between">
                        <div class="h-2 w-20 rounded bg-white/40"></div>
                        <div class="h-2 w-8 rounded" style="background: rgba(225, 6, 0, 0.6);"></div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3"><div class="h-2 w-2 rounded-full" style="background: var(--lp-red);"></div><div class="h-1.5 w-32 rounded bg-white/30"></div><div class="ml-auto h-1.5 w-8 rounded bg-white/20"></div></div>
                        <div class="flex items-center gap-3"><div class="h-2 w-2 rounded-full bg-white/30"></div><div class="h-1.5 w-28 rounded bg-white/20"></div><div class="ml-auto h-1.5 w-8 rounded bg-white/15"></div></div>
                        <div class="flex items-center gap-3"><div class="h-2 w-2 rounded-full bg-white/30"></div><div class="h-1.5 w-24 rounded bg-white/15"></div><div class="ml-auto h-1.5 w-8 rounded bg-white/10"></div></div>
                        <div class="flex items-center gap-3"><div class="h-2 w-2 rounded-full bg-white/20"></div><div class="h-1.5 w-30 rounded bg-white/10"></div><div class="ml-auto h-1.5 w-8 rounded bg-white/10"></div></div>
                    </div>
                </div>
                <div class="ml-8 w-64 rounded-xl p-4 backdrop-blur-sm" style="border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.05);">
                    <div class="mb-3 h-2 w-16 rounded bg-white/30"></div>
                    <div class="grid grid-cols-5 gap-1.5">
                        <div class="h-5 rounded bg-green-500/30"></div>
                        <div class="h-5 rounded bg-green-500/30"></div>
                        <div class="h-5 rounded" style="background: rgba(225, 6, 0, 0.25);"></div>
                        <div class="h-5 rounded bg-green-500/30"></div>
                        <div class="h-5 rounded bg-white/10"></div>
                        <div class="h-5 rounded bg-green-500/30"></div>
                        <div class="h-5 rounded bg-green-500/30"></div>
                        <div class="h-5 rounded bg-green-500/30"></div>
                        <div class="h-5 rounded" style="background: rgba(225, 6, 0, 0.25);"></div>
                        <div class="h-5 rounded bg-green-500/30"></div>
                    </div>
                </div>
                <div class="w-72 rounded-xl p-4 backdrop-blur-sm" style="border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.05);">
                    <div class="flex items-center justify-between">
                        <div class="text-center"><div class="h-3 w-6 mx-auto rounded mb-1" style="background: rgba(225, 6, 0, 0.4);"></div><div class="h-1.5 w-10 rounded bg-white/20"></div></div>
                        <div class="text-center"><div class="h-3 w-8 mx-auto rounded bg-white/25 mb-1"></div><div class="h-1.5 w-10 rounded bg-white/20"></div></div>
                        <div class="text-center"><div class="h-3 w-5 mx-auto rounded bg-white/20 mb-1"></div><div class="h-1.5 w-10 rounded bg-white/20"></div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="relative mx-auto max-w-6xl px-6 pt-28 pb-20 sm:pt-36 sm:pb-28 lg:pt-44 lg:pb-32">
            <div class="mx-auto max-w-2xl text-center">

                <div class="mb-8 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-[13px] font-medium backdrop-blur-sm" style="border: 1px solid var(--lp-border); background: var(--lp-surface); color: var(--lp-text-muted);">
                    <span class="h-1.5 w-1.5 rounded-full" style="background: var(--lp-red);"></span>
                    Multi-Discipline Shooting Scoring
                </div>

                <h1 class="text-[2.75rem] font-black leading-[1.08] tracking-tight sm:text-6xl lg:text-7xl" style="color: var(--lp-text);">
                    Run Every Match.<br>
                    Score Every <span style="color: var(--lp-red);">Shot.</span>
                </h1>

                <p class="mx-auto mt-7 max-w-lg text-[1.05rem] leading-relaxed" style="color: var(--lp-text-soft);">
                    A modern scoring platform for shooting sports. Capture scores offline on tablets, sync across devices, and publish live results for shooters, spectators, and organizers.
                </p>

                <div class="mt-10 flex flex-col items-center gap-3.5 sm:flex-row sm:justify-center">
                    @auth
                        <a href="{{ app_url('/dashboard') }}"
                           class="group relative inline-flex items-center justify-center rounded-xl px-8 py-3.5 text-[15px] font-semibold text-white transition-all duration-200"
                           style="background: var(--lp-red); box-shadow: 0 1px 2px rgba(0,0,0,0.3), 0 8px 24px rgba(225, 6, 0, 0.25);"
                           onmouseover="this.style.background='var(--lp-red-hover)'; this.style.boxShadow='0 1px 2px rgba(0,0,0,0.3), 0 12px 32px rgba(225, 6, 0, 0.35)';"
                           onmouseout="this.style.background='var(--lp-red)'; this.style.boxShadow='0 1px 2px rgba(0,0,0,0.3), 0 8px 24px rgba(225, 6, 0, 0.25)';">
                            Go to Dashboard
                        </a>
                    @else
                        <a href="{{ app_url('/register') }}"
                           class="group relative inline-flex items-center justify-center rounded-xl px-8 py-3.5 text-[15px] font-semibold text-white transition-all duration-200"
                           style="background: var(--lp-red); box-shadow: 0 1px 2px rgba(0,0,0,0.3), 0 8px 24px rgba(225, 6, 0, 0.25);"
                           onmouseover="this.style.background='var(--lp-red-hover)'; this.style.boxShadow='0 1px 2px rgba(0,0,0,0.3), 0 12px 32px rgba(225, 6, 0, 0.35)';"
                           onmouseout="this.style.background='var(--lp-red)'; this.style.boxShadow='0 1px 2px rgba(0,0,0,0.3), 0 8px 24px rgba(225, 6, 0, 0.25)';">
                            Get Started Free
                        </a>
                        <a href="{{ app_url('/login') }}"
                           class="inline-flex items-center justify-center rounded-xl px-8 py-3.5 text-[15px] font-semibold backdrop-blur-sm transition-all duration-200 hover:!text-white"
                           style="border: 1px solid var(--lp-border); background: var(--lp-surface); color: var(--lp-text-soft);"
                           onmouseover="this.style.borderColor='rgba(255,255,255,0.18)'; this.style.background='var(--lp-surface-2)';"
                           onmouseout="this.style.borderColor='var(--lp-border)'; this.style.background='var(--lp-surface)';">
                            Sign In
                        </a>
                    @endauth
                </div>

                <div class="mt-14 flex flex-col items-center gap-6 sm:flex-row sm:justify-center sm:gap-10">
                    <div class="flex items-center gap-2.5 text-[13px]" style="color: var(--lp-text-muted);">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full" style="background: var(--lp-surface-2);">
                            <span class="h-1.5 w-1.5 rounded-full" style="background: rgba(225, 6, 0, 0.7);"></span>
                        </span>
                        Offline scoring on tablets
                    </div>
                    <div class="flex items-center gap-2.5 text-[13px]" style="color: var(--lp-text-muted);">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full" style="background: var(--lp-surface-2);">
                            <span class="h-1.5 w-1.5 rounded-full" style="background: rgba(225, 6, 0, 0.7);"></span>
                        </span>
                        Live synced leaderboards
                    </div>
                    <div class="flex items-center gap-2.5 text-[13px]" style="color: var(--lp-text-muted);">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full" style="background: var(--lp-surface-2);">
                            <span class="h-1.5 w-1.5 rounded-full" style="background: rgba(225, 6, 0, 0.7);"></span>
                        </span>
                        Built for clubs and federations
                    </div>
                </div>

            </div>
        </div>

        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-px" style="background: linear-gradient(to right, transparent, var(--lp-border), transparent);"></div>
    </section>

    {{-- Scoring Engines --}}
    <section style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-16 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Three Scoring Engines</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">From gong shoots to precision rifle to extreme long range &mdash; pick the engine that fits your match.</p>
            </div>
            <div class="grid gap-8 lg:grid-cols-3">

                <div class="rounded-2xl p-8 flex flex-col" style="border: 1px solid rgba(225, 6, 0, 0.15); background: var(--lp-surface);">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold self-start" style="background: rgba(225, 6, 0, 0.08); border: 1px solid rgba(225, 6, 0, 0.15); color: var(--lp-red);">
                        Relay Scoring
                    </div>
                    <h3 class="mb-2 text-xl font-bold" style="color: var(--lp-text);">Synchronized Relay Format</h3>
                    <p class="mb-6 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        All relays complete each stage before advancing together. Distance-based target and gong multipliers reward accuracy at range. Range Officers tap HIT or MISS.
                    </p>
                    <ul class="space-y-2.5 text-sm mt-auto" style="color: var(--lp-text-soft);">
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Gong multipliers (2.5 MOA = 1.0x, 0.5 MOA = 2.0x)</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Distance multipliers (400m = 4x, 700m = 7x)</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Synchronized relay scoring flow</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Quick-add presets: 5 standard MOA targets</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Auto-squadding with shared-rifle constraints</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-amber-500">&#10003;</span> Optional <strong class="text-amber-400">Side Bet</strong> mode</li>
                    </ul>
                </div>

                <div class="rounded-2xl p-8 flex flex-col ring-1 ring-amber-600/10" style="border: 1px solid rgba(146, 64, 14, 0.3); background: var(--lp-surface);">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold self-start" style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(146, 64, 14, 0.3); color: rgb(251, 191, 36);">
                        PRS
                    </div>
                    <h3 class="mb-2 text-xl font-bold" style="color: var(--lp-text);">Hit / Miss / Shot Not Taken</h3>
                    <p class="mb-6 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Each shooter completes an entire stage. Every target has three state buttons: <strong class="text-green-400">Hit</strong>,
                        <strong style="color: var(--lp-red);">Miss</strong>, or <strong class="text-amber-400">Not Taken</strong>.
                    </p>
                    <ul class="space-y-2.5 text-sm mt-auto" style="color: var(--lp-text-soft);">
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Three-button scoring per target</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Timed stages with app timer or manual input</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Tiebreaker stage: impacts first, then time</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Par time auto-fill for incomplete shooters</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Smart decimal time entry (e.g. 105.23s)</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Open / Factory / Limited division presets</li>
                    </ul>
                </div>

                <div class="rounded-2xl p-8 flex flex-col ring-1 ring-emerald-600/10" style="border: 1px solid rgba(5, 150, 105, 0.3); background: var(--lp-surface);">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold self-start" style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(5, 150, 105, 0.3); color: rgb(52, 211, 153);">
                        ELR
                    </div>
                    <h3 class="mb-2 text-xl font-bold" style="color: var(--lp-text);">Extreme Long Range</h3>
                    <p class="mb-6 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Shot-by-shot scoring at extreme distances. Each target has a base point value and shot multipliers reward first-round hits.
                        Optional ladder progression requires a hit before advancing.
                    </p>
                    <ul class="space-y-2.5 text-sm mt-auto" style="color: var(--lp-text-soft);">
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-emerald-500">&#10003;</span> Distance-based base points (1000m = 10pts, 1800m = 25pts)</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-emerald-500">&#10003;</span> Shot multipliers (1st = 1.0x, 2nd = 0.7x, 3rd = 0.5x)</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-emerald-500">&#10003;</span> Ladder &amp; static stage types</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-emerald-500">&#10003;</span> "Must hit to advance" gate per target</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-emerald-500">&#10003;</span> Configurable max shots per target</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-emerald-500">&#10003;</span> One-click default template</li>
                    </ul>
                </div>

            </div>

            <div class="mt-10 text-center">
                <a href="{{ route('scoring') }}" class="text-sm font-medium transition-colors" style="color: var(--lp-red);">
                    Learn more about scoring engines &rarr;
                </a>
            </div>
        </div>
    </section>

    {{-- Built for the Range --}}
    <section style="border-top: 1px solid var(--lp-border);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-16 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Built for the Range</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">Everything you need to run a smooth match &mdash; from setup to final standings.</p>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">

                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(245, 158, 11, 0.08);">
                        <svg class="h-6 w-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" /></svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Offline-First Scoring</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">No signal at the range? No problem. Scores are saved locally on the device and sync automatically when connectivity returns.</p>
                </div>

                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(225, 6, 0, 0.08);">
                        <svg class="h-6 w-6" style="color: var(--lp-red);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" /></svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Live Scoreboards</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">TV scoreboard for the range, plus a mobile-friendly live page spectators can open by scanning a QR code. Auto-refreshes every 10 seconds.</p>
                </div>

                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(59, 130, 246, 0.08);">
                        <svg class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" /></svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Multi-Device Sync</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Multiple Range Officers can score simultaneously on different tablets. All scores merge on the server and appear on the scoreboard in real-time.</p>
                </div>

                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(59, 130, 246, 0.08);">
                        <svg class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" /></svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Divisions &amp; Categories</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Split shooters by equipment class (Open, Factory, Limited) and demographics (Overall, Ladies, Junior, Senior). Filter scoreboards by either axis.</p>
                </div>

                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(34, 197, 94, 0.08);">
                        <svg class="h-6 w-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Leagues &amp; Clubs</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Create a league, add clubs underneath it. Season leaderboards aggregate scores across matches with best-of-N scoring.</p>
                </div>

                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(225, 6, 0, 0.08);">
                        <svg class="h-6 w-6" style="color: var(--lp-red);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Registration &amp; Squadding</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Online registration with equipment details, payment tracking, and auto-squadding that respects shared-rifle constraints across concurrent relays.</p>
                </div>

            </div>
            <div class="mt-10 text-center">
                <a href="{{ route('features') }}" class="text-sm font-medium transition-colors" style="color: var(--lp-red);">
                    View all features &rarr;
                </a>
            </div>
        </div>
    </section>

    {{-- Side Bet --}}
    <section style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-3xl px-6 py-20 lg:py-24">
            <div class="rounded-2xl p-8 lg:p-10 ring-1 ring-amber-600/10" style="border: 1px solid rgba(146, 64, 14, 0.3); background: var(--lp-surface);">
                <div class="flex items-start gap-5">
                    <div class="flex-shrink-0 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(245, 158, 11, 0.08);">
                        <svg class="h-6 w-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0 1 16.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.003 6.003 0 0 1-3.77 1.522m0 0a6.003 6.003 0 0 1-3.77-1.522" /></svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold" style="color: var(--lp-text);">Side Bet &mdash; Royal Flush</h3>
                        <p class="mt-2 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                            Optional side competition for relay-format matches. The winner is whoever hits the most smallest gongs.
                            Ties break by furthest distance, then cascade to the next gong size. A fun, high-stakes addition to any match day.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Android App Coming Soon --}}
    <section style="border-top: 1px solid var(--lp-border);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="rounded-2xl p-8 lg:p-12 relative overflow-hidden" style="border: 1px solid rgba(52, 211, 153, 0.15); background: var(--lp-surface);">
                <div class="pointer-events-none absolute inset-0" style="background: radial-gradient(ellipse 60% 80% at 100% 50%, rgba(16, 185, 129, 0.04), transparent 70%);"></div>

                <div class="relative flex flex-col lg:flex-row lg:items-center lg:gap-12">
                    <div class="flex-1">
                        <div class="mb-4 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-[13px] font-semibold" style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(5, 150, 105, 0.2); color: rgb(52, 211, 153);">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" style="background: rgb(52, 211, 153);"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2" style="background: rgb(52, 211, 153);"></span>
                            </span>
                            Coming Soon
                        </div>
                        <h2 class="text-2xl font-bold tracking-tight lg:text-3xl" style="color: var(--lp-text);">
                            Native Android App
                        </h2>
                        <p class="mt-3 max-w-lg text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                            Take DeadCenter to any range &mdash; even without internet. The native Android app runs a full scoring server on a single tablet, so other devices can connect over local WiFi and score together.
                        </p>
                    </div>

                    <div class="mt-8 lg:mt-0 grid gap-4 sm:grid-cols-3 lg:w-[420px] flex-shrink-0">
                        <div class="rounded-xl p-4 text-center" style="border: 1px solid var(--lp-border); background: rgba(59, 130, 246, 0.04);">
                            <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-lg" style="background: rgba(59, 130, 246, 0.1);">
                                <svg class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 1.332-7.257 3 3 0 0 0-3.758-3.848 5.25 5.25 0 0 0-10.233 2.33A4.502 4.502 0 0 0 2.25 15Z" /></svg>
                            </div>
                            <p class="text-xs font-semibold" style="color: var(--lp-text);">Cloud</p>
                            <p class="mt-0.5 text-[11px] leading-tight" style="color: var(--lp-text-muted);">Online scoring via deadcenter.co.za</p>
                        </div>
                        <div class="rounded-xl p-4 text-center" style="border: 1px solid rgba(225, 6, 0, 0.15); background: rgba(225, 6, 0, 0.04);">
                            <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-lg" style="background: rgba(225, 6, 0, 0.1);">
                                <svg class="h-5 w-5" style="color: var(--lp-red);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>
                            </div>
                            <p class="text-xs font-semibold" style="color: var(--lp-text);">Standalone</p>
                            <p class="mt-0.5 text-[11px] leading-tight" style="color: var(--lp-text-muted);">One device, fully offline</p>
                        </div>
                        <div class="rounded-xl p-4 text-center" style="border: 1px solid rgba(5, 150, 105, 0.2); background: rgba(16, 185, 129, 0.04);">
                            <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-lg" style="background: rgba(16, 185, 129, 0.1);">
                                <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 0 1 7.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 0 1 1.06 0Z" /></svg>
                            </div>
                            <p class="text-xs font-semibold" style="color: var(--lp-text);">Hub</p>
                            <p class="mt-0.5 text-[11px] leading-tight" style="color: var(--lp-text-muted);">Local WiFi server for multiple tablets</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-6xl px-6 py-20 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Ready to Score?</h2>
            <p class="mx-auto mt-3 max-w-md" style="color: var(--lp-text-muted);">Set up your first match in minutes. Free to use.</p>
            <div class="mt-8 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                @auth
                    <a href="{{ app_url('/dashboard') }}" class="rounded-xl px-8 py-3.5 text-lg font-bold text-white transition-all" style="background: var(--lp-red); box-shadow: 0 4px 20px rgba(225, 6, 0, 0.25);" onmouseover="this.style.background='var(--lp-red-hover)'" onmouseout="this.style.background='var(--lp-red)'">
                        Go to Dashboard
                    </a>
                @else
                    <a href="{{ app_url('/register') }}" class="rounded-xl px-8 py-3.5 text-lg font-bold text-white transition-all" style="background: var(--lp-red); box-shadow: 0 4px 20px rgba(225, 6, 0, 0.25);" onmouseover="this.style.background='var(--lp-red-hover)'" onmouseout="this.style.background='var(--lp-red)'">
                        Get Started Free
                    </a>
                    <a href="{{ app_url('/login') }}" class="rounded-xl px-8 py-3.5 text-lg font-semibold transition-colors" style="border: 1px solid var(--lp-border); color: var(--lp-text);" onmouseover="this.style.background='var(--lp-surface-2)'" onmouseout="this.style.background='transparent'">
                        Sign In
                    </a>
                @endauth
            </div>
        </div>
    </section>

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
