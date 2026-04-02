<x-layouts.marketing
    title="Shooting Competitions, Results & Standings in South Africa | DeadCenter"
    description="Find shooting matches, view results, track standings, and connect with clubs across South Africa. Relay, PRS, and ELR scoring. Free to use."
    :schema="[
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => 'DeadCenter',
        'url' => shooter_url('/'),
        'description' => 'Find shooting competitions, results, and standings across South Africa.',
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'DeadCenter',
            'url' => shooter_url('/'),
        ],
    ]"
>

    {{-- ══════════════════════════════════════════ --}}
    {{-- HERO --}}
    {{-- ══════════════════════════════════════════ --}}
    <section class="relative isolate overflow-hidden">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute inset-0" style="background: radial-gradient(ellipse 70% 50% at 50% 0%, rgba(225, 6, 0, 0.06), transparent 70%);"></div>
            <div class="absolute top-0 left-1/2 -translate-x-1/2 h-[600px] w-[900px] rounded-full blur-[120px]" style="background: rgba(225, 6, 0, 0.03);"></div>
        </div>

        <div class="relative mx-auto max-w-6xl px-6 pt-24 pb-20 sm:pt-32 sm:pb-28 lg:pt-40 lg:pb-32">
            <div class="mx-auto max-w-3xl text-center">
                <div class="mb-8 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-[13px] font-medium backdrop-blur-sm" style="border: 1px solid var(--lp-border); background: var(--lp-surface); color: var(--lp-text-muted);">
                    <span class="h-1.5 w-1.5 rounded-full" style="background: var(--lp-red);"></span>
                    Free to Use &mdash; Always
                </div>

                <h1 class="text-[2.5rem] font-black leading-[1.08] tracking-tight sm:text-5xl lg:text-6xl" style="color: var(--lp-text);">
                    Find Shooting Matches, Results &amp; Standings in <span style="color: var(--lp-red);">South Africa</span>
                </h1>

                <p class="mx-auto mt-7 max-w-xl text-[1.05rem] leading-relaxed" style="color: var(--lp-text-soft);">
                    Discover upcoming competitions, register online, choose your own squad, and track your performance across seasons with DeadCenter &mdash; the home of precision rifle shooting in South&nbsp;Africa.
                </p>

                <div class="mt-10 flex flex-col items-center gap-3.5 sm:flex-row sm:justify-center">
                    <a href="{{ route('events') }}"
                       class="lp-btn-primary group relative inline-flex items-center justify-center rounded-xl px-8 py-3.5 text-[15px] font-semibold transition-all duration-200"
                       style="box-shadow: 0 1px 2px rgba(0,0,0,0.3), 0 8px 24px rgba(225, 6, 0, 0.25);">
                        View Upcoming Events
                    </a>
                    <a href="#standings"
                       class="lp-btn-secondary inline-flex items-center justify-center rounded-xl px-8 py-3.5 text-[15px] font-semibold backdrop-blur-sm transition-all duration-200">
                        View Standings
                    </a>
                </div>

                <div class="mt-14 flex flex-wrap items-center justify-center gap-6 sm:gap-10">
                    <div class="flex items-center gap-2.5 text-[13px]" style="color: var(--lp-text-muted);">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full" style="background: var(--lp-surface-2);"><span class="h-1.5 w-1.5 rounded-full" style="background: rgba(225, 6, 0, 0.7);"></span></span>
                        Live results &amp; scoreboards
                    </div>
                    <div class="flex items-center gap-2.5 text-[13px]" style="color: var(--lp-text-muted);">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full" style="background: var(--lp-surface-2);"><span class="h-1.5 w-1.5 rounded-full" style="background: rgba(225, 6, 0, 0.7);"></span></span>
                        Self-service squadding
                    </div>
                    <div class="flex items-center gap-2.5 text-[13px]" style="color: var(--lp-text-muted);">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full" style="background: var(--lp-surface-2);"><span class="h-1.5 w-1.5 rounded-full" style="background: rgba(225, 6, 0, 0.7);"></span></span>
                        Android app
                    </div>
                    <div class="flex items-center gap-2.5 text-[13px]" style="color: var(--lp-text-muted);">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full" style="background: var(--lp-surface-2);"><span class="h-1.5 w-1.5 rounded-full" style="background: rgba(225, 6, 0, 0.7);"></span></span>
                        Three scoring disciplines
                    </div>
                </div>
            </div>
        </div>

        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-px" style="background: linear-gradient(to right, transparent, var(--lp-border), transparent);"></div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- LIVE NOW --}}
    {{-- ══════════════════════════════════════════ --}}
    @if(isset($liveMatches) && $liveMatches->count())
    <section style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-6xl px-6 py-12">
            <div class="mb-8 flex items-center justify-center gap-3">
                <span class="relative flex h-3 w-3">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75" style="background: var(--lp-red);"></span>
                    <span class="relative inline-flex h-3 w-3 rounded-full" style="background: var(--lp-red);"></span>
                </span>
                <h2 class="text-2xl font-bold tracking-tight" style="color: var(--lp-text);">Live Now</h2>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($liveMatches as $match)
                    <a href="{{ route('scoreboard', $match) }}" class="group rounded-2xl p-6 transition-all duration-200 hover:scale-[1.02]" style="border: 1px solid rgba(225,6,0,0.3); background: var(--lp-surface);" onmouseover="this.style.borderColor='rgba(225,6,0,0.5)'" onmouseout="this.style.borderColor='rgba(225,6,0,0.3)'">
                        <div class="flex items-center justify-between mb-3">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider"
                                  style="background: {{ $match->scoring_type === 'prs' ? 'rgba(245,158,11,0.1)' : ($match->scoring_type === 'elr' ? 'rgba(139,92,246,0.1)' : 'rgba(225,6,0,0.08)') }}; color: {{ $match->scoring_type === 'prs' ? 'rgb(251,191,36)' : ($match->scoring_type === 'elr' ? 'rgb(167,139,250)' : 'var(--lp-red)') }};">
                                {{ $match->scoring_type === 'prs' ? 'PRS' : ($match->scoring_type === 'elr' ? 'ELR' : 'Relay') }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider" style="background: rgba(225,6,0,0.1); color: var(--lp-red);">
                                <span class="relative flex h-1.5 w-1.5">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75" style="background: var(--lp-red);"></span>
                                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full" style="background: var(--lp-red);"></span>
                                </span>
                                Live
                            </span>
                        </div>
                        <h3 class="text-lg font-semibold mb-1 group-hover:!text-white transition-colors" style="color: var(--lp-text);">{{ $match->name }}</h3>
                        @if($match->organization)
                            <p class="text-sm" style="color: var(--lp-text-muted);">{{ $match->organization->name }}</p>
                        @endif
                        <span class="mt-3 inline-flex items-center gap-1 text-xs font-semibold" style="color: var(--lp-red);">
                            Watch Live Scores
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ══════════════════════════════════════════ --}}
    {{-- FEATURED EVENTS / COMPETITIONS --}}
    {{-- ══════════════════════════════════════════ --}}
    <section id="events" style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-12 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Featured Competitions</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">Hand-picked events and competitions happening across South Africa.</p>
            </div>

            @if($featuredMatches->count())
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($featuredMatches as $match)
                        <div class="group rounded-2xl p-6 transition-all duration-200 hover:scale-[1.02]" style="border: 1px solid var(--lp-border); background: var(--lp-surface);" onmouseover="this.style.borderColor='rgba(225,6,0,0.3)'" onmouseout="this.style.borderColor='var(--lp-border)'">
                            <div class="flex items-center justify-between mb-3">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider"
                                      style="background: {{ $match->scoring_type === 'prs' ? 'rgba(245,158,11,0.1)' : ($match->scoring_type === 'elr' ? 'rgba(139,92,246,0.1)' : 'rgba(225,6,0,0.08)') }}; color: {{ $match->scoring_type === 'prs' ? 'rgb(251,191,36)' : ($match->scoring_type === 'elr' ? 'rgb(167,139,250)' : 'var(--lp-red)') }};">
                                    {{ $match->scoring_type === 'prs' ? 'PRS' : ($match->scoring_type === 'elr' ? 'ELR' : 'Relay') }}
                                </span>
                                @if($match->date)
                                    <span class="text-xs" style="color: var(--lp-text-muted);">{{ $match->date->format('d M Y') }}</span>
                                @endif
                            </div>
                            <h3 class="text-lg font-semibold mb-1 group-hover:!text-white transition-colors" style="color: var(--lp-text);">{{ $match->name }}</h3>
                            @if($match->organization)
                                <p class="text-sm" style="color: var(--lp-text-muted);">{{ $match->organization->name }}</p>
                            @endif
                            @if($match->location)
                                <p class="text-xs mt-2" style="color: var(--lp-text-muted); opacity: 0.7;">{{ $match->location }}</p>
                            @endif
                            <div class="mt-4">
                                @if($match->status === \App\Enums\MatchStatus::PreRegistration)
                                    <a href="{{ app_url('/matches/' . $match->id) }}" class="inline-flex items-center gap-1 text-xs font-semibold" style="color: var(--lp-red);">
                                        Show Interest <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                    </a>
                                @elseif($match->status === \App\Enums\MatchStatus::RegistrationOpen)
                                    <a href="{{ app_url('/matches/' . $match->id) }}" class="inline-flex items-center gap-1 text-xs font-semibold" style="color: var(--lp-red);">
                                        Register <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                    </a>
                                @elseif($match->status === \App\Enums\MatchStatus::Active)
                                    <a href="{{ route('scoreboard', $match) }}" class="inline-flex items-center gap-1 text-xs font-semibold" style="color: var(--lp-red);">
                                        Live Scores <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                    </a>
                                @elseif($match->status === \App\Enums\MatchStatus::Completed)
                                    <a href="{{ route('scoreboard', $match) }}" class="inline-flex items-center gap-1 text-xs font-semibold" style="color: var(--lp-red);">
                                        View Results <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                    </a>
                                @else
                                    <a href="{{ route('scoreboard', $match) }}" class="inline-flex items-center gap-1 text-xs font-semibold" style="color: var(--lp-red);">
                                        View Details <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl p-12 text-center" style="border: 1px dashed var(--lp-border); background: var(--lp-surface);">
                    <p class="text-sm" style="color: var(--lp-text-muted);">Featured competitions will appear here as they are announced.</p>
                    <a href="{{ app_url('/register') }}" class="mt-4 inline-block text-sm font-medium" style="color: var(--lp-red);">Register to get notified &rarr;</a>
                </div>
            @endif
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- POPULAR / TRENDING EVENTS --}}
    {{-- ══════════════════════════════════════════ --}}
    @if($popularMatches->count())
    <section style="border-top: 1px solid var(--lp-border);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-12 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Popular Competitions</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">The most popular upcoming events based on registrations.</p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($popularMatches as $match)
                    <div class="group rounded-2xl p-6 transition-all duration-200 hover:scale-[1.02]" style="border: 1px solid var(--lp-border); background: var(--lp-surface);" onmouseover="this.style.borderColor='rgba(225,6,0,0.3)'" onmouseout="this.style.borderColor='var(--lp-border)'">
                        <div class="flex items-center justify-between mb-3">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider"
                                  style="background: {{ $match->scoring_type === 'prs' ? 'rgba(245,158,11,0.1)' : ($match->scoring_type === 'elr' ? 'rgba(139,92,246,0.1)' : 'rgba(225,6,0,0.08)') }}; color: {{ $match->scoring_type === 'prs' ? 'rgb(251,191,36)' : ($match->scoring_type === 'elr' ? 'rgb(167,139,250)' : 'var(--lp-red)') }};">
                                {{ $match->scoring_type === 'prs' ? 'PRS' : ($match->scoring_type === 'elr' ? 'ELR' : 'Relay') }}
                            </span>
                            <span class="text-xs font-medium" style="color: var(--lp-text-muted);">{{ $match->registrations_count }} registered</span>
                        </div>
                        <h3 class="text-lg font-semibold mb-1 group-hover:!text-white transition-colors" style="color: var(--lp-text);">{{ $match->name }}</h3>
                        @if($match->organization)
                            <p class="text-sm" style="color: var(--lp-text-muted);">{{ $match->organization->name }}</p>
                        @endif
                        @if($match->date)
                            <p class="text-xs mt-2" style="color: var(--lp-text-muted); opacity: 0.7;">{{ $match->date->format('d M Y') }}</p>
                        @endif
                        <div class="mt-4">
                            @if($match->status === \App\Enums\MatchStatus::PreRegistration)
                                <a href="{{ app_url('/matches/' . $match->id) }}" class="inline-flex items-center gap-1 text-xs font-semibold" style="color: var(--lp-red);">
                                    Show Interest <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                </a>
                            @elseif($match->status === \App\Enums\MatchStatus::RegistrationOpen)
                                <a href="{{ app_url('/matches/' . $match->id) }}" class="inline-flex items-center gap-1 text-xs font-semibold" style="color: var(--lp-red);">
                                    Register <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                </a>
                            @elseif($match->status === \App\Enums\MatchStatus::Active)
                                <a href="{{ route('scoreboard', $match) }}" class="inline-flex items-center gap-1 text-xs font-semibold" style="color: var(--lp-red);">
                                    Live Scores <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                </a>
                            @else
                                <a href="{{ route('scoreboard', $match) }}" class="inline-flex items-center gap-1 text-xs font-semibold" style="color: var(--lp-red);">
                                    View Details <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ══════════════════════════════════════════ --}}
    {{-- FEATURED CLUBS --}}
    {{-- ══════════════════════════════════════════ --}}
    <section style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-12 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Featured Clubs &amp; Organizations</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">Clubs, leagues, and competition organizations using DeadCenter to run precision shooting events.</p>
            </div>

            @if($featuredOrgs->count())
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($featuredOrgs as $org)
                        <x-club-card :organization="$org" context="marketing" />
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl p-12 text-center" style="border: 1px dashed var(--lp-border); background: var(--lp-surface);">
                    <p class="text-sm" style="color: var(--lp-text-muted);">Featured clubs and organizations will be highlighted here.</p>
                </div>
            @endif
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- UPCOMING EVENTS --}}
    {{-- ══════════════════════════════════════════ --}}
    <section style="border-top: 1px solid var(--lp-border);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-12 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Upcoming Shooting Competitions</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">Browse upcoming precision rifle matches, PRS competitions, and ELR events across South Africa.</p>
            </div>

            @if($upcomingMatches->count())
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($upcomingMatches as $match)
                        <div class="group flex items-start gap-4 rounded-xl p-5 transition-all duration-200" style="border: 1px solid var(--lp-border); background: var(--lp-surface);" onmouseover="this.style.borderColor='rgba(225,6,0,0.3)'" onmouseout="this.style.borderColor='var(--lp-border)'">
                            <div class="flex-shrink-0 w-14 rounded-lg p-2 text-center" style="background: var(--lp-surface-2);">
                                <span class="block text-lg font-bold" style="color: var(--lp-text);">{{ $match->date?->format('d') }}</span>
                                <span class="block text-[10px] font-semibold uppercase" style="color: var(--lp-text-muted);">{{ $match->date?->format('M') }}</span>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold truncate group-hover:!text-white transition-colors" style="color: var(--lp-text);">{{ $match->name }}</h3>
                                @if($match->organization)
                                    <p class="text-xs mt-0.5" style="color: var(--lp-text-muted);">{{ $match->organization->name }}</p>
                                @endif
                                <div class="flex items-center gap-2 mt-1.5">
                                    <span class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                                          style="background: {{ $match->scoring_type === 'prs' ? 'rgba(245,158,11,0.1)' : ($match->scoring_type === 'elr' ? 'rgba(139,92,246,0.1)' : 'rgba(225,6,0,0.08)') }}; color: {{ $match->scoring_type === 'prs' ? 'rgb(251,191,36)' : ($match->scoring_type === 'elr' ? 'rgb(167,139,250)' : 'var(--lp-red)') }};">
                                        {{ $match->scoring_type === 'prs' ? 'PRS' : ($match->scoring_type === 'elr' ? 'ELR' : 'Relay') }}
                                    </span>
                                    @if($match->location)
                                        <span class="text-[10px]" style="color: var(--lp-text-muted);">{{ $match->location }}</span>
                                    @endif
                                </div>
                                <div class="mt-2">
                                    @if($match->status === \App\Enums\MatchStatus::PreRegistration)
                                        <a href="{{ app_url('/matches/' . $match->id) }}" class="inline-flex items-center gap-1 text-[11px] font-semibold" style="color: var(--lp-red);">
                                            Show Interest <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                        </a>
                                    @elseif($match->status === \App\Enums\MatchStatus::RegistrationOpen)
                                        <a href="{{ app_url('/matches/' . $match->id) }}" class="inline-flex items-center gap-1 text-[11px] font-semibold" style="color: var(--lp-red);">
                                            Register <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                        </a>
                                    @elseif($match->status === \App\Enums\MatchStatus::Active)
                                        <a href="{{ route('scoreboard', $match) }}" class="inline-flex items-center gap-1 text-[11px] font-semibold" style="color: var(--lp-red);">
                                            Live Scores <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                        </a>
                                    @else
                                        <a href="{{ route('scoreboard', $match) }}" class="inline-flex items-center gap-1 text-[11px] font-semibold" style="color: var(--lp-red);">
                                            View Details <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl p-12 text-center" style="border: 1px dashed var(--lp-border); background: var(--lp-surface);">
                    <p class="text-sm" style="color: var(--lp-text-muted);">No upcoming events at the moment. Check back soon.</p>
                </div>
            @endif

            <div class="mt-10 text-center">
                <a href="{{ route('events') }}"
                   class="lp-btn-secondary inline-flex items-center justify-center rounded-xl px-8 py-3 text-[15px] font-semibold backdrop-blur-sm transition-all duration-200">
                    Browse All Events &rarr;
                </a>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- RECENT RESULTS --}}
    {{-- ══════════════════════════════════════════ --}}
    <section id="results" style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-12 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Recent Match Results</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">See the latest completed matches and their final scoreboards.</p>
            </div>

            @if($recentResults->count())
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($recentResults as $match)
                        <a href="{{ route('scoreboard', $match) }}" class="group rounded-xl p-5 transition-all duration-200" style="border: 1px solid var(--lp-border); background: var(--lp-surface);" onmouseover="this.style.borderColor='rgba(225,6,0,0.3)'" onmouseout="this.style.borderColor='var(--lp-border)'">
                            <div class="flex items-center justify-between mb-2">
                                <span class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                                      style="background: {{ $match->scoring_type === 'prs' ? 'rgba(245,158,11,0.1)' : ($match->scoring_type === 'elr' ? 'rgba(139,92,246,0.1)' : 'rgba(225,6,0,0.08)') }}; color: {{ $match->scoring_type === 'prs' ? 'rgb(251,191,36)' : ($match->scoring_type === 'elr' ? 'rgb(167,139,250)' : 'var(--lp-red)') }};">
                                    {{ $match->scoring_type === 'prs' ? 'PRS' : ($match->scoring_type === 'elr' ? 'ELR' : 'Relay') }}
                                </span>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-medium" style="background: rgba(34,197,94,0.1); color: rgb(134,239,172);">Completed</span>
                            </div>
                            <h3 class="text-sm font-semibold group-hover:!text-white transition-colors" style="color: var(--lp-text);">{{ $match->name }}</h3>
                            @if($match->organization)
                                <p class="text-xs mt-0.5" style="color: var(--lp-text-muted);">{{ $match->organization->name }}</p>
                            @endif
                            @if($match->date)
                                <p class="text-xs mt-1.5" style="color: var(--lp-text-muted); opacity: 0.7;">{{ $match->date->format('d M Y') }}</p>
                            @endif
                            <span class="mt-3 inline-flex text-xs font-medium" style="color: var(--lp-red);">View Results &rarr;</span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl p-12 text-center" style="border: 1px dashed var(--lp-border); background: var(--lp-surface);">
                    <p class="text-sm" style="color: var(--lp-text-muted);">Completed match results will appear here.</p>
                </div>
            @endif
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- SEASON STANDINGS --}}
    {{-- ══════════════════════════════════════════ --}}
    <section id="standings" style="border-top: 1px solid var(--lp-border);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-12 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Season Standings &amp; Relative Scores</h2>
                <p class="mt-3 max-w-2xl mx-auto" style="color: var(--lp-text-muted);">Track shooter performance across an entire season. DeadCenter uses relative scoring to compare results fairly across different matches, conditions, and venues.</p>
            </div>

            <div class="grid gap-8 lg:grid-cols-3">
                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(225, 6, 0, 0.08);">
                        <svg class="h-6 w-6" style="color: var(--lp-red);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2" style="color: var(--lp-text);">Relative Scoring</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Your score is expressed as a percentage of the top shooter&rsquo;s score. This allows fair comparison across different matches, venues, and conditions.</p>
                </div>

                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(59, 130, 246, 0.08);">
                        <svg class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2" style="color: var(--lp-text);">Season Aggregation</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Season standings aggregate your average relative score, matches played, and best/worst performance. Best-of-N rules let organizers drop your weakest results.</p>
                </div>

                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(34, 197, 94, 0.08);">
                        <svg class="h-6 w-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0 1 16.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.003 6.003 0 0 1-3.77 1.522m0 0a6.003 6.003 0 0 1-3.77-1.522" /></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2" style="color: var(--lp-text);">Leaderboards</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">View standings per club, league, or competition. Filter by division and category. Every organization can publish a public leaderboard.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- SUPPORTED DISCIPLINES --}}
    {{-- ══════════════════════════════════════════ --}}
    <section id="disciplines" style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-12 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Three Scoring Disciplines</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">DeadCenter supports multiple scoring modes so every type of precision shooting competition is covered.</p>
            </div>

            <div class="grid gap-8 lg:grid-cols-3">
                <div class="rounded-2xl p-8 flex flex-col" style="border: 1px solid rgba(225, 6, 0, 0.15); background: var(--lp-surface);">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold self-start" style="background: rgba(225, 6, 0, 0.08); border: 1px solid rgba(225, 6, 0, 0.15); color: var(--lp-red);">
                        Relay Scoring
                    </div>
                    <h3 class="mb-2 text-xl font-bold" style="color: var(--lp-text);">Synchronized Relay Format</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        All relays complete each stage before advancing together. Distance-based target multipliers reward accuracy at longer ranges. Perfect for traditional club and field matches.
                    </p>
                </div>

                <div class="rounded-2xl p-8 flex flex-col" style="border: 1px solid rgba(245, 158, 11, 0.2); background: var(--lp-surface);">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold self-start" style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2); color: rgb(251, 191, 36);">
                        PRS Scoring
                    </div>
                    <h3 class="mb-2 text-xl font-bold" style="color: var(--lp-text);">Stage-Based Precision Rifle</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Hit/miss scoring with timed stages. Stage normalization enables relative performance comparison. Designed for competitive precision rifle series matches.
                    </p>
                </div>

                <div class="rounded-2xl p-8 flex flex-col" style="border: 1px solid rgba(139, 92, 246, 0.2); background: var(--lp-surface);">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold self-start" style="background: rgba(139, 92, 246, 0.08); border: 1px solid rgba(139, 92, 246, 0.2); color: rgb(167, 139, 250);">
                        ELR Scoring
                    </div>
                    <h3 class="mb-2 text-xl font-bold" style="color: var(--lp-text);">Extreme Long Range</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">
                        Shot-by-shot scoring with diminishing multipliers. Static and ladder stage types with must-hit-to-advance progression. Tracks furthest target hit and normalized percentage standings.
                    </p>
                </div>
            </div>

            <p class="mt-8 text-center text-xs" style="color: var(--lp-text-muted); opacity: 0.6;">More scoring engines will be added as the platform grows.</p>
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- HOW IT WORKS --}}
    {{-- ══════════════════════════════════════════ --}}
    <section style="border-top: 1px solid var(--lp-border);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-16 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">How It Works for Shooters</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">From finding a match to watching live scores in four simple steps.</p>
            </div>

            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                @foreach([
                    ['step' => '1', 'title' => 'Find a Match', 'desc' => 'Browse upcoming competitions on deadcenter.co.za or through your club\'s portal page.'],
                    ['step' => '2', 'title' => 'Register & Select Equipment', 'desc' => 'Register online, choose your division and category, and add your equipment details.'],
                    ['step' => '3', 'title' => 'Choose Your Squad', 'desc' => 'When squadding opens, pick your preferred squad from available slots with capacity limits.'],
                    ['step' => '4', 'title' => 'View Live Scores', 'desc' => 'On match day, follow live scores on the scoreboard. Review results and track your season standings.'],
                ] as $item)
                    <div class="text-center">
                        <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl text-xl font-black" style="background: rgba(225, 6, 0, 0.08); color: var(--lp-red);">
                            {{ $item['step'] }}
                        </div>
                        <h3 class="text-base font-semibold mb-2" style="color: var(--lp-text);">{{ $item['title'] }}</h3>
                        <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">{{ $item['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- CROSS-LINK TO MATCH DIRECTOR PAGE --}}
    {{-- ══════════════════════════════════════════ --}}
    <section style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-3xl px-6 py-16 text-center">
            <h3 class="text-xl font-bold" style="color: var(--lp-text);">Running Matches or Managing a Club?</h3>
            <p class="mt-2 text-sm" style="color: var(--lp-text-soft);">DeadCenter gives match directors powerful tools to set up matches, manage scoring, and publish results with zero friction.</p>
            <a href="{{ md_url('/') }}" class="mt-5 inline-flex items-center gap-2 text-sm font-semibold transition-colors" style="color: var(--lp-red);">
                Visit the Match Director Page
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
            </a>
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- FAQ --}}
    {{-- ══════════════════════════════════════════ --}}
    <section style="border-top: 1px solid var(--lp-border);">
        <div class="mx-auto max-w-3xl px-6 py-20 lg:py-28">
            <h2 class="text-2xl font-bold tracking-tight text-center mb-12" style="color: var(--lp-text);">Frequently Asked Questions</h2>

            <div class="space-y-4" x-data="{ open: null }">
                @foreach([
                    ['q' => 'Is DeadCenter free to use?', 'a' => 'Yes. DeadCenter is completely free for shooters, clubs, and match directors. We sustain the platform through the sponsor marketplace and optional promoted placements.'],
                    ['q' => 'What types of shooting competitions does DeadCenter support?', 'a' => 'DeadCenter supports three scoring disciplines: Relay Scoring for traditional gong/field matches, PRS Scoring for precision rifle series competitions, and ELR Scoring for extreme long range events. More engines will be added over time.'],
                    ['q' => 'How do I register for a match?', 'a' => 'Create a free account, browse upcoming matches on the platform or through a club portal, and register online. Select your division, category, and equipment details during registration. When squadding opens, choose your preferred squad.'],
                    ['q' => 'Can I view results without an account?', 'a' => 'Yes. Scoreboards and live results are publicly accessible. Just scan the QR code at the range or visit the scoreboard link shared by the match organizer.'],
                    ['q' => 'How are season standings calculated?', 'a' => 'DeadCenter uses relative scoring — your score is expressed as a percentage of the top shooter in each match. Season standings aggregate your average relative score across all matches, with optional best-of-N rules.'],
                    ['q' => 'Is DeadCenter only for South African competitions?', 'a' => 'DeadCenter was built for the South African shooting community, but the platform can be used for competitions anywhere.'],
                ] as $i => $faq)
                    <div class="rounded-xl overflow-hidden" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                        <button @click="open = open === {{ $i }} ? null : {{ $i }}" class="flex w-full items-center justify-between px-6 py-4 text-left text-sm font-semibold transition-colors hover:!text-white" style="color: var(--lp-text);">
                            {{ $faq['q'] }}
                            <svg class="h-4 w-4 flex-shrink-0 transition-transform duration-200" :class="open === {{ $i }} && 'rotate-180'" style="color: var(--lp-text-muted);" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </button>
                        <div x-show="open === {{ $i }}" x-cloak x-collapse>
                            <p class="px-6 pb-4 text-sm leading-relaxed" style="color: var(--lp-text-soft);">{{ $faq['a'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- FINAL CTA --}}
    {{-- ══════════════════════════════════════════ --}}
    <section style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-6xl px-6 py-20 text-center">
            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Ready to Get Involved?</h2>
            <p class="mx-auto mt-3 max-w-md" style="color: var(--lp-text-muted);">Browse upcoming competitions, explore standings, or create your free account today.</p>
            <div class="mt-8 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                <a href="{{ app_url('/register') }}" class="lp-btn-primary rounded-xl px-8 py-3.5 text-lg font-bold transition-all" style="box-shadow: 0 4px 20px rgba(225, 6, 0, 0.25);">
                    Register Free
                </a>
                <a href="#events" class="lp-btn-footer-outline rounded-xl px-8 py-3.5 text-lg font-semibold transition-colors">
                    View Events
                </a>
            </div>
        </div>
    </section>

</x-layouts.marketing>
