<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Organization Matches')]
    class extends Component {
    public Organization $organization;
    public string $search = '';
    public string $tab = 'active';

    private function authorizeMatch(ShootingMatch $match): bool
    {
        if (! $match->userCanEditInOrg(auth()->user())) {
            Flux::toast('You are not authorized to manage this match.', variant: 'danger');
            return false;
        }
        return true;
    }

    public function archiveMatch(int $id): void
    {
        $match = $this->organization->matches()->findOrFail($id);
        if (!$this->authorizeMatch($match)) return;

        $match->delete();
        Flux::toast('Match archived.', variant: 'success');
    }

    public function restoreMatch(int $id): void
    {
        $match = $this->organization->matches()->onlyTrashed()->findOrFail($id);
        if (!$this->authorizeMatch($match)) return;

        $match->restore();
        Flux::toast('Match restored.', variant: 'success');
    }

    public function forceDeleteMatch(int $id): void
    {
        $match = $this->organization->matches()->onlyTrashed()->findOrFail($id);
        if (!$this->authorizeMatch($match)) return;

        $match->forceDelete();
        Flux::toast('Match permanently deleted.', variant: 'danger');
    }

    public function with(): array
    {
        $query = $this->tab === 'archived'
            ? $this->organization->matches()->onlyTrashed()
            : $this->organization->matches();

        $matches = $query
            ->with('creator:id,name')
            ->withCount(['shooters', 'registrations'])
            ->when($this->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest('date')
            ->get();

        $archivedCount = $this->organization->matches()->onlyTrashed()->count();

        return [
            'matches' => $matches,
            'archivedCount' => $archivedCount,
        ];
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

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex gap-2">
            <button wire:click="$set('tab', 'active')"
                    class="rounded-lg px-4 py-2 text-sm font-medium transition-colors {{ $tab === 'active' ? 'bg-accent text-white' : 'bg-surface-2 text-secondary hover:text-primary' }}">
                Active
            </button>
            <button wire:click="$set('tab', 'archived')"
                    class="rounded-lg px-4 py-2 text-sm font-medium transition-colors {{ $tab === 'archived' ? 'bg-accent text-white' : 'bg-surface-2 text-secondary hover:text-primary' }}">
                Archived
                @if($archivedCount > 0)
                    <span class="ml-1.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-white/20 px-1.5 text-xs">{{ $archivedCount }}</span>
                @endif
            </button>
        </div>
        <div class="max-w-sm flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search matches..." icon="magnifying-glass" />
        </div>
    </div>

    <div class="rounded-xl border border-border bg-surface overflow-hidden">
        @if($matches->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="text-muted">
                    @if($search)
                        No matches found for "{{ $search }}".
                    @elseif($tab === 'archived')
                        No archived matches.
                    @else
                        No matches yet. Create your first one!
                    @endif
                </p>
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
                                    @if($tab === 'archived')
                                        <flux:badge size="sm" color="zinc">Archived</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="{{ $match->status->color() }}">{{ $match->status->label() }}</flux:badge>
                                    @endif
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
                                    @if($match->userCanEditInOrg(auth()->user()))
                                        @if($tab === 'archived')
                                            <div class="flex items-center justify-end gap-2">
                                                <flux:button size="sm" variant="ghost"
                                                             wire:click="restoreMatch({{ $match->id }})"
                                                             wire:confirm="Restore this match?">
                                                    Restore
                                                </flux:button>
                                                <flux:button size="sm" variant="ghost" class="!text-accent hover:!text-accent"
                                                             wire:click="forceDeleteMatch({{ $match->id }})"
                                                             wire:confirm="Permanently delete this match? This cannot be undone.">
                                                    Delete Forever
                                                </flux:button>
                                            </div>
                                        @else
                                            <div class="flex items-center justify-end gap-2">
                                                <flux:button href="{{ route('org.matches.edit', [$organization, $match]) }}" size="sm" variant="ghost">Edit</flux:button>
                                                <flux:button size="sm" variant="ghost" class="!text-amber-500 hover:!text-amber-400"
                                                             wire:click="archiveMatch({{ $match->id }})"
                                                             wire:confirm="Archive this match? You can restore it later.">
                                                    Archive
                                                </flux:button>
                                            </div>
                                        @endif
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
