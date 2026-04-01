<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.marketing')]
    #[Title('Relay, PRS & ELR Scoring Modes — DeadCenter')]
    class extends Component {
}; ?>

<section style="border-bottom: 1px solid var(--lp-border);">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">Three Scoring Engines</h2>
            <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-soft);">From gong shoots to precision rifle to extreme long range &mdash; pick the engine that fits your match.</p>
        </div>
        <div class="grid gap-8 lg:grid-cols-3">

            <div class="rounded-2xl p-8 flex flex-col ring-1 ring-amber-600/10" style="border: 1px solid rgba(217, 119, 6, 0.3); background: var(--lp-surface);">
                <div class="mb-5 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold self-start" style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(217, 119, 6, 0.3); color: rgb(251, 191, 36);">
                    PRS
                </div>
                <h3 class="mb-2 text-xl font-bold">Precision Rifle Series</h3>
                <p class="mb-6 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                    Stage-based precision rifle scoring. Each shooter completes an entire stage before the next shooter begins.
                    Multiple positions and targets per stage with hit/miss tracking and timed tiebreaker stages.
                </p>
                <ul class="space-y-2.5 text-sm mt-auto" style="color: var(--lp-text-soft);">
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Multi-position, multi-target per stage</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Hit / Miss / Not Taken per shot</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Tiebreaker stages with mandatory time</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> One shooter fully scored per stage</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Automatic timed stage for tiebreakers</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Open / Factory / Limited division presets</li>
                </ul>
            </div>

            <div class="rounded-2xl p-8 flex flex-col ring-1 ring-emerald-600/10" style="border: 1px solid rgba(5, 150, 105, 0.3); background: var(--lp-surface);">
                <div class="mb-5 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold self-start" style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(5, 150, 105, 0.3); color: rgb(52, 211, 153);">
                    ELR
                </div>
                <h3 class="mb-2 text-xl font-bold">Extreme Long Range</h3>
                <p class="mb-6 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                    Shot-by-shot scoring at extreme distances. Points awarded per impact with diminishing shot multipliers
                    and optional must-hit-to-advance ladder progression.
                </p>
                <ul class="space-y-2.5 text-sm mt-auto" style="color: var(--lp-text-soft);">
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-emerald-500">&#10003;</span> Distance-based stages with shot scoring</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-emerald-500">&#10003;</span> Points awarded per impact</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-emerald-500">&#10003;</span> Detailed shot-by-shot breakdowns</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-emerald-500">&#10003;</span> Must-hit-to-advance progression</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-emerald-500">&#10003;</span> Shot multiplier profiles</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-emerald-500">&#10003;</span> Normalized percentage standings</li>
                </ul>
            </div>

            <div class="rounded-2xl p-8 flex flex-col" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-5 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold self-start" style="background: rgba(225, 6, 0, 0.08); border: 1px solid rgba(225, 6, 0, 0.15); color: var(--lp-red);">
                    Relay / Standard
                </div>
                <h3 class="mb-2 text-xl font-bold">Synchronized Relay Format</h3>
                <p class="mb-6 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                    Traditional relay-based scoring where all relays complete each stage before advancing together.
                    Distance-based target and gong multipliers reward accuracy at range.
                </p>
                <ul class="space-y-2.5 text-sm mt-auto" style="color: var(--lp-text-soft);">
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Distance cards with expandable relay lists</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Squad rotation between stages</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Break screens between relays</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Concurrent relay support</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Gong multiplier scoring</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-amber-500">&#10003;</span> Optional <strong class="text-amber-400">Side Bet</strong> mode</li>
                </ul>
            </div>

        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════ --}}
{{-- HOW SCORES SYNC --}}
{{-- ══════════════════════════════════════════ --}}
<section style="border-bottom: 1px solid var(--lp-border); background: var(--lp-bg-2);">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">How Scores Sync</h2>
            <p class="mt-3 max-w-2xl mx-auto" style="color: var(--lp-text-soft);">From the moment a Range Officer taps a score to the moment it appears on the live scoreboard &mdash; the sync pipeline handles every step.</p>
        </div>

        <div class="mx-auto max-w-4xl">
            <div class="relative">
                <div class="hidden sm:block absolute top-0 bottom-0 left-[27px] w-0.5" style="background: linear-gradient(to bottom, var(--lp-red), rgba(225,6,0,0.15));"></div>

                <div class="space-y-10">
                    <div class="flex gap-5">
                        <div class="flex-shrink-0 relative z-10">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl text-sm font-black" style="background: rgba(225, 6, 0, 0.08); border: 1px solid rgba(225, 6, 0, 0.15); color: var(--lp-red);">1</div>
                        </div>
                        <div class="rounded-2xl p-6 flex-1" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                            <h4 class="font-semibold mb-2" style="color: var(--lp-text);">Device &mdash; Local Save</h4>
                            <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">The Range Officer taps Hit, Miss, or enters a score on their tablet. The score is instantly saved to the device&rsquo;s local database (Room DB on Android, IndexedDB in the browser). No network needed.</p>
                        </div>
                    </div>

                    <div class="flex gap-5">
                        <div class="flex-shrink-0 relative z-10">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl text-sm font-black" style="background: rgba(225, 6, 0, 0.08); border: 1px solid rgba(225, 6, 0, 0.15); color: var(--lp-red);">2</div>
                        </div>
                        <div class="rounded-2xl p-6 flex-1" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                            <h4 class="font-semibold mb-2" style="color: var(--lp-text);">Client &rarr; Hub &mdash; LAN Sync</h4>
                            <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">If the device is a client connected to a hub, it pushes unsynced scores to the hub over the local WiFi network. The hub merges scores from all connected clients using upsert logic. No internet required at this stage.</p>
                        </div>
                    </div>

                    <div class="flex gap-5">
                        <div class="flex-shrink-0 relative z-10">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl text-sm font-black" style="background: rgba(225, 6, 0, 0.08); border: 1px solid rgba(225, 6, 0, 0.15); color: var(--lp-red);">3</div>
                        </div>
                        <div class="rounded-2xl p-6 flex-1" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                            <h4 class="font-semibold mb-2" style="color: var(--lp-text);">Hub &rarr; Cloud &mdash; Bridge Sync</h4>
                            <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">When the hub has internet connectivity, it pushes all collected scores to deadcenter.co.za. The cloud server processes the batch using the same upsert logic. Devices in cloud mode skip the hub and sync directly.</p>
                        </div>
                    </div>

                    <div class="flex gap-5">
                        <div class="flex-shrink-0 relative z-10">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl text-sm font-black" style="background: rgba(225, 6, 0, 0.08); border: 1px solid rgba(225, 6, 0, 0.15); color: var(--lp-red);">4</div>
                        </div>
                        <div class="rounded-2xl p-6 flex-1" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                            <h4 class="font-semibold mb-2" style="color: var(--lp-text);">Cloud &rarr; Scoreboard &mdash; Live Display</h4>
                            <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Once scores reach the cloud, the TV scoreboard and mobile live page pick them up on their next refresh cycle. Spectators see results appear in near real-time. QR codes at the range link directly to the live scoreboard.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-12 rounded-2xl p-6 lg:p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <h4 class="mb-4 font-semibold text-center" style="color: var(--lp-text);">Sync Architecture at a Glance</h4>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg p-4 text-center" style="background: var(--lp-surface-2);">
                        <p class="text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--lp-red);">Standalone</p>
                        <p class="text-sm" style="color: var(--lp-text-soft);">Device &rarr; Cloud</p>
                    </div>
                    <div class="rounded-lg p-4 text-center" style="background: var(--lp-surface-2);">
                        <p class="text-xs font-bold uppercase tracking-wider mb-1" style="color: rgb(251, 191, 36);">Hub + Clients</p>
                        <p class="text-sm" style="color: var(--lp-text-soft);">Clients &rarr; Hub &rarr; Cloud</p>
                    </div>
                    <div class="rounded-lg p-4 text-center" style="background: var(--lp-surface-2);">
                        <p class="text-xs font-bold uppercase tracking-wider mb-1" style="color: rgb(52, 211, 153);">Pure Offline</p>
                        <p class="text-sm" style="color: var(--lp-text-soft);">Device &rarr; Hub (LAN only)</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section style="border-bottom: 1px solid var(--lp-border);">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">Divisions &amp; Categories</h2>
            <p class="mt-3 max-w-2xl mx-auto" style="color: var(--lp-text-soft);">Two independent axes for slicing leaderboards. Together they form a matrix so you can view standings for any combination.</p>
        </div>
        <div class="grid gap-8 lg:grid-cols-2">
            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <h3 class="mb-1 text-lg font-bold" style="color: var(--lp-red);">Divisions</h3>
                <p class="mb-4 text-xs uppercase tracking-wider" style="color: var(--lp-text-muted);">What gear class are you competing in?</p>
                <p class="mb-4 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                    Divisions classify competitors by equipment. Each shooter selects <strong style="color: var(--lp-text);">one division</strong> per match (single-select).
                </p>
                <div class="space-y-2">
                    <div class="flex items-center gap-3 rounded-lg px-4 py-2" style="background: var(--lp-surface-2);">
                        <span class="rounded px-2 py-0.5 text-xs font-bold" style="background: rgba(225, 6, 0, 0.15); color: var(--lp-red);">Open</span>
                        <span class="text-sm" style="color: var(--lp-text-soft);">Unrestricted equipment</span>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg px-4 py-2" style="background: var(--lp-surface-2);">
                        <span class="rounded px-2 py-0.5 text-xs font-bold" style="background: rgba(225, 6, 0, 0.15); color: var(--lp-red);">Factory</span>
                        <span class="text-sm" style="color: var(--lp-text-soft);">Factory-stock rifle, no mods</span>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg px-4 py-2" style="background: var(--lp-surface-2);">
                        <span class="rounded px-2 py-0.5 text-xs font-bold" style="background: rgba(225, 6, 0, 0.15); color: var(--lp-red);">Limited</span>
                        <span class="text-sm" style="color: var(--lp-text-soft);">Limited modifications allowed</span>
                    </div>
                </div>
                <p class="mt-4 text-xs" style="color: var(--lp-text-muted); opacity: 0.65;">Presets included or create your own (e.g. Minor / Major by calibre).</p>
            </div>
            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <h3 class="mb-1 text-lg font-bold text-blue-400">Categories</h3>
                <p class="mb-4 text-xs uppercase tracking-wider" style="color: var(--lp-text-muted);">What demographic group(s) do you belong to?</p>
                <p class="mb-4 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                    Categories classify competitors by who they are. A shooter can belong to <strong style="color: var(--lp-text);">multiple categories</strong> (multi-select).
                    A single score appears in all matching category leaderboards.
                </p>
                <div class="space-y-2">
                    <div class="flex items-center gap-3 rounded-lg px-4 py-2" style="background: var(--lp-surface-2);">
                        <span class="rounded bg-blue-600/20 px-2 py-0.5 text-xs font-bold text-blue-400">Overall</span>
                        <span class="text-sm" style="color: var(--lp-text-soft);">All shooters &mdash; the default</span>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg px-4 py-2" style="background: var(--lp-surface-2);">
                        <span class="rounded bg-blue-600/20 px-2 py-0.5 text-xs font-bold text-blue-400">Ladies</span>
                        <span class="text-sm" style="color: var(--lp-text-soft);">Female shooters</span>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg px-4 py-2" style="background: var(--lp-surface-2);">
                        <span class="rounded bg-blue-600/20 px-2 py-0.5 text-xs font-bold text-blue-400">Junior</span>
                        <span class="text-sm" style="color: var(--lp-text-soft);">Under 21 (centrefire) / Under 18 (rimfire) as of 1 Jan</span>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg px-4 py-2" style="background: var(--lp-surface-2);">
                        <span class="rounded bg-blue-600/20 px-2 py-0.5 text-xs font-bold text-blue-400">Senior</span>
                        <span class="text-sm" style="color: var(--lp-text-soft);">55+</span>
                    </div>
                </div>
                <p class="mt-4 text-xs" style="color: var(--lp-text-muted); opacity: 0.65;">Standard presets included or create your own.</p>
            </div>
        </div>
        <div class="mt-10 rounded-2xl p-6 lg:p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface-2);">
            <h4 class="mb-3 text-center font-semibold" style="color: var(--lp-text);">Leaderboard Matrix</h4>
            <p class="mb-5 text-center text-sm" style="color: var(--lp-text-soft);">Filter by division, category, or both.</p>
            <div class="overflow-x-auto">
                <table class="mx-auto text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-2"></th>
                            <th class="px-4 py-2 text-center font-semibold" style="color: var(--lp-red);">Open</th>
                            <th class="px-4 py-2 text-center font-semibold" style="color: var(--lp-red);">Factory</th>
                            <th class="px-4 py-2 text-center font-semibold" style="color: var(--lp-red);">Limited</th>
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
