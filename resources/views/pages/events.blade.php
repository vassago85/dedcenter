<?php

use App\Enums\MatchStatus;
use App\Enums\Province;
use App\Models\Organization;
use App\Models\ShootingMatch;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.scoreboard')]
    #[Title('Events — DeadCenter')]
    class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $eventType = '';

    #[Url]
    public string $province = '';

    #[Url]
    public string $organizationId = '';

    #[Url]
    public string $dateFilter = '';

    #[Url]
    public string $status = 'upcoming';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedEventType(): void { $this->resetPage(); }
    public function updatedProvince(): void { $this->resetPage(); }
    public function updatedOrganizationId(): void { $this->resetPage(); }
    public function updatedDateFilter(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }

    public function setStatus(string $tab): void
    {
        $this->status = $tab;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'eventType', 'province', 'organizationId', 'dateFilter']);
        $this->resetPage();
    }

    public function with(): array
    {
        $query = ShootingMatch::query()
            ->with('organization')
            ->withCount(['registrations', 'shooters'])
            ->where('status', '!=', MatchStatus::Draft);

        // Status tab — featured events always rank first
        match ($this->status) {
            'upcoming' => $query->whereIn('status', [
                MatchStatus::PreRegistration,
                MatchStatus::RegistrationOpen,
                MatchStatus::RegistrationClosed,
                MatchStatus::SquaddingOpen,
            ])->orderByRaw("CASE WHEN featured_status = 'active' THEN 0 ELSE 1 END")->orderBy('date'),
            'live' => $query->where('status', MatchStatus::Active)->orderByRaw("CASE WHEN featured_status = 'active' THEN 0 ELSE 1 END")->orderByDesc('date'),
            'completed' => $query->where('status', MatchStatus::Completed)->orderByRaw("CASE WHEN featured_status = 'active' THEN 0 ELSE 1 END")->orderByDesc('date'),
            default => $query->orderByRaw("CASE WHEN featured_status = 'active' THEN 0 ELSE 1 END")->orderByDesc('date'),
        };

        // Search
        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('location', 'like', $term));
        }

        // Event type
        if ($this->eventType !== '') {
            if ($this->eventType === 'royal_flush') {
                $query->where('royal_flush_enabled', true);
            } else {
                $query->where('scoring_type', $this->eventType);
            }
        }

        // Province
        if ($this->province !== '') {
            $query->where('province', $this->province);
        }

        // Organization
        if ($this->organizationId !== '') {
            $query->where('organization_id', (int) $this->organizationId);
        }

        // Date
        match ($this->dateFilter) {
            'this_month' => $query->whereMonth('date', now()->month)->whereYear('date', now()->year),
            'next_month' => $query->whereMonth('date', now()->addMonth()->month)->whereYear('date', now()->addMonth()->year),
            'past' => $query->where('date', '<', now()->startOfDay()),
            default => null,
        };

        $matches = $query->paginate(12);
        $organizations = Organization::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        // Counts for tabs
        $baseCounts = ShootingMatch::where('status', '!=', MatchStatus::Draft);
        $upcomingCount = (clone $baseCounts)->whereIn('status', [
            MatchStatus::PreRegistration, MatchStatus::RegistrationOpen,
            MatchStatus::RegistrationClosed, MatchStatus::SquaddingOpen,
        ])->count();
        $liveCount = (clone $baseCounts)->where('status', MatchStatus::Active)->count();
        $completedCount = (clone $baseCounts)->where('status', MatchStatus::Completed)->count();
        $allCount = (clone $baseCounts)->count();

        return [
            'matches' => $matches,
            'organizations' => $organizations,
            'provinces' => Province::cases(),
            'upcomingCount' => $upcomingCount,
            'liveCount' => $liveCount,
            'completedCount' => $completedCount,
            'allCount' => $allCount,
        ];
    }
}; ?>

<div class="min-h-screen bg-app px-4 py-8 sm:px-6 lg:px-10 lg:py-12">
    <div class="mx-auto max-w-7xl">

        {{-- Header --}}
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-black tracking-tight text-primary sm:text-4xl lg:text-5xl">Events</h1>
            <p class="mt-2 text-sm text-muted sm:text-base">Browse shooting competitions, register for events, and view results across South Africa.</p>
        </div>

        {{-- Status tabs --}}
        <div class="mb-6 flex flex-wrap items-center justify-center gap-2">
            @foreach([
                'upcoming' => ['label' => 'Upcoming', 'count' => $upcomingCount],
                'live' => ['label' => 'Live Now', 'count' => $liveCount],
                'completed' => ['label' => 'Completed', 'count' => $completedCount],
                'all' => ['label' => 'All', 'count' => $allCount],
            ] as $key => $tab)
                <button type="button" wire:click="setStatus('{{ $key }}')"
                        class="rounded-full px-4 py-2 text-xs font-bold transition-colors sm:text-sm {{ $status === $key ? 'bg-accent text-white' : 'bg-surface text-muted hover:bg-surface-2 hover:text-secondary' }}">
                    {{ $tab['label'] }}
                    <span class="ml-1 opacity-60">{{ $tab['count'] }}</span>
                </button>
            @endforeach
        </div>

        {{-- Filter bar --}}
        <div class="mb-8 rounded-xl border border-border bg-surface/50 p-4">
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[12rem] flex-1">
                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-wider text-muted">Search</label>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Match name or location..."
                           class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted/50 focus:border-accent focus:ring-1 focus:ring-accent" />
                </div>

                <div class="w-36">
                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-wider text-muted">Event Type</label>
                    <select wire:model.live="eventType"
                            class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:ring-1 focus:ring-accent">
                        <option value="">All Types</option>
                        <option value="prs">PRS</option>
                        <option value="standard">Relay</option>
                        <option value="elr">ELR</option>
                        <option value="royal_flush">Royal Flush</option>
                    </select>
                </div>

                <div class="w-40">
                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-wider text-muted">Province</label>
                    <select wire:model.live="province"
                            class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:ring-1 focus:ring-accent">
                        <option value="">All Provinces</option>
                        @foreach($provinces as $p)
                            <option value="{{ $p->value }}">{{ $p->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-44">
                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-wider text-muted">Organization</label>
                    <select wire:model.live="organizationId"
                            class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:ring-1 focus:ring-accent">
                        <option value="">All Organizations</option>
                        @foreach($organizations as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-36">
                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-wider text-muted">Date</label>
                    <select wire:model.live="dateFilter"
                            class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:ring-1 focus:ring-accent">
                        <option value="">Any Date</option>
                        <option value="this_month">This Month</option>
                        <option value="next_month">Next Month</option>
                        <option value="past">Past Events</option>
                    </select>
                </div>

                @if($search || $eventType || $province || $organizationId || $dateFilter)
                    <button type="button" wire:click="clearFilters"
                            class="rounded-lg border border-border bg-surface-2 px-3 py-2 text-xs font-medium text-muted transition-colors hover:text-primary">
                        Clear
                    </button>
                @endif
            </div>
        </div>

        {{-- Results --}}
        @if($matches->count())
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($matches as $match)
                    @php
                        $statusEnum = $match->status;
                        $statusValue = $statusEnum instanceof \BackedEnum ? $statusEnum->value : $statusEnum;
                        $isLive = $statusValue === 'active';
                        $isCompleted = $statusValue === 'completed';
                        $isPreReg = $statusValue === 'pre_registration';
                        $isRegOpen = $statusValue === 'registration_open';
                        $canRegister = $isPreReg || $isRegOpen;
                        $hasImage = !empty($match->image_url);
                        $org = $match->organization;

                        $scoringColors = [
                            'prs' => 'from-amber-900/40 to-surface',
                            'elr' => 'from-violet-900/40 to-surface',
                            'standard' => 'from-red-900/30 to-surface',
                        ];
                        $fallbackGradient = $scoringColors[$match->scoring_type ?? 'standard'] ?? $scoringColors['standard'];

                        $href = $isCompleted
                            ? route('scoreboard', $match)
                            : ($org ? route('portal.matches.show', [$org, $match]) : route('scoreboard', $match));
                    @endphp

                    <a href="{{ $href }}" class="group relative flex flex-col overflow-hidden rounded-2xl border border-border bg-surface shadow-md transition-all duration-200 hover:scale-[1.01] hover:shadow-lg hover:border-border/80">
                        {{-- Image header --}}
                        <div class="relative aspect-video overflow-hidden">
                            @if($hasImage)
                                <img src="{{ $match->image_url }}" alt="{{ $match->name }}" class="absolute inset-0 h-full w-full object-cover" loading="lazy" />
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-black/10"></div>
                            @else
                                <div class="absolute inset-0 bg-gradient-to-br {{ $fallbackGradient }}"></div>
                            @endif

                            {{-- Org logo --}}
                            @if($org && $org->logo_path)
                                <div class="absolute top-3 left-3">
                                    <img src="{{ Storage::url($org->logo_path) }}" alt="{{ $org->name }}" class="h-8 w-8 rounded-lg border border-white/20 object-cover shadow-lg" loading="lazy" />
                                </div>
                            @elseif($org)
                                <div class="absolute top-3 left-3 flex h-8 w-8 items-center justify-center rounded-lg border border-white/20 bg-surface/80 text-[10px] font-bold text-muted shadow-lg">
                                    {{ strtoupper(substr($org->name, 0, 2)) }}
                                </div>
                            @endif

                            {{-- Status badge --}}
                            <div class="absolute top-3 right-3 flex items-center gap-1.5">
                                @if($match->isFeatured())
                                    <span class="rounded-full bg-amber-500/90 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white backdrop-blur-sm">Featured</span>
                                @endif
                                @if($isLive)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-red-600/90 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white backdrop-blur-sm">
                                        <span class="relative flex h-2 w-2">
                                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                                            <span class="relative inline-flex h-2 w-2 rounded-full bg-white"></span>
                                        </span>
                                        Live
                                    </span>
                                @elseif($isRegOpen)
                                    <span class="rounded-full bg-green-600/90 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white backdrop-blur-sm">Registration Open</span>
                                @elseif($isPreReg)
                                    <span class="rounded-full bg-violet-600/90 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white backdrop-blur-sm">Pre-Registration</span>
                                @elseif($isCompleted)
                                    <span class="rounded-full bg-sky-600/90 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white backdrop-blur-sm">Results</span>
                                @endif
                            </div>

                            {{-- Title overlay --}}
                            <div class="absolute inset-x-0 bottom-0 p-4">
                                <h3 class="text-base font-bold leading-tight {{ $hasImage ? 'text-white' : 'text-primary' }}">{{ $match->name }}</h3>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-xs {{ $hasImage ? 'text-white/70' : 'text-muted' }}">
                                    @if($match->date)
                                        <span>{{ $match->date->format('d M Y') }}</span>
                                    @endif
                                    @if($org)
                                        <span>&bull; {{ $org->name }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Bottom section --}}
                        <div class="flex flex-1 flex-col p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                @if($match->location)
                                    <span class="inline-flex items-center gap-1 text-xs text-muted">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                                        {{ $match->location }}
                                    </span>
                                @endif
                                @if($match->province)
                                    <span class="rounded-full bg-surface-2 px-2 py-0.5 text-[10px] font-medium text-muted">{{ $match->province->label() }}</span>
                                @endif
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide
                                    {{ $match->scoring_type === 'prs' ? 'bg-amber-500/10 text-amber-400' : ($match->scoring_type === 'elr' ? 'bg-violet-500/10 text-violet-400' : 'bg-red-500/10 text-red-400') }}">
                                    {{ $match->royal_flush_enabled ? 'Royal Flush' : strtoupper($match->scoring_type ?? 'relay') }}
                                </span>
                            </div>

                            @if($canRegister)
                                @php $closes = $match->registration_closes_at ?? $match->defaultRegistrationCloseDate(); @endphp
                                @if($closes)
                                    <p class="mt-2 text-[10px] text-muted">Registration closes {{ $closes->diffForHumans() }}</p>
                                @endif
                            @endif

                            <div class="mt-auto flex items-center justify-between pt-3 text-xs text-muted">
                                <span>
                                    @if($canRegister)
                                        {{ $match->registrations_count }} {{ Str::plural('registration', $match->registrations_count) }}
                                    @else
                                        {{ $match->shooters_count }} {{ Str::plural('shooter', $match->shooters_count) }}
                                    @endif
                                </span>
                                @if(!$match->isFree())
                                    <span class="font-medium text-secondary">R{{ number_format($match->entry_fee, 0) }}</span>
                                @else
                                    <span class="font-medium text-green-400">Free</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $matches->links() }}
            </div>
        @else
            <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-surface/30 px-6 py-20 text-center">
                <svg class="mb-4 h-12 w-12 text-muted/30" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
                <h3 class="text-lg font-bold text-primary">No events found</h3>
                <p class="mt-1 max-w-sm text-sm text-muted">Try adjusting your filters or check back later for new events.</p>
                @if($search || $eventType || $province || $organizationId || $dateFilter)
                    <button type="button" wire:click="clearFilters" class="mt-4 rounded-lg bg-surface-2 px-4 py-2 text-sm font-medium text-secondary transition-colors hover:bg-surface-2/80">
                        Clear all filters
                    </button>
                @endif
            </div>
        @endif
    </div>
</div>
