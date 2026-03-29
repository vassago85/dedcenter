<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.marketing')]
    #[Title('Setup Guide — DeadCenter')]
    class extends Component {
}; ?>

<section class="border-b border-border bg-surface/50">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">Setting Up Your First Match</h2>
            <p class="mt-3 text-muted max-w-xl mx-auto">A step-by-step guide from account creation to live scoring.</p>
        </div>
        <div class="mx-auto max-w-3xl space-y-8">

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-accent text-sm font-black text-primary">1</div>
                </div>
                <div>
                    <h4 class="font-semibold text-lg text-primary">Create an account &amp; organisation</h4>
                    <p class="mt-1 text-sm text-muted leading-relaxed">
                        Register for a free account, then create your club or league from the "Organisations" page.
                        Your organisation is submitted for approval &mdash; once approved, you&rsquo;re the owner and can invite other admins.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-accent text-sm font-black text-primary">2</div>
                </div>
                <div>
                    <h4 class="font-semibold text-lg text-primary">Create a match</h4>
                    <p class="mt-1 text-sm text-muted leading-relaxed">
                        Go to your org dashboard, click <strong class="text-primary">New Match</strong>, and fill in the name, date, location, and entry fee.
                        Choose <strong class="text-primary">Standard</strong> for multiplier-based scoring or <strong class="text-primary">PRS</strong> for hit/miss/timed scoring.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-accent text-sm font-black text-primary">3</div>
                </div>
                <div>
                    <h4 class="font-semibold text-lg text-primary">Set up divisions &amp; categories (optional)</h4>
                    <p class="mt-1 text-sm text-muted leading-relaxed">
                        Add divisions for equipment classes (use the Open/Factory/Limited or Minor/Major presets, or create your own).
                        Add categories for demographics (use the standard preset for Overall/Ladies/Junior/Senior, or create custom ones).
                        Both are optional &mdash; without them, all shooters compete in a single pool.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-accent text-sm font-black text-primary">4</div>
                </div>
                <div>
                    <h4 class="font-semibold text-lg text-primary">Add target sets &amp; targets</h4>
                    <p class="mt-1 text-sm text-muted leading-relaxed">
                        Add one target set per stage (e.g. "100m", "200m"). Inside each, add targets (gongs) with multipliers for standard, or use the
                        quick-add PRS preset buttons (5, 8, or 10 targets at 1pt each). For PRS, you can set a <strong class="text-primary">par time</strong> per stage
                        and designate one stage as the <strong class="text-primary">tiebreaker</strong> (impacts first, then time).
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-accent text-sm font-black text-primary">5</div>
                </div>
                <div>
                    <h4 class="font-semibold text-lg text-primary">Add squads &amp; shooters</h4>
                    <p class="mt-1 text-sm text-muted leading-relaxed">
                        Create squads (e.g. "Squad A", "Squad B") and add shooters to each. Assign each shooter a division (single-select dropdown)
                        and categories (multi-select checkboxes). Bib numbers are optional.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-accent text-sm font-black text-primary">6</div>
                </div>
                <div>
                    <h4 class="font-semibold text-lg text-primary">Start the match &amp; share the live link</h4>
                    <p class="mt-1 text-sm text-muted leading-relaxed">
                        Click <strong class="text-primary">Start Match</strong>. A QR code and shareable link appear &mdash; spectators scan it to follow live on their phones.
                        Put the TV scoreboard URL on a big screen at the range if you have one.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-accent text-sm font-black text-primary">7</div>
                </div>
                <div>
                    <h4 class="font-semibold text-lg text-primary">Score on tablets</h4>
                    <p class="mt-1 text-sm text-muted leading-relaxed">
                        Range Officers open <strong class="text-primary">/score</strong> on their tablets, select the active match, and start scoring.
                        For Standard: tap HIT/MISS per gong per shooter in relay order.
                        For PRS: tap Hit/Miss/Not Taken per target for each shooter per stage, enter the stage time, and complete the stage.
                        Everything works offline &mdash; scores sync when signal is available.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-accent text-sm font-black text-primary">8</div>
                </div>
                <div>
                    <h4 class="font-semibold text-lg text-primary">Complete the match</h4>
                    <p class="mt-1 text-sm text-muted leading-relaxed">
                        Once all stages are done and scores are synced, click <strong class="text-primary">Complete Match</strong>. The match moves to completed status.
                        Scores count toward the season leaderboard. You can reopen a match if needed.
                    </p>
                </div>
            </div>

        </div>
    </div>
</section>

<section class="border-b border-border">
    <div class="mx-auto max-w-6xl px-6 py-16 text-center">
        <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">Ready to Score?</h2>
        <p class="mx-auto mt-3 max-w-md text-muted">Set up your first match in minutes. Free to use.</p>
        <div class="mt-8 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-xl bg-accent px-8 py-3.5 text-lg font-bold text-primary shadow-lg shadow-accent/20 transition-all hover:bg-accent-hover">
                    Go to Dashboard
                </a>
            @else
                <a href="{{ route('register') }}" class="rounded-xl bg-accent px-8 py-3.5 text-lg font-bold text-primary shadow-lg shadow-accent/20 transition-all hover:bg-accent-hover">
                    Get Started Free
                </a>
                <a href="{{ route('login') }}" class="rounded-xl border border-border px-8 py-3.5 text-lg font-semibold text-primary transition-colors hover:bg-surface">
                    Sign In
                </a>
            @endauth
        </div>
    </div>
</section>
