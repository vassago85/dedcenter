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
                        Watch scores update from the range
                    </div>
                    <div class="flex items-center gap-2.5 text-[13px]" style="color: var(--lp-text-muted);">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full" style="background: var(--lp-surface-2);"><span class="h-1.5 w-1.5 rounded-full" style="background: rgba(225, 6, 0, 0.7);"></span></span>
                        Choose your squad before match day
                    </div>
                    <div class="flex items-center gap-2.5 text-[13px]" style="color: var(--lp-text-muted);">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full" style="background: var(--lp-surface-2);"><span class="h-1.5 w-1.5 rounded-full" style="background: rgba(225, 6, 0, 0.7);"></span></span>
                        Register and compete from your phone
                    </div>
                    <div class="flex items-center gap-2.5 text-[13px]" style="color: var(--lp-text-muted);">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full" style="background: var(--lp-surface-2);"><span class="h-1.5 w-1.5 rounded-full" style="background: rgba(225, 6, 0, 0.7);"></span></span>
                        Track your season progress in one place
                    </div>
                    <div class="flex items-center gap-2.5 text-[13px]" style="color: var(--lp-text-muted);">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full" style="background: var(--lp-surface-2);"><span class="h-1.5 w-1.5 rounded-full" style="background: rgba(225, 6, 0, 0.7);"></span></span>
                        Follow your squad and your club
                    </div>
                </div>
            </div>
        </div>

        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-px" style="background: linear-gradient(to right, transparent, var(--lp-border), transparent);"></div>
    </section>

    {{-- ══════════════════════════════════════════ --}}
    {{-- PLATFORM ACTIVITY --}}
    {{-- ══════════════════════════════════════════ --}}
    <section style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-6xl px-6 py-10">
            <div class="mb-6 flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight" style="color: var(--lp-text);">Platform Activity</h2>
                    <p class="mt-1 text-sm" style="color: var(--lp-text-muted);">Live snapshot of match activity across South Africa.</p>
                </div>
                @if(!empty($activityStats['scoresUpdatedAt']))
                    <p class="text-xs" style="color: var(--lp-text-muted);">Last score update: {{ \Illuminate\Support\Carbon::parse($activityStats['scoresUpdatedAt'])->diffForHumans() }}</p>
                @endif
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border p-5" style="border-color: var(--lp-border); background: var(--lp-surface);">
                    <p class="text-xs font-semibold uppercase tracking-wider" style="color: var(--lp-text-muted);">Registrations Open</p>
                    <p class="mt-2 text-3xl font-bold" style="color: var(--lp-text);">{{ $activityStats['registrationsOpen'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border p-5" style="border-color: var(--lp-border); background: var(--lp-surface);">
                    <p class="text-xs font-semibold uppercase tracking-wider" style="color: var(--lp-text-muted);">Matches Completed {{ now()->year }}</p>
                    <p class="mt-2 text-3xl font-bold" style="color: var(--lp-text);">{{ $activityStats['matchesCompletedSeason'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border p-5" style="border-color: var(--lp-border); background: var(--lp-surface);">
                    <p class="text-xs font-semibold uppercase tracking-wider" style="color: var(--lp-text-muted);">Shooters Active (30 days)</p>
                    <p class="mt-2 text-3xl font-bold" style="color: var(--lp-text);">{{ $activityStats['activeShootersMonth'] ?? 0 }}</p>
                </div>
            </div>
        </div>
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
                    @php $cardImg = $match->card_image_url; $hasImg = !empty($cardImg); @endphp
                    <a href="{{ route('scoreboard', $match) }}" class="group relative flex flex-col overflow-hidden rounded-2xl transition-all duration-200 hover:scale-[1.02] hover:shadow-xl" style="border: 1px solid rgba(225,6,0,0.3); background: var(--lp-surface);">
                        <div class="relative aspect-[16/9] overflow-hidden">
                            @if($hasImg)
                                <img src="{{ $cardImg }}" alt="{{ $match->name }}" class="absolute inset-0 h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy" />
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-black/10"></div>
                            @else
                                <div class="absolute inset-0" style="background: linear-gradient(135deg, rgba(225,6,0,0.15), var(--lp-surface));"></div>
                            @endif
                            @if($match->organization?->logoUrl())
                                <div class="absolute top-3 left-3">
                                    <img src="{{ $match->organization->logoUrl() }}" alt="" class="h-8 w-8 rounded-lg border border-white/20 object-cover shadow-lg" loading="lazy" />
                                </div>
                            @endif
                            <div class="absolute top-3 right-3 flex items-center gap-1.5">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider backdrop-blur-sm" style="background: rgba(225,6,0,0.85); color: #fff;">
                                    <span class="relative flex h-1.5 w-1.5">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-white"></span>
                                    </span>
                                    Live
                                </span>
                            </div>
                            <div class="absolute inset-x-0 bottom-0 p-4">
                                <h3 class="text-base font-bold leading-tight {{ $hasImg ? 'text-white' : '' }}" @if(!$hasImg) style="color: var(--lp-text);" @endif>{{ $match->name }}</h3>
                                <div class="mt-1 flex items-center gap-2 text-sm {{ $hasImg ? 'text-white/70' : '' }}" @if(!$hasImg) style="color: var(--lp-text-muted);" @endif>
                                    @if($match->organization)<span>{{ $match->organization->name }}</span>@endif
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-4">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider"
                                  style="background: {{ $match->scoring_type === 'prs' ? 'rgba(245,158,11,0.1)' : ($match->scoring_type === 'elr' ? 'rgba(139,92,246,0.1)' : 'rgba(225,6,0,0.08)') }}; color: {{ $match->scoring_type === 'prs' ? 'rgb(251,191,36)' : ($match->scoring_type === 'elr' ? 'rgb(167,139,250)' : 'var(--lp-red)') }};">
                                {{ $match->scoring_type === 'prs' ? 'PRS' : ($match->scoring_type === 'elr' ? 'ELR' : 'Relay') }}
                            </span>
                            <span class="inline-flex items-center gap-1 text-xs font-semibold" style="color: var(--lp-red);">
                                Watch Live Scores
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                            </span>
                        </div>
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
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Featured Events</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">Featured events and competitions happening across South Africa.</p>
            </div>

            @if($featuredMatches->count())
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($featuredMatches as $match)
                        @php
                            $cardImg = $match->card_image_url;
                            $hasImg = !empty($cardImg);
                            $scoringBg = $match->scoring_type === 'prs' ? 'rgba(245,158,11,0.1)' : ($match->scoring_type === 'elr' ? 'rgba(139,92,246,0.1)' : 'rgba(225,6,0,0.08)');
                            $scoringColor = $match->scoring_type === 'prs' ? 'rgb(251,191,36)' : ($match->scoring_type === 'elr' ? 'rgb(167,139,250)' : 'var(--lp-red)');
                            $scoringLabel = $match->scoring_type === 'prs' ? 'PRS' : ($match->scoring_type === 'elr' ? 'ELR' : 'Relay');
                            $gradientFallback = $match->scoring_type === 'prs' ? 'rgba(180,83,9,0.2)' : ($match->scoring_type === 'elr' ? 'rgba(91,33,182,0.2)' : 'rgba(225,6,0,0.12)');

                            if ($match->status === \App\Enums\MatchStatus::Active) {
                                $ctaHref = route('scoreboard', $match);
                                $ctaLabel = 'Live Scores';
                            } elseif ($match->status === \App\Enums\MatchStatus::Completed) {
                                $ctaHref = route('scoreboard', $match);
                                $ctaLabel = 'View Results';
                            } elseif (in_array($match->status, [\App\Enums\MatchStatus::PreRegistration, \App\Enums\MatchStatus::RegistrationOpen])) {
                                $ctaHref = app_url('/matches/' . $match->id);
                                $ctaLabel = $match->status === \App\Enums\MatchStatus::PreRegistration ? 'Show Interest' : 'Register';
                            } else {
                                $ctaHref = route('scoreboard', $match);
                                $ctaLabel = 'View Details';
                            }
                        @endphp
                        <a href="{{ $ctaHref }}" class="group relative flex flex-col overflow-hidden rounded-2xl transition-all duration-200 hover:scale-[1.02] hover:shadow-xl" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                            <div class="relative aspect-[16/9] overflow-hidden">
                                @if($hasImg)
                                    <img src="{{ $cardImg }}" alt="{{ $match->name }}" class="absolute inset-0 h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy" />
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-black/10"></div>
                                @else
                                    <div class="absolute inset-0" style="background: linear-gradient(135deg, {{ $gradientFallback }}, var(--lp-surface));"></div>
                                @endif
                                @if($match->organization?->logoUrl())
                                    <div class="absolute top-3 left-3">
                                        <img src="{{ $match->organization->logoUrl() }}" alt="" class="h-8 w-8 rounded-lg border border-white/20 object-cover shadow-lg" loading="lazy" />
                                    </div>
                                @elseif($match->organization)
                                    <div class="absolute top-3 left-3 flex h-8 w-8 items-center justify-center rounded-lg border border-white/20 shadow-lg text-[10px] font-bold" style="background: var(--lp-surface); color: var(--lp-text-muted);">
                                        {{ strtoupper(substr($match->organization->name, 0, 2)) }}
                                    </div>
                                @endif
                                <div class="absolute top-3 right-3 flex items-center gap-1.5">
                                    @if($match->isFeatured())
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider backdrop-blur-sm" style="background: rgba(245,158,11,0.85); color: #fff;">
                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 0 0 .95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 0 0-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 0 0-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 0 0-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 0 0 .951-.69l1.07-3.292Z"/></svg>
                                            Featured
                                        </span>
                                    @endif
                                    @if($match->status === \App\Enums\MatchStatus::RegistrationOpen)
                                        <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider backdrop-blur-sm" style="background: rgba(22,163,74,0.85); color: #fff;">Registration Open</span>
                                    @elseif($match->status === \App\Enums\MatchStatus::PreRegistration)
                                        <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider backdrop-blur-sm" style="background: rgba(124,58,237,0.85); color: #fff;">Pre-Registration</span>
                                    @endif
                                </div>
                                <div class="absolute inset-x-0 bottom-0 p-4">
                                    <h3 class="text-base font-bold leading-tight {{ $hasImg ? 'text-white' : '' }}" @if(!$hasImg) style="color: var(--lp-text);" @endif>{{ $match->name }}</h3>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-sm {{ $hasImg ? 'text-white/70' : '' }}" @if(!$hasImg) style="color: var(--lp-text-muted);" @endif>
                                        @if($match->date)<span>{{ $match->date->format('d M Y') }}</span>@endif
                                        @if($match->organization)<span>&bull; {{ $match->organization->name }}</span>@endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-1 items-center justify-between p-4">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider" style="background: {{ $scoringBg }}; color: {{ $scoringColor }};">{{ $scoringLabel }}</span>
                                    @if($match->location)
                                        <span class="text-xs truncate max-w-[140px]" style="color: var(--lp-text-muted);">{{ $match->location }}</span>
                                    @endif
                                </div>
                                <span class="inline-flex items-center gap-1 text-xs font-semibold whitespace-nowrap" style="color: var(--lp-red);">
                                    {{ $ctaLabel }}
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl p-12 text-center" style="border: 1px dashed var(--lp-border); background: var(--lp-surface);">
                    <p class="text-sm" style="color: var(--lp-text-muted);">Featured events will appear here as they are announced.</p>
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
                    @php
                        $cardImg = $match->card_image_url;
                        $hasImg = !empty($cardImg);
                        $scoringBg = $match->scoring_type === 'prs' ? 'rgba(245,158,11,0.1)' : ($match->scoring_type === 'elr' ? 'rgba(139,92,246,0.1)' : 'rgba(225,6,0,0.08)');
                        $scoringColor = $match->scoring_type === 'prs' ? 'rgb(251,191,36)' : ($match->scoring_type === 'elr' ? 'rgb(167,139,250)' : 'var(--lp-red)');
                        $scoringLabel = $match->scoring_type === 'prs' ? 'PRS' : ($match->scoring_type === 'elr' ? 'ELR' : 'Relay');
                        $gradientFallback = $match->scoring_type === 'prs' ? 'rgba(180,83,9,0.2)' : ($match->scoring_type === 'elr' ? 'rgba(91,33,182,0.2)' : 'rgba(225,6,0,0.12)');
                        $ctaHref = in_array($match->status, [\App\Enums\MatchStatus::PreRegistration, \App\Enums\MatchStatus::RegistrationOpen])
                            ? app_url('/matches/' . $match->id)
                            : route('scoreboard', $match);
                        $ctaLabel = $match->status === \App\Enums\MatchStatus::PreRegistration ? 'Show Interest' : ($match->status === \App\Enums\MatchStatus::RegistrationOpen ? 'Register' : ($match->status === \App\Enums\MatchStatus::Active ? 'Live Scores' : 'View Details'));
                    @endphp
                    <a href="{{ $ctaHref }}" class="group relative flex flex-col overflow-hidden rounded-2xl transition-all duration-200 hover:scale-[1.02] hover:shadow-xl" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                        <div class="relative aspect-[16/9] overflow-hidden">
                            @if($hasImg)
                                <img src="{{ $cardImg }}" alt="{{ $match->name }}" class="absolute inset-0 h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy" />
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-black/10"></div>
                            @else
                                <div class="absolute inset-0" style="background: linear-gradient(135deg, {{ $gradientFallback }}, var(--lp-surface));"></div>
                            @endif
                            @if($match->organization?->logoUrl())
                                <div class="absolute top-3 left-3">
                                    <img src="{{ $match->organization->logoUrl() }}" alt="" class="h-8 w-8 rounded-lg border border-white/20 object-cover shadow-lg" loading="lazy" />
                                </div>
                            @elseif($match->organization)
                                <div class="absolute top-3 left-3 flex h-8 w-8 items-center justify-center rounded-lg border border-white/20 shadow-lg text-[10px] font-bold" style="background: var(--lp-surface); color: var(--lp-text-muted);">
                                    {{ strtoupper(substr($match->organization->name, 0, 2)) }}
                                </div>
                            @endif
                            <div class="absolute top-3 right-3">
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider backdrop-blur-sm" style="background: rgba(34,197,94,0.85); color: #fff;">{{ $match->registrations_count }} registered</span>
                            </div>
                            <div class="absolute inset-x-0 bottom-0 p-4">
                                <h3 class="text-base font-bold leading-tight {{ $hasImg ? 'text-white' : '' }}" @if(!$hasImg) style="color: var(--lp-text);" @endif>{{ $match->name }}</h3>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-sm {{ $hasImg ? 'text-white/70' : '' }}" @if(!$hasImg) style="color: var(--lp-text-muted);" @endif>
                                    @if($match->date)<span>{{ $match->date->format('d M Y') }}</span>@endif
                                    @if($match->organization)<span>&bull; {{ $match->organization->name }}</span>@endif
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-1 items-center justify-between p-4">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider" style="background: {{ $scoringBg }}; color: {{ $scoringColor }};">{{ $scoringLabel }}</span>
                                @if($match->location)
                                    <span class="text-xs truncate max-w-[140px]" style="color: var(--lp-text-muted);">{{ $match->location }}</span>
                                @endif
                            </div>
                            <span class="inline-flex items-center gap-1 text-xs font-semibold whitespace-nowrap" style="color: var(--lp-red);">
                                {{ $ctaLabel }}
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ══════════════════════════════════════════ --}}
    {{-- CLUBS & ORGANIZATIONS --}}
    {{-- ══════════════════════════════════════════ --}}
    <section id="organizations" style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-12 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Clubs &amp; Organizations</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">Leagues, clubs, and competition organizations running precision shooting events on DeadCenter.</p>
            </div>

            @if($allOrganizations->count())
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($allOrganizations as $org)
                        @php
                            $isFeatured = $featuredOrgs->contains('id', $org->id);
                            $orgLogoUrl = $org->logoUrl();
                            $hasLogo = (bool) $orgLogoUrl;
                            $href = $org->publicMarketingHref();
                        @endphp
                        <a href="{{ $href }}" class="group rounded-2xl p-6 transition-all duration-200 hover:scale-[1.02]" style="border: 1px solid {{ $isFeatured ? 'rgba(225,6,0,0.3)' : 'var(--lp-border)' }}; background: var(--lp-surface);" onmouseover="this.style.borderColor='rgba(225,6,0,0.3)'" onmouseout="this.style.borderColor='{{ $isFeatured ? 'rgba(225,6,0,0.3)' : 'var(--lp-border)' }}'">
                            <div class="flex items-center gap-4 mb-3">
                                @if($hasLogo)
                                    <img src="{{ $orgLogoUrl }}" alt="{{ $org->name }}" class="h-10 w-10 rounded-lg object-contain" style="background: var(--lp-surface-2);">
                                @else
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-bold" style="background: rgba(225,6,0,0.08); color: var(--lp-red);">
                                        {{ strtoupper(substr($org->name, 0, 2)) }}
                                    </div>
                                @endif
                                <div>
                                    <h3 class="text-base font-semibold group-hover:!text-white transition-colors" style="color: var(--lp-text);">{{ $org->name }}</h3>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--lp-text-muted);">{{ $org->type }}</span>
                                        @if($isFeatured)
                                            <span class="text-[10px] font-bold uppercase tracking-wider" style="color: var(--lp-red);">Featured</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @if($org->description)
                                <p class="text-sm leading-relaxed line-clamp-2" style="color: var(--lp-text-soft);">{{ $org->description }}</p>
                            @endif
                            <div class="mt-3 flex items-center gap-3 text-xs" style="color: var(--lp-text-muted);">
                                <span>{{ $org->matches_count }} {{ Str::plural('match', $org->matches_count) }}</span>
                                @if($org->province)
                                    <span>&middot;</span>
                                    <span>{{ $org->province }}</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl p-12 text-center" style="border: 1px dashed var(--lp-border); background: var(--lp-surface);">
                    <p class="text-sm" style="color: var(--lp-text-muted);">Organizations will appear here as they join DeadCenter.</p>
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
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($upcomingMatches as $match)
                        @php
                            $cardImg = $match->card_image_url;
                            $hasImg = !empty($cardImg);
                            $scoringBg = $match->scoring_type === 'prs' ? 'rgba(245,158,11,0.1)' : ($match->scoring_type === 'elr' ? 'rgba(139,92,246,0.1)' : 'rgba(225,6,0,0.08)');
                            $scoringColor = $match->scoring_type === 'prs' ? 'rgb(251,191,36)' : ($match->scoring_type === 'elr' ? 'rgb(167,139,250)' : 'var(--lp-red)');
                            $scoringLabel = $match->scoring_type === 'prs' ? 'PRS' : ($match->scoring_type === 'elr' ? 'ELR' : 'Relay');
                            $gradientFallback = $match->scoring_type === 'prs' ? 'rgba(180,83,9,0.2)' : ($match->scoring_type === 'elr' ? 'rgba(91,33,182,0.2)' : 'rgba(225,6,0,0.12)');
                            $ctaHref = in_array($match->status, [\App\Enums\MatchStatus::PreRegistration, \App\Enums\MatchStatus::RegistrationOpen])
                                ? app_url('/matches/' . $match->id)
                                : route('scoreboard', $match);
                            $ctaLabel = $match->status === \App\Enums\MatchStatus::PreRegistration ? 'Show Interest' : ($match->status === \App\Enums\MatchStatus::RegistrationOpen ? 'Register' : ($match->status === \App\Enums\MatchStatus::Active ? 'Live Scores' : 'View Details'));
                        @endphp
                        <a href="{{ $ctaHref }}" class="group relative flex flex-col overflow-hidden rounded-2xl transition-all duration-200 hover:scale-[1.02] hover:shadow-xl" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                            <div class="relative aspect-[16/9] overflow-hidden">
                                @if($hasImg)
                                    <img src="{{ $cardImg }}" alt="{{ $match->name }}" class="absolute inset-0 h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy" />
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-black/10"></div>
                                @else
                                    <div class="absolute inset-0" style="background: linear-gradient(135deg, {{ $gradientFallback }}, var(--lp-surface));"></div>
                                @endif
                                @if($match->organization?->logoUrl())
                                    <div class="absolute top-3 left-3">
                                        <img src="{{ $match->organization->logoUrl() }}" alt="" class="h-8 w-8 rounded-lg border border-white/20 object-cover shadow-lg" loading="lazy" />
                                    </div>
                                @elseif($match->organization)
                                    <div class="absolute top-3 left-3 flex h-8 w-8 items-center justify-center rounded-lg border border-white/20 shadow-lg text-[10px] font-bold" style="background: var(--lp-surface); color: var(--lp-text-muted);">
                                        {{ strtoupper(substr($match->organization->name, 0, 2)) }}
                                    </div>
                                @endif
                                <div class="absolute top-3 right-3 flex items-center gap-1.5">
                                    @if($match->isFeatured())
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider backdrop-blur-sm" style="background: rgba(245,158,11,0.85); color: #fff;">
                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 0 0 .95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 0 0-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 0 0-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 0 0-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 0 0 .951-.69l1.07-3.292Z"/></svg>
                                            Featured
                                        </span>
                                    @endif
                                    @if($match->status === \App\Enums\MatchStatus::RegistrationOpen)
                                        <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider backdrop-blur-sm" style="background: rgba(22,163,74,0.85); color: #fff;">Open</span>
                                    @elseif($match->status === \App\Enums\MatchStatus::PreRegistration)
                                        <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider backdrop-blur-sm" style="background: rgba(124,58,237,0.85); color: #fff;">Pre-Reg</span>
                                    @elseif($match->status === \App\Enums\MatchStatus::Active)
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider backdrop-blur-sm" style="background: rgba(225,6,0,0.85); color: #fff;">
                                            <span class="relative flex h-1.5 w-1.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span><span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-white"></span></span>
                                            Live
                                        </span>
                                    @endif
                                </div>
                                <div class="absolute inset-x-0 bottom-0 p-4">
                                    <h3 class="text-base font-bold leading-tight {{ $hasImg ? 'text-white' : '' }}" @if(!$hasImg) style="color: var(--lp-text);" @endif>{{ $match->name }}</h3>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-sm {{ $hasImg ? 'text-white/70' : '' }}" @if(!$hasImg) style="color: var(--lp-text-muted);" @endif>
                                        @if($match->date)<span>{{ $match->date->format('d M Y') }}</span>@endif
                                        @if($match->organization)<span>&bull; {{ $match->organization->name }}</span>@endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-1 items-center justify-between p-4">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider" style="background: {{ $scoringBg }}; color: {{ $scoringColor }};">{{ $scoringLabel }}</span>
                                    @if($match->location)
                                        <span class="text-xs truncate max-w-[140px]" style="color: var(--lp-text-muted);">{{ $match->location }}</span>
                                    @endif
                                </div>
                                <span class="inline-flex items-center gap-1 text-xs font-semibold whitespace-nowrap" style="color: var(--lp-red);">
                                    {{ $ctaLabel }}
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                </span>
                            </div>
                        </a>
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
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($recentResults as $match)
                        @php
                            $cardImg = $match->card_image_url;
                            $hasImg = !empty($cardImg);
                            $scoringBg = $match->scoring_type === 'prs' ? 'rgba(245,158,11,0.1)' : ($match->scoring_type === 'elr' ? 'rgba(139,92,246,0.1)' : 'rgba(225,6,0,0.08)');
                            $scoringColor = $match->scoring_type === 'prs' ? 'rgb(251,191,36)' : ($match->scoring_type === 'elr' ? 'rgb(167,139,250)' : 'var(--lp-red)');
                            $scoringLabel = $match->scoring_type === 'prs' ? 'PRS' : ($match->scoring_type === 'elr' ? 'ELR' : 'Relay');
                            $gradientFallback = $match->scoring_type === 'prs' ? 'rgba(180,83,9,0.15)' : ($match->scoring_type === 'elr' ? 'rgba(91,33,182,0.15)' : 'rgba(225,6,0,0.08)');
                        @endphp
                        <a href="{{ route('scoreboard', $match) }}" class="group relative flex flex-col overflow-hidden rounded-2xl transition-all duration-200 hover:scale-[1.02] hover:shadow-xl" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                            <div class="relative aspect-[16/9] overflow-hidden">
                                @if($hasImg)
                                    <img src="{{ $cardImg }}" alt="{{ $match->name }}" class="absolute inset-0 h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy" />
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-black/10"></div>
                                @else
                                    <div class="absolute inset-0" style="background: linear-gradient(135deg, {{ $gradientFallback }}, var(--lp-surface));"></div>
                                @endif
                                @if($match->organization?->logoUrl())
                                    <div class="absolute top-3 left-3">
                                        <img src="{{ $match->organization->logoUrl() }}" alt="" class="h-8 w-8 rounded-lg border border-white/20 object-cover shadow-lg" loading="lazy" />
                                    </div>
                                @elseif($match->organization)
                                    <div class="absolute top-3 left-3 flex h-8 w-8 items-center justify-center rounded-lg border border-white/20 shadow-lg text-[10px] font-bold" style="background: var(--lp-surface); color: var(--lp-text-muted);">
                                        {{ strtoupper(substr($match->organization->name, 0, 2)) }}
                                    </div>
                                @endif
                                <div class="absolute top-3 right-3">
                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider backdrop-blur-sm" style="background: rgba(14,165,233,0.85); color: #fff;">Results</span>
                                </div>
                                <div class="absolute inset-x-0 bottom-0 p-4">
                                    <h3 class="text-base font-bold leading-tight {{ $hasImg ? 'text-white' : '' }}" @if(!$hasImg) style="color: var(--lp-text);" @endif>{{ $match->name }}</h3>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-sm {{ $hasImg ? 'text-white/70' : '' }}" @if(!$hasImg) style="color: var(--lp-text-muted);" @endif>
                                        @if($match->date)<span>{{ $match->date->format('d M Y') }}</span>@endif
                                        @if($match->organization)<span>&bull; {{ $match->organization->name }}</span>@endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-1 items-center justify-between p-4">
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider" style="background: {{ $scoringBg }}; color: {{ $scoringColor }};">{{ $scoringLabel }}</span>
                                <span class="inline-flex items-center gap-1 text-xs font-semibold" style="color: var(--lp-red);">
                                    View Results
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                </span>
                            </div>
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
    {{-- BADGE SHOWCASE --}}
    {{-- ══════════════════════════════════════════ --}}
    @if(($showcaseBadges ?? collect())->isNotEmpty())
        @php
            $badgeConfig = \App\Http\Controllers\BadgeGalleryController::BADGE_CONFIG;
        @endphp
        <section style="border-top: 1px solid var(--lp-border); background: var(--lp-bg-2);">
            <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
                <div class="mb-12 text-center">
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-[13px] font-medium" style="border: 1px solid rgba(56,189,248,0.15); background: rgba(56,189,248,0.06); color: rgba(56,189,248,0.8);">
                        <x-badge-icon name="award" class="h-3.5 w-3.5" />
                        PRS Achievements
                    </div>
                    <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Earn Badges. Build Your Legacy.</h2>
                    <p class="mt-3 max-w-2xl mx-auto" style="color: var(--lp-text-muted);">Every podium finish, milestone, and signature moment earns you a badge. Track your achievements on your shooter profile and see how you compare.</p>
                </div>

                <div class="grid gap-5 grid-cols-1 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($showcaseBadges as $badge)
                        @php
                            $cfg = $badgeConfig[$badge->slug] ?? [];
                            $icon = $cfg['icon'] ?? 'target';
                            $badgeTier = $cfg['tier'] ?? 'earned';
                            $earnChip = $cfg['earnChip'] ?? null;
                        @endphp
                        <x-badge-card
                            :badge="$badge"
                            :icon="$icon"
                            :tier="$badgeTier"
                            family="prs"
                            :earnChip="$earnChip"
                        />
                    @endforeach
                </div>

                <div class="mt-10 text-center">
                    <a href="{{ route('badges.preview') }}" class="lp-cta-nav inline-flex items-center gap-2 rounded-lg px-6 py-3 text-sm font-semibold">
                        View All Badges
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                    </a>
                </div>
            </div>
        </section>
    @endif

    {{-- ══════════════════════════════════════════ --}}
    {{-- SHOOTER FEATURES --}}
    {{-- ══════════════════════════════════════════ --}}
    <section style="border-top: 1px solid var(--lp-border);">
        <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
            <div class="mb-12 text-center">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Your Shooter Experience</h2>
                <p class="mt-3 max-w-xl mx-auto" style="color: var(--lp-text-muted);">More than just scores &mdash; DeadCenter gives you tools to track, improve, and showcase your shooting career.</p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-green-600/10">
                        <svg class="h-6 w-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Team Events</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Register individually, pay your own entry, then team up with friends. Create a team or join an existing one &mdash; team scores are aggregated automatically on the leaderboard.</p>
                </div>

                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(225, 6, 0, 0.08);">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="color: var(--lp-red);">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Personal Match Reports</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Download a detailed PDF report after every match with your scores, stage breakdown, and placement. Reports are also emailed to you automatically when results are published.</p>
                </div>

                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600/10">
                        <svg class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Equipment Profiles</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Save your rifle, ammo, scope, and accessory setup as reusable profiles. Load a profile during match registration to auto-fill your equipment details in seconds.</p>
                </div>

                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background: rgba(225, 6, 0, 0.08);">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="color: var(--lp-red);">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Push Notifications</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Get notified when registration opens, when squadding is live, and when your match results are published. Control exactly which notifications you receive from your settings.</p>
                </div>

                <div class="rounded-2xl p-8" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600/10">
                        <svg class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold" style="color: var(--lp-text);">Install as App</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--lp-text-soft);">Add DeadCenter to your home screen on any phone. Works as a standalone app with offline support and built-in navigation. Also available as a native Android app on the Play Store.</p>
                </div>
            </div>
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

            <div class="space-y-4">
                @foreach([
                    ['q' => 'Is DeadCenter free to use?', 'a' => 'Yes. DeadCenter is completely free for shooters, clubs, and match directors. We sustain the platform through advertising placements on event features.'],
                    ['q' => 'What types of shooting competitions does DeadCenter support?', 'a' => 'DeadCenter supports three scoring disciplines: Relay Scoring for traditional gong/field matches, PRS Scoring for precision rifle series competitions, and ELR Scoring for extreme long range events. More engines will be added over time.'],
                    ['q' => 'How do I register for a match?', 'a' => 'Create a free account, browse upcoming matches on the platform or through a club portal, and register online. Select your division, category, and equipment details during registration. When squadding opens, choose your preferred squad.'],
                    ['q' => 'Can I view results without an account?', 'a' => 'Yes. Scoreboards and live results are publicly accessible. Just scan the QR code at the range or visit the scoreboard link shared by the match organizer.'],
                    ['q' => 'How are season standings calculated?', 'a' => 'DeadCenter uses relative scoring — your score is expressed as a percentage of the top shooter in each match. Season standings aggregate your average relative score across all matches, with optional best-of-N rules.'],
                    ['q' => 'Is DeadCenter only for South African competitions?', 'a' => 'DeadCenter was built for the South African shooting community, but the platform can be used for competitions anywhere.'],
                ] as $i => $faq)
                    <details class="lp-faq-details group rounded-xl overflow-hidden" style="border: 1px solid var(--lp-border); background: var(--lp-surface);">
                        {{-- Flex must live inside <summary>: WebKit breaks native <details> toggle when summary is display:flex --}}
                        <summary class="cursor-pointer list-none px-6 py-4 text-left text-sm font-semibold transition-colors hover:!text-white" style="color: var(--lp-text);">
                            <span class="flex w-full items-center justify-between gap-3">
                                <span class="min-w-0">{{ $faq['q'] }}</span>
                                <svg class="h-4 w-4 flex-shrink-0 transition-transform duration-200 group-open:rotate-180" style="color: var(--lp-text-muted);" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                            </span>
                        </summary>
                        <p class="px-6 pb-4 text-sm leading-relaxed" style="color: var(--lp-text-soft);">{{ $faq['a'] }}</p>
                    </details>
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
