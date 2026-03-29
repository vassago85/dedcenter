<?php

use App\Models\Organization;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Organizations')]
    class extends Component {
    public string $filter = 'pending';

    public function approve(int $id): void
    {
        $org = Organization::findOrFail($id);
        $org->update(['status' => 'approved']);

        $org->admins()->syncWithoutDetaching([
            $org->created_by => ['role' => 'owner'],
        ]);

        Flux::toast("'{$org->name}' approved. Creator is now the owner.", variant: 'success');
    }

    public function reject(int $id): void
    {
        $org = Organization::findOrFail($id);
        $org->update(['status' => 'archived']);

        Flux::toast("'{$org->name}' rejected.", variant: 'warning');
    }

    public function with(): array
    {
        $organizations = Organization::with(['creator', 'parent'])
            ->withCount(['children', 'matches', 'admins'])
            ->when($this->filter !== 'all', fn ($q) => $q->where('status', $this->filter))
            ->latest()
            ->get();

        return ['organizations' => $organizations];
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">Organizations</flux:heading>
        <p class="mt-1 text-sm text-slate-400">Approve and manage organizations.</p>
    </div>

    {{-- Filter --}}
    <div class="flex gap-2 flex-wrap">
        @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'active' => 'Active', 'archived' => 'Archived', 'all' => 'All'] as $value => $label)
            <button wire:click="$set('filter', '{{ $value }}')"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors {{ $filter === $value ? 'bg-red-600 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="rounded-xl border border-slate-700 bg-slate-800 overflow-hidden">
        @if($organizations->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="text-slate-400">No organizations matching this filter.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-700 text-left text-slate-400">
                            <th class="px-6 py-3 font-medium">Name</th>
                            <th class="px-6 py-3 font-medium">Type</th>
                            <th class="px-6 py-3 font-medium">Parent</th>
                            <th class="px-6 py-3 font-medium">Created By</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                            <th class="px-6 py-3 font-medium text-right">Matches</th>
                            <th class="px-6 py-3 font-medium text-right">Admins</th>
                            <th class="px-6 py-3 font-medium">Date</th>
                            <th class="px-6 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        @foreach($organizations as $org)
                            <tr class="hover:bg-slate-700/30 transition-colors" wire:key="org-{{ $org->id }}">
                                <td class="px-6 py-3 font-medium text-white">{{ $org->name }}</td>
                                <td class="px-6 py-3 capitalize text-slate-300">{{ $org->type }}</td>
                                <td class="px-6 py-3 text-slate-400 text-xs">{{ $org->parent?->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-slate-300">{{ $org->creator->name }}</td>
                                <td class="px-6 py-3">
                                    @switch($org->status)
                                        @case('pending')
                                            <flux:badge size="sm" color="amber">Pending</flux:badge>
                                            @break
                                        @case('approved')
                                            <flux:badge size="sm" color="green">Approved</flux:badge>
                                            @break
                                        @case('active')
                                            <flux:badge size="sm" color="blue">Active</flux:badge>
                                            @break
                                        @case('archived')
                                            <flux:badge size="sm" color="zinc">Archived</flux:badge>
                                            @break
                                    @endswitch
                                </td>
                                <td class="px-6 py-3 text-right text-slate-300">{{ $org->matches_count }}</td>
                                <td class="px-6 py-3 text-right text-slate-300">{{ $org->admins_count }}</td>
                                <td class="px-6 py-3 text-slate-400 text-xs">{{ $org->created_at->format('d M Y') }}</td>
                                <td class="px-6 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($org->status === 'pending')
                                            <flux:button size="sm" variant="primary" class="!bg-green-600 hover:!bg-green-700"
                                                         wire:click="approve({{ $org->id }})"
                                                         wire:confirm="Approve '{{ $org->name }}'? The creator will become the owner.">
                                                Approve
                                            </flux:button>
                                            <flux:button size="sm" variant="ghost" class="!text-red-400 hover:!text-red-300"
                                                         wire:click="reject({{ $org->id }})"
                                                         wire:confirm="Reject '{{ $org->name }}'?">
                                                Reject
                                            </flux:button>
                                        @elseif($org->isApproved())
                                            <flux:button size="sm" variant="ghost" href="{{ route('org.dashboard', $org) }}">
                                                Manage
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
</div>
