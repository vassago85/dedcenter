<?php

use App\Models\Organization;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Organization Settings')]
    class extends Component {
    public Organization $organization;

    public string $name = '';
    public string $description = '';
    public string $best_of = '';
    public string $entry_fee_default = '';
    public string $primary_color = '#dc2626';
    public string $secondary_color = '#1e293b';
    public string $hero_text = '';
    public string $hero_description = '';
    public bool $portal_enabled = false;

    public function mount(Organization $organization): void
    {
        $this->organization = $organization;
        $this->name = $organization->name;
        $this->description = $organization->description ?? '';
        $this->best_of = $organization->best_of ? (string) $organization->best_of : '';
        $this->entry_fee_default = $organization->entry_fee_default ? (string) $organization->entry_fee_default : '';
        $this->primary_color = $organization->primary_color ?? '#dc2626';
        $this->secondary_color = $organization->secondary_color ?? '#1e293b';
        $this->hero_text = $organization->hero_text ?? '';
        $this->hero_description = $organization->hero_description ?? '';
        $this->portal_enabled = $organization->portal_enabled;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'best_of' => 'nullable|integer|min:1|max:100',
            'entry_fee_default' => 'nullable|numeric|min:0',
            'primary_color' => 'required|string|max:7',
            'secondary_color' => 'required|string|max:7',
            'hero_text' => 'nullable|string|max:255',
            'hero_description' => 'nullable|string|max:500',
        ]);

        $this->organization->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'best_of' => $this->best_of !== '' ? (int) $this->best_of : null,
            'entry_fee_default' => $this->entry_fee_default !== '' ? (float) $this->entry_fee_default : null,
            'primary_color' => $validated['primary_color'],
            'secondary_color' => $validated['secondary_color'],
            'hero_text' => $validated['hero_text'] ?: null,
            'hero_description' => $validated['hero_description'] ?: null,
            'portal_enabled' => $this->portal_enabled,
        ]);

        Flux::toast('Settings saved.', variant: 'success');
    }
}; ?>

<div class="space-y-6 max-w-2xl">
    <div>
        <flux:heading size="xl">Settings</flux:heading>
        <p class="mt-1 text-sm text-slate-400">{{ $organization->name }} — Configure organization settings.</p>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-xl border border-slate-700 bg-slate-800 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-white">Organization Details</h2>

            <flux:input wire:model="name" label="Name" required />
            <flux:textarea wire:model="description" label="Description" placeholder="What is this organization about..." rows="3" />

            <flux:separator />

            <h2 class="text-lg font-semibold text-white">Leaderboard</h2>
            <div class="max-w-xs">
                <flux:input wire:model="best_of" label="Best-of (X scores)" type="number" min="1" placeholder="Leave empty to count all" />
                <p class="mt-1 text-xs text-slate-500">Leaderboard will rank shooters by their top X match scores. Leave empty to sum all scores.</p>
            </div>

            <flux:separator />

            <h2 class="text-lg font-semibold text-white">Defaults</h2>
            <div class="max-w-xs">
                <flux:input wire:model="entry_fee_default" label="Default Entry Fee (ZAR)" type="number" step="0.01" min="0" placeholder="Leave empty for free" />
                <p class="mt-1 text-xs text-slate-500">New matches will default to this fee.</p>
            </div>

            <flux:separator />

            <h2 class="text-lg font-semibold text-white">Public Portal / White Label</h2>

            <div class="flex items-center gap-3">
                <input type="checkbox" wire:model="portal_enabled" id="portal_enabled"
                       class="rounded border-slate-600 bg-slate-700 text-red-600 focus:ring-red-500">
                <label for="portal_enabled" class="text-sm text-slate-300">Enable public portal</label>
            </div>
            @if($organization->portal_enabled)
                <p class="text-xs text-slate-400">
                    Portal URL: <a href="{{ route('portal.home', $organization) }}" target="_blank" class="text-red-400 hover:text-red-300 font-mono">{{ route('portal.home', $organization) }}</a>
                </p>
            @endif

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Primary Color</label>
                    <div class="flex items-center gap-2">
                        <input type="color" wire:model.live="primary_color" class="h-10 w-14 cursor-pointer rounded border border-slate-600 bg-slate-700">
                        <flux:input wire:model.live="primary_color" class="flex-1" />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Secondary Color</label>
                    <div class="flex items-center gap-2">
                        <input type="color" wire:model.live="secondary_color" class="h-10 w-14 cursor-pointer rounded border border-slate-600 bg-slate-700">
                        <flux:input wire:model.live="secondary_color" class="flex-1" />
                    </div>
                </div>
            </div>

            <flux:input wire:model="hero_text" label="Hero Heading" placeholder="e.g. Royal Flush Competition 2026" />
            <flux:textarea wire:model="hero_description" label="Hero Description" placeholder="A brief description shown on the portal landing page..." rows="2" />

            {{-- Preview swatch --}}
            <div class="rounded-lg p-4 border border-slate-600" style="background-color: {{ $secondary_color }};">
                <p class="text-sm font-bold" style="color: {{ $primary_color }};">{{ $hero_text ?: $organization->name }}</p>
                <p class="text-xs text-slate-400 mt-1">Color preview</p>
            </div>

            <div class="flex justify-end pt-2">
                <flux:button type="submit" variant="primary" class="!bg-red-600 hover:!bg-red-700">Save Settings</flux:button>
            </div>
        </div>
    </form>

    {{-- Info --}}
    <div class="rounded-xl border border-slate-700 bg-slate-800 p-6 space-y-2">
        <h2 class="text-sm font-semibold text-slate-400">Organization Info</h2>
        <div class="grid grid-cols-2 gap-2 text-sm">
            <span class="text-slate-500">Type</span>
            <span class="text-white capitalize">{{ $organization->type }}</span>
            <span class="text-slate-500">Slug</span>
            <span class="text-white font-mono text-xs">{{ $organization->slug }}</span>
            <span class="text-slate-500">Status</span>
            <span class="text-white capitalize">{{ $organization->status }}</span>
            <span class="text-slate-500">Created</span>
            <span class="text-white">{{ $organization->created_at->format('d M Y') }}</span>
            @if($organization->parent)
                <span class="text-slate-500">Parent</span>
                <span class="text-white">{{ $organization->parent->name }}</span>
            @endif
        </div>
    </div>
</div>
