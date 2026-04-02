<?php

use App\Models\Organization;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('League Clubs')]
    class extends Component {
    public Organization $organization;
    public string $clubName = '';
    public string $clubDescription = '';

    public function addClub(): void
    {
        $this->validate([
            'clubName' => 'required|string|max:255',
            'clubDescription' => 'nullable|string|max:1000',
        ]);

        $club = Organization::create([
            'name' => $this->clubName,
            'description' => $this->clubDescription ?: null,
            'type' => 'club',
            'parent_id' => $this->organization->id,
            'status' => 'approved',
            'created_by' => auth()->id(),
        ]);

        $club->admins()->attach(auth()->id(), ['role' => 'owner']);

        $this->reset('clubName', 'clubDescription');
        Flux::toast("Club '{$club->name}' created.", variant: 'success');
    }

    public function removeClub(int $id): void
    {
        $club = $this->organization->children()->findOrFail($id);

        if ($club->matches()->exists()) {
            Flux::toast('Cannot remove a club that has matches.', variant: 'danger');
            return;
        }

        $club->delete();
        Flux::toast('Club removed.', variant: 'success');
    }

    public function with(): array
    {
        return [
            'clubs' => $this->organization->children()
                ->withCount(['matches', 'admins'])
                ->orderBy('name')
                ->get(),
        ];
    }
}; ?>

<div class="space-y-6 max-w-3xl">
    <div>
        <flux:heading size="xl">Clubs</flux:heading>
        <p class="mt-1 text-sm text-muted">{{ $organization->name }} — Manage clubs under this league.</p>
    </div>

    {{-- Clubs list --}}
    <div class="rounded-xl border border-border bg-surface overflow-hidden">
        @if($clubs->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="text-muted">No clubs yet. Add your first club below.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-muted">
                            <th class="px-6 py-3 font-medium">Name</th>
                            <th class="px-6 py-3 font-medium text-right">Matches</th>
                            <th class="px-6 py-3 font-medium text-right">Admins</th>
                            <th class="px-6 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($clubs as $club)
                            <tr class="hover:bg-surface-2/30 transition-colors" wire:key="club-{{ $club->id }}">
                                <td class="px-6 py-3">
                                    <span class="font-medium text-primary">{{ $club->name }}</span>
                                    @if($club->description)
                                        <p class="text-xs text-muted mt-0.5">{{ Str::limit($club->description, 60) }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right text-secondary">{{ $club->matches_count }}</td>
                                <td class="px-6 py-3 text-right text-secondary">{{ $club->admins_count }}</td>
                                <td class="px-6 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <flux:button size="sm" variant="ghost" class="!text-secondary hover:!text-primary"
                                                     href="{{ route('org.dashboard', $club) }}">Manage</flux:button>
                                        @if($club->matches_count === 0)
                                            <flux:button size="sm" variant="ghost" class="!text-accent hover:!text-accent"
                                                         wire:click="removeClub({{ $club->id }})"
                                                         wire:confirm="Remove club '{{ $club->name }}'?">
                                                Remove
                                            </flux:button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Add club --}}
    <div class="rounded-xl border border-dashed border-border bg-surface/50 p-6 space-y-4">
        <h3 class="text-sm font-medium text-secondary">Add Club</h3>
        <form wire:submit="addClub" class="space-y-4">
            <flux:input wire:model="clubName" label="Club Name" placeholder="e.g. Pretoria Shooting Club" required />
            <flux:textarea wire:model="clubDescription" label="Description" placeholder="Optional description..." rows="2" />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">Add Club</flux:button>
            </div>
        </form>
    </div>
</div>
