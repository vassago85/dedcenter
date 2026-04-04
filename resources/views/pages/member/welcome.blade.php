<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Welcome — DeadCenter')]
    class extends Component {

    public function mount(): void
    {
        if (auth()->user()->isOnboarded()) {
            $this->redirect(route('dashboard'), navigate: true);
        }
    }

    public function chooseShooter(): void
    {
        auth()->user()->update(['onboarded_at' => now()]);
        $this->redirect(route('dashboard'), navigate: true);
    }

    public function chooseOrganizer(): void
    {
        auth()->user()->update(['onboarded_at' => now()]);
        $this->redirect(route('organizations.create'), navigate: true);
    }

    public function skip(): void
    {
        auth()->user()->update(['onboarded_at' => now()]);
        $this->redirect(route('dashboard'), navigate: true);
    }
}; ?>

<div class="flex items-center justify-center py-12 sm:py-20">
    <div class="w-full max-w-2xl px-4">
        {{-- Heading --}}
        <div class="mb-10 text-center">
            <h1 class="text-3xl font-bold text-primary sm:text-4xl">Welcome to <span class="text-accent">DeadCenter</span></h1>
            <p class="mt-3 text-lg text-muted">What brings you here?</p>
        </div>

        {{-- Cards --}}
        <div class="grid gap-6 sm:grid-cols-2">
            {{-- Shooter card --}}
            <button
                type="button"
                wire:click="chooseShooter"
                wire:loading.attr="disabled"
                wire:target="chooseShooter"
                class="group flex flex-col items-center gap-4 rounded-xl border border-border bg-surface p-8 text-center transition-all duration-200 hover:border-accent/40 hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-accent min-h-[44px] disabled:opacity-50"
            >
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-accent/10 text-accent transition-colors group-hover:bg-accent/20">
                    {{-- Crosshair / target icon --}}
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <circle cx="12" cy="12" r="9" stroke-linecap="round" stroke-linejoin="round" />
                        <circle cx="12" cy="12" r="5" stroke-linecap="round" stroke-linejoin="round" />
                        <circle cx="12" cy="12" r="1" fill="currentColor" stroke="none" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 2v4M12 18v4M2 12h4M18 12h4" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-primary">I'm a Shooter</h2>
                    <p class="mt-1 text-base text-muted">Find matches, track scores, earn badges</p>
                </div>
            </button>

            {{-- Organizer card --}}
            <button
                type="button"
                wire:click="chooseOrganizer"
                wire:loading.attr="disabled"
                wire:target="chooseOrganizer"
                class="group flex flex-col items-center gap-4 rounded-xl border border-border bg-surface p-8 text-center transition-all duration-200 hover:border-accent/40 hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-accent min-h-[44px] disabled:opacity-50"
            >
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-accent/10 text-accent transition-colors group-hover:bg-accent/20">
                    {{-- Clipboard / organization icon --}}
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15a2.25 2.25 0 0 1 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-primary">I Run Matches</h2>
                    <p class="mt-1 text-base text-muted">Create an organization and start hosting events</p>
                </div>
            </button>
        </div>

        {{-- Skip link --}}
        <div class="mt-8 text-center">
            <button type="button" wire:click="skip" wire:loading.attr="disabled"
                    class="inline-flex min-h-[44px] items-center justify-center rounded-lg px-4 text-base text-muted transition-colors hover:text-secondary focus:outline-none focus:ring-2 focus:ring-accent disabled:opacity-50">
                <span wire:loading.remove wire:target="skip">I'll decide later</span>
                <span wire:loading wire:target="skip">Loading…</span>
            </button>
        </div>
    </div>
</div>
