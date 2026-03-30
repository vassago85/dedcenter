<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.marketing')]
    #[Title('Setup Guide — DeadCenter')]
    class extends Component {
}; ?>

<section style="border-bottom: 1px solid var(--lp-border); background: var(--lp-bg-2);">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Setting Up Your First Match</h2>
            <p class="mx-auto mt-3 max-w-xl" style="color: var(--lp-text-soft);">A step-by-step guide from account creation to live scoring.</p>
        </div>
        <div class="mx-auto max-w-3xl space-y-8">

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: var(--lp-red); color: white;">1</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Create an account &amp; organisation</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Register for a free account, then create your club or league from the "Organisations" page.
                        Your organisation is submitted for approval &mdash; once approved, you&rsquo;re the owner and can invite other admins.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: var(--lp-red); color: white;">2</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Create a match</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Go to your org dashboard, click <strong style="color: var(--lp-text);">New Match</strong>, and fill in the name, date, location, and entry fee.
                        Choose <strong style="color: var(--lp-text);">Relay-Based</strong> for gong multiplier scoring, <strong style="color: var(--lp-text);">PRS</strong> for hit/miss/timed scoring, or <strong style="color: var(--lp-text);">ELR</strong> for extreme long range.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: var(--lp-red); color: white;">3</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Set up divisions &amp; categories (optional)</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Add divisions for equipment classes (use the Open/Factory/Limited or Minor/Major presets, or create your own).
                        Add categories for demographics (use the standard preset for Overall/Ladies/Junior/Senior, or create custom ones).
                        Both are optional &mdash; without them, all shooters compete in a single pool.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: var(--lp-red); color: white;">4</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Add target sets &amp; targets</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Add one target set per stage (e.g. "100m", "200m"). Inside each, add targets (gongs) with multipliers for standard, or use the
                        quick-add PRS preset buttons (5, 8, or 10 targets at 1pt each). For PRS, you can set a <strong style="color: var(--lp-text);">par time</strong> per stage
                        and designate one stage as the <strong style="color: var(--lp-text);">tiebreaker</strong> (impacts first, then time).
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: var(--lp-red); color: white;">5</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Add squads &amp; shooters</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Create squads (e.g. "Squad A", "Squad B") and add shooters to each. Assign each shooter a division (single-select dropdown)
                        and categories (multi-select checkboxes). Bib numbers are optional.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: var(--lp-red); color: white;">6</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Start the match &amp; share the live link</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Click <strong style="color: var(--lp-text);">Start Match</strong>. A QR code and shareable link appear &mdash; spectators scan it to follow live on their phones.
                        Put the TV scoreboard URL on a big screen at the range if you have one.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: var(--lp-red); color: white;">7</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Score on tablets</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Range Officers open <strong style="color: var(--lp-text);">/score</strong> on their tablets, select the active match, and start scoring.
                        For Standard: tap HIT/MISS per gong per shooter in relay order.
                        For PRS: tap Hit/Miss/Not Taken per target for each shooter per stage, enter the stage time, and complete the stage.
                        Everything works offline &mdash; scores sync when signal is available.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: var(--lp-red); color: white;">8</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Complete the match</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Once all stages are done and scores are synced, click <strong style="color: var(--lp-text);">Complete Match</strong>. The match moves to completed status.
                        Scores count toward the season leaderboard. You can reopen a match if needed.
                    </p>
                </div>
            </div>

        </div>
    </div>
</section>

<section style="border-bottom: 1px solid var(--lp-border);">
    <div class="mx-auto max-w-6xl px-6 py-16 text-center">
        <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Ready to Score?</h2>
        <p class="mx-auto mt-3 max-w-md" style="color: var(--lp-text-soft);">Set up your first match in minutes. Free to use.</p>
        <div class="mt-8 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-xl px-8 py-3.5 text-lg font-bold text-white transition-all" style="background: var(--lp-red); box-shadow: 0 4px 20px rgba(225, 6, 0, 0.25);" onmouseover="this.style.background='#b80500'; this.style.boxShadow='0 6px 24px rgba(225, 6, 0, 0.35)';" onmouseout="this.style.background='var(--lp-red)'; this.style.boxShadow='0 4px 20px rgba(225, 6, 0, 0.25)';">
                    Go to Dashboard
                </a>
            @else
                <a href="{{ route('register') }}" class="rounded-xl px-8 py-3.5 text-lg font-bold text-white transition-all" style="background: var(--lp-red); box-shadow: 0 4px 20px rgba(225, 6, 0, 0.25);" onmouseover="this.style.background='#b80500'; this.style.boxShadow='0 6px 24px rgba(225, 6, 0, 0.35)';" onmouseout="this.style.background='var(--lp-red)'; this.style.boxShadow='0 4px 20px rgba(225, 6, 0, 0.25)';">
                    Get Started Free
                </a>
                <a href="{{ route('login') }}" class="rounded-xl px-8 py-3.5 text-lg font-semibold transition-colors" style="border: 1px solid var(--lp-border); color: var(--lp-text);" onmouseover="this.style.background='var(--lp-bg-2)';" onmouseout="this.style.background='transparent';">
                    Sign In
                </a>
            @endauth
        </div>
    </div>
</section>
