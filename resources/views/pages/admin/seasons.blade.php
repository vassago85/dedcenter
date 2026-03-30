<?php

use App\Models\Season;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Seasons')]
    class extends Component {

    public string $name = '';
    public int $year;
    public string $start_date = '';
    public string $end_date = '';
    public ?int $editingId = null;

    public function mount(): void
    {
        $this->year = (int) date('Y');
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'year' => 'required|integer|min:2020|max:2099',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($this->editingId) {
            $season = Season::findOrFail($this->editingId);
            $season->update([
                'name' => $this->name,
                'year' => $this->year,
                'start_date' => $this->start_date ?: null,
                'end_date' => $this->end_date ?: null,
            ]);
            Flux::toast('Season updated.', variant: 'success');
        } else {
            Season::create([
                'name' => $this->name,
                'year' => $this->year,
                'start_date' => $this->start_date ?: null,
                'end_date' => $this->end_date ?: null,
                'created_by' => auth()->id(),
            ]);
            Flux::toast('Season created.', variant: 'success');
        }

        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $season = Season::findOrFail($id);
        $this->editingId = $season->id;
        $this->name = $season->name;
        $this->year = $season->year;
        $this->start_date = $season->start_date?->toDateString() ?? '';
        $this->end_date = $season->end_date?->toDateString() ?? '';
    }

    public function delete(int $id): void
    {
        Season::findOrFail($id)->delete();
        Flux::toast('Season deleted.', variant: 'success');
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->year = (int) date('Y');
        $this->start_date = '';
        $this->end_date = '';
    }

    public function with(): array
    {
        return [
            'seasons' => Season::withCount('matches')->orderByDesc('year')->orderByDesc('start_date')->get(),
        ];
    }
}; ?>

<div class="space-y-6 max-w-2xl">
    <div>
        <h1 class="text-2xl font-bold text-white">Seasons</h1>
        <p class="mt-1 text-sm text-secondary">Manage seasons and aggregate match results into leaderboards.</p>
    </div>

    {{-- Create / Edit form --}}
    <form wire:submit="save" class="rounded-xl border border-border bg-surface p-6 space-y-4">
        <h2 class="text-lg font-semibold text-white">{{ $editingId ? 'Edit Season' : 'New Season' }}</h2>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <flux:input wire:model="name" label="Season Name" placeholder="e.g. 2026 Season" required />
            </div>
            <flux:input wire:model="year" label="Year" type="number" min="2020" max="2099" required />
            <div></div>
            <flux:input wire:model="start_date" label="Start Date" type="date" />
            <flux:input wire:model="end_date" label="End Date" type="date" />
        </div>

        <div class="flex items-center gap-3 pt-2">
            <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                {{ $editingId ? 'Update Season' : 'Create Season' }}
            </flux:button>
            @if($editingId)
                <flux:button wire:click="resetForm" variant="ghost">Cancel</flux:button>
            @endif
        </div>
    </form>

    {{-- Existing seasons --}}
    @if($seasons->isNotEmpty())
    <div class="space-y-3">
        @foreach($seasons as $season)
            <div class="rounded-xl border border-border bg-surface px-5 py-4 flex items-center justify-between" wire:key="season-{{ $season->id }}">
                <div>
                    <p class="font-semibold text-white">{{ $season->name }}</p>
                    <p class="text-xs text-muted">
                        {{ $season->year }}
                        @if($season->start_date && $season->end_date)
                            &middot; {{ $season->start_date->format('d M') }} &ndash; {{ $season->end_date->format('d M') }}
                        @endif
                        &middot; {{ $season->matches_count }} {{ Str::plural('match', $season->matches_count) }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <flux:button size="sm" variant="ghost" wire:click="edit({{ $season->id }})">Edit</flux:button>
                    <flux:button size="sm" variant="ghost" class="!text-accent" wire:click="delete({{ $season->id }})" wire:confirm="Delete this season?">Delete</flux:button>
                </div>
            </div>
        @endforeach
    </div>
    @else
        <p class="text-sm text-muted">No seasons created yet.</p>
    @endif
</div>
