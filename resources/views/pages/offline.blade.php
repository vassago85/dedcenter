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
                    <svg class="h-6 w-6" style="color: var(--lp-red);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Hub Tablet</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">One Android tablet starts a local server. It displays its IP address on screen. The hub collects scores from all connected clients and can also be used for scoring.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(59, 130, 246, 0.08);">
                    <svg class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Client Tablets</h3>
                <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Other tablets enter the hub&rsquo;s IP address and connect as clients. They import the match from the hub, score their assigned stage or squad, and sync scores back over WiFi.</p>
            </div>

            <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(16, 185, 129, 0.08);">
                    <svg class="h-6 w-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                    </svg>
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
                            <svg class="h-5 w-5" style="color: var(--lp-red);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="font-semibold" style="color: var(--lp-text);">Room DB Local Storage</h4>
                            <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">All match data and scores are stored in Room DB on the device. Data persists across app restarts and survives loss of connectivity. No browser cache limitations.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl bg-green-600/10">
                            <svg class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 0 1 7.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 0 1 1.06 0Z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="font-semibold" style="color: var(--lp-text);">Hub &amp; Client Modes</h4>
                            <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">The Android app can run as a hub (hosting a local server) or as a client (connecting to a hub). This enables a full local WiFi mesh for scoring at ranges with no internet.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600/10">
                            <svg class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="font-semibold" style="color: var(--lp-text);">Automatic Sync</h4>
                            <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">Background sync runs every 15 seconds. When connectivity is detected &mdash; to the hub, to the cloud, or both &mdash; all unsynced scores are batched and sent. Dual status indicators show hub and cloud connectivity separately.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl" style="background: rgba(142, 160, 191, 0.1);">
                            <svg class="h-5 w-5" style="color: var(--lp-text-muted);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
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
                            <svg class="h-5 w-5" style="color: var(--lp-red);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="font-semibold" style="color: var(--lp-text);">One-Tap Download</h4>
                            <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">Each match on the scoring app&rsquo;s match list has a <strong style="color: var(--lp-text);">Download</strong> button. Tap it while you have connectivity and the full match payload is saved to IndexedDB on your device.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl bg-green-600/10">
                            <svg class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="font-semibold" style="color: var(--lp-text);">Offline Ready Indicator</h4>
                            <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">Once downloaded, a green <strong class="text-green-400">Offline Ready</strong> badge appears next to the match. You can see at a glance which matches are cached and ready to score without any network connection.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600/10">
                            <svg class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="font-semibold" style="color: var(--lp-text);">Auto-Sync When Connected</h4>
                            <p class="mt-1 text-sm" style="color: var(--lp-text-soft);">A background sync runs every 15 seconds. When connectivity is detected, all unsynced scores are batched and sent to the server. A pending counter in the header shows how many items are waiting to sync.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl" style="background: rgba(142, 160, 191, 0.1);">
                            <svg class="h-5 w-5" style="color: var(--lp-text-muted);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
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
