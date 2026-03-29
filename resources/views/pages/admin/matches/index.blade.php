<?php

use App\Models\ShootingMatch;
use App\Enums\MatchStatus;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Manage Matches')]
    class extends Component {
    public string $search = '';

    public function deleteMatch(int $id): void
    {
        $match = ShootingMatch::findOrFail($id);
        $match->delete();

        Flux::toast('Match deleted.', variant: 'success');
    }

    public function with(): array
    {
        $matches = ShootingMatch::query()
            ->with('creator:id,name')
            ->withCount(['shooters', 'registrations'])
            ->when($this->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest('date')
            ->get();

        return ['matches' => $matches];
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Matches</flux:heading>
            <p class="mt-1 text-sm text-slate-400">Manage your shooting matches.</p>
        </div>
        <flux:button href="{{ route('admin.matches.create') }}" variant="primary" class="!bg-red-600 hover:!bg-red-700">
            <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            New Match
        </flux:button>
    </div>

    <div class="max-w-sm">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search matches..." icon="magnifying-glass" />
    </div>

    <div class="rounded-xl border border-slate-700 bg-slate-800 overflow-hidden">
        @if($matches->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="text-slate-400">
                    @if($search)
                        No matches found for "{{ $search }}".
                    @else
                        No matches yet. Create your first one!
                    @endif
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-700 text-left text-slate-400">
                            <th class="px-6 py-3 font-medium">Name</th>
                            <th class="px-6 py-3 font-medium">Date</th>
                            <th class="px-6 py-3 font-medium">Location</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                            <th class="px-6 py-3 font-medium text-right">Fee</th>
                            <th class="px-6 py-3 font-medium text-right">Registrations</th>
                            <th class="px-6 py-3 font-medium text-right">Shooters</th>
                            <th class="px-6 py-3 font-medium">Created By</th>
                            <th class="px-6 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        @foreach($matches as $match)
                            <tr class="hover:bg-slate-700/30 transition-colors" wire:key="match-{{ $match->id }}">
                                <td class="px-6 py-3 font-medium text-white">{{ $match->name }}</td>
                                <td class="px-6 py-3 text-slate-300">{{ $match->date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-6 py-3 text-slate-300">{{ $match->location ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    @switch($match->status)
                                        @case(MatchStatus::Draft)
                                            <flux:badge size="sm" color="zinc">Draft</flux:badge>
                                            @break
                                        @case(MatchStatus::Active)
                                            <flux:badge size="sm" color="green">Active</flux:badge>
                                            @break
                                        @case(MatchStatus::Completed)
                                            <flux:badge size="sm" color="blue">Completed</flux:badge>
                                            @break
                                    @endswitch
                                </td>
                                <td class="px-6 py-3 text-right text-slate-300">{{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free' }}</td>
                                <td class="px-6 py-3 text-right text-slate-300">{{ $match->registrations_count }}</td>
                                <td class="px-6 py-3 text-right text-slate-300">{{ $match->shooters_count }}</td>
                                <td class="px-6 py-3 text-slate-400 text-sm">{{ $match->creator?->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <flux:button href="{{ route('admin.matches.edit', $match) }}" size="sm" variant="ghost">
                                            Edit
                                        </flux:button>
                                        <flux:button size="sm" variant="ghost" class="!text-red-400 hover:!text-red-300"
                                                     wire:click="deleteMatch({{ $match->id }})"
                                                     wire:confirm="Are you sure you want to delete this match?">
                                            Delete
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
