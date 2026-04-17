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
        <div class="relative mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
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
                    <a href="{{ app_url('/dashboard') }}" class="lp-cta-nav inline-flex shrink-0 items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold whitespace-nowrap sm:px-5">
                        Dashboard
                    </a>
                @else
                    <a href="{{ app_url('/login') }}" class="hidden sm:inline-block rounded-lg px-4 py-2 text-sm font-medium transition-colors lp-nav-text-muted">
                        Sign In
                    </a>
                    <a href="{{ app_url('/register') }}" class="lp-cta-nav inline-flex shrink-0 items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold whitespace-nowrap sm:px-5">
                        Get Started
                    </a>
                @endauth

                <details class="marketing-nav-details md:hidden relative">
                    <summary class="marketing-nav-summary list-none flex cursor-pointer items-center justify-center rounded-lg p-2 transition-colors hover:bg-white/10 outline-none ring-0" aria-label="Toggle menu">
                        <x-icon name="menu" class="marketing-nav-icon-open h-5 w-5 shrink-0" style="color: var(--lp-text-soft);" />
                        <x-icon name="x" class="marketing-nav-icon-close h-5 w-5 shrink-0" style="color: var(--lp-text-soft);" />
                    </summary>
                    <div class="marketing-mobile-panel absolute left-1/2 top-full z-[60] mt-0 w-screen max-w-[100vw] -translate-x-1/2 border-t px-6 py-4 shadow-[0_18px_48px_rgba(0,0,0,0.45)]" style="border-color: var(--lp-border); background: rgba(7, 19, 39, 0.97); backdrop-filter: blur(20px) saturate(1.4);">
                        <div class="mx-auto max-w-6xl space-y-1">
                            <a href="{{ route('features') }}" class="marketing-mobile-link block rounded-lg px-3 py-2.5 text-[13px] font-medium transition-colors hover:bg-white/10" onclick="this.closest('details')?.removeAttribute('open')">Features</a>
                            <a href="{{ route('scoring') }}" class="marketing-mobile-link block rounded-lg px-3 py-2.5 text-[13px] font-medium transition-colors hover:bg-white/10" onclick="this.closest('details')?.removeAttribute('open')">Scoring</a>
                            <a href="{{ route('offline') }}" class="marketing-mobile-link block rounded-lg px-3 py-2.5 text-[13px] font-medium transition-colors hover:bg-white/10" onclick="this.closest('details')?.removeAttribute('open')">Offline</a>
                            <a href="{{ route('setup') }}" class="marketing-mobile-link block rounded-lg px-3 py-2.5 text-[13px] font-medium transition-colors hover:bg-white/10" onclick="this.closest('details')?.removeAttribute('open')">Setup</a>
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
                           class="lp-btn-primary group relative inline-flex items-center justify-center rounded-xl px-8 py-3.5 text-[15px] font-semibold transition-all duration-200"
                           style="box-shadow: 0 1px 2px rgba(0,0,0,0.3), 0 8px 24px rgba(225, 6, 0, 0.25);">
                            Go to Dashboard
                        </a>
                    @else
                        <a href="{{ app_url('/register') }}"
                           class="lp-btn-primary group relative inline-flex items-center justify-center rounded-xl px-8 py-3.5 text-[15px] font-semibold transition-all duration-200"
                           style="box-shadow: 0 1px 2px rgba(0,0,0,0.3), 0 8px 24px rgba(225, 6, 0, 0.25);">
                            Get Started Free
                        </a>
                        <a href="{{ app_url('/login') }}"
                           class="lp-btn-secondary inline-flex items-center justify-center rounded-xl px-8 py-3.5 text-[15px] font-semibold backdrop-blur-sm transition-all duration-200">
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
                        <x-icon name="lightbulb" class="h-6 w-6 text-amber-500" />
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Offline-First Scoring</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">No signal at the range? No problem. Scores are saved locally on the device and sync automatically when connectivity returns.</p>
                </div>

                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(225, 6, 0, 0.08);">
                        <x-icon name="monitor" class="h-6 w-6" style="color: var(--lp-red);" />
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Live Scoreboards</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">TV scoreboard for the range, plus a mobile-friendly live page spectators can open by scanning a QR code. Auto-refreshes every 10 seconds.</p>
                </div>

                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(59, 130, 246, 0.08);">
                        <x-icon name="refresh-cw" class="h-6 w-6 text-blue-500" />
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Multi-Device Sync</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Multiple Range Officers can score simultaneously on different tablets. All scores merge on the server and appear on the scoreboard in real-time.</p>
                </div>

                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(59, 130, 246, 0.08);">
                        <x-icon name="layout-grid" class="h-6 w-6 text-blue-500" />
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Divisions &amp; Categories</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Split shooters by equipment class (Open, Factory, Limited) and demographics (Overall, Ladies, Junior, Senior). Filter scoreboards by either axis.</p>
                </div>

                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(34, 197, 94, 0.08);">
                        <x-icon name="users" class="h-6 w-6 text-green-500" />
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Leagues &amp; Clubs</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Create a league, add clubs underneath it. Season leaderboards aggregate scores across matches with best-of-N scoring.</p>
                </div>

                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(225, 6, 0, 0.08);">
                        <x-icon name="users" class="h-6 w-6" style="color: var(--lp-red);" />
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
                        <x-icon name="trophy" class="h-6 w-6 text-amber-500" />
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
                                <x-icon name="cloud" class="h-5 w-5 text-blue-400" />
                            </div>
                            <p class="text-xs font-semibold" style="color: var(--lp-text);">Cloud</p>
                            <p class="mt-0.5 text-[11px] leading-tight" style="color: var(--lp-text-muted);">Online scoring via deadcenter.co.za</p>
                        </div>
                        <div class="rounded-xl p-4 text-center" style="border: 1px solid rgba(225, 6, 0, 0.15); background: rgba(225, 6, 0, 0.04);">
                            <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-lg" style="background: rgba(225, 6, 0, 0.1);">
                                <x-icon name="smartphone" class="h-5 w-5" style="color: var(--lp-red);" />
                            </div>
                            <p class="text-xs font-semibold" style="color: var(--lp-text);">Standalone</p>
                            <p class="mt-0.5 text-[11px] leading-tight" style="color: var(--lp-text-muted);">One device, fully offline</p>
                        </div>
                        <div class="rounded-xl p-4 text-center" style="border: 1px solid rgba(5, 150, 105, 0.2); background: rgba(16, 185, 129, 0.04);">
                            <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-lg" style="background: rgba(16, 185, 129, 0.1);">
                                <x-icon name="wifi" class="h-5 w-5 text-emerald-400" />
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
                    <a href="{{ app_url('/dashboard') }}" class="lp-btn-primary rounded-xl px-8 py-3.5 text-lg font-bold transition-all" style="box-shadow: 0 4px 20px rgba(225, 6, 0, 0.25);">
                        Go to Dashboard
                    </a>
                @else
                    <a href="{{ app_url('/register') }}" class="lp-btn-primary rounded-xl px-8 py-3.5 text-lg font-bold transition-all" style="box-shadow: 0 4px 20px rgba(225, 6, 0, 0.25);">
                        Get Started Free
                    </a>
                    <a href="{{ app_url('/login') }}" class="lp-btn-footer-outline rounded-xl px-8 py-3.5 text-lg font-semibold transition-colors">
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
