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
                        Choose <strong style="color: var(--lp-text);">Relay Scoring</strong> for gong multiplier scoring, <strong style="color: var(--lp-text);">PRS</strong> for hit/miss/timed scoring, or <strong style="color: var(--lp-text);">ELR</strong> for extreme long range.
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
                        Range Officers open the Android app or PWA on their tablets, select the active match, and start scoring.
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

{{-- ══════════════════════════════════════════ --}}
{{-- ANDROID APP SETUP --}}
{{-- ══════════════════════════════════════════ --}}
<section style="border-bottom: 1px solid var(--lp-border);">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Android App Setup</h2>
            <p class="mx-auto mt-3 max-w-xl" style="color: var(--lp-text-soft);">Install the native Android app for the best offline scoring experience.</p>
        </div>
        <div class="mx-auto max-w-3xl space-y-8">

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: rgb(52, 211, 153); color: white;">1</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Install from Google Play Store</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Search for <strong style="color: var(--lp-text);">DeadCenter</strong> on the Google Play Store and install. The app is optimised for Android tablets but works on phones too.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: rgb(52, 211, 153); color: white;">2</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Sign in with your DeadCenter account</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Use the same credentials you use on deadcenter.co.za. The app links to your account so your assigned matches and permissions are available.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: rgb(52, 211, 153); color: white;">3</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Import your match</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        From the match list, tap your match to import it. The full match data &mdash; stages, targets, squads, shooters &mdash; is downloaded and stored locally in Room DB. You can also import from a hub on the local network if there is no internet.
                    </p>
                </div>
            </div>

        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════ --}}
{{-- HUB MODE SETUP --}}
{{-- ══════════════════════════════════════════ --}}
<section style="border-bottom: 1px solid var(--lp-border); background: var(--lp-bg-2);">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Hub Mode Setup</h2>
            <p class="mx-auto mt-3 max-w-xl" style="color: var(--lp-text-soft);">Turn one tablet into a local server that coordinates scoring across all devices.</p>
        </div>
        <div class="mx-auto max-w-3xl space-y-8">

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: rgb(251, 191, 36); color: white;">1</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Start the hub server</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Open the Android app on your hub tablet, navigate to the match, and tap <strong style="color: var(--lp-text);">Start Hub</strong>. The app starts a local HTTP server and displays the hub&rsquo;s IP address on screen (e.g. <code style="color: var(--lp-text);">192.168.1.50</code>).
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: rgb(251, 191, 36); color: white;">2</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Share the IP with client tablets</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Make sure all tablets are on the same WiFi network (a portable hotspot works perfectly). Tell each Range Officer the hub IP address &mdash; they&rsquo;ll enter it on their tablet to connect.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: rgb(251, 191, 36); color: white;">3</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Clients import and start scoring</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Each client tablet imports the match from the hub over the LAN. They can start scoring immediately. Scores sync back to the hub automatically. If the hub has internet, it bridges scores to the cloud.
                    </p>
                </div>
            </div>

        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════ --}}
{{-- CLIENT MODE SETUP --}}
{{-- ══════════════════════════════════════════ --}}
<section style="border-bottom: 1px solid var(--lp-border);">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Client Mode Setup</h2>
            <p class="mx-auto mt-3 max-w-xl" style="color: var(--lp-text-soft);">Connect to a hub on the local network and start scoring in seconds.</p>
        </div>
        <div class="mx-auto max-w-3xl space-y-8">

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: rgb(96, 165, 250); color: white;">1</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Enter the hub IP address</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Open the Android app, go to settings or the connection screen, and enter the hub&rsquo;s IP address. Make sure you&rsquo;re on the same WiFi network as the hub.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: rgb(96, 165, 250); color: white;">2</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Import the match from the hub</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        The app connects to the hub and downloads the full match data &mdash; stages, targets, squads, shooters, and any existing scores. No internet required. All data is stored locally in Room DB.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: rgb(96, 165, 250); color: white;">3</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Score and sync automatically</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Start scoring your assigned stage or squad. Scores save locally first, then sync to the hub automatically in the background. The hub status indicator shows your connection state at all times.
                    </p>
                </div>
            </div>

        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════ --}}
{{-- DEVICE LOCK --}}
{{-- ══════════════════════════════════════════ --}}
<section style="border-bottom: 1px solid var(--lp-border); background: var(--lp-bg-2);">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Device Lock</h2>
            <p class="mx-auto mt-3 max-w-xl" style="color: var(--lp-text-soft);">Prevent accidental navigation and keep each tablet focused on its assigned work.</p>
        </div>
        <div class="mx-auto max-w-3xl">
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(225, 6, 0, 0.08);">
                        <x-icon name="lock" class="h-6 w-6" style="color: var(--lp-red);" />
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Set a PIN</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">The Match Director sets a numeric PIN when locking a device. The PIN prevents the Range Officer from navigating away from their assigned stage or squad.</p>
                </div>

                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(225, 6, 0, 0.08);">
                        <x-icon name="lock-open" class="h-6 w-6" style="color: var(--lp-red);" />
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">MD Override</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">If a Range Officer forgets the PIN, the Match Director can unlock the device by entering their DeadCenter username and password. No need to reset the tablet.</p>
                </div>
            </div>

            <div class="mt-8 rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <h4 class="mb-4 font-semibold" style="color: var(--lp-text);">Lock to Stage or Squad</h4>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                    When locking a device, the Match Director chooses what to lock it to &mdash; a specific <strong style="color: var(--lp-text);">stage</strong> (for PRS/ELR where each stage has its own scoring tablet) or a specific <strong style="color: var(--lp-text);">squad</strong> (for relay matches where each squad has a dedicated scorer). The lock prevents the scorer from accidentally navigating to other stages or squads.
                </p>
            </div>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════ --}}
{{-- REGISTRATION WORKFLOW --}}
{{-- ══════════════════════════════════════════ --}}
<section style="border-bottom: 1px solid var(--lp-border);">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Registration Workflow</h2>
            <p class="mx-auto mt-3 max-w-xl" style="color: var(--lp-text-soft);">The full lifecycle from match creation through to shooters selecting their own squads.</p>
        </div>
        <div class="mx-auto max-w-3xl space-y-8">

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: rgb(167, 139, 250); color: white;">1</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Create the match</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Set up the match with name, date, venue, scoring mode, entry fee, stages, and targets. The match starts in <strong style="color: var(--lp-text);">draft</strong> status and is not yet visible to shooters.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: rgb(167, 139, 250); color: white;">2</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Open pre-registration</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Advance the match to <strong style="color: var(--lp-text);">pre-registration</strong>. Shooters can now see the match and express interest without committing. This gives match directors early visibility into expected attendance and helps with planning.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: rgb(167, 139, 250); color: white;">3</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Open registration</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Advance to <strong style="color: var(--lp-text);">registration open</strong>. Shooters can now fully register with their division, category, and equipment details. Match directors can approve, reject, or waitlist entries. Entry fee tracking is built in.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: rgb(167, 139, 250); color: white;">4</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Open squadding</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Once registrations are finalised, open <strong style="color: var(--lp-text);">self-service squadding</strong>. Registered shooters choose their preferred squad from available slots. Capacity limits are enforced automatically. No more spreadsheet juggling.
                    </p>
                </div>
            </div>

            <div class="flex gap-5">
                <div class="flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black" style="background: rgb(167, 139, 250); color: white;">5</div>
                </div>
                <div>
                    <h4 class="text-lg font-semibold" style="color: var(--lp-text);">Close registration &amp; start scoring</h4>
                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Close registration when you&rsquo;re ready. The match advances to <strong style="color: var(--lp-text);">scoring</strong> status. Range Officers can now score on their tablets. Squads, stages, and shooter assignments are locked in.
                    </p>
                </div>
            </div>

        </div>
    </div>
</section>

<section style="border-bottom: 1px solid var(--lp-border); background: var(--lp-bg-2);">
    <div class="mx-auto max-w-6xl px-6 py-16 text-center">
        <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Ready to Score?</h2>
        <p class="mx-auto mt-3 max-w-md" style="color: var(--lp-text-soft);">Set up your first match in minutes. Free to use.</p>
        <div class="mt-8 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
            @auth
                <a href="{{ app_url('/dashboard') }}" class="rounded-xl px-8 py-3.5 text-lg font-bold text-white transition-all" style="background: var(--lp-red); box-shadow: 0 4px 20px rgba(225, 6, 0, 0.25);" onmouseover="this.style.background='#b80500'; this.style.boxShadow='0 6px 24px rgba(225, 6, 0, 0.35)';" onmouseout="this.style.background='var(--lp-red)'; this.style.boxShadow='0 4px 20px rgba(225, 6, 0, 0.25)';">
                    Go to Dashboard
                </a>
            @else
                <a href="{{ app_url('/register') }}" class="rounded-xl px-8 py-3.5 text-lg font-bold text-white transition-all" style="background: var(--lp-red); box-shadow: 0 4px 20px rgba(225, 6, 0, 0.25);" onmouseover="this.style.background='#b80500'; this.style.boxShadow='0 6px 24px rgba(225, 6, 0, 0.35)';" onmouseout="this.style.background='var(--lp-red)'; this.style.boxShadow='0 4px 20px rgba(225, 6, 0, 0.25)';">
                    Get Started Free
                </a>
                <a href="{{ app_url('/login') }}" class="rounded-xl px-8 py-3.5 text-lg font-semibold transition-colors" style="border: 1px solid var(--lp-border); color: var(--lp-text);" onmouseover="this.style.background='var(--lp-bg-2)';" onmouseout="this.style.background='transparent';">
                    Sign In
                </a>
            @endauth
        </div>
    </div>
</section>
