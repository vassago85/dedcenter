{{--
    Royal Flush / standard scoreboard.

    Public scoreboard body for `scoring_type = standard` — both with and
    without the Royal Flush overlay (`royal_flush_enabled`). Included by
    resources/views/pages/scoreboard.blade.php via $scoreboardVariant.

    This is a faithful extraction from the pre-split scoreboard blade —
    same top-level tab bar (Scoreboard / Detailed / Teams / Royal Flush /
    Side Bet / Badges), same tab bodies, same main leaderboard table.
    Royal Flush is the interesting flavour of `standard`; a plain
    standard match still lands here but only sees the tabs and columns
    that make sense (no RF distance filter, no Winning Hand, etc.).

    Shared chrome (page header, publish gate, claim banner, footer) is
    owned by the parent dispatcher.
--}}

{{-- ============================================================
     Filter bar — DIV + CAT
     ============================================================ --}}
@if($divisions->isNotEmpty() || $categories->isNotEmpty())
    <div class="mb-4 min-w-0 space-y-2">
        @if($divisions->isNotEmpty())
            <div class="-mx-1 flex flex-nowrap gap-2 overflow-x-auto px-1 pb-1 sm:mx-0 sm:px-0 [scrollbar-width:thin]">
                <span class="shrink-0 self-center pr-1 text-xs text-muted/60">DIV</span>
                <button type="button" wire:click="filterDivision(null)"
                        class="shrink-0 rounded-lg px-3 py-2 text-xs font-medium transition-colors sm:px-4 sm:text-sm {{ !$activeDivision ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
                    All
                </button>
                @foreach($divisions as $div)
                    <button type="button" wire:click="filterDivision({{ $div->id }})"
                            class="shrink-0 rounded-lg px-3 py-2 text-xs font-medium transition-colors sm:px-4 sm:text-sm {{ $activeDivision === $div->id ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
                        {{ $div->name }}
                    </button>
                @endforeach
            </div>
        @endif

        @if($categories->isNotEmpty())
            <div class="-mx-1 flex flex-nowrap gap-2 overflow-x-auto px-1 pb-1 sm:mx-0 sm:px-0 [scrollbar-width:thin]">
                <span class="shrink-0 self-center pr-1 text-xs text-muted/60">CAT</span>
                <button type="button" wire:click="filterCategory(null)"
                        class="shrink-0 rounded-lg px-3 py-2 text-xs font-medium transition-colors sm:text-sm {{ !$activeCategory ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
                    All
                </button>
                @foreach($categories as $cat)
                    <button type="button" wire:click="filterCategory({{ $cat->id }})"
                            class="shrink-0 rounded-lg px-3 py-2 text-xs font-medium transition-colors sm:text-sm {{ $activeCategory === $cat->id ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
                        {{ $cat->name }}
                    </button>
                @endforeach
            </div>
        @endif
    </div>
@endif

{{-- ============================================================
     Top-level tab bar — Scoreboard / Detailed / Teams / RF / SideBet / Badges
     ============================================================ --}}
@if($isStandard || $royalFlushEnabled || $isTeamEvent)
    <div class="mb-4 flex min-w-0 flex-wrap gap-2">
        <button type="button" wire:click="setTab('main')"
                class="min-w-0 flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors sm:flex-none sm:px-5 sm:py-2.5 sm:text-sm {{ $activeTab === 'main' ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
            Scoreboard
        </button>
        @if($isStandard)
            <button type="button" wire:click="setTab('detailed')"
                    class="min-w-0 flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors sm:flex-none sm:px-5 sm:py-2.5 sm:text-sm {{ $activeTab === 'detailed' ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
                Detailed Breakdown
            </button>
        @endif
        @if($isTeamEvent)
            <button type="button" wire:click="setTab('teams')"
                    class="min-w-0 flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors sm:flex-none sm:px-5 sm:py-2.5 sm:text-sm {{ $activeTab === 'teams' ? 'bg-indigo-600 text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
                Teams
            </button>
        @endif
        @if($royalFlushEnabled)
            <button type="button" wire:click="setTab('royalflush')"
                    class="min-w-0 flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors sm:flex-none sm:px-5 sm:py-2.5 sm:text-sm {{ $activeTab === 'royalflush' ? 'bg-amber-600 text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
                Royal Flush
            </button>
            @if($sideBetEnabled)
                <button type="button" wire:click="setTab('sidebet')"
                        class="min-w-0 flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors sm:flex-none sm:px-5 sm:py-2.5 sm:text-sm {{ $activeTab === 'sidebet' ? 'bg-emerald-600 text-white' : 'bg-surface text-muted hover:bg-surface-2' }}">
                    Side Bet
                </button>
            @endif
            <button type="button" wire:click="setTab('badges')"
                    class="min-w-0 flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors sm:flex-none sm:px-5 sm:py-2.5 sm:text-sm {{ $activeTab === 'badges' ? 'bg-amber-600 text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
                Badges Awarded
            </button>
        @endif
    </div>
@endif

@if($isTeamEvent && $activeTab === 'teams')
    <div class="space-y-6">
        @php
            $activeView = $teamCategoryView;
            if ($activeView !== 'all' && ! $teamCategories->has($activeView)) {
                $activeView = 'all';
            }
            $teamsForView = $activeView === 'all'
                ? $teamLeaderboard
                : ($teamCategories[$activeView] ?? collect());
        @endphp

        @if($teamCategories->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="setTeamCategoryView('all')"
                        class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-xs font-bold uppercase tracking-wider transition-colors sm:text-sm
                            {{ $activeView === 'all'
                                ? 'bg-accent text-white'
                                : 'bg-surface text-muted hover:bg-surface-2 hover:text-secondary' }}">
                    Overall
                    <span class="opacity-70">{{ $teamLeaderboard->count() }}</span>
                </button>
                @foreach($teamCategories as $categoryLabel => $teamsInCategory)
                    <button type="button" wire:click="setTeamCategoryView('{{ $categoryLabel }}')"
                            class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-xs font-bold uppercase tracking-wider transition-colors sm:text-sm
                                {{ $activeView === $categoryLabel
                                    ? 'bg-accent text-white'
                                    : 'bg-surface text-muted hover:bg-surface-2 hover:text-secondary' }}">
                        {{ $categoryLabel }}
                        <span class="opacity-70">{{ $teamsInCategory->count() }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        <div class="space-y-3">
            @forelse($teamsForView as $index => $entry)
                @include('partials.team-leaderboard-row', ['entry' => $entry, 'index' => $index, 'isPrs' => false])
            @empty
                <div class="rounded-2xl border border-dashed border-border bg-surface/50 p-8 text-center">
                    <p class="text-muted">
                        @if($activeView === 'all')
                            No teams set up for this event.
                        @else
                            No teams in {{ $activeView }} yet.
                        @endif
                    </p>
                </div>
            @endforelse
        </div>
    </div>
@elseif($isStandard && $activeTab === 'detailed')
    <div class="space-y-3">
        @forelse($detailedData as $index => $entry)
            @php
                $rank = $entry->shooter->display_rank ?? null;
                $entryStatus = $entry->shooter->status ?? 'active';
                $isDqRow = $entryStatus === 'dq';
                $isNoShowRow = $entryStatus === 'no_show';
                $isOfflineRow = $isDqRow || $isNoShowRow;
                $rankLabel = $isDqRow ? 'DQ' : ($isNoShowRow ? 'N/S' : $rank);
                $isExpanded = $expandedShooterId === $entry->shooter->id;
                $canClaimEntry = $entry->shooter->isUnclaimedResult()
                    && ! $isOfflineRow
                    && ($match->status === \App\Enums\MatchStatus::Completed || $match->status === \App\Enums\MatchStatus::Active);
            @endphp
            <div class="overflow-hidden rounded-2xl border border-border bg-app {{ $isOfflineRow ? 'opacity-60' : '' }}">
                <div role="button" tabindex="0"
                     wire:click="toggleExpand({{ $entry->shooter->id }})"
                     x-on:keydown.enter.prevent="$wire.toggleExpand({{ $entry->shooter->id }})"
                     x-on:keydown.space.prevent="$wire.toggleExpand({{ $entry->shooter->id }})"
                     class="flex w-full cursor-pointer items-center gap-3 px-3 py-3 text-left transition-colors hover:bg-surface/50 focus:outline-none focus:ring-2 focus:ring-accent/50 sm:gap-4 sm:px-6 sm:py-4">
                    @php
                        $detailedMatchComplete = $match->status === \App\Enums\MatchStatus::Completed;
                        $detailedShowPodium = $detailedMatchComplete && ! $isOfflineRow && $rank !== null && $rank <= 3;
                        $detailedPodiumIcon = $rank === 1 ? 'medal-1' : ($rank === 2 ? 'medal-2' : 'medal-3');
                        $detailedPodiumLabel = $rank === 1 ? 'Podium Gold' : ($rank === 2 ? 'Podium Silver' : 'Podium Bronze');
                        $detailedCrestFamily = $royalFlushEnabled ? 'royal_flush' : 'prs';
                    @endphp
                    @if($isOfflineRow)
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center text-sm text-muted font-bold italic" title="{{ $isDqRow ? 'Disqualified' : 'Did not attend' }}">{{ $rankLabel }}</span>
                    @elseif($detailedShowPodium)
                        <span class="flex-shrink-0" title="{{ $detailedPodiumLabel }}">
                            <x-badge-crest :icon="$detailedPodiumIcon" tier="earned" :family="$detailedCrestFamily" />
                        </span>
                    @elseif($rank <= 3)
                        @php
                            $medalClass = match($rank) {
                                1 => 'bg-amber-500 text-black',
                                2 => 'bg-slate-400 text-black',
                                3 => 'bg-orange-600 text-white',
                            };
                        @endphp
                        <span class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-lg font-black {{ $medalClass }}">{{ $rank }}</span>
                    @else
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center text-xl text-muted font-medium">{{ $rank }}</span>
                    @endif

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate text-xl font-semibold text-primary">{{ $entry->shooter->name }}</p>
                            @if($canClaimEntry)
                                <a href="{{ app_url('/claim?match=' . $match->id . '&shooter=' . $entry->shooter->id) }}"
                                   wire:click.stop
                                   x-on:click.stop
                                   class="inline-flex items-center gap-1 rounded-full border border-accent/40 bg-accent/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-accent transition-colors hover:border-accent hover:bg-accent/20 hover:text-white sm:text-xs"
                                   title="Link this result to your DeadCenter account (admin approval required)">
                                    <span class="h-1 w-1 rounded-full bg-accent"></span>
                                    Claim
                                </a>
                            @endif
                        </div>
                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted">
                            <span>{{ $entry->shooter->squad?->name ?? '—' }}</span>
                            <span class="opacity-50">&middot;</span>
                            <span class="inline-flex items-center gap-1 text-green-400" title="{{ $entry->total_hits }} hits">
                                <x-icon name="check" class="h-3.5 w-3.5" />
                                <span class="tabular-nums">{{ $entry->total_hits }}</span>
                            </span>
                            <span class="inline-flex items-center gap-1 text-accent" title="{{ $entry->total_misses }} misses">
                                <x-icon name="x" class="h-3.5 w-3.5" />
                                <span class="tabular-nums">{{ $entry->total_misses }}</span>
                            </span>
                        </p>
                    </div>

                    <span class="text-lg font-black text-amber-400 tabular-nums sm:text-2xl">{{ number_format($entry->total_score, 1) }}</span>

                    <x-icon name="chevron-down" class="h-5 w-5 flex-shrink-0 text-muted transition-transform sm:h-6 sm:w-6 {{ $isExpanded ? 'rotate-180' : '' }}" />
                </div>

                @if($isExpanded)
                    @php $matchCompleted = $match->status === \App\Enums\MatchStatus::Completed; @endphp
                    @if($matchCompleted && ! $isOfflineRow)
                        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface/30 px-4 py-2.5 sm:px-6">
                            <a href="{{ route('scoreboard.matches.report.view', [$match, $entry->shooter]) }}"
                               wire:click.stop
                               x-on:click.stop
                               target="_blank"
                               rel="noopener"
                               class="inline-flex items-center gap-1.5 rounded-lg border border-accent/40 bg-accent/10 px-3 py-1.5 text-xs font-semibold text-accent transition-colors hover:border-accent hover:bg-accent/20 hover:text-white">
                                <x-icon name="document-check" class="h-3.5 w-3.5" />
                                View Match Report
                            </a>
                        </div>
                    @endif
                    <div class="border-t border-border">
                        @foreach($targetSetDetails as $ts)
                            @php $dist = $entry->distances[$ts->id] ?? null; @endphp
                            @if($dist)
                                <div class="border-b border-border/50 last:border-b-0">
                                    <div class="flex items-center justify-between bg-surface/40 px-6 py-3">
                                        <span class="text-base font-semibold text-primary">
                                            {{ $ts->label }} ({{ $ts->distance_meters }}m)
                                            <span class="ml-2 text-xs font-normal text-muted">&times;{{ number_format($ts->distance_multiplier ?? 1, 1) }} stage value</span>
                                        </span>
                                        <div class="flex items-center gap-4 text-sm">
                                            <span class="inline-flex items-center gap-1.5 font-medium text-green-400" title="{{ $dist->hits }} hits">
                                                <x-icon name="check" class="h-4 w-4" />
                                                <span class="tabular-nums">{{ $dist->hits }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1.5 font-medium text-accent" title="{{ $dist->misses }} misses">
                                                <x-icon name="x" class="h-4 w-4" />
                                                <span class="tabular-nums">{{ $dist->misses }}</span>
                                            </span>
                                            <span class="font-bold text-amber-400">{{ number_format($dist->subtotal, 1) }} pts</span>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-px bg-border/30 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                                        @foreach($dist->gongs as $gong)
                                            <div class="flex items-center justify-between bg-app px-4 py-3 text-sm">
                                                <span class="text-muted">
                                                    #{{ $gong->number }}
                                                    @if($gong->label) <span class="text-secondary">({{ $gong->label }})</span> @endif
                                                    <span class="text-muted/60 text-xs">&times;{{ number_format($gong->multiplier, 1) }}</span>
                                                </span>
                                                @if($gong->is_hit === true)
                                                    <span class="inline-flex items-center gap-1.5 font-bold text-green-400" title="Hit +{{ number_format($gong->points, 1) }}">
                                                        <x-icon name="check" class="h-4 w-4" />
                                                        <span class="tabular-nums">+{{ number_format($gong->points, 1) }}</span>
                                                    </span>
                                                @elseif($gong->is_hit === false)
                                                    <span class="inline-flex items-center gap-1 font-bold text-accent" title="Miss">
                                                        <x-icon name="x" class="h-4 w-4" />
                                                    </span>
                                                @else
                                                    <span class="text-muted/50">&mdash;</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-border bg-app px-6 py-16 text-center text-2xl text-muted">
                No scores recorded yet
            </div>
        @endforelse
    </div>
@elseif($royalFlushEnabled && $activeTab === 'royalflush')
    @if($rfDistances->count() > 1)
        <div class="mb-4 rounded-2xl border border-amber-600/30 bg-surface/40 p-3 sm:p-4">
            <div class="mb-2.5 flex items-center gap-2">
                <x-icon name="filter" class="h-4 w-4 text-amber-400 sm:h-5 sm:w-5" />
                <span class="text-xs font-bold uppercase tracking-wider text-amber-400 sm:text-sm">Filter flushes by distance</span>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="$set('rfDistanceFilter', null)"
                        class="rounded-full px-4 py-2 text-sm font-bold transition-colors sm:px-5 {{ $rfDistanceFilter === null ? 'bg-amber-600 text-white ring-2 ring-amber-400/50' : 'bg-surface-2 text-muted hover:bg-surface-2/70 hover:text-primary' }}">
                    All distances
                </button>
                @foreach($rfDistances as $dist)
                    <button type="button" wire:click="$set('rfDistanceFilter', {{ $dist }})"
                            class="rounded-full px-4 py-2 text-sm font-bold transition-colors sm:px-5 {{ $rfDistanceFilter === $dist ? 'bg-amber-600 text-white ring-2 ring-amber-400/50' : 'bg-surface-2 text-muted hover:bg-surface-2/70 hover:text-primary' }}">
                        {{ $dist }}m
                    </button>
                @endforeach
            </div>
            @if($rfDistanceFilter)
                <p class="mt-2.5 text-xs text-amber-200/70">Showing shooters who flushed <span class="font-bold text-amber-300">{{ $rfDistanceFilter }}m</span>. Tap <span class="font-semibold">All distances</span> to clear.</p>
            @endif
        </div>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-amber-700/50 bg-app [-webkit-overflow-scrolling:touch]">
        <table class="w-full min-w-[36rem] text-left">
            <thead>
                <tr class="border-b border-border bg-surface/80">
                    <th class="px-3 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">#</th>
                    <th class="px-3 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Shooter</th>
                    <th class="px-3 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Squad</th>
                    <th class="px-3 py-2 text-center text-xs font-bold text-amber-400 sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Flushes</th>
                    <th class="px-3 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Distances</th>
                    <th class="px-3 py-2 text-right text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Score</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($royalFlushEntries as $entry)
                    @php
                        $rowClass = match($entry->rank) {
                            1 => 'bg-amber-500/10 border-l-4 border-l-amber-400',
                            2 => 'bg-slate-400/5 border-l-4 border-l-slate-400',
                            3 => 'bg-orange-500/5 border-l-4 border-l-orange-600',
                            default => 'border-l-4 border-l-transparent',
                        };
                        $rankClass = match($entry->rank) {
                            1 => 'text-amber-400 font-black',
                            2 => 'text-secondary font-bold',
                            3 => 'text-orange-500 font-bold',
                            default => 'text-muted font-medium',
                        };
                        $rfMatchComplete = $match->status === \App\Enums\MatchStatus::Completed;
                        $rfShowPodiumCrest = $rfMatchComplete && in_array($entry->rank, [1, 2, 3], true);
                        $rfPodiumIcon = $entry->rank === 1 ? 'medal-1' : ($entry->rank === 2 ? 'medal-2' : 'medal-3');
                        $rfPodiumLabel = $entry->rank === 1 ? 'Podium Gold' : ($entry->rank === 2 ? 'Podium Silver' : 'Podium Bronze');
                    @endphp
                    <tr class="{{ $rowClass }} transition-colors">
                        <td class="px-3 py-2 sm:px-6 sm:py-4">
                            @if($rfShowPodiumCrest)
                                <div class="flex items-center gap-2 sm:gap-3">
                                    <span class="inline-flex shrink-0 scale-[0.72] sm:scale-90 lg:scale-100" title="{{ $rfPodiumLabel }}">
                                        <x-badge-crest :icon="$rfPodiumIcon" tier="earned" family="royal_flush" />
                                    </span>
                                    <span class="text-lg {{ $rankClass }} sm:text-2xl lg:text-3xl">{{ $entry->rank }}</span>
                                </div>
                            @else
                                <span class="text-lg {{ $rankClass }} sm:text-2xl lg:text-3xl">{{ $entry->rank }}</span>
                            @endif
                        </td>
                        <td class="max-w-[10rem] truncate px-3 py-2 text-sm font-semibold sm:max-w-none sm:px-6 sm:py-4 sm:text-xl lg:text-2xl" title="{{ $entry->name }}">
                            @if($entry->user_id)
                                <a href="{{ route('shooter.profile', $entry->user_id) }}" class="text-primary hover:underline">{{ $entry->name }}</a>
                            @else
                                <span class="text-primary">{{ $entry->name }}</span>
                            @endif
                        </td>
                        <td class="max-w-[6rem] truncate px-3 py-2 text-xs text-muted sm:max-w-none sm:px-6 sm:py-4 sm:text-lg lg:text-xl">{{ $entry->squad_name }}</td>
                        <td class="px-3 py-2 text-center text-lg font-black text-amber-400 sm:px-6 sm:py-4 sm:text-2xl lg:text-3xl">{{ $entry->flush_count }}</td>
                        <td class="px-3 py-2 sm:px-6 sm:py-4">
                            @if(!empty($entry->flush_distances))
                                <div class="flex flex-wrap gap-2">
                                    @foreach($entry->flush_distances as $d)
                                        <span class="rounded-full bg-amber-600/20 px-3 py-1 text-sm font-bold text-amber-400">{{ $d }}m</span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-lg text-muted">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right text-base font-bold text-amber-400 tabular-nums sm:px-6 sm:py-4 sm:text-xl lg:text-2xl">{{ $entry->total_score }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-sm text-muted sm:px-6 sm:py-16 sm:text-2xl">No Royal Flush data yet</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@elseif($sideBetEnabled && $activeTab === 'sidebet')
    @php
        $sbComplete = $match->status === \App\Enums\MatchStatus::Completed;
        $sbSmallGongLabel = $sideBetGongLabels[0] ?? 'smallest gong';
    @endphp

    @if($sideBetWinner)
        <div class="mb-5 overflow-hidden rounded-2xl border-2 {{ $sbComplete && ! $sideBetTied ? 'border-emerald-500/50' : 'border-emerald-600/30' }} bg-gradient-to-br from-emerald-900/30 via-surface to-surface">
            <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:gap-6 sm:p-7">
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-emerald-500/15 ring-1 ring-emerald-400/30 sm:h-20 sm:w-20">
                    <x-badge-icon name="spade" class="h-8 w-8 text-emerald-300 sm:h-10 sm:w-10" />
                </div>
                <div class="min-w-0 flex-1">
                    <span class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-400 sm:text-sm">
                        {{ $sideBetTied ? 'Side Bet — Tied for the Lead' : ($sbComplete ? 'Side Bet Winner' : 'Side Bet — Current Leader') }}
                    </span>
                    <h3 class="mt-1 truncate text-2xl font-black text-primary sm:text-4xl" title="{{ $sideBetWinner['name'] }}">{{ $sideBetWinner['name'] }}</h3>
                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm sm:text-base">
                        <span class="text-muted">{{ $sideBetWinner['squad_name'] }}</span>
                        <span class="text-muted">&bull;</span>
                        <span class="font-bold tabular-nums text-emerald-300">{{ $sideBetWinner['small_gong_hits'] }} {{ $sbSmallGongLabel }} hits</span>
                        @if(!empty($sideBetWinner['distances']))
                            <span class="text-muted">&bull;</span>
                            <span class="text-secondary">{{ implode('m, ', $sideBetWinner['distances']) }}m</span>
                        @endif
                    </div>
                    @if($sideBetTied)
                        <p class="mt-2 text-xs text-emerald-200/70">Tied on every gong — no outright winner{{ $sbComplete ? '. Winning Hand was not awarded.' : ' yet.' }}</p>
                    @endif
                </div>
                @unless($sbComplete)
                    <div class="shrink-0 self-start rounded-full bg-app/50 px-3 py-1 ring-1 ring-emerald-500/20 sm:self-center">
                        <span class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-emerald-300">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400"></span> Live
                        </span>
                    </div>
                @endunless
            </div>
        </div>
    @endif

    <div class="overflow-x-auto rounded-2xl border border-emerald-700/40 bg-app [-webkit-overflow-scrolling:touch]">
        <table class="w-full min-w-[36rem] text-left">
            <thead>
                <tr class="border-b border-border bg-surface/80">
                    <th class="px-3 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">#</th>
                    <th class="px-3 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Shooter</th>
                    <th class="px-3 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Squad</th>
                    <th class="px-3 py-2 text-center text-xs font-bold text-emerald-400 sm:px-6 sm:py-4 sm:text-lg lg:text-xl">{{ ucfirst($sbSmallGongLabel) }} hits</th>
                    <th class="px-3 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Distances</th>
                    <th class="px-3 py-2 text-right text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Score</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($sideBetStandings as $entry)
                    @php
                        $rowClass = match($entry['rank']) {
                            1 => 'bg-emerald-500/10 border-l-4 border-l-emerald-400',
                            2 => 'bg-slate-400/5 border-l-4 border-l-slate-400',
                            3 => 'bg-orange-500/5 border-l-4 border-l-orange-600',
                            default => 'border-l-4 border-l-transparent',
                        };
                        $rankClass = match($entry['rank']) {
                            1 => 'text-emerald-400 font-black',
                            2 => 'text-secondary font-bold',
                            3 => 'text-orange-500 font-bold',
                            default => 'text-muted font-medium',
                        };
                    @endphp
                    <tr class="{{ $rowClass }} transition-colors">
                        <td class="px-3 py-2 text-lg {{ $rankClass }} sm:px-6 sm:py-4 sm:text-2xl lg:text-3xl">{{ $entry['rank'] }}</td>
                        <td class="max-w-[12rem] px-3 py-2 sm:max-w-none sm:px-6 sm:py-4">
                            <span class="block truncate text-sm font-semibold text-primary sm:text-xl lg:text-2xl" title="{{ $entry['name'] }}">{{ $entry['name'] }}</span>
                            @if($entry['tiebreaker_reason'])
                                <span class="mt-0.5 block text-[10px] font-normal leading-tight text-muted sm:text-xs">{{ $entry['tiebreaker_reason'] }}</span>
                            @endif
                        </td>
                        <td class="max-w-[6rem] truncate px-3 py-2 text-xs text-muted sm:max-w-none sm:px-6 sm:py-4 sm:text-lg lg:text-xl">{{ $entry['squad_name'] }}</td>
                        <td class="px-3 py-2 text-center text-lg font-black text-emerald-400 sm:px-6 sm:py-4 sm:text-2xl lg:text-3xl">{{ $entry['small_gong_hits'] }}</td>
                        <td class="px-3 py-2 sm:px-6 sm:py-4">
                            @if(!empty($entry['distances']))
                                <div class="flex flex-wrap gap-2">
                                    @foreach($entry['distances'] as $d)
                                        <span class="rounded-full bg-emerald-600/20 px-3 py-1 text-sm font-bold text-emerald-300">{{ $d }}m</span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-lg text-muted">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right text-base font-bold tabular-nums text-emerald-300 sm:px-6 sm:py-4 sm:text-xl lg:text-2xl">{{ $entry['total_score'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-sm text-muted sm:px-6 sm:py-16 sm:text-2xl">No side bet buy-ins yet</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-center text-xs text-muted">Ranked on most hits on the {{ $sbSmallGongLabel }} (highest multiplier), then furthest distances, cascading down through the larger gongs.</p>
@elseif($royalFlushEnabled && $activeTab === 'badges')
    @php
        $badgesByCategory = $matchBadges->groupBy(fn ($ua) => $ua->achievement?->category ?? 'unknown');
        $matchSpecials = $badgesByCategory->get('match_special', collect());
        $lifetimeBadges = $badgesByCategory->get('lifetime', collect());
        $repeatableBadges = $badgesByCategory->get('repeatable', collect());
        $hasBadges = $matchBadges->isNotEmpty();
        $bcfg = \App\Http\Controllers\BadgeGalleryController::BADGE_CONFIG;
        $rfAwardedSlugs = $matchBadges->pluck('achievement.slug')->filter()->unique()->values()->all();
    @endphp

    @if(!$hasBadges)
        <div class="flex flex-col items-center justify-center rounded-2xl border border-border bg-surface/30 px-6 py-16 text-center">
            <x-badge-icon name="award" class="mb-3 h-10 w-10 text-muted opacity-40" />
            <h3 class="text-lg font-bold text-primary">No badges awarded yet</h3>
            <p class="mt-2 max-w-sm text-sm text-muted">Badges earned during this match will appear here once scoring has been finalized.</p>
        </div>

        <x-badge-criteria-reference competitionType="royal_flush" :awardedSlugs="$rfAwardedSlugs" />
    @else
        <div class="space-y-6">

            @php $winningHand = $matchSpecials->first(fn ($ua) => $ua->achievement?->slug === 'winning-hand'); @endphp
            @if($winningHand)
                <section>
                    <div class="mb-3 flex items-center gap-2">
                        <x-badge-icon name="sparkles" class="h-3.5 w-3.5 text-amber-400" />
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-400">Signature Badge</span>
                    </div>
                    <div class="overflow-hidden rounded-2xl border-2 border-amber-500/40 bg-gradient-to-br from-amber-900/20 via-surface to-surface">
                        <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:gap-5 sm:p-6">
                            <x-badge-crest icon="spade" tier="featured" family="royal_flush" />
                            <div class="min-w-0 flex-1">
                                <h3 class="text-xl font-semibold text-amber-300 sm:text-2xl">Winning Hand</h3>
                                <p class="mt-1 text-sm text-secondary">{{ $winningHand->achievement->description }}</p>
                                @php $whCriteria = \App\Http\Controllers\BadgeGalleryController::criteriaFor('winning-hand'); @endphp
                                @if($whCriteria)
                                    <div class="mt-2 rounded-lg border border-amber-400/15 bg-amber-900/10 px-3 py-2">
                                        <span class="block text-[10px] font-bold uppercase tracking-wider text-amber-400/70">How to earn it</span>
                                        <p class="mt-0.5 text-xs leading-snug text-secondary">{{ $whCriteria }}</p>
                                    </div>
                                @endif
                                <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                                    @if($winningHand->user_id)
                                        <a href="{{ route('shooter.profile', $winningHand->user_id) }}" class="font-bold text-primary hover:underline">{{ $winningHand->shooter?->name ?? $winningHand->user?->name ?? 'Unknown' }}</a>
                                    @else
                                        <span class="font-bold text-primary">{{ $winningHand->shooter?->name ?? 'Unknown' }}</span>
                                    @endif
                                    @if(isset($winningHand->metadata['small_gong_hits']))
                                        <span class="text-muted">&bull;</span>
                                        <span class="tabular-nums text-amber-400">{{ $winningHand->metadata['small_gong_hits'] }} small gong hits</span>
                                    @endif
                                    @if(!empty($winningHand->metadata['distances_hit']))
                                        <span class="text-muted">&bull;</span>
                                        <span class="text-muted">{{ implode('m, ', $winningHand->metadata['distances_hit']) }}m</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            @elseif($match->side_bet_enabled)
                <section>
                    <div class="mb-3 flex items-center gap-2">
                        <x-badge-icon name="sparkles" class="h-3.5 w-3.5 text-amber-400" />
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-400">Signature Badge</span>
                    </div>
                    <div class="rounded-2xl border border-border/50 bg-surface/20 px-5 py-8 text-center">
                        <x-badge-icon name="spade" class="mx-auto mb-2 h-8 w-8 text-muted opacity-30" />
                        <p class="text-sm font-semibold text-muted">No Winning Hand was awarded for this match.</p>
                        <p class="mt-1 text-xs text-muted/60">Awarded to the side bet winner. If tied, it is not awarded.</p>
                    </div>
                </section>
            @endif

            @if($lifetimeBadges->isNotEmpty())
                <section>
                    <div class="mb-3 flex items-center gap-2">
                        <x-badge-icon name="award" class="h-3.5 w-3.5 text-amber-400/70" />
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-400/70">Lifetime Achievements</span>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach($lifetimeBadges->sortBy(fn ($ua) => $ua->achievement?->sort_order ?? 99) as $ua)
                            @php
                                $badge = $ua->achievement;
                                $cfg = $bcfg[$badge->slug] ?? [];
                                $icon = $cfg['icon'] ?? 'target';
                                $tier = $cfg['tier'] ?? 'earned';
                            @endphp
                            <div class="flex items-start gap-4 rounded-2xl border border-amber-400/15 bg-amber-900/8 px-4 py-4">
                                <x-badge-crest :icon="$icon" :tier="$tier" family="royal_flush" />
                                <div class="min-w-0 flex-1">
                                    <span class="text-base font-bold text-amber-200">{{ $badge->label }}</span>
                                    <p class="mt-0.5 text-xs text-muted leading-snug">{{ $badge->description }}</p>
                                    @php $lbCriteria = \App\Http\Controllers\BadgeGalleryController::criteriaFor($badge->slug); @endphp
                                    @if($lbCriteria && $lbCriteria !== $badge->description)
                                        <p class="mt-1 text-[11px] leading-snug text-amber-200/70"><span class="font-semibold uppercase tracking-wider text-amber-400/70 text-[9px]">How to earn &middot;</span> {{ $lbCriteria }}</p>
                                    @endif
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                        @if($ua->user_id)
                                            <a href="{{ route('shooter.profile', $ua->user_id) }}" class="font-semibold text-primary hover:underline">{{ $ua->shooter?->name ?? $ua->user?->name ?? 'Unknown' }}</a>
                                        @else
                                            <span class="font-semibold text-primary">{{ $ua->shooter?->name ?? 'Unknown' }}</span>
                                        @endif
                                        @if(isset($ua->metadata['distance_meters']))
                                            <span class="text-muted">&bull;</span>
                                            <span class="tabular-nums text-muted">{{ $ua->metadata['distance_meters'] }}m</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if($repeatableBadges->isNotEmpty())
                <section>
                    <div class="mb-3 flex items-center gap-2">
                        <x-badge-icon name="layers" class="h-3.5 w-3.5 text-amber-500/60" />
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-500/60">Stackable Badges</span>
                    </div>
                    @php
                        $rfGrouped = $repeatableBadges->groupBy(fn ($ua) => $ua->achievement?->slug ?? 'unknown');
                    @endphp
                    <div class="space-y-4">
                        @foreach($rfGrouped as $slug => $entries)
                            @php
                                $badge = $entries->first()?->achievement;
                                if (!$badge) continue;
                                $cfg = $bcfg[$badge->slug] ?? [];
                                $icon = $cfg['icon'] ?? 'target';
                                $tier = $cfg['tier'] ?? 'earned';
                            @endphp
                            <div class="overflow-hidden rounded-2xl border border-amber-500/12 bg-amber-900/5">
                                <div class="flex items-start gap-4 border-b border-amber-500/10 px-4 py-3">
                                    <x-badge-crest :icon="$icon" :tier="$tier" family="royal_flush" />
                                    <div class="min-w-0 flex-1">
                                        <span class="text-base font-bold text-amber-200">{{ $badge->label }}</span>
                                        <p class="mt-0.5 text-xs text-muted leading-snug">{{ $badge->description }}</p>
                                        @php $rbCriteria = \App\Http\Controllers\BadgeGalleryController::criteriaFor($badge->slug); @endphp
                                        @if($rbCriteria && $rbCriteria !== $badge->description)
                                            <p class="mt-1 text-[11px] leading-snug text-amber-200/70"><span class="font-semibold uppercase tracking-wider text-amber-400/70 text-[9px]">How to earn &middot;</span> {{ $rbCriteria }}</p>
                                        @endif
                                    </div>
                                    <span class="flex-shrink-0 rounded-full bg-amber-600/20 px-2.5 py-1 text-xs font-bold tabular-nums text-amber-400">{{ $entries->count() }}&times;</span>
                                </div>
                                <div class="divide-y divide-border/30">
                                    @foreach($entries->sortBy(fn ($ua) => $ua->shooter?->name ?? $ua->user?->name ?? '') as $ua)
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 text-sm">
                                            @if($ua->user_id)
                                                <a href="{{ route('shooter.profile', $ua->user_id) }}" class="font-medium text-primary hover:underline">{{ $ua->shooter?->name ?? $ua->user?->name ?? 'Unknown' }}</a>
                                            @else
                                                <span class="font-medium text-primary">{{ $ua->shooter?->name ?? 'Unknown' }}</span>
                                            @endif
                                            @if($ua->stage)
                                                <span class="text-xs text-muted">{{ $ua->stage->label ?? 'Stage ' . $ua->stage->stage_number }}</span>
                                            @endif
                                            @if(isset($ua->metadata['distance_meters']))
                                                <span class="text-xs tabular-nums text-muted">{{ $ua->metadata['distance_meters'] }}m</span>
                                            @endif
                                            @if(isset($ua->metadata['flush_count']))
                                                <span class="text-xs tabular-nums text-muted">{{ $ua->metadata['flush_count'] }} flushes</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <x-badge-criteria-reference competitionType="royal_flush" :awardedSlugs="$rfAwardedSlugs" />

        </div>
    @endif
@else
    {{-- ============================================================
         Default Scoreboard tab — the main standings table.
         ============================================================ --}}
    <div class="overflow-x-auto rounded-2xl border border-border bg-app [-webkit-overflow-scrolling:touch]">
        <table class="w-full min-w-[36rem] text-left lg:min-w-0">
            <thead>
                <tr class="border-b border-border bg-surface/80">
                    <th class="px-2 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">#</th>
                    <th class="px-2 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Shooter</th>
                    <th class="px-2 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Squad</th>
                    @if($divisions->isNotEmpty())
                        <th class="px-2 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Div</th>
                    @endif
                    <th class="px-2 py-2 text-center text-xs font-bold text-green-400 sm:px-6 sm:py-4 sm:text-lg lg:text-xl">
                        <span class="inline-flex items-center justify-center gap-1.5">
                            <x-icon name="check" class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                            Hits
                        </span>
                    </th>
                    <th class="px-2 py-2 text-center text-xs font-bold text-accent sm:px-6 sm:py-4 sm:text-lg lg:text-xl">
                        <span class="inline-flex items-center justify-center gap-1.5">
                            <x-icon name="x" class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                            Miss
                        </span>
                    </th>
                    <th class="px-2 py-2 text-right text-xs font-bold text-amber-400 sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Score</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($shooters as $index => $shooter)
                    @php
                        $rank = $shooter->display_rank ?? null;
                        $shooterStatus = $shooter->status ?? 'active';
                        $isDqRow = $shooterStatus === 'dq';
                        $isNoShowRow = $shooterStatus === 'no_show';
                        $isOfflineRow = $isDqRow || $isNoShowRow;

                        $rowClass = match(true) {
                            $isOfflineRow => 'opacity-60 border-l-4 border-l-transparent',
                            $rank === 1 => 'bg-amber-500/10 border-l-4 border-l-amber-400',
                            $rank === 2 => 'bg-slate-400/5 border-l-4 border-l-slate-400',
                            $rank === 3 => 'bg-orange-500/5 border-l-4 border-l-orange-600',
                            default => 'border-l-4 border-l-transparent',
                        };
                        $rankClass = match(true) {
                            $isOfflineRow => 'text-muted font-semibold italic',
                            $rank === 1 => 'text-amber-400 font-black',
                            $rank === 2 => 'text-secondary font-bold',
                            $rank === 3 => 'text-orange-500 font-bold',
                            default => 'text-muted font-medium',
                        };
                        $rankLabel = match(true) {
                            $isDqRow => 'DQ',
                            $isNoShowRow => 'N/S',
                            default => $rank,
                        };
                        $matchComplete = $match->status === \App\Enums\MatchStatus::Completed;
                        $showPodiumCrest = $matchComplete && ! $isOfflineRow && in_array($rank, [1, 2, 3], true);
                        $podiumIcon = $rank === 1 ? 'medal-1' : ($rank === 2 ? 'medal-2' : 'medal-3');
                        $podiumLabel = $rank === 1 ? 'Podium Gold' : ($rank === 2 ? 'Podium Silver' : 'Podium Bronze');
                        $crestFamily = $royalFlushEnabled ? 'royal_flush' : 'prs';
                    @endphp
                    <tr class="{{ $rowClass }} transition-colors">
                        <td class="px-2 py-2 sm:px-6 sm:py-4" @if($isNoShowRow) title="Did not attend" @endif>
                            @if($showPodiumCrest)
                                <div class="flex items-center gap-2 sm:gap-3">
                                    <span class="inline-flex shrink-0 scale-[0.72] sm:scale-90 lg:scale-100" title="{{ $podiumLabel }}">
                                        <x-badge-crest :icon="$podiumIcon" tier="earned" :family="$crestFamily" />
                                    </span>
                                    <span class="text-lg {{ $rankClass }} sm:text-2xl lg:text-3xl">{{ $rankLabel }}</span>
                                </div>
                            @else
                                <span class="text-lg {{ $rankClass }} sm:text-2xl lg:text-3xl">{{ $rankLabel }}</span>
                            @endif
                        </td>
                        <td class="max-w-[7rem] px-2 py-2 text-sm font-semibold sm:max-w-[12rem] sm:px-6 sm:py-4 sm:text-xl lg:max-w-none lg:text-2xl">
                            @php
                                $shooterUnclaimed = $shooter->isUnclaimedResult();
                                $shooterIsRealUser = ! $shooterUnclaimed && $shooter->user_id;
                                $canClaimRow = $shooterUnclaimed
                                    && ! $isOfflineRow
                                    && ($match->status === \App\Enums\MatchStatus::Completed || $match->status === \App\Enums\MatchStatus::Active);
                            @endphp
                            @if($shooterIsRealUser)
                                <a href="{{ route('shooter.profile', $shooter->user_id) }}" class="line-clamp-2 text-primary hover:underline sm:line-clamp-none" title="{{ $shooter->name }}">{{ $shooter->name }}</a>
                            @else
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="line-clamp-2 text-primary sm:line-clamp-none" title="{{ $shooter->name }}">{{ $shooter->name }}</span>
                                    @if($canClaimRow)
                                        <a href="{{ app_url('/claim?match=' . $match->id . '&shooter=' . $shooter->id) }}"
                                           class="inline-flex items-center gap-1 rounded-full border border-accent/40 bg-accent/10 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-accent transition-colors hover:border-accent hover:bg-accent/20 hover:text-white sm:text-[10px]"
                                           title="Link this result to your DeadCenter account (admin approval required)">
                                            <span class="h-1 w-1 rounded-full bg-accent"></span>
                                            Claim
                                        </a>
                                    @endif
                                </div>
                            @endif
                            @if($shooterIsRealUser)
                                <x-shooter-badges :userId="$shooter->user_id" :matchId="$match->id" :badges="$rowBadgesByUser->get($shooter->user_id, collect())" :competitionType="$royalFlushEnabled ? 'royal_flush' : null" :compact="true" />
                            @endif
                            @if(isset($customFieldMap[$shooter->id]))
                                <div class="flex flex-wrap gap-1 mt-0.5">
                                    @foreach($customFieldMap[$shooter->id] as $cfv)
                                        <span class="rounded bg-surface-2 px-1.5 py-0.5 text-[10px] text-muted" title="{{ $cfv['label'] }}">{{ $cfv['value'] }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="max-w-[4.5rem] truncate px-2 py-2 text-xs text-muted sm:max-w-none sm:px-6 sm:py-4 sm:text-lg lg:text-xl" title="{{ $shooter->squad?->name ?? '—' }}">{{ $shooter->squad?->name ?? '—' }}</td>
                        @if($divisions->isNotEmpty())
                            <td class="max-w-[4rem] truncate px-2 py-2 text-xs text-muted sm:max-w-none sm:px-6 sm:py-4 sm:text-lg lg:text-xl" title="{{ $shooter->division?->name ?? '—' }}">{{ $shooter->division?->name ?? '—' }}</td>
                        @endif
                        <td class="px-2 py-2 text-center text-base font-bold text-green-400 sm:px-6 sm:py-4 sm:text-xl lg:text-2xl">{{ $shooter->hits_count }}</td>
                        <td class="px-2 py-2 text-center text-base font-bold text-accent sm:px-6 sm:py-4 sm:text-xl lg:text-2xl">{{ $shooter->misses_count }}</td>
                        <td class="whitespace-nowrap px-2 py-2 text-right text-lg font-black text-amber-400 sm:px-6 sm:py-4 sm:text-2xl lg:text-3xl">
                            {{ number_format($shooter->display_score, 1) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 6 + ($divisions->isNotEmpty() ? 1 : 0) }}" class="px-4 py-12 text-center text-sm text-muted sm:px-6 sm:py-16 sm:text-2xl">
                            No scores recorded yet
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif
