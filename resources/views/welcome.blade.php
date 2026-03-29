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
                        <a href="{{ route('dashboard') }}"
                           class="group relative inline-flex items-center justify-center rounded-xl px-8 py-3.5 text-[15px] font-semibold text-white transition-all duration-200"
                           style="background: var(--lp-red); box-shadow: 0 1px 2px rgba(0,0,0,0.3), 0 8px 24px rgba(225, 6, 0, 0.25);"
                           onmouseover="this.style.background='var(--lp-red-hover)'; this.style.boxShadow='0 1px 2px rgba(0,0,0,0.3), 0 12px 32px rgba(225, 6, 0, 0.35)';"
                           onmouseout="this.style.background='var(--lp-red)'; this.style.boxShadow='0 1px 2px rgba(0,0,0,0.3), 0 8px 24px rgba(225, 6, 0, 0.25)';">
                            Go to Dashboard
                        </a>
                    @else
                        <a href="{{ route('register') }}"
                           class="group relative inline-flex items-center justify-center rounded-xl px-8 py-3.5 text-[15px] font-semibold text-white transition-all duration-200"
                           style="background: var(--lp-red); box-shadow: 0 1px 2px rgba(0,0,0,0.3), 0 8px 24px rgba(225, 6, 0, 0.25);"
                           onmouseover="this.style.background='var(--lp-red-hover)'; this.style.boxShadow='0 1px 2px rgba(0,0,0,0.3), 0 12px 32px rgba(225, 6, 0, 0.35)';"
                           onmouseout="this.style.background='var(--lp-red)'; this.style.boxShadow='0 1px 2px rgba(0,0,0,0.3), 0 8px 24px rgba(225, 6, 0, 0.25)';">
                            Get Started Free
                        </a>
                        <a href="{{ route('login') }}"
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

    {{-- Features teaser --}}
    <section style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
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
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(245, 158, 11, 0.08);">
                        <svg class="h-6 w-6 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" /></svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">PRS Scoring</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Hit / Miss / Shot Not Taken buttons for each target. Timed stages with smart decimal input, tiebreaker stage support, and par time auto-fill.</p>
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
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(59, 130, 246, 0.08);">
                        <svg class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" /></svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Multi-Device Sync</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Multiple Range Officers can score simultaneously on different tablets. All scores merge on the server and appear on the scoreboard in real-time.</p>
                </div>

            </div>
            <div class="mt-10 text-center">
                <a href="{{ route('features') }}" class="text-sm font-medium transition-colors" style="color: var(--lp-red);">
                    View all features &rarr;
                </a>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section style="border-top: 1px solid var(--lp-border);">
        <div class="mx-auto max-w-6xl px-6 py-20 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Ready to Score?</h2>
            <p class="mx-auto mt-3 max-w-md" style="color: var(--lp-text-muted);">Set up your first match in minutes. Free to use.</p>
            <div class="mt-8 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-xl px-8 py-3.5 text-lg font-bold text-white transition-all" style="background: var(--lp-red); box-shadow: 0 4px 20px rgba(225, 6, 0, 0.25);" onmouseover="this.style.background='var(--lp-red-hover)'" onmouseout="this.style.background='var(--lp-red)'">
                        Go to Dashboard
                    </a>
                @else
                    <a href="{{ route('register') }}" class="rounded-xl px-8 py-3.5 text-lg font-bold text-white transition-all" style="background: var(--lp-red); box-shadow: 0 4px 20px rgba(225, 6, 0, 0.25);" onmouseover="this.style.background='var(--lp-red-hover)'" onmouseout="this.style.background='var(--lp-red)'">
                        Get Started Free
                    </a>
                    <a href="{{ route('login') }}" class="rounded-xl px-8 py-3.5 text-lg font-semibold transition-colors" style="border: 1px solid var(--lp-border); color: var(--lp-text);" onmouseover="this.style.background='var(--lp-surface-2)'" onmouseout="this.style.background='transparent'">
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
