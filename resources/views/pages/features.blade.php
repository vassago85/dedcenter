<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.marketing')]
    #[Title('Features — DeadCenter')]
    class extends Component {
}; ?>

<section class="py-20 lg:py-28" style="border-bottom: 1px solid var(--lp-border); background: var(--lp-bg-2);">
    <div class="mx-auto max-w-6xl px-6">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Built for the Range</h2>
            <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-soft);">Everything you need to run a smooth match &mdash; from setup to final standings.</p>
        </div>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-amber-600/10">
                    <svg class="h-6 w-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Offline-First Scoring</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">No signal at the range? No problem. Scores are saved locally on the device and sync automatically when connectivity returns.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(225, 6, 0, 0.08);">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="color: var(--lp-red);">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Live Scoreboards</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">TV scoreboard for the range, plus a mobile-friendly live page spectators can open by scanning a QR code. Auto-refreshes every 10 seconds.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/10">
                    <svg class="h-6 w-6 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">PRS Scoring</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Hit / Miss / Shot Not Taken buttons for each target. Timed stages with smart decimal input, tiebreaker stage support, and par time auto-fill.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600/10">
                    <svg class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" />
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Divisions &amp; Categories</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Split shooters by equipment class (Open, Factory, Limited) and demographics (Overall, Ladies, Junior, Senior). Filter scoreboards by either axis.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-green-600/10">
                    <svg class="h-6 w-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Leagues &amp; Clubs</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Create a league, add clubs underneath it. Season leaderboards aggregate scores across matches with best-of-N scoring.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-purple-600/10">
                    <svg class="h-6 w-6 text-purple-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Match Director Control</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Only the person who created a match can edit or delete it. Multiple admins per club, but each match director owns their match.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-green-600/10">
                    <svg class="h-6 w-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">QR Code Sharing</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">A QR code is generated for every active match. Print it or show it on screen &mdash; spectators scan it and get the live scoreboard on their phone.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600/10">
                    <svg class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Multi-Device Sync</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Multiple Range Officers can score simultaneously on different tablets. All scores merge on the server and appear on the scoreboard in real-time.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(142, 160, 191, 0.1);">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="color: var(--lp-text-muted);">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Match Registration &amp; Fees</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Shooters register for matches online. Set entry fees, approve/reject registrations, and track payments. Free matches are supported too.</p>
            </div>

            <div class="rounded-2xl border border-amber-800/30 p-8 ring-1 ring-amber-600/10" style="background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-amber-600/10">
                    <svg class="h-6 w-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0 1 16.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.003 6.003 0 0 1-3.77 1.522m0 0a6.003 6.003 0 0 1-3.77-1.522" />
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Side Bet (Royal Flush)</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Optional side competition for round-robin matches. The winner is whoever hits the most smallest gongs. Ties break by furthest distance, then cascade to the next gong size.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(225, 6, 0, 0.08);">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="color: var(--lp-red);">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Scoring Security &amp; Squad Lock</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Only authorized users can submit scores. The Match Director logs into each tablet and locks it to a specific squad to prevent accidental edits on other squads.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-600/10">
                    <svg class="h-6 w-6 text-cyan-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" />
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Relay-Based Gong Scoring</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Point multipliers based on gong size and difficulty. Round-robin relay flow with auto-advancing scorer. Quick-add preset targets included.</p>
            </div>

        </div>
    </div>
</section>

<section class="py-16" style="border-bottom: 1px solid var(--lp-border);">
    <div class="mx-auto max-w-6xl px-6 text-center">
        <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Ready to Score?</h2>
        <p class="mx-auto mt-3 max-w-md" style="color: var(--lp-text-soft);">Set up your first match in minutes. Free to use.</p>
        <div class="mt-8 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-xl px-8 py-3.5 text-lg font-bold text-white transition-all" style="background: var(--lp-red); box-shadow: 0 4px 20px rgba(225, 6, 0, 0.25);" onmouseover="this.style.background='var(--lp-red-hover)'" onmouseout="this.style.background='var(--lp-red)'">
                    Go to Dashboard
                </a>
            @else
                <a href="{{ route('register') }}" class="rounded-xl px-8 py-3.5 text-lg font-bold text-white transition-all" style="background: var(--lp-red); box-shadow: 0 4px 20px rgba(225, 6, 0, 0.25);" onmouseover="this.style.background='var(--lp-red-hover)'" onmouseout="this.style.background='var(--lp-red)'">
                    Get Started Free
                </a>
                <a href="{{ route('login') }}" class="rounded-xl px-8 py-3.5 text-lg font-semibold transition-colors" style="border: 1px solid var(--lp-border); color: var(--lp-text);" onmouseover="this.style.background='var(--lp-surface)'" onmouseout="this.style.background='transparent'">
                    Sign In
                </a>
            @endauth
        </div>
    </div>
</section>
