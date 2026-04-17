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
            $this->redirect(route('dashboard'));
        }
    }

    public function chooseShooter(): void
    {
        auth()->user()->update(['onboarded_at' => now()]);
        $this->redirect(route('dashboard'));
    }

    public function chooseOrganizer(): void
    {
        auth()->user()->update(['onboarded_at' => now()]);
        $this->redirect(route('organizations.create'));
    }

    public function skip(): void
    {
        auth()->user()->update(['onboarded_at' => now()]);
        $this->redirect(route('dashboard'));
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
                    <x-icon name="crosshair" class="h-8 w-8" />
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
                    <x-icon name="clipboard-list" class="h-8 w-8" />
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
