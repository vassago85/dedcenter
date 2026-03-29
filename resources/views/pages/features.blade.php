<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Features — DeadCenter')]
    class extends Component {
}; ?>

<div class="space-y-12 max-w-4xl mx-auto">
    <div class="text-center">
        <flux:heading size="xl">Platform Features</flux:heading>
        <p class="mt-2 text-muted max-w-xl mx-auto">Everything you need to run, score, and follow shooting matches.</p>
    </div>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-xl border border-border bg-surface p-6">
            <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-amber-600/10">
                <svg class="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" /></svg>
            </div>
            <h3 class="mb-1 font-semibold text-primary">Offline-First Scoring</h3>
            <p class="text-sm text-muted leading-relaxed">No signal at the range? Scores save locally and sync when connectivity returns.</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-6">
            <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-accent/10">
                <svg class="h-5 w-5 text-accent" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" /></svg>
            </div>
            <h3 class="mb-1 font-semibold text-primary">Live Scoreboards</h3>
            <p class="text-sm text-muted leading-relaxed">TV scoreboard and mobile-friendly live page with QR code sharing. Auto-refreshes every 10 seconds.</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-6">
            <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500/10">
                <svg class="h-5 w-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" /></svg>
            </div>
            <h3 class="mb-1 font-semibold text-primary">PRS Scoring</h3>
            <p class="text-sm text-muted leading-relaxed">Hit / Miss / Shot Not Taken per target. Timed stages with tiebreaker support.</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-6">
            <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600/10">
                <svg class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" /></svg>
            </div>
            <h3 class="mb-1 font-semibold text-primary">Divisions & Categories</h3>
            <p class="text-sm text-muted leading-relaxed">Equipment classes and demographic groups. Filter leaderboards by any combination.</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-6">
            <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-green-600/10">
                <svg class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
            </div>
            <h3 class="mb-1 font-semibold text-primary">Leagues & Clubs</h3>
            <p class="text-sm text-muted leading-relaxed">Create leagues with clubs underneath. Season leaderboards with best-of-N scoring.</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-6">
            <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600/10">
                <svg class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" /></svg>
            </div>
            <h3 class="mb-1 font-semibold text-primary">Multi-Device Sync</h3>
            <p class="text-sm text-muted leading-relaxed">Multiple Range Officers score simultaneously on different tablets. All scores merge in real-time.</p>
        </div>
    </div>

    <div class="text-center">
        @auth
            <flux:button href="{{ route('dashboard') }}" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                Go to Dashboard
            </flux:button>
        @else
            <flux:button href="{{ route('register') }}" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                Get Started Free
            </flux:button>
        @endauth
    </div>
</div>
