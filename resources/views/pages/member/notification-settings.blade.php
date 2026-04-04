<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Flux\Flux;

new #[Layout('components.layouts.app')]
    #[Title('Notification Settings — DeadCenter')]
    class extends Component {
    public array $preferences = [];

    public function mount(): void
    {
        $defaults = [
            'registration_open' => true,
            'squadding_open' => true,
            'scores_published' => true,
            'match_reminders' => true,
            'match_updates' => true,
            'email_registration_open' => false,
            'email_squadding_open' => false,
            'email_scores_published' => false,
            'email_match_reminders' => false,
            'email_match_updates' => false,
        ];

        $this->preferences = array_merge($defaults, auth()->user()->notification_preferences ?? []);
    }

    public function save(): void
    {
        auth()->user()->update([
            'notification_preferences' => $this->preferences,
        ]);

        Flux::toast('Notification preferences saved.', variant: 'success');
    }

    public function with(): array
    {
        return [];
    }
}; ?>

<div>
    <div class="mx-auto max-w-2xl py-8 px-4 space-y-8">

        <div>
            <h1 class="text-2xl font-bold text-primary">Notification Settings</h1>
            <p class="mt-1 text-base text-muted">Control how and when DeadCenter contacts you. Every notification type can be delivered in-app, by email, or both.</p>
        </div>

        {{-- ═══════════════════════════════════════════════
             Per-Notification Toggles
             ═══════════════════════════════════════════════ --}}
        <div class="rounded-xl border border-border bg-surface p-6 space-y-6">
            <div>
                <h2 class="text-lg font-semibold text-primary">Match Notifications</h2>
                <p class="mt-1 text-sm text-muted">For each notification type, choose whether you want it in the app, by email, or both. In-app notifications appear in your notification bell. Email goes to <strong class="text-secondary">{{ auth()->user()->email }}</strong>.</p>
            </div>

            <div class="space-y-4">
                @foreach([
                    'registration_open' => ['Registration Open', 'A match you showed interest in opens for registration.'],
                    'squadding_open' => ['Squadding Open', 'You can now choose your squad for a match.'],
                    'scores_published' => ['Results Published', 'Match results and badges are available.'],
                    'match_reminders' => ['Match Reminders', 'A reminder the day before your match.'],
                    'match_updates' => ['Match Updates & MD Messages', 'Changes to matches you\'re registered for, and messages from Match Directors.'],
                ] as $key => [$label, $desc])
                    <div class="rounded-lg border border-border bg-surface-2/50 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <p class="text-base font-medium text-primary">{{ $label }}</p>
                                <p class="mt-0.5 text-sm text-muted">{{ $desc }}</p>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-2">
                            <label class="flex items-center gap-2 cursor-pointer min-h-[44px]">
                                <input type="checkbox" wire:model.live="preferences.{{ $key }}"
                                       class="h-5 w-5 rounded border-border bg-surface-2 text-accent focus:ring-2 focus:ring-accent">
                                <span class="text-sm font-medium text-secondary">In-App</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer min-h-[44px]">
                                <input type="checkbox" wire:model.live="preferences.email_{{ $key }}"
                                       class="h-5 w-5 rounded border-border bg-surface-2 text-accent focus:ring-2 focus:ring-accent">
                                <span class="text-sm font-medium text-secondary">Email</span>
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>

            <button wire:click="save"
                    wire:loading.attr="disabled"
                    class="w-full rounded-lg bg-accent hover:bg-accent-hover disabled:opacity-50 px-4 min-h-[44px] text-base font-semibold text-white transition-colors focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 focus:ring-offset-surface">
                <span wire:loading.remove wire:target="save">Save Preferences</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </div>

        {{-- ═══════════════════════════════════════════════
             Push Notifications (PWA)
             ═══════════════════════════════════════════════ --}}
        <div class="rounded-xl border border-border bg-surface p-6 space-y-5"
             x-data="{
                 supported: false,
                 subscribed: false,
                 loading: true,
                 denied: false,

                 async init() {
                     if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                         this.loading = false;
                         return;
                     }
                     this.supported = true;
                     this.denied = Notification.permission === 'denied';

                     try {
                         const reg = await navigator.serviceWorker.ready;
                         const sub = await reg.pushManager.getSubscription();
                         this.subscribed = !!sub;
                     } catch {}

                     this.loading = false;
                 },

                 async toggle() {
                     if (this.loading || !window.pushUtils) return;
                     this.loading = true;

                     try {
                         if (this.subscribed) {
                             await window.pushUtils.unsubscribe();
                             this.subscribed = false;
                         } else {
                             const sub = await window.pushUtils.subscribe();
                             this.subscribed = !!sub;
                             this.denied = Notification.permission === 'denied';
                         }
                     } catch (e) {
                         console.error('Push toggle failed:', e);
                         this.denied = Notification.permission === 'denied';
                     }

                     this.loading = false;
                 }
             }">

            <div>
                <h2 class="text-lg font-semibold text-primary">Push Notifications</h2>
                <p class="mt-1 text-sm text-muted">Get real-time alerts on your phone or computer — even when the browser is closed. Push notifications work through the DeadCenter PWA (Progressive Web App).</p>
            </div>

            {{-- Push toggle --}}
            <template x-if="supported && !denied">
                <div class="flex items-center justify-between rounded-lg border border-border bg-surface-2/50 p-4" x-show="!loading || subscribed !== null">
                    <div>
                        <p class="text-base font-medium text-primary">Enable Push Notifications</p>
                        <p class="text-sm text-muted">Receive instant alerts on your device for all your enabled notification types.</p>
                    </div>
                    <button @click="toggle()" :disabled="loading"
                            class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 focus:ring-offset-surface disabled:opacity-50"
                            :class="subscribed ? 'bg-accent' : 'bg-surface-2 border border-border'">
                        <span class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform"
                              :class="subscribed ? 'translate-x-6' : 'translate-x-0.5'"></span>
                    </button>
                </div>
            </template>

            <template x-if="supported && denied">
                <div class="rounded-lg border border-amber-600/30 bg-amber-900/10 p-4">
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 text-amber-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                        <div>
                            <p class="text-base font-medium text-amber-400">Push Notifications Blocked</p>
                            <p class="mt-1 text-sm text-muted">Your browser has blocked notifications for this site. To re-enable, open your browser settings and allow notifications for <strong class="text-secondary">deadcenter.co.za</strong>.</p>
                        </div>
                    </div>
                </div>
            </template>

            <template x-if="loading">
                <div class="flex items-center gap-2 text-sm text-muted">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Checking push status...
                </div>
            </template>

            {{-- PWA Install Guide --}}
            <div class="rounded-lg border border-blue-600/20 bg-blue-900/10 p-5 space-y-4">
                <div class="flex items-start gap-3">
                    <svg class="h-6 w-6 text-blue-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>
                    <div>
                        <p class="text-base font-semibold text-blue-300">Install DeadCenter on Your Phone</p>
                        <p class="mt-1 text-sm text-muted">Push notifications require the DeadCenter app to be installed on your device. It installs from your browser in seconds — no app store needed.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Android --}}
                    <div class="rounded-lg border border-border bg-surface p-4 space-y-2">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-green-400 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M17.523 2.216l1.662 2.88c.059.103.02.233-.083.293l-.96.554c-.104.06-.234.02-.294-.082l-1.69-2.926C14.961 2.305 13.53 1.951 12 1.951c-1.53 0-2.961.354-4.158.984L6.152 5.86c-.06.103-.19.143-.294.083l-.96-.554a.215.215 0 01-.083-.294l1.662-2.879C3.897 3.837 2.16 6.781 2.16 10.15h19.68c0-3.37-1.738-6.313-4.317-7.934zM7.293 7.975a.828.828 0 01-.828-.828.828.828 0 01.828-.828.828.828 0 01.828.828.828.828 0 01-.828.828zm9.414 0a.828.828 0 01-.828-.828.828.828 0 01.828-.828.828.828 0 01.828.828.828.828 0 01-.828.828zM2.16 11.1v8.626c0 .605.49 1.097 1.097 1.097h1.097v3.152c0 .606.49 1.096 1.096 1.096.607 0 1.097-.49 1.097-1.096v-3.152h2.194v3.152c0 .606.49 1.096 1.096 1.096.607 0 1.097-.49 1.097-1.096v-3.152h2.194v3.152c0 .606.49 1.096 1.097 1.096.606 0 1.096-.49 1.096-1.096v-3.152h1.097c.607 0 1.097-.492 1.097-1.097V11.1H2.16zm-1.097 0c-.606 0-1.096.49-1.096 1.097v5.483c0 .606.49 1.097 1.096 1.097.607 0 1.097-.491 1.097-1.097v-5.483c0-.607-.49-1.097-1.097-1.097zm21.874 0c-.607 0-1.097.49-1.097 1.097v5.483c0 .606.49 1.097 1.097 1.097.606 0 1.096-.491 1.096-1.097v-5.483c0-.607-.49-1.097-1.096-1.097z"/></svg>
                            <p class="text-sm font-semibold text-primary">Android (Chrome)</p>
                        </div>
                        <ol class="text-sm text-muted space-y-1 list-decimal list-inside">
                            <li>Open <strong class="text-secondary">deadcenter.co.za</strong> in Chrome</li>
                            <li>Tap the <strong class="text-secondary">three-dot menu</strong> (top right)</li>
                            <li>Tap <strong class="text-secondary">"Install app"</strong> or <strong class="text-secondary">"Add to Home screen"</strong></li>
                            <li>Open the app from your home screen</li>
                            <li>Allow notifications when prompted</li>
                        </ol>
                    </div>

                    {{-- iOS --}}
                    <div class="rounded-lg border border-border bg-surface p-4 space-y-2">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-gray-300 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
                            <p class="text-sm font-semibold text-primary">iPhone / iPad (Safari)</p>
                        </div>
                        <ol class="text-sm text-muted space-y-1 list-decimal list-inside">
                            <li>Open <strong class="text-secondary">deadcenter.co.za</strong> in Safari</li>
                            <li>Tap the <strong class="text-secondary">Share button</strong> (square with arrow)</li>
                            <li>Scroll down and tap <strong class="text-secondary">"Add to Home Screen"</strong></li>
                            <li>Tap <strong class="text-secondary">"Add"</strong></li>
                            <li>Open the app and enable notifications in Settings &gt; DeadCenter</li>
                        </ol>
                        <p class="text-xs text-amber-400/80 mt-1">Requires iOS 16.4 or later for push notification support.</p>
                    </div>
                </div>
            </div>

            <template x-if="!supported">
                <div class="rounded-lg border border-border bg-surface-2/50 p-4">
                    <p class="text-sm text-muted">Push notifications are not supported in this browser. Try opening DeadCenter in Chrome (Android) or Safari (iPhone) and install it as an app using the instructions above.</p>
                </div>
            </template>
        </div>

        {{-- ═══════════════════════════════════════════════
             Summary / Explanation
             ═══════════════════════════════════════════════ --}}
        <div class="rounded-xl border border-border bg-surface/50 p-5 space-y-3">
            <h3 class="text-base font-semibold text-primary">How Notifications Work</h3>
            <div class="text-sm text-muted space-y-2">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface-2 text-xs font-bold text-primary">1</span>
                    <p><strong class="text-secondary">In-App</strong> — Notifications appear in your notification bell inside DeadCenter. You'll see them whenever you open the site or app.</p>
                </div>
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface-2 text-xs font-bold text-primary">2</span>
                    <p><strong class="text-secondary">Email</strong> — Get an email to <strong>{{ auth()->user()->email }}</strong>. Great if you prefer checking email or don't open the app often.</p>
                </div>
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface-2 text-xs font-bold text-primary">3</span>
                    <p><strong class="text-secondary">Push</strong> — Instant popup on your phone or desktop, even when the app is closed. Requires the DeadCenter PWA to be installed (see instructions above).</p>
                </div>
            </div>
            <p class="text-xs text-muted pt-1">You can enable any combination. Most shooters use In-App + Push. If you prefer email, enable that instead or alongside.</p>
        </div>

    </div>
</div>
