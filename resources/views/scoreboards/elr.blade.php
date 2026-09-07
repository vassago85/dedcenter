{{--
    ELR scoreboard.

    Public scoreboard body for `scoring_type = elr`. Included by
    resources/views/pages/scoreboard.blade.php via $scoreboardVariant.

    Uses the same column language as the ELR view in the scoring app
    (Points / Hits / 1st Rd / Furthest / Norm %) so a spectator and a
    match director see the same numbers on both screens. A shooter row
    expands to the per-stage / per-target ELR breakdown carried on
    `$shooter->elr_stages`.

    Filters mirror the shared bar (DIV + CAT). ELR doesn't use CLASS.

    All shared chrome (header, publish gate, claim banner, footer) is
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

{{-- Optional Teams tab for ELR team gong-sequence matches. Uses the
     shared team-leaderboard partial so ELR points totals stay in
     lockstep with what the ELR service returns. --}}
@if($isTeamEvent)
    <div class="mb-4 flex min-w-0 flex-wrap gap-2">
        <button type="button" wire:click="setTab('main')"
                class="min-w-0 flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors sm:flex-none sm:px-5 sm:py-2.5 sm:text-sm {{ $activeTab !== 'teams' ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
            Scoreboard
        </button>
        <button type="button" wire:click="setTab('teams')"
                class="min-w-0 flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors sm:flex-none sm:px-5 sm:py-2.5 sm:text-sm {{ $activeTab === 'teams' ? 'bg-indigo-600 text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
            Teams
        </button>
    </div>
@endif

@if($isTeamEvent && $activeTab === 'teams')
    <div class="space-y-3">
        @forelse($teamLeaderboard as $index => $entry)
            @include('partials.team-leaderboard-row', ['entry' => $entry, 'index' => $index, 'isPrs' => false])
        @empty
            <div class="rounded-2xl border border-dashed border-border bg-surface/50 p-8 text-center">
                <p class="text-muted">No teams set up for this event.</p>
            </div>
        @endforelse
    </div>
@else

{{-- ============================================================
     ELR standings table — Points, Hits, 1st Rd, Furthest, Norm %
     ============================================================ --}}
<div class="overflow-x-auto rounded-2xl border border-border bg-app [-webkit-overflow-scrolling:touch]">
    <table class="w-full min-w-[40rem] text-left lg:min-w-0">
        <thead>
            <tr class="border-b border-border bg-surface/80">
                <th class="px-2 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">#</th>
                <th class="px-2 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Shooter</th>
                <th class="px-2 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Squad</th>
                @if($divisions->isNotEmpty())
                    <th class="px-2 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Div</th>
                @endif
                <th class="px-2 py-2 text-right text-xs font-bold text-amber-400 sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Points</th>
                <th class="px-2 py-2 text-center text-xs font-bold text-green-400 sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Hits</th>
                <th class="px-2 py-2 text-center text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">1st&nbsp;Rd</th>
                <th class="px-2 py-2 text-center text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Furthest&nbsp;(m)</th>
                <th class="px-2 py-2 text-right text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Norm&nbsp;%</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-border">
            @forelse($shooters as $shooter)
                @php
                    $rank = $shooter->display_rank ?? null;
                    $shooterStatus = $shooter->status ?? 'active';
                    $isDqRow = $shooterStatus === 'dq';
                    $isNoShowRow = $shooterStatus === 'no_show';
                    $isOfflineRow = $isDqRow || $isNoShowRow;
                    $isExpanded = $expandedShooterId === $shooter->id;
                    $hasElrStages = ! empty($shooter->elr_stages);

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
                    $shooterUnclaimed = $shooter->isUnclaimedResult();
                    $shooterIsRealUser = ! $shooterUnclaimed && $shooter->user_id;
                    $canClaimRow = $shooterUnclaimed
                        && ! $isOfflineRow
                        && ($match->status === \App\Enums\MatchStatus::Completed || $match->status === \App\Enums\MatchStatus::Active);
                @endphp
                <tr class="{{ $rowClass }} transition-colors {{ $hasElrStages ? 'cursor-pointer hover:bg-surface/50' : '' }}"
                    @if($hasElrStages) wire:click="toggleExpand({{ $shooter->id }})" @endif>
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
                    <td class="max-w-[8rem] px-2 py-2 text-sm font-semibold sm:max-w-[14rem] sm:px-6 sm:py-4 sm:text-xl lg:max-w-none lg:text-2xl">
                        @if($shooterIsRealUser)
                            <a href="{{ route('shooter.profile', $shooter->user_id) }}" wire:click.stop
                               class="line-clamp-2 text-primary hover:underline sm:line-clamp-none" title="{{ $shooter->name }}">{{ $shooter->name }}</a>
                        @else
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="line-clamp-2 text-primary sm:line-clamp-none" title="{{ $shooter->name }}">{{ $shooter->name }}</span>
                                @if($canClaimRow)
                                    <a href="{{ app_url('/claim?match=' . $match->id . '&shooter=' . $shooter->id) }}"
                                       wire:click.stop
                                       class="inline-flex items-center gap-1 rounded-full border border-accent/40 bg-accent/10 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-accent transition-colors hover:border-accent hover:bg-accent/20 hover:text-white sm:text-[10px]"
                                       title="Link this result to your DeadCenter account (admin approval required)">
                                        <span class="h-1 w-1 rounded-full bg-accent"></span>
                                        Claim
                                    </a>
                                @endif
                            </div>
                        @endif
                        @if(isset($customFieldMap[$shooter->id]))
                            <div class="mt-0.5 flex flex-wrap gap-1">
                                @foreach($customFieldMap[$shooter->id] as $cfv)
                                    <span class="rounded bg-surface-2 px-1.5 py-0.5 text-[10px] text-muted" title="{{ $cfv['label'] }}">{{ $cfv['value'] }}</span>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="max-w-[6rem] truncate px-2 py-2 text-xs text-muted sm:max-w-none sm:px-6 sm:py-4 sm:text-lg lg:text-xl" title="{{ $shooter->squad?->name ?? '—' }}">{{ $shooter->squad?->name ?? '—' }}</td>
                    @if($divisions->isNotEmpty())
                        <td class="max-w-[4rem] truncate px-2 py-2 text-xs text-muted sm:max-w-none sm:px-6 sm:py-4 sm:text-lg lg:text-xl" title="{{ $shooter->division?->name ?? '—' }}">{{ $shooter->division?->name ?? '—' }}</td>
                    @endif
                    <td class="whitespace-nowrap px-2 py-2 text-right text-lg font-black text-amber-400 tabular-nums sm:px-6 sm:py-4 sm:text-2xl lg:text-3xl">{{ number_format((float) $shooter->display_score, 2) }}</td>
                    <td class="px-2 py-2 text-center text-base font-bold text-green-400 sm:px-6 sm:py-4 sm:text-xl lg:text-2xl">{{ $shooter->hits_count }}</td>
                    <td class="px-2 py-2 text-center text-base font-medium text-secondary tabular-nums sm:px-6 sm:py-4 sm:text-xl">{{ (int) ($shooter->first_round_hits ?? 0) }}</td>
                    <td class="px-2 py-2 text-center text-base font-medium text-secondary tabular-nums sm:px-6 sm:py-4 sm:text-xl">
                        @php $furthest = (int) ($shooter->furthest_hit_m ?? 0); @endphp
                        {{ $furthest > 0 ? $furthest : '—' }}
                    </td>
                    <td class="whitespace-nowrap px-2 py-2 text-right text-base font-bold text-secondary tabular-nums sm:px-6 sm:py-4 sm:text-xl">
                        @php $norm = $shooter->normalized_score; @endphp
                        {{ $norm !== null ? number_format((float) $norm, 1) . '%' : '—' }}
                    </td>
                </tr>
                @if($hasElrStages && $isExpanded)
                    <tr>
                        <td colspan="{{ 8 + ($divisions->isNotEmpty() ? 1 : 0) }}" class="bg-surface/40 px-3 py-3 sm:px-6 sm:py-4">
                            <div class="space-y-3">
                                @foreach($shooter->elr_stages as $stage)
                                    <div class="rounded-xl border border-border bg-app/60">
                                        <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-border/60 px-3 py-2 sm:px-4">
                                            <span class="text-sm font-bold text-primary">{{ $stage['label'] ?? ('Stage ' . ($stage['stage_number'] ?? '?')) }}</span>
                                            <span class="text-xs text-muted">
                                                <span class="font-semibold text-emerald-300 tabular-nums">{{ number_format((float) ($stage['stage_points'] ?? 0), 2) }}</span>
                                                <span class="ml-1">pts</span>
                                                &bull;
                                                <span class="text-green-400 tabular-nums">{{ (int) ($stage['stage_hits'] ?? 0) }}</span>
                                                <span class="ml-1">hits</span>
                                            </span>
                                        </div>
                                        <div class="divide-y divide-border/50">
                                            @foreach($stage['targets'] ?? [] as $target)
                                                <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-xs sm:px-4 sm:text-sm">
                                                    <div class="flex items-baseline gap-2">
                                                        <span class="font-semibold text-secondary">{{ $target['name'] ?? '—' }}</span>
                                                        @if(! empty($target['distance_m']))
                                                            <span class="text-muted">{{ (int) $target['distance_m'] }}m</span>
                                                        @endif
                                                    </div>
                                                    <div class="flex flex-wrap items-center gap-1.5">
                                                        @foreach($target['shots'] ?? [] as $shot)
                                                            @php
                                                                $result = $shot['result'] ?? 'not_taken';
                                                                $isHit = $result === 'hit';
                                                                $isMiss = $result === 'miss';
                                                            @endphp
                                                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold leading-none
                                                                {{ $isHit ? 'bg-green-500 text-white' : ($isMiss ? 'bg-red-500 text-white' : 'bg-zinc-700 text-zinc-500') }}"
                                                                title="Shot {{ $shot['shot_number'] ?? '' }} — {{ ucfirst((string) $result) }}">
                                                                {{ $isHit ? '✓' : ($isMiss ? '✗' : '–') }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @endif
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

@endif
