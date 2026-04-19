<?php

use App\Enums\MatchStatus;
use App\Models\Organization;
use App\Models\ShootingMatch;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    class extends Component {
    public Organization $organization;
    public ShootingMatch $match;

    public array $boughtIn = [];

    public function mount(Organization $organization, ShootingMatch $match): void
    {
        if (! $match->userCanEditInOrg(auth()->user())) {
            abort(403, 'You are not authorized to manage side bet for this match.');
        }

        $this->organization = $organization;
        $this->match = $match;
        $this->boughtIn = $match->sideBetShooters()->pluck('shooters.id')->map(fn ($id) => (int) $id)->toArray();
    }

    public function getTitle(): string
    {
        return 'Side Bet Buy-In — '.$this->match->name;
    }

    public function enableSideBet(): void
    {
        if (! $this->organization->isRoyalFlushOrg()) {
            Flux::toast('Side Bet is only available on Royal Flush matches.', variant: 'danger');
            return;
        }

        $this->match->update([
            'royal_flush_enabled' => true,
            'side_bet_enabled' => true,
        ]);
        $this->match->refresh();
        Flux::toast('Side Bet enabled. Tap shooters to add them to the pot.', variant: 'success');
    }

    public function disableSideBet(): void
    {
        if ($this->match->status === MatchStatus::Completed) {
            Flux::toast('Side Bet is locked once the match is completed.', variant: 'danger');
            return;
        }

        $this->match->update(['side_bet_enabled' => false]);
        $this->match->sideBetShooters()->detach();
        $this->match->refresh();
        $this->boughtIn = [];
        Flux::toast('Side Bet disabled. All buy-ins cleared.', variant: 'success');
    }

    public function toggleShooter(int $shooterId): void
    {
        if ($this->match->status === MatchStatus::Completed) {
            Flux::toast('Buy-in is locked once the match is completed.', variant: 'danger');
            return;
        }
        if (! $this->match->side_bet_enabled) {
            return;
        }

        $shooter = $this->match->shooters()->whereKey($shooterId)->first();
        if (! $shooter) {
            return;
        }

        if (in_array($shooterId, $this->boughtIn, true)) {
            $this->match->sideBetShooters()->detach($shooterId);
            $this->boughtIn = array_values(array_filter($this->boughtIn, fn ($id) => $id !== $shooterId));
        } else {
            $this->match->sideBetShooters()->syncWithoutDetaching([$shooterId]);
            $this->boughtIn[] = $shooterId;
        }
    }

    public function with(): array
    {
        // Single flat A→Z list. MDs want to find "that one Johan guy" without
        // first remembering which squad he's on — grouping by squad added
        // friction for matches with 40+ shooters. The squad is still
        // rendered inline on each row so the context isn't lost.
        $shooters = $this->match
            ->shooters()
            ->with('squad:id,name')
            ->orderByRaw('LOWER(shooters.name) asc')
            ->get();

        return [
            'shooters' => $shooters,
            'totalShooters' => $shooters->count(),
            'totalBoughtIn' => count($this->boughtIn),
            'locked' => $this->match->status === MatchStatus::Completed,
        ];
    }
}; ?>

<div class="mx-auto max-w-5xl p-4 sm:p-6 space-y-6">

    <x-match-hub-tabs :match="$match" :organization="$organization" />

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-muted">
                <a href="{{ route('org.matches.hub', [$organization, $match]) }}" class="hover:text-accent">{{ $match->name }}</a>
                <span>›</span>
                <span>Side Bet</span>
            </div>
            <h1 class="mt-1 text-2xl font-bold text-primary">Side Bet Buy-In</h1>
            <p class="text-sm text-muted">Individual shooters only. Tap a shooter to toggle them in or out of the pot. Saves instantly.</p>
        </div>
        <div class="flex items-center gap-2">
            @if($match->side_bet_enabled)
                <flux:button href="{{ route('org.matches.side-bet-report', [$organization, $match]) }}" variant="ghost" size="sm">Report</flux:button>
            @endif
        </div>
    </div>

    {{-- Not enabled yet --}}
    @if(! $match->side_bet_enabled || ! $match->royal_flush_enabled)
        <div class="rounded-xl border border-amber-600/40 bg-amber-900/10 p-6 space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-primary">Side Bet is off</h2>
                <p class="mt-1 text-sm text-muted">Turn Side Bet on to start collecting buy-ins. You can disable it again anytime before the match completes.</p>
            </div>

            @if(! $organization->isRoyalFlushOrg())
                <p class="text-sm text-amber-300">Side Bet requires a Royal Flush match. Ask Dead Center to flip your organization onto the Royal Flush programme.</p>
            @else
                <button type="button" wire:click="enableSideBet"
                        wire:confirm="Enable Side Bet for this match? Royal Flush will also be turned on if it isn't already."
                        class="inline-flex items-center gap-2 rounded-lg bg-amber-600 hover:bg-amber-700 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-amber-900/40 transition-colors min-h-[44px]">
                    <x-icon name="check" class="h-4 w-4" />
                    Enable Side Bet
                </button>
            @endif
        </div>
    @else
        {{-- Summary + actions --}}
        <div class="rounded-xl border border-amber-600/50 bg-gradient-to-br from-amber-900/15 to-surface p-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-6">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-muted">In the pot</div>
                        <div class="mt-1 text-3xl font-bold text-amber-400 tabular-nums">{{ $totalBoughtIn }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-muted">Out</div>
                        <div class="mt-1 text-3xl font-bold text-muted tabular-nums">{{ max(0, $totalShooters - $totalBoughtIn) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-muted">Total registered</div>
                        <div class="mt-1 text-3xl font-bold text-primary tabular-nums">{{ $totalShooters }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if($locked)
                        <span class="rounded-full bg-zinc-600 px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-zinc-300">Locked — Match Completed</span>
                    @else
                        <flux:button href="{{ route('org.matches.side-bet-report', [$organization, $match]) }}" variant="ghost" size="sm">View Report</flux:button>
                        <button type="button" wire:click="disableSideBet"
                                wire:confirm="Disable Side Bet and clear all buy-ins? You can re-enable it later but the buy-in list will be empty."
                                class="rounded-lg border border-red-600/40 px-3 py-2 text-xs font-medium text-red-400 hover:border-red-500 hover:text-red-300 hover:bg-red-900/20 transition-colors min-h-[36px]">
                            Turn off
                        </button>
                    @endif
                </div>
            </div>
        </div>

        @if($totalShooters === 0)
            <div class="rounded-xl border border-border bg-surface p-10 text-center">
                <p class="text-sm text-muted">No shooters registered yet. Add shooters on the <a href="{{ route('org.matches.squadding', [$organization, $match]) }}" class="text-accent hover:underline">Squadding page</a>, then come back to tick buy-ins.</p>
            </div>
        @else
            {{-- Flat alphabetical list with client-side search.
                 Squad grouping was removed because it slowed MDs down on
                 big matches; the squad name is still shown inline so the
                 context isn't lost. Search runs in Alpine so every
                 keystroke stays on the client — Livewire only fires when
                 a buy-in is actually toggled. --}}
            <div
                x-data="{
                    q: '',
                    norm(s){ return (s ?? '').toString().toLowerCase(); },
                    matches(haystack){ return this.q === '' || haystack.includes(this.norm(this.q)); },
                    get visibleCount() {
                        const needle = this.norm(this.q);
                        if (!needle) return this.$root.dataset.total | 0;
                        return (this.$root.dataset.haystacks || '')
                            .split('|')
                            .filter(h => h.includes(needle))
                            .length;
                    }
                }"
                data-total="{{ $totalShooters }}"
                data-haystacks="{{ $shooters->map(fn($s) => trim(strtolower(implode(' ', array_filter([$s->name, $s->bib_number, $s->squad?->name]))))) ->implode('|') }}"
                class="rounded-xl border border-border bg-surface overflow-hidden">
                <div class="border-b border-border px-4 py-3 bg-surface-2/40">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base font-semibold text-primary">Shooters</h3>
                        <span class="text-xs text-muted">{{ $totalShooters }} registered &bull; sorted A→Z</span>
                    </div>
                    <div class="relative mt-3">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                            <x-icon name="search" class="h-4 w-4" />
                        </span>
                        <input
                            type="text"
                            x-model="q"
                            placeholder="Search by name, bib number, or squad…"
                            aria-label="Search shooters"
                            class="w-full rounded-lg border border-border bg-surface py-2 pl-9 pr-9 text-sm text-primary placeholder:text-muted focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent min-h-[40px]"
                        />
                        <button
                            type="button"
                            x-show="q.length > 0"
                            x-cloak
                            @click="q = ''"
                            aria-label="Clear search"
                            class="absolute inset-y-0 right-2 flex items-center text-muted hover:text-primary"
                        >
                            <x-icon name="x" class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <ul class="divide-y divide-border/40">
                    @foreach($shooters as $shooter)
                        @php
                            $isIn = in_array((int) $shooter->id, $boughtIn, true);
                            $squadLabel = $shooter->squad?->name;
                            // Pre-compose the haystack server-side so Alpine
                            // only has to lowercase it once per keystroke.
                            // Stored on `data-haystack` rather than inlined
                            // into the Alpine expression, so apostrophes in
                            // names (e.g. "O'Brien") can't break the JS.
                            $haystack = trim(strtolower(implode(' ', array_filter([
                                $shooter->name,
                                $shooter->bib_number,
                                $squadLabel,
                            ]))));
                        @endphp
                        <li wire:key="sb-shooter-{{ $shooter->id }}"
                            data-haystack="{{ $haystack }}"
                            x-show="matches($el.dataset.haystack)"
                            x-cloak>
                            <button type="button"
                                    @if(! $locked) wire:click="toggleShooter({{ $shooter->id }})" @else disabled @endif
                                    class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition-colors min-h-[56px]
                                        {{ $isIn ? 'bg-amber-600/10 hover:bg-amber-600/15' : 'bg-transparent hover:bg-surface-2/50' }}
                                        {{ $locked ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer' }}">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 transition-colors
                                        {{ $isIn ? 'border-amber-500 bg-amber-500 text-white' : 'border-border bg-surface-2 text-transparent' }}">
                                        <x-icon name="check" class="h-5 w-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-primary truncate">{{ $shooter->name }}</div>
                                        <div class="flex items-center gap-2 text-xs text-muted">
                                            @if($shooter->bib_number)
                                                <span class="whitespace-nowrap">Bib {{ $shooter->bib_number }}</span>
                                                <span aria-hidden="true">&bull;</span>
                                            @endif
                                            <span class="truncate">{{ $squadLabel ?? '—' }}</span>
                                        </div>
                                    </div>
                                </div>
                                @if($isIn)
                                    <span class="rounded-full bg-amber-600/25 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-300 whitespace-nowrap">In the pot</span>
                                @else
                                    <span class="text-[10px] uppercase tracking-wider text-muted whitespace-nowrap">Tap to add</span>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>

                {{-- No-matches sentinel. Reactive: visibleCount is a
                     getter that recomputes whenever `q` changes. --}}
                <div x-show="q.length > 0 && visibleCount === 0" x-cloak class="px-4 py-8 text-center">
                    <p class="text-sm text-muted">No shooters match &ldquo;<span class="text-primary font-medium" x-text="q"></span>&rdquo;.</p>
                </div>
            </div>
        @endif
    @endif
</div>
