<?php

use App\Enums\MatchStatus;
use App\Models\ShootingMatch;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Results — DeadCenter')]
    class extends Component {
    /**
     * The sidebar "Results" tab lands here. This is the shooter's home for
     * their completed matches — one place per match with a report + full
     * scoreboard link. Previously the sidebar pointed at
     * `/events?tab=my_events`, which is a different job (upcoming +
     * registered matches) and confusingly rendered as "My Matches". If a
     * result is not showing up, the "missing match" hint at the bottom
     * routes them to the public scoreboard where the Claim chip lives.
     */
    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'year')]
    public string $yearFilter = '';

    public function clearFilters(): void
    {
        $this->reset(['search', 'yearFilter']);
    }

    public function with(): array
    {
        $userId = Auth::id();

        // Every completed match where the current user has a linked
        // Shooter row (via squad → match). Matches the dashboard "Recent
        // Results" query so both surfaces agree on what "my results" means.
        $query = ShootingMatch::query()
            ->with('organization')
            ->where('status', MatchStatus::Completed)
            ->whereHas('shooters', fn ($q) => $q->where('user_id', $userId))
            ->withCount('shooters')
            ->orderByDesc('date');

        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)
                ->orWhere('location', 'like', $term)
                ->orWhereHas('organization', fn ($oq) => $oq->where('name', 'like', $term)));
        }

        if ($this->yearFilter !== '' && ctype_digit($this->yearFilter)) {
            $query->whereYear('date', (int) $this->yearFilter);
        }

        $matches = $query->get();

        // Year chips — pulled from *all* the user's completed matches so
        // clearing the search still surfaces every year available. Sorted
        // newest first so the current season is the leftmost chip.
        $availableYears = ShootingMatch::query()
            ->where('status', MatchStatus::Completed)
            ->whereHas('shooters', fn ($q) => $q->where('user_id', $userId))
            ->selectRaw('DISTINCT YEAR(date) as y')
            ->orderByDesc('y')
            ->pluck('y')
            ->filter()
            ->values();

        return [
            'matches' => $matches,
            'availableYears' => $availableYears,
            'hasFilters' => $this->search !== '' || $this->yearFilter !== '',
        ];
    }
}; ?>

<div class="space-y-6">
    <x-app-page-header
        eyebrow="Your history"
        title="Results"
        subtitle="Every completed match you've shot — with a personal report and the full scoreboard.">
        <x-slot:actions>
            <a href="{{ route('browse-events') }}"
               class="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-border bg-surface px-4 text-sm font-semibold text-secondary transition-colors hover:border-accent hover:text-primary">
                <x-icon name="plus" class="h-4 w-4" />
                Find a match
            </a>
        </x-slot:actions>
    </x-app-page-header>

    {{-- Filter bar --}}
    @if($matches->isNotEmpty() || $hasFilters)
        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-surface px-4 py-3">
            <div class="relative min-w-[220px] flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
                <input type="search" wire:model.live.debounce.300ms="search"
                       placeholder="Search by match, location, or organization"
                       class="w-full rounded-lg border border-border bg-surface-2 py-2 pl-9 pr-3 text-sm text-primary placeholder:text-muted focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
            </div>

            @if($availableYears->count() > 1)
                <div class="flex flex-wrap items-center gap-1.5">
                    <button type="button" wire:click="$set('yearFilter', '')"
                            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $yearFilter === '' ? 'bg-accent text-white' : 'bg-surface-2 text-muted hover:text-primary' }}">
                        All years
                    </button>
                    @foreach($availableYears as $year)
                        <button type="button" wire:click="$set('yearFilter', '{{ $year }}')"
                                class="rounded-lg px-3 py-1.5 text-xs font-semibold tabular-nums transition-colors {{ (string) $yearFilter === (string) $year ? 'bg-accent text-white' : 'bg-surface-2 text-muted hover:text-primary' }}">
                            {{ $year }}
                        </button>
                    @endforeach
                </div>
            @endif

            @if($hasFilters)
                <button type="button" wire:click="clearFilters"
                        class="text-xs font-semibold text-muted transition-colors hover:text-primary">
                    Clear
                </button>
            @endif
        </div>
    @endif

    {{-- Results list --}}
    @if($matches->isEmpty())
        <x-panel :padding="false">
            <x-empty-state
                title="{{ $hasFilters ? 'No matches match those filters' : 'No results yet' }}"
                description="{{ $hasFilters ? 'Try clearing the filters, or search for a different term.' : 'Once you shoot a completed match, it will appear here with your personal report and the full scoreboard.' }}">
                <x-slot:icon>
                    <x-icon name="trophy" class="h-6 w-6" />
                </x-slot:icon>
                @if(! $hasFilters)
                    <x-slot:actions>
                        <a href="{{ route('browse-events') }}"
                           class="inline-flex min-h-[36px] items-center gap-1.5 rounded-lg bg-accent px-3 text-xs font-semibold text-white hover:bg-accent-hover">
                            Find a match
                        </a>
                    </x-slot:actions>
                @endif
            </x-empty-state>
        </x-panel>
    @else
        <x-panel title="Your completed matches" :subtitle="$matches->count() . ' ' . \Illuminate\Support\Str::plural('match', $matches->count())" :padding="false">
            <ul class="divide-y divide-border/70">
                @foreach($matches as $match)
                    @php
                        $type = strtoupper($match->scoring_type ?? 'standard');
                        $typeLabel = $type === 'STANDARD' ? 'RELAY' : $type;
                    @endphp
                    <li class="group px-5 py-4 transition-colors hover:bg-surface-2/50">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                            <a href="{{ route('scoreboard', $match) }}"
                               class="flex min-w-0 flex-1 items-center gap-3">
                                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-surface-2 text-muted">
                                    <x-icon name="trophy" class="h-4 w-4" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate text-sm font-semibold text-primary transition-colors group-hover:text-accent">{{ $match->name }}</p>
                                        <span class="inline-flex items-center rounded-full bg-surface-2 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted">
                                            {{ $typeLabel }}
                                        </span>
                                        @if($match->royal_flush_enabled)
                                            <span class="inline-flex items-center rounded-full bg-amber-600/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-400">
                                                Royal Flush
                                            </span>
                                        @endif
                                    </div>
                                    <p class="mt-0.5 truncate text-xs text-muted">
                                        {{ $match->date?->format('d M Y') ?? '—' }}
                                        @if($match->organization) · {{ $match->organization->name }} @endif
                                        @if($match->location) · {{ $match->location }} @endif
                                    </p>
                                </div>
                            </a>
                            <div class="flex flex-shrink-0 flex-wrap items-center gap-2">
                                <a href="{{ route('matches.my-report', $match) }}"
                                   title="View & share your match report"
                                   class="inline-flex items-center gap-1.5 rounded-lg bg-accent px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-accent-hover">
                                    <x-icon name="share" class="h-3.5 w-3.5" />
                                    My report
                                </a>
                                <a href="{{ route('scoreboard', $match) }}"
                                   class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-2 px-3 py-1.5 text-xs font-semibold text-secondary transition-colors hover:border-accent/50 hover:text-primary">
                                    <x-icon name="chart-column" class="h-3.5 w-3.5" />
                                    Full scoreboard
                                </a>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-panel>
    @endif

    {{-- Missing-a-match hint. Claim only lives on the scoreboard as an
         inline chip on your unclaimed row; without this pointer users can't
         find it from Results. --}}
    <div class="flex items-start gap-3 rounded-xl border border-border bg-surface-2/30 px-4 py-3">
        <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-muted" />
        <div class="min-w-0 flex-1 text-xs text-muted">
            <span class="font-semibold text-secondary">Missing a match you shot?</span>
            Open its <a href="{{ route('events', ['tab' => 'past']) }}" class="text-accent hover:underline">public scoreboard</a>, find your name, and tap <span class="font-semibold text-primary">Claim</span>. A platform admin approves it, then your report and badges show up here automatically.
        </div>
    </div>
</div>
