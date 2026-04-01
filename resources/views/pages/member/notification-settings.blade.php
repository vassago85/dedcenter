<?php

use Livewire\Volt\Component;

new class extends Component {
    public array $preferences = [];

    public function mount(): void
    {
        $this->preferences = auth()->user()->notification_preferences ?? [
            'registration_open' => true,
            'squadding_open' => true,
            'scores_published' => true,
            'match_reminders' => true,
            'match_updates' => true,
        ];
    }

    public function save(): void
    {
        auth()->user()->update([
            'notification_preferences' => $this->preferences,
        ]);

        $this->dispatch('notify', message: 'Notification preferences saved.');
    }

    public function with(): array
    {
        return [];
    }
}; ?>

<x-layouts.app title="Notification Settings">
    <div class="mx-auto max-w-lg py-8 px-4">
        <h1 class="text-2xl font-bold mb-6">Notification Settings</h1>

        <div class="rounded-xl border border-border bg-surface p-6 space-y-5">
            <p class="text-sm text-muted">Choose which notifications you want to receive.</p>

            @foreach([
                'registration_open' => ['Registration Open', 'Get notified when a match opens for registration'],
                'squadding_open' => ['Squadding Open', 'Get notified when you can choose your squad'],
                'scores_published' => ['Scores Published', 'Get notified when match results are available'],
                'match_reminders' => ['Match Reminders', 'Get a reminder the day before your match'],
                'match_updates' => ['Match Updates', 'Get notified about changes to matches you\'re registered for'],
            ] as $key => [$label, $desc])
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" wire:model.live="preferences.{{ $key }}"
                           class="mt-1 rounded border-border bg-surface-2 text-red-600 focus:ring-red-600">
                    <div>
                        <p class="text-sm font-medium">{{ $label }}</p>
                        <p class="text-xs text-muted">{{ $desc }}</p>
                    </div>
                </label>
            @endforeach

            <button wire:click="save" class="w-full rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 transition-colors">
                Save Preferences
            </button>
        </div>
    </div>
</x-layouts.app>
