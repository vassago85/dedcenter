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

                <div class="mb-8 flex justify-center">
                    <x-landing-ad-slot placement="landing_hero_monthly" variant="cover" />
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
                        Explore Match-Day Workflow
                    </a>
                    <a href="#how-it-works"
                       class="lp-btn-secondary inline-flex items-center justify-center rounded-xl px-8 py-3.5 text-[15px] font-semibold backdrop-blur-sm transition-all duration-200">
                        See Director Workflow
                    </a>
                </div>

                <div class="mt-14 flex flex-col items-center gap-6 sm:flex-row sm:justify-center sm:gap-10">
                    <div class="flex items-center gap-2.5 text-[13px]" style="color: var(--lp-text-muted);">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full" style="background: var(--lp-surface-2);"><span class="h-1.5 w-1.5 rounded-full" style="background: rgba(225, 6, 0, 0.7);"></span></span>
                        Run PRS, Relay, and ELR in one platform
                    </div>
                    <div class="flex items-center gap-2.5 text-[13px]" style="color: var(--lp-text-muted);">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full" style="background: var(--lp-surface-2);"><span class="h-1.5 w-1.5 rounded-full" style="background: rgba(225, 6, 0, 0.7);"></span></span>
                        Keep range staff in sync across devices
                    </div>
                    <div class="flex items-center gap-2.5 text-[13px]" style="color: var(--lp-text-muted);">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full" style="background: var(--lp-surface-2);"><span class="h-1.5 w-1.5 rounded-full" style="background: rgba(225, 6, 0, 0.7);"></span></span>
                        Score from tablets with Android app and PWA
                    </div>
                </div>
            </div>

            <div class="mx-auto max-w-4xl px-2 pb-6">
                <x-landing-ad-slot placement="landing_strip_monthly" variant="block" />
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
                        ['icon' => 'plus', 'title' => 'Create Match'],
                        ['icon' => 'settings', 'title' => 'Configure Stages'],
                        ['icon' => 'layout-grid', 'title' => 'Set Divisions'],
                        ['icon' => 'clock', 'title' => 'Pre-Registration'],
                        ['icon' => 'user', 'title' => 'Open Registration'],
                        ['icon' => 'users', 'title' => 'Open Squadding'],
                        ['icon' => 'lock', 'title' => 'Close Registration'],
                        ['icon' => 'smartphone', 'title' => 'Score in Field'],
                        ['icon' => 'circle-check', 'title' => 'Review & Publish'],
                        ['icon' => 'trophy', 'title' => 'Update Standings'],
                    ] as $step)
                        <div class="text-center">
                            <div class="relative mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl" style="background: rgba(225, 6, 0, 0.08); border: 1px solid rgba(225, 6, 0, 0.15);">
                                <x-icon name="{{ $step['icon'] }}" class="h-6 w-6" style="color: var(--lp-red);" />
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
                <x-icon name="arrow-right" class="h-4 w-4" />
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
                        {{-- Flex must live inside <summary>: WebKit breaks native <details> toggle when summary is display:flex --}}
                        <summary class="cursor-pointer list-none px-6 py-4 text-left text-sm font-semibold transition-colors hover:!text-white" style="color: var(--lp-text);">
                            <span class="flex w-full items-center justify-between gap-3">
                                <span class="min-w-0">{{ $faq['q'] }}</span>
                                <x-icon name="chevron-down" class="h-4 w-4 flex-shrink-0 transition-transform duration-200 group-open:rotate-180" style="color: var(--lp-text-muted);" />
                            </span>
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
