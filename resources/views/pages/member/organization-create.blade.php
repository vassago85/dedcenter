<?php

use App\Enums\Province;
use App\Models\Organization;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Create Organization')]
    class extends Component {
    public string $name = '';
    public string $type = 'club';
    public string $province = '';
    public string $description = '';
    public string $parent_id = '';

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:league,club,competition,challenge',
            'province' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:2000',
            'parent_id' => 'nullable|exists:organizations,id',
        ]);

        $org = Organization::create([
            'name' => $this->name,
            'type' => $this->type,
            'province' => $this->province ?: null,
            'description' => $this->description ?: null,
            'parent_id' => $this->parent_id ?: null,
            'status' => 'pending',
            'created_by' => auth()->id(),
        ]);

        Flux::toast("'{$org->name}' has been submitted for approval.", variant: 'success');
        $this->redirect(route('dashboard'), navigate: true);
    }

    public function with(): array
    {
        return [
            'leagues' => Organization::active()->ofType('league')->orderBy('name')->get(['id', 'name']),
            'provinces' => Province::cases(),
        ];
    }
}; ?>

<div class="space-y-6 max-w-2xl" x-data="{ step: 1 }">
    <div class="flex items-center gap-4">
        <flux:button href="{{ route('organizations') }}" variant="ghost" size="sm">
            <x-icon name="chevron-left" class="mr-1 h-4 w-4" />
            Back
        </flux:button>
        <div>
            <flux:heading size="xl">Create Organization</flux:heading>
            <p class="mt-1 text-sm text-muted">Submit a new club, league, competition, or challenge for approval.</p>
        </div>
    </div>

    {{-- Step Indicator --}}
    <div class="flex items-center gap-3">
        <div class="flex items-center gap-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold transition-colors"
                  :class="step === 1 ? 'bg-accent text-white' : 'bg-green-600 text-white'">
                <template x-if="step === 1">
                    <span>1</span>
                </template>
                <template x-if="step > 1">
                    <x-icon name="check" class="h-4 w-4" />
                </template>
            </span>
            <span class="text-sm font-medium" :class="step === 1 ? 'text-accent' : 'text-green-400'">About Your Organization</span>
        </div>
        <div class="h-px flex-1 bg-border"></div>
        <div class="flex items-center gap-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold transition-colors"
                  :class="step === 2 ? 'bg-accent text-white' : 'bg-surface-2 text-muted'">2</span>
            <span class="text-sm font-medium" :class="step === 2 ? 'text-accent' : 'text-muted'">Review & Submit</span>
        </div>
    </div>

    <form wire:submit="save">
        {{-- Step 1: About Your Organization --}}
        <div x-show="step === 1" x-transition class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <h2 class="text-base font-semibold text-primary">About Your Organization</h2>

            <flux:input wire:model="name" label="Organization Name" placeholder="e.g. Gauteng Shooting League" required />

            <div>
                <label class="block text-sm font-medium text-secondary mb-1">Type</label>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach(['club' => 'Club', 'league' => 'League', 'competition' => 'Competition Series', 'challenge' => 'Challenge'] as $value => $label)
                        <button type="button" wire:click="$set('type', '{{ $value }}')"
                                class="rounded-lg border px-3 py-2 text-sm font-medium text-center transition-colors {{ $type === $value ? 'border-accent bg-accent/20 text-accent' : 'border-border bg-surface-2 text-secondary hover:border-muted' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-secondary mb-1">Province</label>
                <select wire:model="province" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:ring-1 focus:ring-accent">
                    <option value="">Select a province</option>
                    @foreach($provinces as $prov)
                        <option value="{{ $prov->value }}">{{ $prov->label() }}</option>
                    @endforeach
                </select>
            </div>

            @if($type === 'club' && $leagues->isNotEmpty())
                <div>
                    <label class="block text-sm font-medium text-secondary mb-1">Parent League (optional)</label>
                    <select wire:model="parent_id" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:ring-1 focus:ring-accent">
                        <option value="">No parent league</option>
                        @foreach($leagues as $league)
                            <option value="{{ $league->id }}">{{ $league->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <flux:textarea wire:model="description" label="Description (optional)" placeholder="What is this organization about..." rows="3" />

            <div class="flex justify-end pt-2">
                <button type="button" @click="if ($wire.name.trim()) step = 2" class="inline-flex items-center gap-2 rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white hover:bg-accent-hover transition-colors">
                    Continue
                    <x-icon name="chevron-right" class="h-4 w-4" />
                </button>
            </div>
        </div>

        {{-- Step 2: Review & Submit --}}
        <div x-show="step === 2" x-transition class="space-y-4">
            <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
                <h2 class="text-base font-semibold text-primary">Review Your Organization</h2>

                <div class="divide-y divide-border rounded-lg border border-border bg-surface-2/30 overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3">
                        <span class="text-sm text-muted">Name</span>
                        <span class="text-sm font-medium text-primary" x-text="$wire.name"></span>
                    </div>
                    <div class="flex items-center justify-between px-4 py-3">
                        <span class="text-sm text-muted">Type</span>
                        <span class="text-sm font-medium text-primary capitalize" x-text="$wire.type === 'competition' ? 'Competition Series' : $wire.type"></span>
                    </div>
                    <div class="flex items-center justify-between px-4 py-3">
                        <span class="text-sm text-muted">Province</span>
                        <span class="text-sm font-medium text-primary" x-text="$wire.province ? $wire.province.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'Not specified'"></span>
                    </div>
                    <div class="flex items-center justify-between px-4 py-3" x-show="$wire.description">
                        <span class="text-sm text-muted">Description</span>
                        <span class="text-sm font-medium text-primary max-w-[60%] text-right" x-text="$wire.description"></span>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-amber-800/50 bg-amber-900/10 p-5">
                <h3 class="text-sm font-semibold text-amber-400">What happens next</h3>
                <ul class="mt-2 space-y-1.5 text-sm text-muted">
                    <li class="flex items-start gap-2">
                        <x-icon name="clock" class="h-4 w-4 mt-0.5 text-amber-500 shrink-0" />
                        A site administrator will review your request.
                    </li>
                    <li class="flex items-start gap-2">
                        <x-icon name="bell" class="h-4 w-4 mt-0.5 text-amber-500 shrink-0" />
                        You'll receive a notification when approved.
                    </li>
                    <li class="flex items-start gap-2">
                        <x-icon name="rocket" class="h-4 w-4 mt-0.5 text-amber-500 shrink-0" />
                        Once approved, you can start creating matches immediately.
                    </li>
                </ul>
            </div>

            <div class="flex items-center justify-between pt-2">
                <button type="button" @click="step = 1" class="inline-flex items-center gap-2 rounded-lg border border-border bg-surface-2 px-4 py-2 text-sm font-medium text-secondary hover:text-primary transition-colors">
                    <x-icon name="chevron-left" class="h-4 w-4" />
                    Back
                </button>
                <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">Submit for Approval</flux:button>
            </div>
        </div>
    </form>
</div>
