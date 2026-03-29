<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.marketing')]
    #[Title('Offline Scoring — DeadCenter')]
    class extends Component {
}; ?>

<section class="border-b border-border">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">How Offline Scoring Works</h2>
            <p class="mt-3 text-muted max-w-2xl mx-auto">Ranges often have poor or no mobile signal. DeadCenter is built from the ground up to handle this.</p>
        </div>
        <div class="grid gap-8 lg:grid-cols-2">
            <div class="space-y-6">
                <div class="flex gap-4">
                    <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-full bg-amber-600 text-sm font-black text-primary">1</div>
                    <div>
                        <h4 class="font-semibold text-primary">Load the match while online</h4>
                        <p class="mt-1 text-sm text-muted">Before heading to the range, open the scoring app and select your match. The full match data (target sets, gongs, squads, shooters, existing scores) is downloaded and cached locally in the browser&rsquo;s IndexedDB.</p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-full bg-amber-600 text-sm font-black text-primary">2</div>
                    <div>
                        <h4 class="font-semibold text-primary">Score offline &mdash; everything saves locally</h4>
                        <p class="mt-1 text-sm text-muted">Every tap of Hit, Miss, or Shot Not Taken is instantly saved to the device&rsquo;s local database. Stage times are saved locally too. The app works exactly the same whether you&rsquo;re online or offline &mdash; no loading spinners, no errors.</p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-full bg-amber-600 text-sm font-black text-primary">3</div>
                    <div>
                        <h4 class="font-semibold text-primary">Auto-sync when signal returns</h4>
                        <p class="mt-1 text-sm text-muted">A background sync runs every 15 seconds. When connectivity is detected, all unsynced scores and stage times are batched and sent to the server in a single API call. The server uses upsert logic so duplicate submissions are harmless.</p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-full bg-amber-600 text-sm font-black text-primary">4</div>
                    <div>
                        <h4 class="font-semibold text-primary">Scoreboards update live</h4>
                        <p class="mt-1 text-sm text-muted">Once scores reach the server, the TV scoreboard and mobile live page pick them up on their next 10-second refresh cycle. Spectators see results appear in real-time as devices sync.</p>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-border bg-surface p-8">
                <h4 class="mb-4 font-semibold text-primary">Under the Hood</h4>
                <div class="space-y-4 text-sm text-muted">
                    <div class="rounded-lg bg-surface-2 p-4">
                        <p class="mb-1 text-xs font-bold text-amber-400 uppercase tracking-wider">Local Storage</p>
                        <p>Scores are persisted in <strong class="text-primary">IndexedDB</strong> via Dexie.js. Each score is keyed by (shooterId, gongId) so re-tapping the same target overwrites rather than duplicates.</p>
                    </div>
                    <div class="rounded-lg bg-surface-2 p-4">
                        <p class="mb-1 text-xs font-bold text-amber-400 uppercase tracking-wider">Sync Protocol</p>
                        <p>The sync payload includes <strong class="text-primary">scores</strong>, <strong class="text-primary">stage_times</strong>, and <strong class="text-primary">deleted_scores</strong> (for Shot Not Taken reversals). The server processes deletions first, then upserts.</p>
                    </div>
                    <div class="rounded-lg bg-surface-2 p-4">
                        <p class="mb-1 text-xs font-bold text-amber-400 uppercase tracking-wider">Conflict Resolution</p>
                        <p>Last-write-wins. Each score carries a <strong class="text-primary">device_id</strong> and <strong class="text-primary">recorded_at</strong> timestamp. Multiple devices can score different stages simultaneously without conflict.</p>
                    </div>
                    <div class="rounded-lg bg-surface-2 p-4">
                        <p class="mb-1 text-xs font-bold text-amber-400 uppercase tracking-wider">Pending Counter</p>
                        <p>A badge in the scoring app header shows how many unsynced items exist. Tap "Sync" to force an immediate upload, or let it happen automatically.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="border-b border-border bg-surface/50">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">Download for Offline</h2>
            <p class="mt-3 text-muted max-w-2xl mx-auto">Explicitly cache matches before you leave for the range &mdash; so you know you&rsquo;re covered even with zero signal.</p>
        </div>
        <div class="mx-auto max-w-3xl">
            <div class="rounded-2xl border border-border bg-surface p-8 lg:p-10">
                <div class="space-y-6">
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl bg-accent/10">
                            <svg class="h-5 w-5 text-accent" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="font-semibold text-primary">One-Tap Download</h4>
                            <p class="mt-1 text-sm text-muted">Each match on the scoring app&rsquo;s match list has a <strong class="text-primary">Download</strong> button. Tap it while you have connectivity and the full match payload &mdash; squads, shooters, target sets, and existing scores &mdash; is saved to your device.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl bg-green-600/10">
                            <svg class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="font-semibold text-primary">Offline Ready Indicator</h4>
                            <p class="mt-1 text-sm text-muted">Once downloaded, a green <strong class="text-green-400">Offline Ready</strong> badge appears next to the match. You can see at a glance which matches are cached and ready to score without any network connection.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600/10">
                            <svg class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="font-semibold text-primary">Auto-Cache on Entry</h4>
                            <p class="mt-1 text-sm text-muted">Even without the download button, simply opening a match to score it will cache the full data automatically. The indicator updates when you return to the match list.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-xl bg-muted/10">
                            <svg class="h-5 w-5 text-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="font-semibold text-primary">Clear Cache</h4>
                            <p class="mt-1 text-sm text-muted">Done with a match? Tap <strong class="text-primary">Clear</strong> to free up device storage. The match data is removed from the local database but nothing on the server is affected.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
