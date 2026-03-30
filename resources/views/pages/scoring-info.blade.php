<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.marketing')]
    #[Title('Scoring Modes — DeadCenter')]
    class extends Component {
}; ?>

<section style="border-bottom: 1px solid var(--lp-border);">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">Two Scoring Modes</h2>
            <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-soft);">Choose the right format for your match.</p>
        </div>
        <div class="grid gap-8 lg:grid-cols-2">

            <div class="rounded-2xl p-8 lg:p-10" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold" style="background: rgba(225, 6, 0, 0.08); border: 1px solid rgba(225, 6, 0, 0.15); color: var(--lp-red);">
                    Relay-Based Scoring
                </div>
                <h3 class="mb-3 text-xl font-bold">Gong Multiplier System</h3>
                <p class="mb-6 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                    Each gong has a point multiplier based on size and difficulty. Shooters rotate through gongs in relay order.
                    Range Officers tap HIT or MISS for each shooter at each gong. The scorer auto-advances through the relay sequence.
                </p>
                <ul class="space-y-2 text-sm" style="color: var(--lp-text-soft);">
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

            <div class="rounded-2xl border border-amber-800/30 p-8 lg:p-10 ring-1 ring-amber-600/10" style="background: var(--lp-surface);">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-amber-800/30 px-4 py-1.5 text-sm font-semibold text-amber-400 bg-amber-600/10">
                    PRS Scoring
                </div>
                <h3 class="mb-3 text-xl font-bold">Hit / Miss / Shot Not Taken</h3>
                <p class="mb-6 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                    Each shooter completes an entire stage at once. Every target has three state buttons: <strong class="text-green-400">Hit</strong>,
                    <strong style="color: var(--lp-red);">Miss</strong>, or <strong class="text-amber-400">Shot Not Taken</strong> (the default).
                    If a shooter runs out of time, remaining targets stay as "shot not taken."
                </p>
                <ul class="space-y-2 text-sm" style="color: var(--lp-text-soft);">
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
                        Smart time input: enter seconds with optional decimal (e.g. 105.23)
                    </li>
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
