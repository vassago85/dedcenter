<?php

use App\Enums\MatchStatus;
use App\Models\ShootingMatch;
use App\Models\MatchRegistration;
use App\Models\Shooter;
use App\Models\Squad;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Flux\Flux;

new #[Layout('components.layouts.app')]
    class extends Component {
    public ShootingMatch $match;
    public ?MatchRegistration $registration = null;
    public ?Shooter $myShooter = null;

    public function mount(ShootingMatch $match): void
    {
        $this->match = $match;

        $this->registration = MatchRegistration::where('match_id', $match->id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$this->registration || !$this->registration->isConfirmed()) {
            abort(403, 'You must have a confirmed registration to pick a squad.');
        }

        if ($match->status !== MatchStatus::SquaddingOpen) {
            abort(403, 'Squadding is not open for this match.');
        }

        if (!$match->isSelfSquaddingEnabled()) {
            abort(403, 'Self-squadding is not enabled for this match. The match director will assign squads.');
        }

        $this->myShooter = Shooter::where('user_id', auth()->id())
            ->whereIn('squad_id', $match->squads()->pluck('id'))
            ->first();
    }

    public function getTitle(): string
    {
        return 'Pick Your Squad — ' . $this->match->name;
    }

    public function joinSquad(int $squadId): void
    {
        $squad = $this->match->squads()->findOrFail($squadId);

        if ($squad->isFull()) {
            Flux::toast("{$squad->name} is full. Choose another squad.", variant: 'danger');
            return;
        }

        if ($this->myShooter) {
            if ($this->myShooter->squad_id === $squadId) {
                Flux::toast("You're already in {$squad->name}.", variant: 'warning');
                return;
            }
            $this->myShooter->update(['squad_id' => $squadId]);
            Flux::toast("Moved to {$squad->name}.", variant: 'success');
        } else {
            $maxSort = Shooter::where('squad_id', $squadId)->max('sort_order') ?? 0;
            $this->myShooter = Shooter::create([
                'squad_id' => $squadId,
                'name' => auth()->user()->name,
                'user_id' => auth()->id(),
                'sort_order' => $maxSort + 1,
            ]);
            Flux::toast("Joined {$squad->name}!", variant: 'success');
        }

        $this->myShooter = $this->myShooter->fresh();
    }

    public function with(): array
    {
        $squads = $this->match->squads()
            ->withCount('shooters')
            ->with(['shooters' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->reject(fn ($s) => in_array($s->name, ['Default', 'Unassigned']));

        return ['squads' => $squads];
    }
}; ?>

<div class="space-y-8 max-w-4xl" wire:poll.5s>
    <div class="flex items-center gap-4">
        <flux:button href="{{ route('matches.show', $match) }}" variant="ghost" size="sm">
            <x-icon name="chevron-left" class="mr-1 h-4 w-4" />
            Back
        </flux:button>
        <div>
            <flux:heading size="xl">Pick Your Squad</flux:heading>
            <p class="mt-1 text-sm text-muted">{{ $match->name }} &mdash; {{ $match->date?->format('d M Y') }}</p>
        </div>
    </div>

    @if($myShooter)
        <div class="rounded-xl border border-green-800 bg-green-900/20 p-4 flex items-center gap-3">
            <x-icon name="circle-check" class="h-5 w-5 text-green-400" />
            <div>
                <p class="text-sm font-medium text-green-400">You are in: {{ $myShooter->squad->name }}</p>
                <p class="text-xs text-muted">You can switch to another squad below if there's room.</p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($squads as $squad)
            @php
                $cap = $squad->effectiveCapacity();
                $remaining = $squad->spotsRemaining();
                $isMine = $myShooter && $myShooter->squad_id === $squad->id;
                $full = $remaining !== null && $remaining <= 0 && !$isMine;
            @endphp
            <div class="rounded-xl border {{ $isMine ? 'border-green-600 ring-2 ring-green-600/30' : 'border-border' }} bg-surface p-5 space-y-3 transition-all {{ $full ? 'opacity-50' : '' }}">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-primary">{{ $squad->name }}</h3>
                    @if($isMine)
                        <flux:badge size="sm" color="green">Your Squad</flux:badge>
                    @elseif($full)
                        <flux:badge size="sm" color="red">Full</flux:badge>
                    @endif
                </div>

                <div class="flex items-center gap-4 text-sm text-muted">
                    <span>{{ $squad->shooters_count }}{{ $cap ? " / {$cap}" : '' }} shooters</span>
                    @if($remaining !== null && !$full)
                        <span class="text-green-400">{{ $remaining }} spots left</span>
                    @endif
                </div>

                @if($squad->shooters->isNotEmpty())
                    <div class="space-y-1 max-h-40 overflow-y-auto">
                        @foreach($squad->shooters as $shooter)
                            <div class="flex items-center gap-2 text-sm {{ $shooter->user_id === auth()->id() ? 'text-green-400 font-medium' : 'text-secondary' }}">
                                @if($shooter->user_id === auth()->id())
                                    <span class="h-1.5 w-1.5 rounded-full bg-green-400"></span>
                                @else
                                    <span class="h-1.5 w-1.5 rounded-full bg-surface-2"></span>
                                @endif
                                {{ $shooter->name }}
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-muted">No shooters yet — be the first!</p>
                @endif

                @if(!$isMine && !$full)
                    <button wire:click="joinSquad({{ $squad->id }})"
                            class="w-full rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-accent-hover">
                        {{ $myShooter ? 'Switch to ' . $squad->name : 'Join ' . $squad->name }}
                    </button>
                @elseif($isMine)
                    <p class="text-center text-xs text-green-400/60">You're here!</p>
                @endif
            </div>
        @endforeach
    </div>

    @if($squads->isEmpty())
        <div class="rounded-xl border border-dashed border-border bg-surface/50 p-8 text-center">
            <p class="text-muted">No squads have been set up for this match yet. Check back later.</p>
        </div>
    @endif
</div>
