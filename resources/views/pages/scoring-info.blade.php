<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.marketing')]
    #[Title('Scoring Modes — DeadCenter')]
    class extends Component {
}; ?>

<section class="border-b border-border">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">Two Scoring Modes</h2>
            <p class="mt-3 text-muted max-w-xl mx-auto">Choose the right format for your match.</p>
        </div>
        <div class="grid gap-8 lg:grid-cols-2">

            <div class="rounded-2xl border border-border bg-surface p-8 lg:p-10">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-accent/10 border border-accent/20 px-4 py-1.5 text-sm font-semibold text-accent">
                    Standard Scoring
                </div>
                <h3 class="mb-3 text-xl font-bold">Gong Multiplier System</h3>
                <p class="mb-6 text-sm text-muted leading-relaxed">
                    Each gong has a point multiplier based on size and difficulty. Shooters rotate through gongs in relay order.
                    Range Officers tap HIT or MISS for each shooter at each gong. The scorer auto-advances through the relay sequence.
                </p>
                <ul class="space-y-2 text-sm text-muted">
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

            <div class="rounded-2xl border border-amber-800/30 bg-surface p-8 lg:p-10 ring-1 ring-amber-600/10">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-amber-600/10 border border-amber-800/30 px-4 py-1.5 text-sm font-semibold text-amber-400">
                    PRS Scoring
                </div>
                <h3 class="mb-3 text-xl font-bold">Hit / Miss / Shot Not Taken</h3>
                <p class="mb-6 text-sm text-muted leading-relaxed">
                    Each shooter completes an entire stage at once. Every target has three state buttons: <strong class="text-green-400">Hit</strong>,
                    <strong class="text-accent">Miss</strong>, or <strong class="text-amber-400">Shot Not Taken</strong> (the default).
                    If a shooter runs out of time, remaining targets stay as "shot not taken."
                </p>
                <ul class="space-y-2 text-sm text-muted">
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

<section class="border-b border-border bg-surface/50">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">Divisions &amp; Categories</h2>
            <p class="mt-3 text-muted max-w-2xl mx-auto">Two independent axes for slicing leaderboards. Together they form a matrix so you can view standings for any combination.</p>
        </div>
        <div class="grid gap-8 lg:grid-cols-2">
            <div class="rounded-2xl border border-border bg-surface p-8">
                <h3 class="mb-1 text-lg font-bold text-accent">Divisions</h3>
                <p class="mb-4 text-xs text-muted uppercase tracking-wider">What gear class are you competing in?</p>
                <p class="mb-4 text-sm text-muted leading-relaxed">
                    Divisions classify competitors by equipment. Each shooter selects <strong class="text-primary">one division</strong> per match (single-select).
                </p>
                <div class="space-y-2">
                    <div class="flex items-center gap-3 rounded-lg bg-surface-2/50 px-4 py-2">
                        <span class="rounded bg-accent/20 px-2 py-0.5 text-xs font-bold text-accent">Open</span>
                        <span class="text-sm text-muted">Unrestricted equipment</span>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg bg-surface-2/50 px-4 py-2">
                        <span class="rounded bg-accent/20 px-2 py-0.5 text-xs font-bold text-accent">Factory</span>
                        <span class="text-sm text-muted">Factory-stock rifle, no mods</span>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg bg-surface-2/50 px-4 py-2">
                        <span class="rounded bg-accent/20 px-2 py-0.5 text-xs font-bold text-accent">Limited</span>
                        <span class="text-sm text-muted">Limited modifications allowed</span>
                    </div>
                </div>
                <p class="mt-4 text-xs text-muted/60">Presets included or create your own (e.g. Minor / Major by calibre).</p>
            </div>
            <div class="rounded-2xl border border-border bg-surface p-8">
                <h3 class="mb-1 text-lg font-bold text-blue-400">Categories</h3>
                <p class="mb-4 text-xs text-muted uppercase tracking-wider">What demographic group(s) do you belong to?</p>
                <p class="mb-4 text-sm text-muted leading-relaxed">
                    Categories classify competitors by who they are. A shooter can belong to <strong class="text-primary">multiple categories</strong> (multi-select).
                    A single score appears in all matching category leaderboards.
                </p>
                <div class="space-y-2">
                    <div class="flex items-center gap-3 rounded-lg bg-surface-2/50 px-4 py-2">
                        <span class="rounded bg-blue-600/20 px-2 py-0.5 text-xs font-bold text-blue-400">Overall</span>
                        <span class="text-sm text-muted">All shooters &mdash; the default</span>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg bg-surface-2/50 px-4 py-2">
                        <span class="rounded bg-blue-600/20 px-2 py-0.5 text-xs font-bold text-blue-400">Ladies</span>
                        <span class="text-sm text-muted">Female shooters</span>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg bg-surface-2/50 px-4 py-2">
                        <span class="rounded bg-blue-600/20 px-2 py-0.5 text-xs font-bold text-blue-400">Junior</span>
                        <span class="text-sm text-muted">Under 21 (centrefire) / Under 18 (rimfire) as of 1 Jan</span>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg bg-surface-2/50 px-4 py-2">
                        <span class="rounded bg-blue-600/20 px-2 py-0.5 text-xs font-bold text-blue-400">Senior</span>
                        <span class="text-sm text-muted">55+</span>
                    </div>
                </div>
                <p class="mt-4 text-xs text-muted/60">Standard presets included or create your own.</p>
            </div>
        </div>
        <div class="mt-10 rounded-2xl border border-border bg-surface-2/50 p-6 lg:p-8">
            <h4 class="mb-3 text-center font-semibold text-primary">Leaderboard Matrix</h4>
            <p class="mb-5 text-center text-sm text-muted">Filter by division, category, or both.</p>
            <div class="overflow-x-auto">
                <table class="mx-auto text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-2"></th>
                            <th class="px-4 py-2 text-center text-accent font-semibold">Open</th>
                            <th class="px-4 py-2 text-center text-accent font-semibold">Factory</th>
                            <th class="px-4 py-2 text-center text-accent font-semibold">Limited</th>
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
