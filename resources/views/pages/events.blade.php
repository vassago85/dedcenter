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

new #[Layout('components.layouts.marketing')]
    #[Title('Events — DeadCenter')]
    class extends Component {
    use WithPagination;

    #[Url]
    public string $tab = 'upcoming';

    #[Url]
    public string $search = '';

    #[Url]
    public string $eventType = '';

    #[Url]
    public string $province = '';

    #[Url]
    public string $organizationId = '';

    public function updatedTab(): void { $this->resetPage(); }
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedEventType(): void { $this->resetPage(); }
    public function updatedProvince(): void { $this->resetPage(); }
    public function updatedOrganizationId(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->reset(['search', 'eventType', 'province', 'organizationId']);
        $this->resetPage();
    }

    public function with(): array
    {
        if ($this->tab === 'my_events' && ! auth()->check()) {
            $this->tab = 'upcoming';
        }

        $query = ShootingMatch::query()
            ->with('organization')
            ->withCount(['registrations', 'shooters']);

        match ($this->tab) {
            'upcoming' => $query->whereIn('status', [
                MatchStatus::PreRegistration,
                MatchStatus::RegistrationOpen,
                MatchStatus::RegistrationClosed,
                MatchStatus::SquaddingOpen,
            ])->orderByRaw("CASE WHEN featured_status = 'active' THEN 0 ELSE 1 END")->orderBy('date'),

            'live' => $query->where('status', MatchStatus::Active)
                ->orderByRaw("CASE WHEN featured_status = 'active' THEN 0 ELSE 1 END")->orderBy('date'),

            'my_events' => $query->whereHas('registrations', fn ($r) => $r->where('user_id', auth()->id()))
                ->orderByDesc('date'),

            'past' => $query->where('status', MatchStatus::Completed)
                ->orderByRaw("CASE WHEN featured_status = 'active' THEN 0 ELSE 1 END")->orderByDesc('date'),

            default => $query->whereIn('status', [
                MatchStatus::PreRegistration, MatchStatus::RegistrationOpen,
                MatchStatus::RegistrationClosed, MatchStatus::SquaddingOpen,
            ])->orderBy('date'),
        };

        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('location', 'like', $term));
        }

        if ($this->eventType !== '') {
            if ($this->eventType === 'royal_flush') {
                $query->where('royal_flush_enabled', true);
            } else {
                $query->where('scoring_type', $this->eventType);
            }
        }

        if ($this->province !== '') {
            $query->where('province', $this->province);
        }

        if ($this->organizationId !== '') {
            $query->where('organization_id', (int) $this->organizationId);
        }

        $matches = $query->paginate(12);
        $organizations = Organization::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        $baseCounts = ShootingMatch::where('status', '!=', MatchStatus::Draft);
        $upcomingCount = (clone $baseCounts)->whereIn('status', [
            MatchStatus::PreRegistration, MatchStatus::RegistrationOpen,
            MatchStatus::RegistrationClosed, MatchStatus::SquaddingOpen,
        ])->count();
        $liveCount = (clone $baseCounts)->where('status', MatchStatus::Active)->count();
        $completedCount = (clone $baseCounts)->where('status', MatchStatus::Completed)->count();
        $myEventsCount = auth()->check()
            ? ShootingMatch::whereHas('registrations', fn ($q) => $q->where('user_id', auth()->id()))->count()
            : 0;

        return [
            'matches'        => $matches,
            'organizations'  => $organizations,
            'provinces'      => Province::cases(),
            'upcomingCount'  => $upcomingCount,
            'liveCount'      => $liveCount,
            'completedCount' => $completedCount,
            'myEventsCount'  => $myEventsCount,
        ];
    }
}; ?>

<div class="mx-auto max-w-6xl px-6 py-8" x-data="{ tab: @entangle('tab') }">

    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl" style="color: var(--lp-text);">Find a Match</h1>
        <p class="mt-1 text-base" style="color: var(--lp-text-muted);">Browse competitions, register for events, and view results.</p>
    </div>

    {{-- Tabs --}}
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <button @click="tab = 'upcoming'" type="button"
                :class="tab === 'upcoming' ? 'bg-accent text-white' : 'bg-surface text-muted hover:bg-surface-2 hover:text-secondary'"
                class="inline-flex min-h-[44px] items-center rounded-full px-4 py-2 text-sm font-bold transition-colors focus:outline-none focus:ring-2 focus:ring-accent sm:text-base">
            Upcoming
            <span class="ml-1 opacity-60">{{ $upcomingCount }}</span>
        </button>

        <button @click="tab = 'live'" type="button"
                :class="tab === 'live' ? 'bg-red-600 text-white' : 'bg-surface text-muted hover:bg-surface-2 hover:text-secondary'"
                class="inline-flex min-h-[44px] items-center gap-2 rounded-full px-4 py-2 text-sm font-bold transition-colors focus:outline-none focus:ring-2 focus:ring-accent sm:text-base">
            @if($liveCount > 0)
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-red-400"></span>
                </span>
            @endif
            Live Now
            <span class="opacity-60">{{ $liveCount }}</span>
        </button>

        @auth
            <button @click="tab = 'my_events'" type="button"
                    :class="tab === 'my_events' ? 'bg-accent text-white' : 'bg-surface text-muted hover:bg-surface-2 hover:text-secondary'"
                    class="inline-flex min-h-[44px] items-center rounded-full px-4 py-2 text-sm font-bold transition-colors focus:outline-none focus:ring-2 focus:ring-accent sm:text-base">
                My Events
                <span class="ml-1 opacity-60">{{ $myEventsCount }}</span>
            </button>
        @endauth

        <button @click="tab = 'past'" type="button"
                :class="tab === 'past' ? 'bg-accent text-white' : 'bg-surface text-muted hover:bg-surface-2 hover:text-secondary'"
                class="inline-flex min-h-[44px] items-center rounded-full px-4 py-2 text-sm font-bold transition-colors focus:outline-none focus:ring-2 focus:ring-accent sm:text-base">
            Past Results
            <span class="ml-1 opacity-60">{{ $completedCount }}</span>
        </button>
    </div>

    {{-- Filters --}}
    <div class="mb-6 rounded-xl border border-border bg-surface/50 p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[12rem] flex-1">
                <label class="mb-1 block text-[11px] font-medium uppercase tracking-wider text-muted">Search</label>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Match name or location..."
                       class="w-full min-h-[44px] rounded-lg border border-border bg-surface-2 px-3 py-2 text-base text-primary placeholder-muted/50 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent" />
            </div>

            <div class="w-36">
                <label class="mb-1 block text-[11px] font-medium uppercase tracking-wider text-muted">Type</label>
                <select wire:model.live="eventType"
                        class="w-full min-h-[44px] rounded-lg border border-border bg-surface-2 px-3 py-2 text-base text-primary focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent">
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
                        class="w-full min-h-[44px] rounded-lg border border-border bg-surface-2 px-3 py-2 text-base text-primary focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent">
                    <option value="">All Provinces</option>
                    @foreach($provinces as $p)
                        <option value="{{ $p->value }}">{{ $p->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-44">
                <label class="mb-1 block text-[11px] font-medium uppercase tracking-wider text-muted">Organization</label>
                <select wire:model.live="organizationId"
                        class="w-full min-h-[44px] rounded-lg border border-border bg-surface-2 px-3 py-2 text-base text-primary focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent">
                    <option value="">All Organizations</option>
                    @foreach($organizations as $org)
                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                    @endforeach
                </select>
            </div>

            @if($search || $eventType || $province || $organizationId)
                <button type="button" wire:click="clearFilters"
                        class="min-h-[44px] rounded-lg border border-border bg-surface-2 px-4 py-2 text-sm font-medium text-muted transition-colors hover:text-primary focus:outline-none focus:ring-2 focus:ring-accent">
                    Clear
                </button>
            @endif
        </div>
    </div>

    {{-- Results --}}
    @if($matches->count())
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($matches as $match)
                @php
                    $statusEnum  = $match->status;
                    $statusValue = $statusEnum instanceof \BackedEnum ? $statusEnum->value : $statusEnum;
                    $isLive      = $statusValue === 'active';
                    $isCompleted = $statusValue === 'completed';
                    $isPreReg    = $statusValue === 'pre_registration';
                    $isRegOpen   = $statusValue === 'registration_open';
                    $canRegister = $isPreReg || $isRegOpen;
                    $cardImage   = $match->card_image_url;
                    $hasImage    = ! empty($cardImage);
                    $org         = $match->organization;

                    $scoringColors = [
                        'prs'      => 'from-amber-900/40 to-surface',
                        'elr'      => 'from-violet-900/40 to-surface',
                        'standard' => 'from-red-900/30 to-surface',
                    ];
                    $fallbackGradient = $scoringColors[$match->scoring_type ?? 'standard'] ?? $scoringColors['standard'];

                    if ($isCompleted) {
                        $href = route('scoreboard', $match);
                    } elseif (auth()->check()) {
                        $href = route('matches.show', $match);
                    } elseif ($org) {
                        $href = route('portal.matches.show', [$org, $match]);
                    } else {
                        $href = route('login');
                    }

                    if ($isCompleted) {
                        $ctaLabel = 'View Results';
                    } elseif ($isLive) {
                        $ctaLabel = 'View Scores';
                    } elseif ($isRegOpen) {
                        $ctaLabel = auth()->check() ? 'Register' : 'Sign in to Register';
                    } elseif ($isPreReg) {
                        $ctaLabel = auth()->check() ? 'Show Interest' : 'Sign in to Register';
                    } else {
                        $ctaLabel = 'View Details';
                    }
                @endphp

                <a href="{{ $href }}"
                   class="group relative flex flex-col overflow-hidden rounded-xl border border-border bg-surface shadow-sm transition-all duration-200 hover:shadow-md hover:border-border/80 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 focus:ring-offset-app">

                    {{-- Image / gradient header --}}
                    <div class="relative aspect-video overflow-hidden">
                        @if($hasImage)
                            <img src="{{ $cardImage }}" alt="{{ $match->name }}" class="absolute inset-0 h-full w-full object-cover" loading="lazy" />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-black/10"></div>
                        @else
                            <div class="absolute inset-0 bg-gradient-to-br {{ $fallbackGradient }}"></div>
                        @endif

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
                            @elseif($statusValue === 'registration_closed' || $statusValue === 'squadding_open')
                                <span class="rounded-full bg-amber-600/90 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white backdrop-blur-sm">{{ $statusEnum->label() }}</span>
                            @elseif($isCompleted)
                                <span class="rounded-full bg-sky-600/90 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white backdrop-blur-sm">Results</span>
                            @endif
                        </div>

                        <div class="absolute inset-x-0 bottom-0 p-4">
                            <h3 class="text-base font-bold leading-tight {{ $hasImage ? 'text-white' : 'text-primary' }}">{{ $match->name }}</h3>
                            <div class="mt-1 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-sm {{ $hasImage ? 'text-white/80' : 'text-muted' }}">
                                @if($match->date)
                                    <span>{{ $match->date->format('d M Y') }}</span>
                                @endif
                                @if($org)
                                    <span>&bull; {{ $org->name }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Card body --}}
                    <div class="flex flex-1 flex-col p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            @if($match->location)
                                <span class="inline-flex items-center gap-2 text-sm text-muted">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                                    {{ $match->location }}
                                </span>
                            @endif
                            @if($match->province)
                                <span class="rounded-full bg-surface-2 px-2 py-0.5 text-[10px] font-medium text-muted">{{ $match->province->label() }}</span>
                            @endif
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide
                                {{ $match->scoring_type === 'prs' ? 'bg-amber-500/10 text-amber-400' : ($match->scoring_type === 'elr' ? 'bg-violet-500/10 text-violet-400' : 'bg-red-500/10 text-red-400') }}">
                                {{ $match->royal_flush_enabled ? 'Royal Flush' : (($match->scoring_type ?? 'standard') === 'standard' ? 'RELAY' : strtoupper($match->scoring_type)) }}
                            </span>
                        </div>

                        @if($canRegister)
                            @php $closes = $match->registration_closes_at ?? $match->defaultRegistrationCloseDate(); @endphp
                            @if($closes)
                                <p class="mt-2 text-[10px] text-muted">Registration closes {{ $closes->diffForHumans() }}</p>
                            @endif
                        @endif

                        <div class="mt-auto flex items-center justify-between gap-3 pt-3">
                            <div class="flex flex-col gap-2 text-base text-muted">
                                <span>
                                    @if($canRegister)
                                        {{ $match->registrations_count }} {{ Str::plural('registration', $match->registrations_count) }}
                                    @else
                                        {{ $match->shooters_count }} {{ Str::plural('shooter', $match->shooters_count) }}
                                    @endif
                                </span>
                                @if(! $match->isFree())
                                    <span class="font-medium text-secondary">R{{ number_format($match->entry_fee, 0) }}</span>
                                @else
                                    <span class="font-medium text-green-400">Free</span>
                                @endif
                            </div>

                            <span class="inline-flex items-center rounded-lg px-3 min-h-[44px] text-sm font-semibold transition-colors
                                @if($isLive) bg-red-600/10 text-red-400 group-hover:bg-red-600/20
                                @elseif($canRegister) bg-accent/10 text-accent group-hover:bg-accent/20
                                @elseif($isCompleted) bg-sky-600/10 text-sky-400 group-hover:bg-sky-600/20
                                @else bg-surface-2 text-secondary group-hover:bg-surface-2/80
                                @endif">
                                {{ $ctaLabel }}
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $matches->links() }}
        </div>
    @else
        {{-- Empty states --}}
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-surface/30 px-6 py-20 text-center">
            @if($tab === 'upcoming')
                <svg class="mb-4 h-12 w-12 text-muted/30" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
                <h3 class="text-lg font-bold text-primary">No upcoming events</h3>
                <p class="mt-1 max-w-sm text-base text-muted">There are no upcoming competitions right now. Check back soon!</p>
            @elseif($tab === 'live')
                <svg class="mb-4 h-12 w-12 text-muted/30" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                </svg>
                <h3 class="text-lg font-bold text-primary">No live events</h3>
                <p class="mt-1 max-w-sm text-base text-muted">No competitions are running right now. Check the upcoming tab for future events.</p>
            @elseif($tab === 'my_events')
                <svg class="mb-4 h-12 w-12 text-muted/30" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
                <h3 class="text-lg font-bold text-primary">No registered events</h3>
                <p class="mt-1 max-w-sm text-base text-muted">You haven't registered for any events yet. Browse upcoming matches to get started!</p>
                <button type="button" @click="tab = 'upcoming'"
                        class="mt-4 rounded-lg bg-accent px-4 min-h-[44px] text-base font-medium text-white transition-colors hover:bg-accent/90 focus:outline-none focus:ring-2 focus:ring-accent">
                    Browse upcoming
                </button>
            @elseif($tab === 'past')
                <svg class="mb-4 h-12 w-12 text-muted/30" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0 1 16.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.023 6.023 0 0 1-7.54 0" />
                </svg>
                <h3 class="text-lg font-bold text-primary">No past results</h3>
                <p class="mt-1 max-w-sm text-base text-muted">No completed competitions found. Results will appear here once events finish.</p>
            @endif

            @if($search || $eventType || $province || $organizationId)
                <button type="button" wire:click="clearFilters"
                        class="mt-4 rounded-lg bg-surface-2 px-4 min-h-[44px] text-base font-medium text-secondary transition-colors hover:bg-surface-2/80 focus:outline-none focus:ring-2 focus:ring-accent">
                    Clear all filters
                </button>
            @endif
        </div>
    @endif
</div>
