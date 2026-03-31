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
                       class="group relative inline-flex items-center justify-center rounded-xl px-8 py-3.5 text-[15px] font-semibold text-white transition-all duration-200"
                       style="background: var(--lp-red); box-shadow: 0 1px 2px rgba(0,0,0,0.3), 0 8px 24px rgba(225, 6, 0, 0.25);"
                       onmouseover="this.style.background='var(--lp-red-hover)'; this.style.boxShadow='0 1px 2px rgba(0,0,0,0.3), 0 12px 32px rgba(225, 6, 0, 0.35)';"
                       onmouseout="this.style.background='var(--lp-red)'; this.style.boxShadow='0 1px 2px rgba(0,0,0,0.3), 0 8px 24px rgba(225, 6, 0, 0.25)';">
                        Explore Features
                    </a>
                    <a href="#how-it-works"
                       class="inline-flex items-center justify-center rounded-xl px-8 py-3.5 text-[15px] font-semibold backdrop-blur-sm transition-all duration-200 hover:!text-white"
                       style="border: 1px solid var(--lp-border); background: var(--lp-surface); color: var(--lp-text-soft);"
                       onmouseover="this.style.borderColor='rgba(255,255,255,0.18)'; this.style.background='var(--lp-surface-2)';"
                       onmouseout="this.style.borderColor='var(--lp-border)'; this.style.background='var(--lp-surface)';">
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
                        Offline tablet scoring
                    </div>
                    <div class="flex items-center gap-2.5 text-[13px]" style="color: var(--lp-text-muted);">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full" style="background: var(--lp-surface-2);"><span class="h-1.5 w-1.5 rounded-full" style="background: rgba(225, 6, 0, 0.7);"></span></span>
                        Free for all organizers
                    </div>
                </div>
            </div>
        </div>

        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-px" style="background: linear-gradient(to right, transparent, var(--lp-border), transparent);"></div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- MATCH WORKFLOW --}}
    {{-- ══════════════════════════════════════════ --}}
    <section style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-16 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">The Match Lifecycle</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">From creating a match to publishing final standings &mdash; DeadCenter handles the entire workflow.</p>
            </div>

            <div class="relative">
                <div class="hidden lg:block absolute top-10 left-[calc(8.33%+28px)] right-[calc(8.33%+28px)] h-0.5" style="background: linear-gradient(to right, var(--lp-red), rgba(225,6,0,0.3), var(--lp-red));"></div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                    @foreach([
                        ['icon' => 'M12 4.5v15m7.5-7.5h-15', 'title' => 'Create Match'],
                        ['icon' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z', 'title' => 'Configure Stages'],
                        ['icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z', 'title' => 'Assign Scoring'],
                        ['icon' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z', 'title' => 'Open Registration'],
                        ['icon' => 'M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3', 'title' => 'Score in Field'],
                        ['icon' => 'M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6', 'title' => 'Publish Results'],
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

                {{-- Relay Scoring --}}
                <div class="rounded-2xl p-8 flex flex-col" style="border: 1px solid rgba(225, 6, 0, 0.15); background: var(--lp-surface);">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold self-start" style="background: rgba(225, 6, 0, 0.08); border: 1px solid rgba(225, 6, 0, 0.15); color: var(--lp-red);">
                        Relay Scoring
                    </div>
                    <h3 class="mb-2 text-xl font-bold" style="color: var(--lp-text);">Synchronized Relay Format</h3>
                    <p class="mb-6 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        All relays complete each stage before advancing together. Distance-based target and gong multipliers reward accuracy at range.
                        Includes gong transition screens, relay summary tables, and quick-add preset targets.
                    </p>
                    <ul class="space-y-2.5 text-sm mt-auto" style="color: var(--lp-text-soft);">
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Synchronized stage-based relay format</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Distance-based target/gong multipliers</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Gong transition screens &amp; relay summaries</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Auto-squadding with shared-rifle constraints</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Suited to traditional club and field matches</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-amber-500">&#10003;</span> Optional <strong class="text-amber-400">Side Bet</strong> mode</li>
                    </ul>
                </div>

                {{-- PRS Scoring --}}
                <div class="rounded-2xl p-8 flex flex-col ring-1 ring-amber-600/10" style="border: 1px solid rgba(245, 158, 11, 0.2); background: var(--lp-surface);">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold self-start" style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2); color: rgb(251, 191, 36);">
                        PRS Scoring
                    </div>
                    <h3 class="mb-2 text-xl font-bold" style="color: var(--lp-text);">Stage-Based Precision Rifle</h3>
                    <p class="mb-6 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Hit/miss scoring with timed stages. Stage normalization enables relative performance comparison across different venues and conditions. Designed for competitive precision rifle series matches.
                    </p>
                    <ul class="space-y-2.5 text-sm mt-auto" style="color: var(--lp-text-soft);">
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Three-button scoring per target (Hit / Miss / Not Taken)</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Timed stages with app timer or manual entry</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Stage normalization / relative performance</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Tiebreaker stage: impacts first, then time</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Par time auto-fill for incomplete shooters</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Open / Factory / Limited division presets</li>
                    </ul>
                </div>

                {{-- ELR Scoring --}}
                <div class="rounded-2xl p-8 flex flex-col ring-1 ring-purple-600/10" style="border: 1px solid rgba(139, 92, 246, 0.2); background: var(--lp-surface);">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold self-start" style="background: rgba(139, 92, 246, 0.08); border: 1px solid rgba(139, 92, 246, 0.2); color: rgb(167, 139, 250);">
                        ELR Scoring
                    </div>
                    <h3 class="mb-2 text-xl font-bold" style="color: var(--lp-text);">Shot-by-Shot Extreme Long Range</h3>
                    <p class="mb-6 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Static and ladder stage types with multi-shot per target and diminishing multipliers. Must-hit-to-advance ladder logic with shot-level data recording. Normalized percentage standings and furthest target hit tracking.
                    </p>
                    <ul class="space-y-2.5 text-sm mt-auto" style="color: var(--lp-text-soft);">
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-purple-400">&#10003;</span> Static &amp; ladder stage types</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-purple-400">&#10003;</span> Multi-shot per target with diminishing multipliers</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-purple-400">&#10003;</span> Must-hit-to-advance ladder logic</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-purple-400">&#10003;</span> Shot-level data recording</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-purple-400">&#10003;</span> Normalized percentage standings</li>
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-purple-400">&#10003;</span> Furthest target hit tracking</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- SEASON STANDINGS & RELATIVE SCORES --}}
    {{-- ══════════════════════════════════════════ --}}
    <section style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
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
    <section id="features" style="border-top: 1px solid var(--lp-border);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-16 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Everything You Need to Run a Match</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">From match setup to final standings &mdash; built for the range, not the office.</p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach([
                    ['title' => 'Match Setup', 'desc' => 'Create a match, set the date, location, scoring mode, entry fee, and notes. Everything configurable from one screen.', 'color' => 'var(--lp-red)', 'bg' => 'rgba(225,6,0,0.08)'],
                    ['title' => 'Stage Builder', 'desc' => 'Define target sets with distances, gong sizes, multipliers, and presets. Different configuration per scoring engine.', 'color' => 'rgb(251,191,36)', 'bg' => 'rgba(245,158,11,0.08)'],
                    ['title' => 'Online Registration', 'desc' => 'Shooters register online with division/category selection, equipment details, and payment tracking. EFT or free.', 'color' => 'rgb(96,165,250)', 'bg' => 'rgba(59,130,246,0.08)'],
                    ['title' => 'Tablet Field Scoring', 'desc' => 'Range Officers score on tablets in the field. Offline-first with IndexedDB storage. Syncs automatically when connected.', 'color' => 'rgb(52,211,153)', 'bg' => 'rgba(16,185,129,0.08)'],
                    ['title' => 'Live Scoreboards', 'desc' => 'TV scoreboard for the range and a mobile-friendly live page for spectators. QR code sharing with 10-second auto-refresh.', 'color' => 'var(--lp-red)', 'bg' => 'rgba(225,6,0,0.08)'],
                    ['title' => 'Results Publishing', 'desc' => 'Complete a match and results are published instantly. Division and category filtered scoreboards are always available.', 'color' => 'rgb(96,165,250)', 'bg' => 'rgba(59,130,246,0.08)'],
                    ['title' => 'Season Standings', 'desc' => 'Relative scoring across a season. Best-of-N rules, average relative score, and public org leaderboards.', 'color' => 'rgb(52,211,153)', 'bg' => 'rgba(16,185,129,0.08)'],
                    ['title' => 'Multi-Discipline Support', 'desc' => 'Relay, PRS, and ELR scoring in one platform. Each match chooses its engine. More modes coming.', 'color' => 'rgb(167,139,250)', 'bg' => 'rgba(139,92,246,0.08)'],
                    ['title' => 'Multi-Device Sync', 'desc' => 'Multiple Range Officers score simultaneously on different tablets. All scores merge in real-time on the server.', 'color' => 'rgb(251,191,36)', 'bg' => 'rgba(245,158,11,0.08)'],
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
    <section id="how-it-works" style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
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
    <section id="for-clubs" style="border-top: 1px solid var(--lp-border);">
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
    <section style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
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
    <section style="border-top: 1px solid var(--lp-border);">
        <div class="mx-auto max-w-3xl px-6 py-20 lg:py-28">
            <h2 class="text-2xl font-bold tracking-tight text-center mb-12" style="color: var(--lp-text);">Frequently Asked Questions</h2>

            <div class="space-y-4" x-data="{ open: null }">
                @foreach([
                    ['q' => 'Is DeadCenter really free for match directors?', 'a' => 'Yes. DeadCenter is free for match directors, clubs, and federations. There are no subscription fees, no per-match charges, and no feature gates. The platform is sustained through optional promoted placements and partner visibility.'],
                    ['q' => 'What scoring modes are supported?', 'a' => 'Three modes: Relay Scoring for synchronized relay-format matches with gong/distance multipliers, PRS Scoring for precision rifle series with hit/miss and timed stages, and ELR Scoring for extreme long range with shot-by-shot tracking and ladder progression. More engines will be added over time.'],
                    ['q' => 'Can I score offline at the range?', 'a' => 'Yes. The scoring interface uses IndexedDB for local storage. Range Officers can score without any internet connection. Scores sync automatically when the device reconnects.'],
                    ['q' => 'How do multiple Range Officers score simultaneously?', 'a' => 'Each tablet locks to a specific squad. Multiple tablets can score different squads at the same time. All scores merge on the server in real-time and appear on the live scoreboard.'],
                    ['q' => 'Can my club have its own branded page?', 'a' => 'Yes. Each organization can enable a portal with custom branding, colours, and logo. The portal shows upcoming matches, leaderboards, and match details under your club identity.'],
                    ['q' => 'How are season standings calculated?', 'a' => 'DeadCenter uses relative scoring — each shooter\'s result is expressed as a percentage of the top scorer. Season standings aggregate the average relative score across all matches, with optional best-of-N rules to drop weakest results.'],
                ] as $i => $faq)
                    <div class="rounded-xl overflow-hidden" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                        <button @click="open = open === {{ $i }} ? null : {{ $i }}" class="flex w-full items-center justify-between px-6 py-4 text-left text-sm font-semibold transition-colors hover:!text-white" style="color: var(--lp-text);">
                            {{ $faq['q'] }}
                            <svg class="h-4 w-4 flex-shrink-0 transition-transform duration-200" :class="open === {{ $i }} && 'rotate-180'" style="color: var(--lp-text-muted);" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </button>
                        <div x-show="open === {{ $i }}" x-cloak x-collapse>
                            <p class="px-6 pb-4 text-sm leading-relaxed" style="color: var(--lp-text-soft);">{{ $faq['a'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- FINAL CTA --}}
    {{-- ══════════════════════════════════════════ --}}
    <section style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-6xl px-6 py-20 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Ready to Run Your Next Match?</h2>
            <p class="mx-auto mt-3 max-w-md" style="color: var(--lp-text-muted);">Create your free account and set up your first match in minutes.</p>
            <div class="mt-8 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                <a href="{{ app_url('/register') }}" class="rounded-xl px-8 py-3.5 text-lg font-bold text-white transition-all" style="background: var(--lp-red); box-shadow: 0 4px 20px rgba(225, 6, 0, 0.25);" onmouseover="this.style.background='var(--lp-red-hover)'" onmouseout="this.style.background='var(--lp-red)'">
                    Get Started Free
                </a>
                <a href="{{ app_url('/login') }}" class="rounded-xl px-8 py-3.5 text-lg font-semibold transition-colors" style="border: 1px solid var(--lp-border); color: var(--lp-text);" onmouseover="this.style.background='var(--lp-surface-2)'" onmouseout="this.style.background='transparent'">
                    Sign In
                </a>
            </div>
        </div>
    </section>

</x-layouts.marketing>
