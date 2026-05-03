<?php

use App\Enums\ShooterClaimStatus;
use App\Models\Shooter;
use App\Models\ShooterAccountClaim;
use App\Models\User;
use App\Services\ShooterAccountClaimService;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Shooter Account Claims')]
    class extends Component {
    public string $statusFilter = 'pending';
    public array $reviewerNotes = [];

    public function with(): array
    {
        $claims = ShooterAccountClaim::query()
            ->with(['shooter.squad', 'user', 'match:id,name,date', 'reviewer:id,name'])
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->limit(100)
            ->get();

        $pendingCount = ShooterAccountClaim::where('status', ShooterClaimStatus::Pending)->count();

        return [
            'claims' => $claims,
            'pendingCount' => $pendingCount,
        ];
    }

    public function approve(int $claimId, ShooterAccountClaimService $claims): void
    {
        $claim = ShooterAccountClaim::findOrFail($claimId);

        $outcome = $claims->approve(
            $claim,
            auth()->id(),
            $this->reviewerNotes[$claim->id] ?? null,
        );

        match ($outcome) {
            ShooterAccountClaimService::NOT_PENDING =>
                Flux::toast('Claim is not pending.', variant: 'warning'),
            ShooterAccountClaimService::REJECTED_ALREADY_LINKED =>
                Flux::toast('That shooter is already linked to another account. Rejecting claim.', variant: 'danger'),
            ShooterAccountClaimService::APPROVED_IMPORTED =>
                Flux::toast('Claim approved. Shooter and imported registration linked to the account.', variant: 'success'),
            ShooterAccountClaimService::APPROVED_WALKIN =>
                Flux::toast('Claim approved. Shooter linked to account.', variant: 'success'),
        };

        // Tell other Livewire components on the page (admin dashboard, sidebar
        // counters) that the moderation backlog just changed so they refresh
        // their cached "X pending" badges instead of staying stale until a
        // hard reload.
        $this->dispatch('moderation-updated');
    }

    public function reject(int $claimId): void
    {
        $claim = ShooterAccountClaim::findOrFail($claimId);

        if (! $claim->isPending()) {
            Flux::toast('Claim is not pending.', variant: 'warning');
            return;
        }

        $claim->update([
            'status' => ShooterClaimStatus::Rejected,
            'reviewer_id' => auth()->id(),
            'reviewed_at' => now(),
            'reviewer_note' => $this->reviewerNotes[$claim->id] ?? null,
        ]);

        Flux::toast('Claim rejected.', variant: 'success');
        $this->dispatch('moderation-updated');
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-primary">Shooter Account Claims</h1>
            <p class="text-sm text-muted">Approve or reject requests from members to link their account to a shooter entry.</p>
        </div>
        @if($pendingCount > 0)
            <flux:badge size="md" color="amber">{{ $pendingCount }} pending</flux:badge>
        @endif
    </div>

    <div class="flex items-center gap-2">
        @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
            <button wire:click="$set('statusFilter', '{{ $key }}')"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors {{ $statusFilter === $key ? 'bg-accent text-white' : 'bg-surface-2 text-muted hover:text-primary' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if($claims->isEmpty())
        <div class="rounded-xl border border-border bg-surface px-5 py-12 text-center">
            <p class="text-muted">No claims match this filter.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($claims as $claim)
                <div wire:key="claim-row-{{ $claim->id }}" class="rounded-xl border border-border bg-surface p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-primary">{{ $claim->user?->name ?? 'Unknown user' }}</span>
                                <span class="text-muted text-sm">claiming</span>
                                <span class="font-semibold text-primary">{{ $claim->shooter?->name ?? 'Unknown shooter' }}</span>
                                @if($claim->shooter?->squad)
                                    <span class="text-xs text-muted">({{ $claim->shooter->squad->name }})</span>
                                @endif
                                <flux:badge size="sm" color="{{ $claim->status->color() }}">{{ $claim->status->label() }}</flux:badge>
                            </div>
                            <div class="mt-1 text-xs text-muted">
                                {{ $claim->user?->email }} &bull; {{ $claim->match?->name ?? '—' }}
                                @if($claim->match?->date)
                                    &bull; {{ $claim->match->date->format('d M Y') }}
                                @endif
                                &bull; submitted {{ $claim->created_at->diffForHumans() }}
                            </div>
                            @if($claim->evidence)
                                <p class="mt-2 rounded-md bg-surface-2 px-3 py-2 text-xs text-secondary"><strong>Evidence:</strong> {{ $claim->evidence }}</p>
                            @endif
                            @if($claim->reviewer_note)
                                <p class="mt-2 text-xs text-muted"><strong>Reviewer ({{ $claim->reviewer?->name }}):</strong> {{ $claim->reviewer_note }}</p>
                            @endif
                        </div>

                        @if($claim->isPending())
                            <div class="flex flex-col items-end gap-2">
                                <input type="text" wire:model.defer="reviewerNotes.{{ $claim->id }}"
                                       placeholder="Note (optional)"
                                       class="w-60 rounded-md border border-border bg-surface-2 px-2 py-1 text-xs text-primary" />
                                <div class="flex gap-2">
                                    <flux:button size="sm" wire:click="reject({{ $claim->id }})"
                                                 wire:confirm="Reject this claim?"
                                                 variant="ghost" class="!text-red-400 hover:!text-red-300">Reject</flux:button>
                                    <flux:button size="sm" wire:click="approve({{ $claim->id }})"
                                                 wire:confirm="Approve this claim and link the shooter to this user?"
                                                 variant="primary" class="!bg-emerald-600 hover:!bg-emerald-500">Approve</flux:button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
