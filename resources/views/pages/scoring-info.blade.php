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

            <div class="rounded-2xl p-8 flex flex-col" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-5 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold self-start" style="background: rgba(225, 6, 0, 0.08); border: 1px solid rgba(225, 6, 0, 0.15); color: var(--lp-red);">
                    Relay Scoring
                </div>
                <h3 class="mb-2 text-xl font-bold">Synchronized Relay Format</h3>
                <p class="mb-6 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                    All relays complete each stage before advancing together. Distance-based target and gong multipliers reward accuracy at range.
                    Range Officers tap HIT or MISS.
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

            <div class="rounded-2xl p-8 flex flex-col ring-1 ring-amber-600/10" style="border: 1px solid rgba(217, 119, 6, 0.3); background: var(--lp-surface);">
                <div class="mb-5 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold self-start" style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(217, 119, 6, 0.3); color: rgb(251, 191, 36);">
                    PRS
                </div>
                <h3 class="mb-2 text-xl font-bold">Hit / Miss / Shot Not Taken</h3>
                <p class="mb-6 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                    Each shooter completes an entire stage. Every target has three state buttons: <strong class="text-green-400">Hit</strong>,
                    <strong style="color: var(--lp-red);">Miss</strong>, or <strong class="text-amber-400">Shot Not Taken</strong>.
                    If a shooter runs out of time, remaining targets stay as "shot not taken."
                </p>
                <ul class="space-y-2.5 text-sm mt-auto" style="color: var(--lp-text-soft);">
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Three-button scoring per target (Hit / Miss / Not Taken)</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 text-green-500">&#10003;</span> Timed stages with app timer or smart manual input</li>
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
                <h3 class="mb-2 text-xl font-bold">Extreme Long Range</h3>
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
    </div>
</section>

<section style="border-bottom: 1px solid var(--lp-border); background: var(--lp-bg-2);">
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
