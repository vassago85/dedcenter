<?php

use App\Enums\MatchStatus;
use App\Enums\ShooterClaimStatus;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\ShooterAccountClaim;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Claim Your Account')]
    class extends Component {
    public ?int $selectedMatchId = null;
    public ?int $selectedShooterId = null;
    public string $evidence = '';

    /** True when the selection came from a deep-link (e.g. the results-page chip). */
    public bool $prefilled = false;

    /**
     * Route: GET /claim?match={id}&shooter={id}
     *
     * Deep-link support for the "Claim this result" chip on match report pages.
     * If the link targets a shooter entry that still has no linked user and the
     * user has no pending/approved claim for it yet, we pre-select both values
     * so the member only has to click "Submit claim".
     *
     * The member still clicks Submit; the claim still goes to Pending for
     * administrator review. No auto-approval.
     */
    public function mount(?int $match = null, ?int $shooter = null): void
    {
        $matchParam = $match ?? (int) request()->query('match');
        $shooterParam = $shooter ?? (int) request()->query('shooter');

        if (! $matchParam || ! $shooterParam) {
            return;
        }

        $shooterModel = Shooter::query()
            ->whereHas('squad', fn ($q) => $q->where('match_id', $matchParam))
            ->unclaimedResult()
            ->find($shooterParam);

        if (! $shooterModel) {
            Flux::toast(
                'That shooter entry is either already linked to a real account or not part of the match in the link.',
                variant: 'warning',
            );
            return;
        }

        $already = ShooterAccountClaim::where('user_id', auth()->id())
            ->where('shooter_id', $shooterModel->id)
            ->whereIn('status', [ShooterClaimStatus::Pending, ShooterClaimStatus::Approved])
            ->exists();

        if ($already) {
            Flux::toast('You already have a claim in for that shooter — waiting for admin review.', variant: 'info');
            return;
        }

        $this->selectedMatchId = $matchParam;
        $this->selectedShooterId = $shooterModel->id;
        $this->prefilled = true;
    }

    public function with(): array
    {
        $user = auth()->user();

        $recentMatches = ShootingMatch::query()
            ->where('status', MatchStatus::Completed)
            ->whereDate('date', '>=', now()->subDays(30))
            ->orderByDesc('date')
            ->get(['id', 'name', 'date']);

        // If the user arrived via a deep link for a match that isn't in the
        // 30-day Completed window (e.g. scores published before the match is
        // formally marked Completed, or slightly older), make sure the
        // selected match still appears in the dropdown so the form is valid.
        if ($this->selectedMatchId && ! $recentMatches->firstWhere('id', $this->selectedMatchId)) {
            $deepLinked = ShootingMatch::query()
                ->whereKey($this->selectedMatchId)
                ->first(['id', 'name', 'date']);
            if ($deepLinked) {
                $recentMatches = $recentMatches->prepend($deepLinked);
            }
        }

        $availableShooters = collect();
        if ($this->selectedMatchId) {
            $match = ShootingMatch::find($this->selectedMatchId);
            if ($match) {
                $availableShooters = $match->shooters()
                    ->unclaimedResult()
                    ->with('squad:id,name')
                    ->orderBy('name')
                    ->get(['shooters.id', 'shooters.name', 'shooters.squad_id', 'shooters.user_id']);
            }
        }

        $myClaims = ShooterAccountClaim::where('user_id', $user->id)
            ->with(['shooter:id,name', 'match:id,name,date'])
            ->latest()
            ->get();

        $hasLinkedShooter = Shooter::where('user_id', $user->id)->exists();

        return [
            'recentMatches' => $recentMatches,
            'availableShooters' => $availableShooters,
            'myClaims' => $myClaims,
            'hasLinkedShooter' => $hasLinkedShooter,
        ];
    }

    public function submitClaim(): void
    {
        $this->validate([
            'selectedMatchId' => ['required', 'integer', 'exists:matches,id'],
            'selectedShooterId' => ['required', 'integer', 'exists:shooters,id'],
            'evidence' => ['nullable', 'string', 'max:2000'],
        ]);

        $shooter = Shooter::with('user:id,email')->findOrFail($this->selectedShooterId);

        if (! $shooter->isUnclaimedResult()) {
            Flux::toast('That shooter entry has already been linked to a real account.', variant: 'danger');
            return;
        }

        $existing = ShooterAccountClaim::where('user_id', auth()->id())
            ->where('shooter_id', $shooter->id)
            ->whereIn('status', [ShooterClaimStatus::Pending, ShooterClaimStatus::Approved])
            ->exists();

        if ($existing) {
            Flux::toast('You already have a claim for that shooter.', variant: 'warning');
            return;
        }

        ShooterAccountClaim::create([
            'shooter_id' => $shooter->id,
            'user_id' => auth()->id(),
            'match_id' => $this->selectedMatchId,
            'status' => ShooterClaimStatus::Pending,
            'evidence' => $this->evidence ?: null,
        ]);

        $this->reset(['selectedShooterId', 'evidence']);
        Flux::toast('Claim submitted. An administrator will review it shortly.', variant: 'success');
    }

    public function withdraw(int $claimId): void
    {
        $claim = ShooterAccountClaim::where('id', $claimId)
            ->where('user_id', auth()->id())
            ->where('status', ShooterClaimStatus::Pending)
            ->first();

        if (! $claim) {
            Flux::toast('Claim not found or already reviewed.', variant: 'danger');
            return;
        }

        $claim->update(['status' => ShooterClaimStatus::Withdrawn]);
        Flux::toast('Claim withdrawn.', variant: 'success');
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-primary">Claim your account</h1>
        <p class="mt-1 text-sm text-muted">
            Shot in a recent match but you weren't logged in? Find your name in the match's shooter list and submit a claim. An administrator will review it and link the shooter entry to your account so scores and badges appear on your profile.
        </p>
    </div>

    @if($prefilled && $selectedShooterId)
        @php
            $prefilledMatch = $recentMatches->firstWhere('id', $selectedMatchId);
            $prefilledShooter = $availableShooters->firstWhere('id', $selectedShooterId);
        @endphp
        <div class="rounded-xl border border-accent/40 bg-accent/10 px-4 py-3 text-sm text-primary">
            <div class="font-semibold">We've pre-filled this claim for you.</div>
            <div class="mt-1 text-sm text-muted">
                You're claiming
                @if($prefilledShooter)<span class="font-semibold text-primary">{{ $prefilledShooter->name }}</span>@endif
                @if($prefilledMatch) at <span class="font-semibold text-primary">{{ $prefilledMatch->name }}</span>@endif.
                Add any evidence below (optional) and submit — an administrator will review the request before your scores
                and badges appear on your profile.
            </div>
        </div>
    @endif

    @if($hasLinkedShooter)
        <div class="rounded-xl border border-emerald-600/40 bg-emerald-900/10 px-4 py-3 text-sm text-emerald-300">
            You already have at least one linked shooter profile. You can still submit additional claims for other matches.
        </div>
    @endif

    <div class="rounded-xl border border-border bg-surface p-5 space-y-4">
        <h2 class="text-lg font-semibold text-primary">New claim</h2>

        <div>
            <label class="block text-xs font-medium text-muted mb-1">Match (last 30 days, completed)</label>
            <select wire:model.live="selectedMatchId" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary">
                <option value="">Select a match...</option>
                @foreach($recentMatches as $m)
                    <option value="{{ $m->id }}" wire:key="match-opt-{{ $m->id }}">{{ $m->name }} — {{ $m->date?->format('d M Y') }}</option>
                @endforeach
            </select>
            @if($recentMatches->isEmpty())
                <p class="mt-1 text-xs text-muted">No completed matches in the last 30 days.</p>
            @endif
        </div>

        @if($selectedMatchId)
            <div>
                <label class="block text-xs font-medium text-muted mb-1">Shooter entry</label>
                <select wire:model="selectedShooterId" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary">
                    <option value="">Select your name...</option>
                    @foreach($availableShooters as $s)
                        <option value="{{ $s->id }}" wire:key="shooter-opt-{{ $s->id }}">{{ $s->name }}{{ $s->squad ? ' (' . $s->squad->name . ')' : '' }}</option>
                    @endforeach
                </select>
                @if($availableShooters->isEmpty())
                    <p class="mt-1 text-xs text-muted">All shooter entries for this match are already linked to accounts.</p>
                @endif
            </div>
        @endif

        <div>
            <label class="block text-xs font-medium text-muted mb-1">Evidence / notes (optional)</label>
            <textarea wire:model="evidence" rows="3"
                      placeholder="e.g. bib #12 on squad Alpha, rifle was 6.5 Creedmoor"
                      class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary"></textarea>
        </div>

        <div class="flex justify-end">
            <flux:button wire:click="submitClaim" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                Submit claim
            </flux:button>
        </div>
    </div>

    <div>
        <h2 class="mb-3 text-lg font-semibold text-primary">Your claims</h2>
        @if($myClaims->isEmpty())
            <div class="rounded-xl border border-border bg-surface px-5 py-6 text-center text-sm text-muted">
                You have not submitted any claims yet.
            </div>
        @else
            <div class="rounded-xl border border-border bg-surface overflow-hidden divide-y divide-border/50">
                @foreach($myClaims as $claim)
                    <div class="flex items-center justify-between gap-3 px-5 py-3" wire:key="claim-{{ $claim->id }}">
                        <div class="min-w-0">
                            <div class="font-semibold text-primary truncate">{{ $claim->shooter?->name ?? 'Unknown shooter' }}</div>
                            <div class="text-xs text-muted">
                                {{ $claim->match?->name ?? '—' }}
                                @if($claim->match?->date)
                                    &bull; {{ $claim->match->date->format('d M Y') }}
                                @endif
                                &bull; submitted {{ $claim->created_at->diffForHumans() }}
                            </div>
                            @if($claim->status === \App\Enums\ShooterClaimStatus::Rejected && $claim->reviewer_note)
                                <p class="mt-1 text-xs text-red-400">Admin: {{ $claim->reviewer_note }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" color="{{ $claim->status->color() }}">{{ $claim->status->label() }}</flux:badge>
                            @if($claim->status === \App\Enums\ShooterClaimStatus::Pending)
                                <flux:button size="xs" variant="ghost" wire:click="withdraw({{ $claim->id }})"
                                             wire:confirm="Withdraw this claim?">Withdraw</flux:button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
