<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Enums\MatchStatus;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Organization Matches')]
    class extends Component {
    public Organization $organization;
    public string $search = '';

    public function deleteMatch(int $id): void
    {
        $match = $this->organization->matches()->findOrFail($id);

        if ($match->created_by !== auth()->id() && !auth()->user()->isAdmin()) {
            Flux::toast('Only the match creator can delete this match.', variant: 'danger');
            return;
        }

        $match->delete();
        Flux::toast('Match deleted.', variant: 'success');
    }

    public function with(): array
    {
        $matches = $this->organization->matches()
            ->with('creator:id,name')
            ->withCount(['shooters', 'registrations'])
            ->when($this->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest('date')
            ->get();

        return ['matches' => $matches, 'isGlobalAdmin' => auth()->user()->isAdmin()];
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Matches</flux:heading>
            <p class="mt-1 text-sm text-muted">{{ $organization->name }}</p>
        </div>
        <flux:button href="{{ route('org.matches.create', $organization) }}" variant="primary" class="!bg-accent hover:!bg-accent-hover">
            <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            New Match
        </flux:button>
    </div>

    <div class="max-w-sm">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search matches..." icon="magnifying-glass" />
    </div>

    <div class="rounded-xl border border-border bg-surface overflow-hidden">
        @if($matches->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="text-muted">{{ $search ? "No matches found for \"{$search}\"." : 'No matches yet.' }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-muted">
                            <th class="px-6 py-3 font-medium">Name</th>
                            <th class="px-6 py-3 font-medium">Date</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                            <th class="px-6 py-3 font-medium text-right">Fee</th>
                            <th class="px-6 py-3 font-medium text-right">Registrations</th>
                            <th class="px-6 py-3 font-medium text-right">Shooters</th>
                            <th class="px-6 py-3 font-medium">Created By</th>
                            <th class="px-6 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($matches as $match)
                            <tr class="hover:bg-surface-2/30 transition-colors" wire:key="match-{{ $match->id }}">
                                <td class="px-6 py-3 font-medium text-primary">{{ $match->name }}</td>
                                <td class="px-6 py-3 text-secondary">{{ $match->date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    @switch($match->status)
                                        @case(MatchStatus::Draft) <flux:badge size="sm" color="zinc">Draft</flux:badge> @break
                                        @case(MatchStatus::Active) <flux:badge size="sm" color="green">Active</flux:badge> @break
                                        @case(MatchStatus::Completed) <flux:badge size="sm" color="blue">Completed</flux:badge> @break
                                    @endswitch
                                </td>
                                <td class="px-6 py-3 text-right text-secondary">{{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free' }}</td>
                                <td class="px-6 py-3 text-right text-secondary">{{ $match->registrations_count }}</td>
                                <td class="px-6 py-3 text-right text-secondary">{{ $match->shooters_count }}</td>
                                <td class="px-6 py-3 text-muted text-sm">
                                    {{ $match->creator?->name ?? '—' }}
                                    @if($match->created_by === auth()->id())
                                        <span class="text-[10px] text-green-500">(you)</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right">
                                    @if($match->created_by === auth()->id() || $isGlobalAdmin)
                                        <div class="flex items-center justify-end gap-2">
                                            <flux:button href="{{ route('org.matches.edit', [$organization, $match]) }}" size="sm" variant="ghost">Edit</flux:button>
                                            <flux:button size="sm" variant="ghost" class="!text-accent hover:!text-accent"
                                                         wire:click="deleteMatch({{ $match->id }})"
                                                         wire:confirm="Delete this match?">Delete</flux:button>
                                        </div>
                                    @else
                                        <span class="text-xs text-muted/60">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
