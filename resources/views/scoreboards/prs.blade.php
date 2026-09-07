{{--
    PRS scoreboard.

    Public scoreboard body for `scoring_type = prs`. Included by
    resources/views/pages/scoreboard.blade.php via $scoreboardVariant.

    This is a faithful extraction from the pre-split scoreboard blade —
    same Alpine tab wrapper (Scoreboard / Score Sheet / Badges / Teams),
    same leaderboard columns (Hits / Miss / N/T / TB Time / Pts), same
    per-shot grid on the Score Sheet, same badges layout. Nothing about
    PRS behaviour changed in the extraction.

    Shared chrome (page header, publish gate, claim banner, footer) is
    owned by the parent dispatcher.
--}}

{{-- ============================================================
     Filter bar — DIV + CAT (PRS does not use CLASS)
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

<div x-data="{ prsTab: 'leaderboard' }" class="min-w-0">
    <div class="mb-4 flex min-w-0 gap-1.5">
        <button type="button" @click="prsTab = 'leaderboard'" :class="prsTab === 'leaderboard' ? 'bg-red-600 text-white' : 'bg-zinc-800 text-zinc-400 hover:bg-zinc-700'" class="min-w-0 flex-1 rounded-lg px-2 py-2 text-[11px] font-bold transition-colors sm:px-3 sm:text-xs">Scoreboard</button>
        <button type="button" @click="prsTab = 'scoresheet'" :class="prsTab === 'scoresheet' ? 'bg-red-600 text-white' : 'bg-zinc-800 text-zinc-400 hover:bg-zinc-700'" class="min-w-0 flex-1 rounded-lg px-2 py-2 text-[11px] font-bold transition-colors sm:px-3 sm:text-xs">Score Sheet</button>
        <button type="button" @click="prsTab = 'badges'" :class="prsTab === 'badges' ? 'bg-amber-600 text-white' : 'bg-zinc-800 text-zinc-400 hover:bg-zinc-700'" class="min-w-0 flex-1 rounded-lg px-2 py-2 text-[11px] font-bold transition-colors sm:px-3 sm:text-xs">Badges Awarded</button>
        @if($isTeamEvent)
            <button type="button" @click="prsTab = 'teams'" :class="prsTab === 'teams' ? 'bg-indigo-600 text-white' : 'bg-zinc-800 text-zinc-400 hover:bg-zinc-700'" class="min-w-0 flex-1 rounded-lg px-2 py-2 text-[11px] font-bold transition-colors sm:px-3 sm:text-xs">Teams</button>
        @endif
    </div>

    {{-- ============================================================
         PRS Leaderboard sub-tab
         ============================================================ --}}
    <div x-show="prsTab === 'leaderboard'">
        <div class="overflow-x-auto rounded-2xl border border-border bg-app [-webkit-overflow-scrolling:touch]">
            <table class="w-full min-w-[42rem] text-left lg:min-w-0">
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
                        <th class="px-2 py-2 text-center text-xs font-bold text-amber-400/60 sm:px-6 sm:py-4 sm:text-lg lg:text-xl">N/T</th>
                        <th class="px-2 py-2 text-right text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">TB Time</th>
                        <th class="px-2 py-2 text-right text-xs font-bold text-amber-400 sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Pts</th>
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
                        @endphp
                        <tr class="{{ $rowClass }} transition-colors">
                            <td class="px-2 py-2 sm:px-6 sm:py-4" @if($isNoShowRow) title="Did not attend" @endif>
                                @if($showPodiumCrest)
                                    <div class="flex items-center gap-2 sm:gap-3">
                                        <span class="inline-flex shrink-0 scale-[0.72] sm:scale-90 lg:scale-100" title="{{ $podiumLabel }}">
                                            <x-badge-crest :icon="$podiumIcon" tier="earned" family="prs" />
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
                                    <x-shooter-badges :userId="$shooter->user_id" :matchId="$match->id" competitionType="prs" :compact="true" />
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
                            <td class="px-2 py-2 text-center text-base font-bold text-amber-400/60 sm:px-6 sm:py-4 sm:text-xl lg:text-2xl">{{ $shooter->not_taken ?? 0 }}</td>
                            <td class="whitespace-nowrap px-2 py-2 text-right text-xs font-mono text-secondary sm:px-6 sm:py-4 sm:text-xl lg:text-2xl">
                                @if($shooter->tb_time > 0)
                                    {{ number_format($shooter->tb_time, 1) }}s
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-2 py-2 text-right text-lg font-black text-amber-400 sm:px-6 sm:py-4 sm:text-2xl lg:text-3xl">
                                {{ number_format($shooter->prs_points ?? 0, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 8 + ($divisions->isNotEmpty() ? 1 : 0) }}" class="px-4 py-12 text-center text-sm text-muted sm:px-6 sm:py-16 sm:text-2xl">
                                No scores recorded yet
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ============================================================
         PRS Score Sheet sub-tab — per-shot grid.
         ============================================================ --}}
    <div x-show="prsTab === 'scoresheet'" x-cloak class="min-w-0">
        <div class="overflow-x-auto rounded-2xl border border-zinc-700 bg-zinc-800/50 [-webkit-overflow-scrolling:touch]">
            <table class="w-full text-[11px] leading-tight">
                <thead>
                    <tr class="border-b border-zinc-700">
                        <th class="relative z-0 w-10 min-w-10 bg-zinc-800 px-2 py-2 text-left text-zinc-500 sm:sticky sm:left-0 sm:z-20 sm:shadow-[4px_0_12px_-6px_rgba(0,0,0,0.65)]">#</th>
                        <th class="relative z-0 min-w-[7rem] bg-zinc-800 px-2 py-2 text-left text-zinc-500 sm:sticky sm:left-10 sm:z-20 sm:shadow-[4px_0_12px_-6px_rgba(0,0,0,0.65)]">Shooter</th>
                        @foreach($prsTargetSets as $ts)
                            <th colspan="{{ $ts->gongs_count }}" class="px-1 py-2 text-center border-l-2 border-zinc-500 {{ $ts->is_tiebreaker ? 'text-amber-400 bg-amber-900/10' : 'text-zinc-400' }}">
                                {{ $ts->label }}
                            </th>
                        @endforeach
                        <th class="px-2 py-2 text-center font-bold text-zinc-400 border-l-2 border-zinc-500">Total</th>
                        <th class="px-2 py-2 text-center text-zinc-500">Time</th>
                    </tr>
                    <tr class="border-b border-zinc-700 text-[9px] text-zinc-600">
                        <th class="relative z-0 bg-zinc-800 sm:sticky sm:left-0 sm:z-20 sm:shadow-[4px_0_12px_-6px_rgba(0,0,0,0.65)]"></th>
                        <th class="relative z-0 bg-zinc-800 sm:sticky sm:left-10 sm:z-20 sm:shadow-[4px_0_12px_-6px_rgba(0,0,0,0.65)]"></th>
                        @foreach($prsTargetSets as $ts)
                            @for($g = 1; $g <= $ts->gongs_count; $g++)
                                <th class="px-0.5 py-1 text-center {{ $g === 1 ? 'border-l-2 border-zinc-500' : '' }}">{{ $g }}</th>
                            @endfor
                        @endforeach
                        <th class="border-l-2 border-zinc-500"></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-700/30">
                    @foreach($shooters as $shooter)
                        <tr class="hover:bg-zinc-700/20">
                            <td class="relative z-0 bg-zinc-800 px-2 py-1.5 text-center text-zinc-500 sm:sticky sm:left-0 sm:z-20 sm:shadow-[4px_0_12px_-6px_rgba(0,0,0,0.65)]">{{ $loop->iteration }}</td>
                            <td class="relative z-0 bg-zinc-800 px-2 py-1.5 sm:sticky sm:left-10 sm:z-20 sm:shadow-[4px_0_12px_-6px_rgba(0,0,0,0.65)]">
                                <p class="max-w-[6.5rem] truncate font-medium text-white sm:max-w-[100px]" title="{{ $shooter->name }}">{{ $shooter->name }}</p>
                            </td>
                            @foreach($prsTargetSets as $ts)
                                @for($g = 1; $g <= $ts->gongs_count; $g++)
                                    @php
                                        $shotResult = $shooter->shot_grid[$ts->id][$g] ?? null;
                                    @endphp
                                    <td class="px-0 py-1.5 text-center {{ $g === 1 ? 'border-l-2 border-zinc-500' : '' }}">
                                        @if($shotResult === 'hit')
                                            <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-green-500 text-[10px] font-bold leading-none text-white" title="Hit">&check;</span>
                                        @elseif($shotResult === 'miss')
                                            <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold leading-none text-white" title="Miss">&#10007;</span>
                                        @elseif($shotResult === 'not_taken')
                                            <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-amber-500/30 text-[11px] font-bold leading-none text-amber-300" title="Not taken">&ndash;</span>
                                        @else
                                            <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-zinc-700 text-[11px] font-bold leading-none text-zinc-500" title="Not scored">&ndash;</span>
                                        @endif
                                    </td>
                                @endfor
                            @endforeach
                            <td class="px-2 py-1.5 text-center font-bold text-white tabular-nums border-l-2 border-zinc-500">{{ $shooter->hits_count }}</td>
                            <td class="px-2 py-1.5 text-center tabular-nums text-zinc-500">{{ $shooter->tb_time > 0 ? number_format($shooter->tb_time, 1) . 's' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ============================================================
         PRS Badges Awarded sub-tab
         ============================================================ --}}
    <div x-show="prsTab === 'badges'" x-cloak class="min-w-0">
        @php
            $badgesByCategory = $matchBadges->groupBy(fn ($ua) => $ua->achievement?->category ?? 'unknown');
            $matchSpecials = $badgesByCategory->get('match_special', collect());
            $lifetimeBadges = $badgesByCategory->get('lifetime', collect());
            $repeatableBadges = $badgesByCategory->get('repeatable', collect());
            $hasBadges = $matchBadges->isNotEmpty();
            $bcfg = \App\Http\Controllers\BadgeGalleryController::BADGE_CONFIG;
            $prsAwardedSlugs = $matchBadges->pluck('achievement.slug')->filter()->unique()->values()->all();
        @endphp

        @if(!$hasBadges)
            <div class="flex flex-col items-center justify-center rounded-2xl border border-zinc-700 bg-zinc-800/30 px-6 py-16 text-center">
                <x-badge-icon name="award" class="mx-auto mb-3 h-10 w-10 text-zinc-600 opacity-40" />
                <h3 class="text-lg font-bold text-zinc-300">No badges awarded yet</h3>
                <p class="mt-2 max-w-sm text-sm text-zinc-500">Badges earned during this PRS match will appear here once scoring has been finalized.</p>
            </div>

            <x-badge-criteria-reference competitionType="prs" :awardedSlugs="$prsAwardedSlugs" />
        @else
            <div class="space-y-6">

                @php $deadCenter = $matchSpecials->first(fn ($ua) => $ua->achievement?->slug === 'deadcenter'); @endphp
                @if($deadCenter)
                    <section>
                        <div class="mb-3 flex items-center gap-2">
                            <x-badge-icon name="sparkles" class="h-3.5 w-3.5 text-sky-400" />
                            <span class="text-xs font-bold uppercase tracking-wider text-sky-400">Signature Badge</span>
                        </div>
                        <div class="overflow-hidden rounded-2xl border-2 border-sky-500/40 bg-gradient-to-br from-sky-900/20 via-zinc-900 to-zinc-900">
                            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:gap-5 sm:p-6">
                                <x-badge-crest icon="deadcenter" tier="featured" family="prs" />
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-xl font-semibold text-sky-300 sm:text-2xl">DeadCenter</h3>
                                    <p class="mt-1 text-sm text-zinc-300">{{ $deadCenter->achievement->description }}</p>
                                    @php $dcCriteria = \App\Http\Controllers\BadgeGalleryController::criteriaFor('deadcenter'); @endphp
                                    @if($dcCriteria)
                                        <div class="mt-2 rounded-lg border border-sky-400/15 bg-sky-900/10 px-3 py-2">
                                            <span class="block text-[10px] font-bold uppercase tracking-wider text-sky-400/70">How to earn it</span>
                                            <p class="mt-0.5 text-xs leading-snug text-zinc-300">{{ $dcCriteria }}</p>
                                        </div>
                                    @endif
                                    <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                                        @if($deadCenter->user_id)
                                            <a href="{{ route('shooter.profile', $deadCenter->user_id) }}" class="font-bold text-white hover:underline">{{ $deadCenter->shooter?->name ?? $deadCenter->user?->name ?? 'Unknown' }}</a>
                                        @else
                                            <span class="font-bold text-white">{{ $deadCenter->shooter?->name ?? 'Unknown' }}</span>
                                        @endif
                                        @if($deadCenter->stage)
                                            <span class="text-zinc-500">&bull;</span>
                                            <span class="text-zinc-400">{{ $deadCenter->stage->label ?? 'Stage ' . $deadCenter->stage->stage_number }}</span>
                                        @endif
                                        @if(isset($deadCenter->metadata['time']))
                                            <span class="text-zinc-500">&bull;</span>
                                            <span class="tabular-nums text-sky-400">{{ number_format($deadCenter->metadata['time'], 2) }}s</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                @else
                    <section>
                        <div class="mb-3 flex items-center gap-2">
                            <x-badge-icon name="sparkles" class="h-3.5 w-3.5 text-sky-400" />
                            <span class="text-xs font-bold uppercase tracking-wider text-sky-400">Signature Badge</span>
                        </div>
                        <div class="rounded-2xl border border-zinc-700/50 bg-zinc-800/20 px-5 py-8 text-center">
                            <x-badge-icon name="deadcenter" class="mx-auto mb-2 h-8 w-8 text-zinc-600 opacity-30" />
                            <p class="text-sm font-semibold text-zinc-400">No DeadCenter was awarded for this match.</p>
                            <p class="mt-1 text-xs text-zinc-600">Awarded for the fastest clean run on the tiebreaker stage.</p>
                        </div>
                    </section>
                @endif

                @if($lifetimeBadges->isNotEmpty())
                    <section>
                        <div class="mb-3 flex items-center gap-2">
                            <x-badge-icon name="award" class="h-3.5 w-3.5 text-sky-400/70" />
                            <span class="text-xs font-bold uppercase tracking-wider text-sky-400/70">Lifetime Achievements</span>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach($lifetimeBadges->sortBy(fn ($ua) => $ua->achievement?->sort_order ?? 99) as $ua)
                                @php
                                    $badge = $ua->achievement;
                                    $cfg = $bcfg[$badge->slug] ?? [];
                                    $icon = $cfg['icon'] ?? 'target';
                                    $tier = $cfg['tier'] ?? 'earned';
                                @endphp
                                <div class="flex items-start gap-4 rounded-2xl border border-sky-400/15 bg-sky-900/8 px-4 py-4">
                                    <x-badge-crest :icon="$icon" :tier="$tier" family="prs" />
                                    <div class="min-w-0 flex-1">
                                        <span class="text-base font-bold text-sky-200">{{ $badge->label }}</span>
                                        <p class="mt-0.5 text-xs text-zinc-400 leading-snug">{{ $badge->description }}</p>
                                        @php $plbCriteria = \App\Http\Controllers\BadgeGalleryController::criteriaFor($badge->slug); @endphp
                                        @if($plbCriteria && $plbCriteria !== $badge->description)
                                            <p class="mt-1 text-[11px] leading-snug text-sky-200/70"><span class="font-semibold uppercase tracking-wider text-sky-400/70 text-[9px]">How to earn &middot;</span> {{ $plbCriteria }}</p>
                                        @endif
                                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                            @if($ua->user_id)
                                                <a href="{{ route('shooter.profile', $ua->user_id) }}" class="font-semibold text-white hover:underline">{{ $ua->shooter?->name ?? $ua->user?->name ?? 'Unknown' }}</a>
                                            @else
                                                <span class="font-semibold text-white">{{ $ua->shooter?->name ?? 'Unknown' }}</span>
                                            @endif
                                            @if($ua->stage)
                                                <span class="text-zinc-600">&bull;</span>
                                                <span class="text-zinc-500">{{ $ua->stage->label ?? 'Stage ' . $ua->stage->stage_number }}</span>
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
                            <x-badge-icon name="layers" class="h-3.5 w-3.5 text-sky-500/60" />
                            <span class="text-xs font-bold uppercase tracking-wider text-sky-500/60">Stackable Badges</span>
                        </div>
                        @php
                            $grouped = $repeatableBadges->groupBy(fn ($ua) => $ua->achievement?->slug ?? 'unknown');
                        @endphp
                        <div class="space-y-4">
                            @foreach($grouped as $slug => $entries)
                                @php
                                    $badge = $entries->first()?->achievement;
                                    if (!$badge) continue;
                                    $cfg = $bcfg[$badge->slug] ?? [];
                                    $icon = $cfg['icon'] ?? 'target';
                                    $tier = $cfg['tier'] ?? 'earned';
                                @endphp
                                <div class="overflow-hidden rounded-2xl border border-sky-500/12 bg-sky-900/5">
                                    <div class="flex items-start gap-4 border-b border-sky-500/10 px-4 py-3">
                                        <x-badge-crest :icon="$icon" :tier="$tier" family="prs" />
                                        <div class="min-w-0 flex-1">
                                            <span class="text-base font-bold text-sky-200">{{ $badge->label }}</span>
                                            <p class="mt-0.5 text-xs text-zinc-500 leading-snug">{{ $badge->description }}</p>
                                            @php $prbCriteria = \App\Http\Controllers\BadgeGalleryController::criteriaFor($badge->slug); @endphp
                                            @if($prbCriteria && $prbCriteria !== $badge->description)
                                                <p class="mt-1 text-[11px] leading-snug text-sky-200/70"><span class="font-semibold uppercase tracking-wider text-sky-400/70 text-[9px]">How to earn &middot;</span> {{ $prbCriteria }}</p>
                                            @endif
                                        </div>
                                        <span class="flex-shrink-0 rounded-full bg-sky-600/20 px-2.5 py-1 text-xs font-bold tabular-nums text-sky-400">{{ $entries->count() }}&times;</span>
                                    </div>
                                    <div class="divide-y divide-zinc-800">
                                        @foreach($entries->sortBy(fn ($ua) => $ua->shooter?->name ?? $ua->user?->name ?? '') as $ua)
                                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 text-sm">
                                                @if($ua->user_id)
                                                    <a href="{{ route('shooter.profile', $ua->user_id) }}" class="font-medium text-white hover:underline">{{ $ua->shooter?->name ?? $ua->user?->name ?? 'Unknown' }}</a>
                                                @else
                                                    <span class="font-medium text-white">{{ $ua->shooter?->name ?? 'Unknown' }}</span>
                                                @endif
                                                @if($ua->stage)
                                                    <span class="text-xs text-zinc-500">{{ $ua->stage->label ?? 'Stage ' . $ua->stage->stage_number }}</span>
                                                @elseif($badge->scope === 'match')
                                                    <span class="text-xs text-zinc-600">Match Achievement</span>
                                                @endif
                                                @if($ua->metadata)
                                                    @if(isset($ua->metadata['streak']))
                                                        <span class="text-xs tabular-nums text-zinc-500">{{ $ua->metadata['streak'] }} consecutive hits</span>
                                                    @endif
                                                    @if(isset($ua->metadata['rank']))
                                                        <span class="text-xs text-zinc-500">#{{ $ua->metadata['rank'] }} overall</span>
                                                    @endif
                                                    @if(isset($ua->metadata['time']))
                                                        <span class="text-xs tabular-nums text-zinc-500">{{ number_format($ua->metadata['time'], 2) }}s</span>
                                                    @endif
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <x-badge-criteria-reference competitionType="prs" :awardedSlugs="$prsAwardedSlugs" />

            </div>
        @endif
    </div>

    {{-- ============================================================
         PRS Teams sub-tab (team events only)
         ============================================================ --}}
    @if($isTeamEvent)
        <div x-show="prsTab === 'teams'" x-cloak class="min-w-0">
            <div class="space-y-4">
                @forelse($teamLeaderboard as $index => $entry)
                    <div class="rounded-2xl border border-zinc-700 bg-zinc-800/50 overflow-hidden" x-data="{ tOpen: false }">
                        <button @click="tOpen = !tOpen" class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-zinc-700/50 transition-colors sm:px-6 sm:py-4">
                            <div class="flex items-center gap-3 min-w-0">
                                @php $medal = match($index) { 0 => 'text-amber-400', 1 => 'text-zinc-300', 2 => 'text-amber-700', default => 'text-zinc-500' }; @endphp
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-700 text-sm font-black {{ $medal }} sm:h-10 sm:w-10 sm:text-base">{{ $index + 1 }}</span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-white sm:text-base">{{ $entry->team->name }}</p>
                                    <p class="text-xs text-zinc-500">{{ $entry->member_count }} {{ Str::plural('member', $entry->member_count) }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-lg font-black text-amber-400 tabular-nums sm:text-2xl">{{ $entry->total_score }}</span>
                                <x-icon name="chevron-down" x-bind:class="tOpen && 'rotate-180'" class="h-5 w-5 text-zinc-500 transition-transform" />
                            </div>
                        </button>
                        <div x-show="tOpen" x-collapse>
                            <div class="border-t border-zinc-700 px-4 py-3 sm:px-6">
                                <table class="w-full text-sm">
                                    <thead><tr class="text-left text-zinc-500"><th class="pb-1 font-medium">Shooter</th><th class="pb-1 font-medium text-right">Score</th></tr></thead>
                                    <tbody class="divide-y divide-zinc-700/50">
                                        @foreach($entry->members as $member)
                                            <tr><td class="py-1.5 text-zinc-300">{{ $member->name }}</td><td class="py-1.5 text-right font-bold tabular-nums text-white">{{ $member->score }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-zinc-700 bg-zinc-800/30 p-8 text-center">
                        <p class="text-zinc-500">No teams set up for this event.</p>
                    </div>
                @endforelse
            </div>
        </div>
    @endif
</div>
