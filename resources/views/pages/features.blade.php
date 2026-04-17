<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.marketing')]
    #[Title('Match Scoring Features — DeadCenter Platform')]
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
                    <x-icon name="lightbulb" class="h-6 w-6 text-amber-500" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Offline-First Scoring</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">No signal at the range? No problem. The Android app uses Room DB for local storage. The PWA uses IndexedDB. Scores sync automatically when connectivity returns.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(225, 6, 0, 0.08);">
                    <x-icon name="chart-column" class="h-6 w-6" style="color: var(--lp-red);" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Live Scoreboards</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">TV scoreboard for the range, plus a mobile-friendly live page spectators can open by scanning a QR code. Auto-refreshes every 10 seconds.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/10">
                    <x-icon name="star" class="h-6 w-6 text-amber-400" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">PRS Scoring</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Hit / Miss / Shot Not Taken buttons for each target. Timed stages with smart decimal input, tiebreaker stage support, and par time auto-fill.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600/10">
                    <x-icon name="layout-grid" class="h-6 w-6 text-blue-500" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Divisions &amp; Categories</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Split shooters by equipment class (Open, Factory, Limited) and demographics (Overall, Ladies, Junior, Senior). Filter scoreboards by either axis.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-green-600/10">
                    <x-icon name="users" class="h-6 w-6 text-green-500" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Leagues &amp; Clubs</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Create a league, add clubs underneath it. Season leaderboards aggregate scores across matches with best-of-N scoring.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-purple-600/10">
                    <x-icon name="lock-open" class="h-6 w-6 text-purple-500" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Match Director Control</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Only the person who created a match can edit or delete it. Multiple admins per club, but each match director owns their match.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-green-600/10">
                    <x-icon name="smartphone" class="h-6 w-6 text-green-500" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">QR Code Sharing</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">A QR code is generated for every active match. Print it or show it on screen &mdash; spectators scan it and get the live scoreboard on their phone.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600/10">
                    <x-icon name="refresh-cw" class="h-6 w-6 text-blue-500" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Two-Way Cloud Sync</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Scores sync automatically between devices and cloud every 15 seconds. Last-write-wins conflict resolution with device ID and timestamp tracking.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(142, 160, 191, 0.1);">
                    <x-icon name="wifi" class="h-6 w-6" style="color: var(--lp-text-muted);" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Hub/Client Architecture</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Set up a local WiFi mesh for match-day scoring without internet. One tablet hosts the hub, others connect as clients. The hub bridges scores to cloud when available.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(225, 6, 0, 0.08);">
                    <x-icon name="file-text" class="h-6 w-6" style="color: var(--lp-red);" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Score Audit Trail</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Every score change is logged with device, user, and timestamp. Reshoot tracking with mandatory reasons. Score reassignment preserves full history.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(225, 6, 0, 0.08);">
                    <x-icon name="shield-check" class="h-6 w-6" style="color: var(--lp-red);" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Device Lock &amp; PIN</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Lock scoring tablets to specific stages or squads with a PIN. Prevents accidental navigation. MD override with username/password if the PIN is forgotten.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(142, 160, 191, 0.1);">
                    <x-icon name="clock" class="h-6 w-6" style="color: var(--lp-text-muted);" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Match Lifecycle</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Full lifecycle management: pre-registration, registration, squadding, scoring, review, and results phases. Each phase has its own controls and visibility rules.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-green-600/10">
                    <x-icon name="users" class="h-6 w-6 text-green-500" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Self-Service Squadding</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Shooters choose their own squad from available slots with capacity management. No more spreadsheet juggling or manual squad assignments.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600/10">
                    <x-icon name="eye" class="h-6 w-6 text-blue-500" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Score Publishing Control</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Choose to show scores live as they are entered or hold them back and publish after review. Match directors control exactly when results become visible.</p>
            </div>

            <div class="rounded-2xl border border-amber-800/30 p-8 ring-1 ring-amber-600/10" style="background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-amber-600/10">
                    <x-icon name="trophy" class="h-6 w-6 text-amber-500" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Advertising Placements</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Brands can purchase feature-based advertising on leaderboards, results, and scoring screens — connecting the shooting community with supporting businesses through "powered by" visibility.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(142, 160, 191, 0.1);">
                    <x-icon name="banknote" class="h-6 w-6" style="color: var(--lp-text-muted);" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Match Registration &amp; Fees</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Shooters register for matches online. Set entry fees, approve/reject registrations, and track payments. Free matches are supported too.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-600/10">
                    <x-icon name="layout-dashboard" class="h-6 w-6 text-cyan-500" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Relay Scoring</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Point multipliers based on gong size and distance. Synchronized relay flow with squad rotation, break screens between relays, and concurrent relay support.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-purple-600/10">
                    <x-icon name="trending-up" class="h-6 w-6 text-purple-500" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">ELR Scoring</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Extreme Long Range scoring with shot-by-shot tracking, diminishing multipliers, and must-hit-to-advance ladder progression. Static and ladder stage types with furthest-hit tracking.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-green-600/10">
                    <x-icon name="users" class="h-6 w-6 text-green-500" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Team Events</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Run team-based competitions where shooters register individually and self-select into teams. Team leaderboards aggregate member scores automatically. Configurable team sizes with create-and-join flow.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-amber-600/10">
                    <x-icon name="star" class="h-6 w-6 text-amber-500" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Achievements &amp; Badges</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Shooters earn badges for podium finishes, milestone achievements, and season performance. Public shooter profiles showcase earned badges. A badge gallery displays all available achievements.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(225, 6, 0, 0.08);">
                    <x-icon name="file-text" class="h-6 w-6" style="color: var(--lp-red);" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">PDF Match Reports</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Personal post-match PDF reports with detailed scores, stage breakdowns, and placement info. Match directors can also export full standings and match books as PDFs.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600/10">
                    <x-icon name="download" class="h-6 w-6 text-blue-500" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">CSV Exports</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Export standings and detailed score breakdowns as CSV files for any match. Available on public scoreboards and event pages for shooters and organisers alike.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-green-600/10">
                    <x-icon name="wrench" class="h-6 w-6 text-green-500" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Equipment Profiles</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Shooters save rifle, ammo, scope, and accessory presets. Load a profile during registration to auto-fill equipment fields. Manage rifles and ammo loads from the dashboard.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-purple-600/10">
                    <x-icon name="calendar" class="h-6 w-6 text-purple-500" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Multi-Day Matches</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Support for matches spanning multiple days. Per-day stage filtering on scoreboards and results. Shooters and spectators can follow day-by-day progress.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(225, 6, 0, 0.08);">
                    <x-icon name="bell" class="h-6 w-6" style="color: var(--lp-red);" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Push Notifications</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Web push notifications for registration confirmations, squadding changes, score updates, and match reminders. Shooters control which notifications they receive from their settings.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600/10">
                    <x-icon name="smartphone" class="h-6 w-6 text-blue-500" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">PWA &amp; Add to Home Screen</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Install DeadCenter as an app on any phone or tablet. Works offline, with a native-feel navigation bar for back, home, and forward on iOS and Android standalone mode.</p>
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
                <a href="{{ app_url('/dashboard') }}" class="rounded-xl px-8 py-3.5 text-lg font-bold text-white transition-all" style="background: var(--lp-red); box-shadow: 0 4px 20px rgba(225, 6, 0, 0.25);" onmouseover="this.style.background='var(--lp-red-hover)'" onmouseout="this.style.background='var(--lp-red)'">
                    Go to Dashboard
                </a>
            @else
                <a href="{{ app_url('/register') }}" class="rounded-xl px-8 py-3.5 text-lg font-bold text-white transition-all" style="background: var(--lp-red); box-shadow: 0 4px 20px rgba(225, 6, 0, 0.25);" onmouseover="this.style.background='var(--lp-red-hover)'" onmouseout="this.style.background='var(--lp-red)'">
                    Get Started Free
                </a>
                <a href="{{ app_url('/login') }}" class="rounded-xl px-8 py-3.5 text-lg font-semibold transition-colors" style="border: 1px solid var(--lp-border); color: var(--lp-text);" onmouseover="this.style.background='var(--lp-surface)'" onmouseout="this.style.background='transparent'">
                    Sign In
                </a>
            @endauth
        </div>
    </div>
</section>
