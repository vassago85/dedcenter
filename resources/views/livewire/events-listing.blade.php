<div x-data="{ tab: $wire.entangle('tab').live }">

    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-primary sm:text-3xl">Find a Match</h1>
        <p class="mt-1 text-base text-muted">Browse competitions, register for events, and view results.</p>
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
                    $canRegister = ($isPreReg || $isRegOpen) && ! $match->isRegistrationPastDeadline();
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
                                    <x-icon name="map-pin" class="h-3.5 w-3.5" />
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
                <x-icon name="calendar" class="mb-4 h-12 w-12 text-muted/30" />
                <h3 class="text-lg font-bold text-primary">No upcoming events</h3>
                <p class="mt-1 max-w-sm text-base text-muted">There are no upcoming competitions right now. Check back soon!</p>
            @elseif($tab === 'live')
                <x-icon name="play" class="mb-4 h-12 w-12 text-muted/30" />
                <h3 class="text-lg font-bold text-primary">No live events</h3>
                <p class="mt-1 max-w-sm text-base text-muted">No competitions are running right now. Check the upcoming tab for future events.</p>
            @elseif($tab === 'my_events')
                <x-icon name="user" class="mb-4 h-12 w-12 text-muted/30" />
                <h3 class="text-lg font-bold text-primary">No registered events</h3>
                <p class="mt-1 max-w-sm text-base text-muted">You haven't registered for any events yet. Browse upcoming matches to get started!</p>
                <button type="button" @click="tab = 'upcoming'"
                        class="mt-4 rounded-lg bg-accent px-4 min-h-[44px] text-base font-medium text-white transition-colors hover:bg-accent/90 focus:outline-none focus:ring-2 focus:ring-accent">
                    Browse upcoming
                </button>
            @elseif($tab === 'past')
                <x-icon name="trophy" class="mb-4 h-12 w-12 text-muted/30" />
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
