<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.marketing')]
    #[Title('Offline & Multi-Device Scoring — DeadCenter')]
    class extends Component {
}; ?>

<section style="border-bottom: 1px solid var(--lp-border);">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">How Offline Scoring Works</h2>
            <p class="mt-3 max-w-2xl mx-auto" style="color: var(--lp-text-soft);">Ranges often have poor or no mobile signal. DeadCenter is built from the ground up to handle this &mdash; with a native Android app, a browser PWA, and a local hub/client mesh.</p>
        </div>
        <div class="grid gap-8 lg:grid-cols-2">
            <div class="space-y-6">
                <div class="flex gap-4">
                    <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-full bg-amber-600 text-sm font-black" style="color: var(--lp-text);">1</div>
                    <div>
                        <h4 class="font-semibold" style="color: var(--lp-text);">Import the match while online (or from the hub)</h4>
                        <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">Before heading to the range, open the Android app or PWA and select your match. The full match data &mdash; target sets, squads, shooters, and existing scores &mdash; is downloaded. On the Android app, data is stored in Room DB. On the PWA, it uses IndexedDB. Alternatively, import the match directly from a hub on the local network without any internet.</p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-full bg-amber-600 text-sm font-black" style="color: var(--lp-text);">2</div>
                    <div>
                        <h4 class="font-semibold" style="color: var(--lp-text);">Score offline &mdash; everything saves locally</h4>
                        <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">Every tap of Hit, Miss, or Shot Not Taken is instantly saved to the device&rsquo;s local database. Stage times are saved locally too. The app works exactly the same whether you&rsquo;re online or offline &mdash; no loading spinners, no errors.</p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-full bg-amber-600 text-sm font-black" style="color: var(--lp-text);">3</div>
                    <div>
                        <h4 class="font-semibold" style="color: var(--lp-text);">Sync to hub or cloud when ready</h4>
                        <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">A background sync runs every 15 seconds. If connected to a hub on the LAN, scores push to the hub. If internet is available, they go directly to the cloud. The hub can also bridge scores from clients to the cloud. Duplicate submissions are harmless &mdash; the server uses upsert logic.</p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-full bg-amber-600 text-sm font-black" style="color: var(--lp-text);">4</div>
                    <div>
                        <h4 class="font-semibold" style="color: var(--lp-text);">Scoreboards update live</h4>
                        <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">Once scores reach the cloud, the TV scoreboard and mobile live page pick them up on their next refresh cycle. Spectators see results appear in real-time as devices sync.</p>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <h4 class="mb-4 font-semibold" style="color: var(--lp-text);">Under the Hood</h4>
                <div class="space-y-4 text-sm" style="color: var(--lp-text-soft);">
                    <div class="rounded-lg p-4" style="background: var(--lp-surface-2);">
                        <p class="mb-1 text-xs font-bold text-amber-400 uppercase tracking-wider">Android App &mdash; Room DB</p>
                        <p>The native Android app uses <strong style="color: var(--lp-text);">Room DB</strong> for local storage. Scores persist across app restarts. The app supports standalone, hub, client, and cloud modes.</p>
                    </div>
                    <div class="rounded-lg p-4" style="background: var(--lp-surface-2);">
                        <p class="mb-1 text-xs font-bold text-amber-400 uppercase tracking-wider">PWA &mdash; IndexedDB</p>
                        <p>The progressive web app stores scores in <strong style="color: var(--lp-text);">IndexedDB</strong> via Dexie.js. Each score is keyed by (shooterId, targetId) so re-tapping the same target overwrites rather than duplicates.</p>
                    </div>
                    <div class="rounded-lg p-4" style="background: var(--lp-surface-2);">
                        <p class="mb-1 text-xs font-bold text-amber-400 uppercase tracking-wider">Dual Sync Paths</p>
                        <p>Each device shows <strong style="color: var(--lp-text);">two sync indicators</strong>: hub connectivity (LAN) and cloud connectivity (internet). Scores can flow through either or both paths simultaneously.</p>
                    </div>
                    <div class="rounded-lg p-4" style="background: var(--lp-surface-2);">
                        <p class="mb-1 text-xs font-bold text-amber-400 uppercase tracking-wider">Conflict Resolution</p>
                        <p>Last-write-wins. Each score carries a <strong style="color: var(--lp-text);">device_id</strong> and <strong style="color: var(--lp-text);">recorded_at</strong> timestamp. Multiple devices can score different stages simultaneously without conflict.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════ --}}
{{-- HUB / CLIENT ARCHITECTURE --}}
{{-- ══════════════════════════════════════════ --}}
<section style="border-bottom: 1px solid var(--lp-border); background: var(--lp-bg-2);">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Hub / Client WiFi Mesh</h2>
            <p class="mt-3 max-w-2xl mx-auto" style="color: var(--lp-text-soft);">Set up a local scoring network at the range with zero internet. One tablet runs as the hub, the rest connect as clients.</p>
        </div>

        <div class="grid gap-8 lg:grid-cols-3">
            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(225, 6, 0, 0.08);">
                    <x-icon name="server" class="h-6 w-6" style="color: var(--lp-red);" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Hub Tablet</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">One Android tablet starts a local server. It displays its IP address on screen. The hub collects scores from all connected clients and can also be used for scoring.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(59, 130, 246, 0.08);">
                    <x-icon name="smartphone" class="h-6 w-6 text-blue-500" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Client Tablets</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Other tablets enter the hub&rsquo;s IP address and connect as clients. They import the match from the hub, score their assigned stage or squad, and sync scores back over WiFi.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(16, 185, 129, 0.08);">
                    <x-icon name="globe" class="h-6 w-6 text-green-500" />
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Bridge to Cloud</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">When the hub has internet (mobile hotspot, venue WiFi), it bridges all collected scores to deadcenter.co.za. Client tablets never need internet directly &mdash; the hub handles it.</p>
            </div>
        </div>

        <div class="mt-12 rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
            <h4 class="mb-4 font-semibold text-center" style="color: var(--lp-text);">Import Match from Hub</h4>
            <p class="text-sm leading-relaxed text-center max-w-2xl mx-auto" style="color: var(--lp-text-soft);">Client tablets don&rsquo;t need internet to get the match data. When a client connects to the hub, it can import the full match &mdash; stages, targets, squads, shooters, and existing scores &mdash; directly from the hub over the local network. This is especially useful at remote ranges with no cell coverage.</p>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════ --}}
{{-- ANDROID APP --}}
{{-- ══════════════════════════════════════════ --}}
<section style="border-bottom: 1px solid var(--lp-border);">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Android App</h2>
            <p class="mt-3 max-w-2xl mx-auto" style="color: var(--lp-text-soft);">A native Android app purpose-built for scoring on tablets in the field. Available on the Google Play Store.</p>
        </div>

        <div class="mx-auto max-w-3xl">
            <div class="rounded-2xl p-8 lg:p-10" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="space-y-6">
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl" style="background: rgba(225, 6, 0, 0.08);">
                            <x-icon name="download" class="h-5 w-5" style="color: var(--lp-red);" />
                        </div>
                        <div>
                            <h4 class="font-semibold" style="color: var(--lp-text);">Room DB Local Storage</h4>
                            <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">All match data and scores are stored in Room DB on the device. Data persists across app restarts and survives loss of connectivity. No browser cache limitations.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl bg-green-600/10">
                            <x-icon name="wifi" class="h-5 w-5 text-green-500" />
                        </div>
                        <div>
                            <h4 class="font-semibold" style="color: var(--lp-text);">Hub &amp; Client Modes</h4>
                            <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">The Android app can run as a hub (hosting a local server) or as a client (connecting to a hub). This enables a full local WiFi mesh for scoring at ranges with no internet.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600/10">
                            <x-icon name="refresh-cw" class="h-5 w-5 text-blue-500" />
                        </div>
                        <div>
                            <h4 class="font-semibold" style="color: var(--lp-text);">Automatic Sync</h4>
                            <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">Background sync runs every 15 seconds. When connectivity is detected &mdash; to the hub, to the cloud, or both &mdash; all unsynced scores are batched and sent. Dual status indicators show hub and cloud connectivity separately.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl" style="background: rgba(142, 160, 191, 0.1);">
                            <x-icon name="shield-check" class="h-5 w-5" style="color: var(--lp-text-muted);" />
                        </div>
                        <div>
                            <h4 class="font-semibold" style="color: var(--lp-text);">Device Lock &amp; PIN</h4>
                            <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">Lock the app to a specific stage or squad with a PIN. Prevents accidental navigation. If a Range Officer forgets their PIN, the Match Director can unlock with their credentials.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════ --}}
{{-- DOWNLOAD FOR OFFLINE (PWA) --}}
{{-- ══════════════════════════════════════════ --}}
<section style="border-bottom: 1px solid var(--lp-border); background: var(--lp-bg-2);">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">PWA &mdash; Download for Offline</h2>
            <p class="mt-3 max-w-2xl mx-auto" style="color: var(--lp-text-soft);">Don&rsquo;t have an Android tablet? The progressive web app works on any device with a modern browser and offers the same offline scoring capabilities.</p>
        </div>
        <div class="mx-auto max-w-3xl">
            <div class="rounded-2xl p-8 lg:p-10" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="space-y-6">
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl" style="background: rgba(225, 6, 0, 0.08);">
                            <x-icon name="download" class="h-5 w-5" style="color: var(--lp-red);" />
                        </div>
                        <div>
                            <h4 class="font-semibold" style="color: var(--lp-text);">One-Tap Download</h4>
                            <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">Each match on the scoring app&rsquo;s match list has a <strong style="color: var(--lp-text);">Download</strong> button. Tap it while you have connectivity and the full match payload is saved to IndexedDB on your device.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl bg-green-600/10">
                            <x-icon name="circle-check" class="h-5 w-5 text-green-500" />
                        </div>
                        <div>
                            <h4 class="font-semibold" style="color: var(--lp-text);">Offline Ready Indicator</h4>
                            <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">Once downloaded, a green <strong class="text-green-400">Offline Ready</strong> badge appears next to the match. You can see at a glance which matches are cached and ready to score without any network connection.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600/10">
                            <x-icon name="refresh-cw" class="h-5 w-5 text-blue-500" />
                        </div>
                        <div>
                            <h4 class="font-semibold" style="color: var(--lp-text);">Auto-Sync When Connected</h4>
                            <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">A background sync runs every 15 seconds. When connectivity is detected, all unsynced scores are batched and sent to the server. A pending counter in the header shows how many items are waiting to sync.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl" style="background: rgba(142, 160, 191, 0.1);">
                            <x-icon name="trash-2" class="h-5 w-5" style="color: var(--lp-text-muted);" />
                        </div>
                        <div>
                            <h4 class="font-semibold" style="color: var(--lp-text);">Clear Cache</h4>
                            <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">Done with a match? Tap <strong style="color: var(--lp-text);">Clear</strong> to free up device storage. The match data is removed from the local database but nothing on the server is affected.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
