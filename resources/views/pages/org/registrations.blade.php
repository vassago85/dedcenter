<?php

use App\Models\Organization;
use App\Models\MatchRegistration;
use App\Models\Squad;
use App\Models\Shooter;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Organization Registrations')]
    class extends Component {
    public Organization $organization;
    public string $filter = 'proof_submitted';

    public function approve(int $id): void
    {
        $reg = MatchRegistration::findOrFail($id);
        $reg->update(['payment_status' => 'confirmed']);

        $match = $reg->match;
        $squad = $match->squads()->firstOrCreate(['name' => 'Default'], ['sort_order' => 0]);
        $maxSort = $squad->shooters()->max('sort_order') ?? 0;

        Shooter::create([
            'squad_id' => $squad->id,
            'name' => $reg->user->name,
            'user_id' => $reg->user_id,
            'sort_order' => $maxSort + 1,
        ]);

        Flux::toast('Registration approved. Shooter added to match.', variant: 'success');
    }

    public function reject(int $id): void
    {
        MatchRegistration::findOrFail($id)->update(['payment_status' => 'rejected']);
        Flux::toast('Registration rejected.', variant: 'warning');
    }

    public function with(): array
    {
        $matchIds = $this->organization->matches()->pluck('id');

        $registrations = MatchRegistration::with(['user', 'match'])
            ->whereIn('match_id', $matchIds)
            ->when($this->filter !== 'all', fn ($q) => $q->where('payment_status', $this->filter))
            ->latest()
            ->get();

        return ['registrations' => $registrations];
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">Registrations</flux:heading>
        <p class="mt-1 text-sm text-muted">{{ $organization->name }} — Review and approve member registrations.</p>
    </div>

    <div class="flex gap-2 flex-wrap">
        @foreach(['proof_submitted' => 'Pending Review', 'pending_payment' => 'Awaiting Payment', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected', 'all' => 'All'] as $value => $label)
            <button wire:click="$set('filter', '{{ $value }}')"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors {{ $filter === $value ? 'bg-accent text-primary' : 'bg-surface-2 text-secondary hover:bg-surface-2' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="rounded-xl border border-border bg-surface overflow-hidden">
        @if($registrations->isEmpty())
            <div class="px-6 py-12 text-center"><p class="text-muted">No registrations matching this filter.</p></div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-muted">
                            <th class="px-6 py-3 font-medium">Member</th>
                            <th class="px-6 py-3 font-medium">Match</th>
                            <th class="px-6 py-3 font-medium">Reference</th>
                            <th class="px-6 py-3 font-medium">Amount</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                            <th class="px-6 py-3 font-medium">POP</th>
                            <th class="px-6 py-3 font-medium">Date</th>
                            <th class="px-6 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($registrations as $reg)
                            <tr class="hover:bg-surface-2/30 transition-colors" wire:key="reg-{{ $reg->id }}">
                                <td class="px-6 py-3 text-primary">{{ $reg->user->name }}</td>
                                <td class="px-6 py-3 text-secondary">{{ $reg->match->name }}</td>
                                <td class="px-6 py-3 font-mono text-xs text-muted">{{ $reg->payment_reference }}</td>
                                <td class="px-6 py-3 text-secondary">{{ $reg->amount ? 'R'.number_format($reg->amount, 2) : 'Free' }}</td>
                                <td class="px-6 py-3">
                                    @switch($reg->payment_status)
                                        @case('pending_payment') <flux:badge size="sm" color="zinc">Awaiting Payment</flux:badge> @break
                                        @case('proof_submitted') <flux:badge size="sm" color="amber">Pending Review</flux:badge> @break
                                        @case('confirmed') <flux:badge size="sm" color="green">Confirmed</flux:badge> @break
                                        @case('rejected') <flux:badge size="sm" color="red">Rejected</flux:badge> @break
                                    @endswitch
                                </td>
                                <td class="px-6 py-3">
                                    @if($reg->proof_of_payment_path)
                                        <a href="{{ Storage::url($reg->proof_of_payment_path) }}" target="_blank" class="text-accent hover:text-accent text-xs font-medium">View POP</a>
                                    @else
                                        <span class="text-muted text-xs">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-muted text-xs">{{ $reg->created_at->format('d M Y H:i') }}</td>
                                <td class="px-6 py-3 text-right">
                                    @if($reg->payment_status === 'proof_submitted')
                                        <div class="flex items-center justify-end gap-2">
                                            <flux:button size="sm" variant="primary" class="!bg-green-600 hover:!bg-green-700" wire:click="approve({{ $reg->id }})" wire:confirm="Approve this registration?">Approve</flux:button>
                                            <flux:button size="sm" variant="ghost" class="!text-accent hover:!text-accent" wire:click="reject({{ $reg->id }})" wire:confirm="Reject this registration?">Reject</flux:button>
                                        </div>
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
