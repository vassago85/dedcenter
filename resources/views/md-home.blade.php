<x-layouts.marketing
    title="Shooting Match Scoring Software | DeadCenter for Match Directors"
    description="Set up matches, manage scoring, and publish results. Relay, PRS, and ELR scoring engines for clubs, leagues, and federations in South Africa."
    :schema="[
        ['@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => 'DeadCenter', 'applicationCategory' => 'SportsApplication', 'operatingSystem' => 'Web', 'url' => md_url('/'), 'description' => 'Multi-discipline shooting match scoring platform for match directors, clubs, and federations.', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'ZAR']],
        ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'DeadCenter', 'url' => shooter_url('/')]
    ]"
>

    {{-- ══════════════════════════════════════════ --}}
    {{-- HERO --}}
    {{-- ══════════════════════════════════════════ --}}
    <section class="relative isolate overflow-hidden">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute inset-0" style="background: radial-gradient(ellipse 70% 50% at 50% 0%, rgba(225, 6, 0, 0.06), transparent 70%);"></div>
            <div class="absolute top-0 left-1/2 -translate-x-1/2 h-[600px] w-[900px] rounded-full blur-[120px]" style="background: rgba(225, 6, 0, 0.03);"></div>
        </div>

        <div class="relative mx-auto max-w-6xl px-6 pt-24 pb-20 sm:pt-32 sm:pb-28 lg:pt-40 lg:pb-32">
            <div class="mx-auto max-w-3xl text-center">
                <div class="mb-8 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-[13px] font-medium backdrop-blur-sm" style="border: 1px solid var(--lp-border); background: var(--lp-surface); color: var(--lp-text-muted);">
                    <span class="h-1.5 w-1.5 rounded-full" style="background: var(--lp-red);"></span>
                    For Match Directors &amp; Organizers
                </div>

                <h1 class="text-[2.5rem] font-black leading-[1.08] tracking-tight sm:text-5xl lg:text-6xl" style="color: var(--lp-text);">
                    Run Better Shooting Matches With <span style="color: var(--lp-red);">Less Admin</span>
                </h1>

                <p class="mx-auto mt-7 max-w-xl text-[1.05rem] leading-relaxed" style="color: var(--lp-text-soft);">
                    Set up matches, manage scoring in the field, and publish results instantly. DeadCenter is a free, modern scoring platform built for South African shooting competition organisers.
                </p>

                <div class="mt-10 flex flex-col items-center gap-3.5 sm:flex-row sm:justify-center">
                    <a href="#features"
                       class="lp-btn-primary group relative inline-flex items-center justify-center rounded-xl px-8 py-3.5 text-[15px] font-semibold transition-all duration-200"
                       style="box-shadow: 0 1px 2px rgba(0,0,0,0.3), 0 8px 24px rgba(225, 6, 0, 0.25);">
                        Explore Features
                    </a>
                    <a href="#how-it-works"
                       class="lp-btn-secondary inline-flex items-center justify-center rounded-xl px-8 py-3.5 text-[15px] font-semibold backdrop-blur-sm transition-all duration-200">
                        Learn How It Works
                    </a>
                </div>

                <div class="mt-14 flex flex-col items-center gap-6 sm:flex-row sm:justify-center sm:gap-10">
                    <div class="flex items-center gap-2.5 text-[13px]" style="color: var(--lp-text-muted);">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full" style="background: var(--lp-surface-2);"><span class="h-1.5 w-1.5 rounded-full" style="background: rgba(225, 6, 0, 0.7);"></span></span>
                        Three scoring engines
                    </div>
                    <div class="flex items-center gap-2.5 text-[13px]" style="color: var(--lp-text-muted);">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full" style="background: var(--lp-surface-2);"><span class="h-1.5 w-1.5 rounded-full" style="background: rgba(225, 6, 0, 0.7);"></span></span>
                        Multi-device sync
                    </div>
                    <div class="flex items-center gap-2.5 text-[13px]" style="color: var(--lp-text-muted);">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full" style="background: var(--lp-surface-2);"><span class="h-1.5 w-1.5 rounded-full" style="background: rgba(225, 6, 0, 0.7);"></span></span>
                        Android app + PWA
                    </div>
                </div>
            </div>
        </div>

        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-px" style="background: linear-gradient(to right, transparent, var(--lp-border), transparent);"></div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- MATCH LIFECYCLE --}}
    {{-- ══════════════════════════════════════════ --}}
    <section style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-16 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">The Match Lifecycle</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">From creating a match to updating season standings &mdash; DeadCenter handles the entire workflow in ten steps.</p>
            </div>

            <div>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-5">
                    @foreach([
                        ['icon' => 'M12 4.5v15m7.5-7.5h-15', 'title' => 'Create Match'],
                        ['icon' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z', 'title' => 'Configure Stages'],
                        ['icon' => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z', 'title' => 'Set Divisions'],
                        ['icon' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z', 'title' => 'Pre-Registration'],
                        ['icon' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z', 'title' => 'Open Registration'],
                        ['icon' => 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z', 'title' => 'Open Squadding'],
                        ['icon' => 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z', 'title' => 'Close Registration'],
                        ['icon' => 'M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3', 'title' => 'Score in Field'],
                        ['icon' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z', 'title' => 'Review & Publish'],
                        ['icon' => 'M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0 1 16.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.003 6.003 0 0 1-3.77 1.522m0 0a6.003 6.003 0 0 1-3.77-1.522', 'title' => 'Update Standings'],
                    ] as $step)
                        <div class="text-center">
                            <div class="relative mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl" style="background: rgba(225, 6, 0, 0.08); border: 1px solid rgba(225, 6, 0, 0.15);">
                                <svg class="h-6 w-6" style="color: var(--lp-red);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $step['icon'] }}" /></svg>
                            </div>
                            <h3 class="text-xs font-semibold" style="color: var(--lp-text);">{{ $step['title'] }}</h3>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- THREE SCORING MODES --}}
    {{-- ══════════════════════════════════════════ --}}
    <section id="scoring-modes" style="border-top: 1px solid var(--lp-border);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-16 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Three Scoring Modes</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">Choose the scoring engine that fits your match format. More engines will be added as the platform evolves.</p>
            </div>

            <div class="grid gap-8 lg:grid-cols-3">

                {{-- PRS Scoring --}}
                <div class="rounded-2xl p-8 flex flex-col ring-1 ring-amber-600/10" style="border: 1px solid rgba(245, 158, 11, 0.2); background: var(--lp-surface);">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold self-start" style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2); color: rgb(251, 191, 36);">
                        PRS Scoring
                    </div>
                    <h3 class="mb-2 text-xl font-bold" style="color: var(--lp-text);">Precision Rifle Series</h3>
                    <p class="mb-6 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Stage-based precision rifle scoring. Each shooter completes an entire stage before the next shooter begins, with hit/miss tracking and timed tiebreaker stages.
                    </p>
                    <ul class="space-y-2.5 text-sm mt-auto" style="color: var(--lp-text-soft);">
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Multi-position, multi-target per stage</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Hit / Miss / Not Taken per shot</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Tiebreaker stages with mandatory time</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> One shooter fully scored per stage</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Automatic timed stage for tiebreakers</li>
                    </ul>
                </div>

                {{-- ELR Scoring --}}
                <div class="rounded-2xl p-8 flex flex-col ring-1 ring-purple-600/10" style="border: 1px solid rgba(139, 92, 246, 0.2); background: var(--lp-surface);">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold self-start" style="background: rgba(139, 92, 246, 0.08); border: 1px solid rgba(139, 92, 246, 0.2); color: rgb(167, 139, 250);">
                        ELR Scoring
                    </div>
                    <h3 class="mb-2 text-xl font-bold" style="color: var(--lp-text);">Extreme Long Range</h3>
                    <p class="mb-6 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Shot-by-shot scoring at extreme distances with point-based impact tracking, diminishing multipliers, and optional must-hit-to-advance ladder progression.
                    </p>
                    <ul class="space-y-2.5 text-sm mt-auto" style="color: var(--lp-text-soft);">
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-purple-400">&#10003;</span> Distance-based stages with shot scoring</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-purple-400">&#10003;</span> Points awarded per impact</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-purple-400">&#10003;</span> Detailed shot-by-shot breakdowns</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-purple-400">&#10003;</span> Must-hit-to-advance progression</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-purple-400">&#10003;</span> Shot multiplier profiles</li>
                    </ul>
                </div>

                {{-- Relay / Standard Scoring --}}
                <div class="rounded-2xl p-8 flex flex-col" style="border: 1px solid rgba(225, 6, 0, 0.15); background: var(--lp-surface);">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold self-start" style="background: rgba(225, 6, 0, 0.08); border: 1px solid rgba(225, 6, 0, 0.15); color: var(--lp-red);">
                        Relay / Standard
                    </div>
                    <h3 class="mb-2 text-xl font-bold" style="color: var(--lp-text);">Synchronized Relay Format</h3>
                    <p class="mb-6 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Traditional relay-based scoring where all relays complete each stage before advancing together. Distance-based multipliers reward accuracy at range.
                    </p>
                    <ul class="space-y-2.5 text-sm mt-auto" style="color: var(--lp-text-soft);">
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Distance cards with expandable relay lists</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Squad rotation between stages</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Break screens between relays</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Concurrent relay support</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Gong multiplier scoring</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- MULTI-DEVICE SYNC --}}
    {{-- ══════════════════════════════════════════ --}}
    <section style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-16 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Multi-Device Sync</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">Six connectivity modes to keep scoring running no matter what your network situation looks like on match day.</p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach([
                    ['title' => 'Standalone Mode', 'desc' => 'Single tablet, fully offline. All match data and scores stored locally in Room DB. Perfect for small club matches with one scorer.', 'color' => 'var(--lp-red)', 'bg' => 'rgba(225,6,0,0.08)'],
                    ['title' => 'Hub Mode', 'desc' => 'One tablet hosts a local server. Other devices connect to it over WiFi. No internet required — the hub coordinates all scoring across connected clients.', 'color' => 'rgb(251,191,36)', 'bg' => 'rgba(245,158,11,0.08)'],
                    ['title' => 'Client Mode', 'desc' => 'Connect to a hub on the local network. Import the match from the hub, score your assigned stage or squad, and sync back to the hub automatically.', 'color' => 'rgb(96,165,250)', 'bg' => 'rgba(59,130,246,0.08)'],
                    ['title' => 'Cloud Mode', 'desc' => 'Direct sync to deadcenter.co.za. Scores upload every 15 seconds when the device has internet connectivity. Ideal when the range has signal.', 'color' => 'rgb(52,211,153)', 'bg' => 'rgba(16,185,129,0.08)'],
                    ['title' => 'Bridge Sync', 'desc' => 'The hub receives scores from all clients on the LAN and pushes them to the cloud when internet is available. Clients never need internet directly.', 'color' => 'rgb(167,139,250)', 'bg' => 'rgba(139,92,246,0.08)'],
                    ['title' => 'Dual Status', 'desc' => 'Live indicators on every device show both hub connectivity and cloud sync status. Always know exactly where your scores are and whether they have reached the server.', 'color' => 'var(--lp-red)', 'bg' => 'rgba(225,6,0,0.08)'],
                ] as $mode)
                    <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: {{ $mode['bg'] }};">
                            <div class="h-3 w-3 rounded-full" style="background: {{ $mode['color'] }};"></div>
                        </div>
                        <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">{{ $mode['title'] }}</h3>
                        <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">{{ $mode['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- SCORE INTEGRITY --}}
    {{-- ══════════════════════════════════════════ --}}
    <section style="border-top: 1px solid var(--lp-border);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-16 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Score Integrity</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">Every score change is tracked, every correction is auditable, and every device is locked down.</p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach([
                    ['title' => 'Audit Log', 'desc' => 'Every score change is recorded with the device, user, and timestamp. Full traceability from field to final result.', 'color' => 'var(--lp-red)', 'bg' => 'rgba(225,6,0,0.08)'],
                    ['title' => 'Score Reassignment', 'desc' => 'Move scores between shooters when corrections are needed. The original record is preserved in the audit trail.', 'color' => 'rgb(96,165,250)', 'bg' => 'rgba(59,130,246,0.08)'],
                    ['title' => 'Reshoot Tracking', 'desc' => 'Flag reshoots with a mandatory reason. The reshoot is linked to the original score so the full history is visible.', 'color' => 'rgb(251,191,36)', 'bg' => 'rgba(245,158,11,0.08)'],
                    ['title' => 'Publishing Control', 'desc' => 'Choose to show scores live as they are entered or hold them back and publish after review. Match directors control the release.', 'color' => 'rgb(52,211,153)', 'bg' => 'rgba(16,185,129,0.08)'],
                    ['title' => 'Device Lock', 'desc' => 'PIN-protected lock ties a scoring tablet to a specific stage or squad. Prevents accidental navigation or edits on other squads.', 'color' => 'rgb(167,139,250)', 'bg' => 'rgba(139,92,246,0.08)'],
                    ['title' => 'MD Override', 'desc' => 'If a Range Officer forgets their PIN, the Match Director can unlock the device with their username and password.', 'color' => 'var(--lp-red)', 'bg' => 'rgba(225,6,0,0.08)'],
                ] as $item)
                    <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: {{ $item['bg'] }};">
                            <div class="h-3 w-3 rounded-full" style="background: {{ $item['color'] }};"></div>
                        </div>
                        <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">{{ $item['title'] }}</h3>
                        <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">{{ $item['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- REGISTRATION & SQUADDING --}}
    {{-- ══════════════════════════════════════════ --}}
    <section style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-16 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Registration &amp; Squadding</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">Three-phase registration flow that puts shooters in control while keeping match directors informed.</p>
            </div>

            <div class="grid gap-8 lg:grid-cols-3">
                @foreach([
                    ['step' => '1', 'title' => 'Pre-Registration', 'desc' => 'Shooters express interest before registration officially opens. Match directors see demand early and can plan capacity. No commitment required from the shooter at this stage.'],
                    ['step' => '2', 'title' => 'Registration', 'desc' => 'Full registration with division selection, equipment details, and approval workflow. Match directors can approve, reject, or waitlist entries. Entry fee tracking included.'],
                    ['step' => '3', 'title' => 'Self-Service Squadding', 'desc' => 'Once registration closes, squadding opens. Shooters choose their own squad from available slots with capacity limits enforced automatically. No more spreadsheet juggling.'],
                ] as $phase)
                    <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                        <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl text-xl font-black" style="background: rgba(225, 6, 0, 0.08); color: var(--lp-red);">
                            {{ $phase['step'] }}
                        </div>
                        <h3 class="text-lg font-semibold mb-3 text-center" style="color: var(--lp-text);">{{ $phase['title'] }}</h3>
                        <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">{{ $phase['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- SEASON STANDINGS & RELATIVE SCORES --}}
    {{-- ══════════════════════════════════════════ --}}
    <section style="border-top: 1px solid var(--lp-border);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-12 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Season Standings &amp; Relative Scores</h2>
                <p class="mt-3 max-w-2xl mx-auto" style="color: var(--lp-text-muted);">DeadCenter uses relative scoring to create fair, meaningful season standings that work across different matches, venues, and conditions.</p>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <h3 class="text-lg font-semibold mb-4" style="color: var(--lp-text);">How Relative Scoring Works</h3>
                    <div class="space-y-4 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        <p>After each match, every shooter's score is expressed as a <strong style="color: var(--lp-text);">percentage of the top shooter's score</strong>. The winner always scores 100%. A shooter with 80% of the winner's score gets a relative score of 80%.</p>
                        <p>This allows <strong style="color: var(--lp-text);">fair comparison across different matches</strong> &mdash; a 92% at a difficult venue is directly comparable to a 92% at an easier one.</p>
                    </div>
                </div>

                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <h3 class="text-lg font-semibold mb-4" style="color: var(--lp-text);">Season Aggregation</h3>
                    <ul class="space-y-3 text-sm" style="color: var(--lp-text-soft);">
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 text-green-500 flex-shrink-0">&#10003;</span>
                            <span><strong style="color: var(--lp-text);">Average relative score</strong> across all matches in the season</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 text-green-500 flex-shrink-0">&#10003;</span>
                            <span><strong style="color: var(--lp-text);">Matches played</strong> count tracked per shooter</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 text-green-500 flex-shrink-0">&#10003;</span>
                            <span><strong style="color: var(--lp-text);">Best/worst performance</strong> visible in standings</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 text-green-500 flex-shrink-0">&#10003;</span>
                            <span><strong style="color: var(--lp-text);">Best-of-N rules</strong> let organizers drop worst results</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 text-green-500 flex-shrink-0">&#10003;</span>
                            <span><strong style="color: var(--lp-text);">Division &amp; category filters</strong> on all leaderboards</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- FEATURES --}}
    {{-- ══════════════════════════════════════════ --}}
    <section id="features" style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-16 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Everything You Need to Run a Match</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">From match setup to final standings &mdash; built for the range, not the office.</p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach([
                    ['title' => 'Match Setup', 'desc' => 'Create a match, set the date, location, scoring mode, entry fee, and notes. Everything configurable from one screen.', 'color' => 'var(--lp-red)', 'bg' => 'rgba(225,6,0,0.08)'],
                    ['title' => 'Stage Builder', 'desc' => 'Define target sets with distances, gong sizes, multipliers, and presets. Different configuration per scoring engine.', 'color' => 'rgb(251,191,36)', 'bg' => 'rgba(245,158,11,0.08)'],
                    ['title' => 'Registration & Squadding', 'desc' => 'Pre-registration, full registration with approval, and self-service squadding. Shooters manage their own entries.', 'color' => 'rgb(96,165,250)', 'bg' => 'rgba(59,130,246,0.08)'],
                    ['title' => 'Tablet Scoring', 'desc' => 'Range Officers score on Android tablets or any device with a browser. Offline-first with Room DB and IndexedDB storage.', 'color' => 'rgb(52,211,153)', 'bg' => 'rgba(16,185,129,0.08)'],
                    ['title' => 'Live Scoreboards', 'desc' => 'TV scoreboard for the range and a mobile-friendly live page for spectators. QR code sharing with auto-refresh.', 'color' => 'var(--lp-red)', 'bg' => 'rgba(225,6,0,0.08)'],
                    ['title' => 'Results & Exports', 'desc' => 'Complete a match and results are published instantly. Division and category filtered scoreboards with export options.', 'color' => 'rgb(96,165,250)', 'bg' => 'rgba(59,130,246,0.08)'],
                    ['title' => 'Season Standings', 'desc' => 'Relative scoring across a season. Best-of-N rules, average relative score, and public org leaderboards.', 'color' => 'rgb(52,211,153)', 'bg' => 'rgba(16,185,129,0.08)'],
                    ['title' => 'Multi-Discipline', 'desc' => 'Relay, PRS, and ELR scoring in one platform. Each match chooses its engine. More modes coming.', 'color' => 'rgb(167,139,250)', 'bg' => 'rgba(139,92,246,0.08)'],
                    ['title' => 'Multi-Device Sync', 'desc' => 'Standalone, hub/client, cloud, and bridge sync modes. Multiple tablets score simultaneously with real-time merge.', 'color' => 'rgb(251,191,36)', 'bg' => 'rgba(245,158,11,0.08)'],
                    ['title' => 'Score Audit Trail', 'desc' => 'Every score change logged with device, user, and timestamp. Reshoot tracking with mandatory reasons. Full traceability.', 'color' => 'var(--lp-red)', 'bg' => 'rgba(225,6,0,0.08)'],
                    ['title' => 'Device Lock', 'desc' => 'PIN-protected lock ties each tablet to a stage or squad. MD override with username/password if the PIN is forgotten.', 'color' => 'rgb(96,165,250)', 'bg' => 'rgba(59,130,246,0.08)'],
                    ['title' => 'Advertising', 'desc' => 'Brands can purchase advertising placements on leaderboards, results, and scoring screens. Feature-based "powered by" visibility for every event.', 'color' => 'rgb(52,211,153)', 'bg' => 'rgba(16,185,129,0.08)'],
                    ['title' => 'Team Events', 'desc' => 'Enable team mode so shooters register individually and self-select into teams. Configurable team sizes, auto-team distribution, and team leaderboards.', 'color' => 'rgb(52,211,153)', 'bg' => 'rgba(16,185,129,0.08)'],
                    ['title' => 'Walk-in Shooters', 'desc' => 'Add late arrivals on match day directly from the squadding screen. Walk-ins get a registration and shooter record created automatically.', 'color' => 'var(--lp-red)', 'bg' => 'rgba(225,6,0,0.08)'],
                    ['title' => 'PDF Reports & Match Books', 'desc' => 'Generate personal match reports for every shooter, export full standings as PDF, and create digital match books with stage details and shot data.', 'color' => 'rgb(167,139,250)', 'bg' => 'rgba(139,92,246,0.08)'],
                    ['title' => 'CSV Exports', 'desc' => 'Export standings and detailed score breakdowns as CSV for any match. Available on public scoreboards for shooters and organisers alike.', 'color' => 'rgb(96,165,250)', 'bg' => 'rgba(59,130,246,0.08)'],
                    ['title' => 'Multi-Day Matches', 'desc' => 'Run matches that span multiple days with per-day stage filtering on scoreboards. Shooters and spectators follow day-by-day progress.', 'color' => 'rgb(251,191,36)', 'bg' => 'rgba(245,158,11,0.08)'],
                    ['title' => 'Side Bets & Royal Flush', 'desc' => 'Optional side bet competitions and Royal Flush challenges within a match. Dedicated reporting for side game results and payouts.', 'color' => 'var(--lp-red)', 'bg' => 'rgba(225,6,0,0.08)'],
                ] as $feature)
                    <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: {{ $feature['bg'] }};">
                            <div class="h-3 w-3 rounded-full" style="background: {{ $feature['color'] }};"></div>
                        </div>
                        <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">{{ $feature['title'] }}</h3>
                        <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">{{ $feature['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- HOW IT WORKS --}}
    {{-- ══════════════════════════════════════════ --}}
    <section id="how-it-works" style="border-top: 1px solid var(--lp-border);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-16 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">How It Works for Match Directors</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">Five steps from match creation to published results.</p>
            </div>

            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-5">
                @foreach([
                    ['step' => '1', 'title' => 'Set Up Your Match', 'desc' => 'Name, date, venue, scoring mode, entry fee. Done in under two minutes.'],
                    ['step' => '2', 'title' => 'Choose Scoring Mode', 'desc' => 'Pick Relay, PRS, or ELR. Configure stages, targets, and multipliers.'],
                    ['step' => '3', 'title' => 'Register Shooters', 'desc' => 'Open registration. Shooters sign up online with division/category selection.'],
                    ['step' => '4', 'title' => 'Score the Match', 'desc' => 'Range Officers score on tablets in the field. Offline-capable with auto-sync.'],
                    ['step' => '5', 'title' => 'Publish Results', 'desc' => 'Complete the match. Results, scoreboards, and standings update instantly.'],
                ] as $item)
                    <div class="text-center">
                        <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl text-xl font-black" style="background: rgba(225, 6, 0, 0.08); color: var(--lp-red);">
                            {{ $item['step'] }}
                        </div>
                        <h3 class="text-sm font-semibold mb-2" style="color: var(--lp-text);">{{ $item['title'] }}</h3>
                        <p class="text-xs leading-relaxed" style="color: var(--lp-text-soft);">{{ $item['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- FOR CLUBS & FEDERATIONS --}}
    {{-- ══════════════════════════════════════════ --}}
    <section id="for-clubs" style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-16 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">For Clubs, Leagues &amp; Federations</h2>
                <p class="mt-3 max-w-2xl mx-auto" style="color: var(--lp-text-muted);">Whether you run a single club or manage a national federation, DeadCenter scales to fit your competition structure.</p>
            </div>

            <div class="grid gap-8 lg:grid-cols-2">
                @foreach([
                    ['title' => 'Clubs', 'desc' => 'Create your club, add match directors, configure branding, and publish a white-label portal with upcoming matches and leaderboards. Each club controls its own matches and banking details.'],
                    ['title' => 'Leagues', 'desc' => 'Group clubs under a league to aggregate season standings. Season leaderboards pull results from all child clubs. Best-of-N scoring drops each shooter\'s weakest results.'],
                    ['title' => 'Federations', 'desc' => 'Oversee multiple leagues and clubs. All scoring data flows through the same platform, so federation-level reporting is always up to date.'],
                    ['title' => 'Competition Organizers', 'desc' => 'Run standalone competitions or challenge events. Online registration, multi-device scoring, live results, and instant standings &mdash; all in one place.'],
                ] as $useCase)
                    <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                        <h3 class="text-lg font-semibold mb-3" style="color: var(--lp-text);">{{ $useCase['title'] }}</h3>
                        <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">{{ $useCase['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- CROSS-LINK TO SHOOTER PAGE --}}
    {{-- ══════════════════════════════════════════ --}}
    <section style="border-top: 1px solid var(--lp-border);">
        <div class="mx-auto max-w-3xl px-6 py-16 text-center">
            <h3 class="text-xl font-bold" style="color: var(--lp-text);">Looking for Events, Standings &amp; Results?</h3>
            <p class="mt-2 text-sm" style="color: var(--lp-text-soft);">Browse upcoming competitions, check live scoreboards, and explore season standings on the shooter portal.</p>
            <a href="{{ shooter_url('/') }}" class="mt-5 inline-flex items-center gap-2 text-sm font-semibold transition-colors" style="color: var(--lp-red);">
                Visit the Shooter Portal
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
            </a>
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- FAQ --}}
    {{-- ══════════════════════════════════════════ --}}
    <section style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-3xl px-6 py-20 lg:py-28">
            <h2 class="text-2xl font-bold tracking-tight text-center mb-12" style="color: var(--lp-text);">Frequently Asked Questions</h2>

            <div class="space-y-4">
                @foreach([
                    ['q' => 'How does offline scoring work?', 'a' => 'The Android app stores all match data in Room DB locally on the device. Scores are saved instantly as they are entered — no internet required. When connectivity returns, a background sync pushes all unsynced scores to the hub or cloud. The PWA uses IndexedDB for the same purpose in browsers.'],
                    ['q' => 'What devices can I use?', 'a' => 'DeadCenter has a native Android app available on the Play Store, optimised for tablets. You can also use the progressive web app (PWA) on any device with a modern browser — iPads, laptops, or phones. The Android app supports hub/client mode for local WiFi mesh scoring.'],
                    ['q' => 'How does multi-device sync work?', 'a' => 'You can run multiple tablets simultaneously. In hub mode, one tablet acts as a local server and others connect as clients over WiFi. The hub collects scores from all clients and optionally bridges them to the cloud. In cloud mode, each device syncs directly to deadcenter.co.za every 15 seconds.'],
                    ['q' => 'Is it really free?', 'a' => 'Yes. DeadCenter is free for match directors, clubs, and federations. There are no subscription fees, no per-match charges, and no feature gates. The platform is sustained through advertising placements on event features.'],
                    ['q' => 'Can shooters register themselves?', 'a' => 'Yes. Match directors open registration and shooters sign up online with their division, category, and equipment details. Entries can be auto-approved or require manual approval. When squadding opens, shooters choose their own squad from available slots.'],
                    ['q' => 'How do I set up hub mode?', 'a' => 'Open the Android app on your hub tablet, go to the match, and tap "Start Hub." The app displays the hub\'s IP address. On each client tablet, enter the hub IP to connect. Clients import the match from the hub and can start scoring immediately — no internet needed on any device.'],
                ] as $i => $faq)
                    <details class="lp-faq-details group rounded-xl overflow-hidden" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                        <summary class="flex w-full cursor-pointer items-center justify-between px-6 py-4 text-left text-sm font-semibold transition-colors hover:!text-white list-none" style="color: var(--lp-text);">
                            {{ $faq['q'] }}
                            <svg class="h-4 w-4 flex-shrink-0 transition-transform duration-200 group-open:rotate-180" style="color: var(--lp-text-muted);" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </summary>
                        <p class="px-6 pb-4 text-sm leading-relaxed" style="color: var(--lp-text-soft);">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- FINAL CTA --}}
    {{-- ══════════════════════════════════════════ --}}
    <section style="border-top: 1px solid var(--lp-border);">
        <div class="mx-auto max-w-6xl px-6 py-20 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Ready to Run Your Next Match?</h2>
            <p class="mx-auto mt-3 max-w-md" style="color: var(--lp-text-muted);">Create your free account and set up your first match in minutes.</p>
            <div class="mt-8 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                <a href="{{ app_url('/register') }}" class="lp-btn-primary rounded-xl px-8 py-3.5 text-lg font-bold transition-all" style="box-shadow: 0 4px 20px rgba(225, 6, 0, 0.25);">
                    Get Started Free
                </a>
                <a href="{{ app_url('/login') }}" class="lp-btn-footer-outline rounded-xl px-8 py-3.5 text-lg font-semibold transition-colors">
                    Sign In
                </a>
            </div>
        </div>
    </section>

</x-layouts.marketing>
