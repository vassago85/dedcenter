{{--
    ALRHA scoreboard.

    Public scoreboard body for `scoring_type = alrha`. Included by
    resources/views/pages/scoreboard.blade.php via $scoreboardVariant.

    ALRHA is prize-table driven, not gong-count driven, and it can run two
    parallel competitions on the same day (LR Hunters + LR Varmint), so
    the layout is:

      - CLASS chips (only when the match is dual-class)
      - DIV chips (rare on ALRHA, kept for parity with the shared filter bar)
      - CAT chips (scoped to the selected class's categories — Hunters has
        no Ladies)
      - Tab bar: Standings · Cold Bore · Teams (Hunters) · Categories
      - Standings tab: one ALRHA table per class block. When a class filter
        is active, only that block renders. Columns are the sport's real
        prize columns — Points / Hits / 1st Rd / Furthest (m) — not a
        gong-based Score total (which is always 0 for ALRHA).
      - CBC / Teams / Categories tabs are rendered straight off
        `AlrhaScoringService::calculateStandings()` per_class blocks so the
        prize tables here match the exports and PDF.

    All shared chrome (page header, publish gate, claim banner, footer)
    lives in the parent dispatcher.
--}}

@php
    $alrhaTab = in_array($activeTab, ['cbc', 'teams', 'categories', 'prize-book'], true) ? $activeTab : 'standings';
    $perClass = $alrhaData['per_class'] ?? [];
    $isDualClass = (bool) ($alrhaData['match']['is_dual_class'] ?? false);

    // Class blocks visible for the active tab. When a class filter is
    // active we only render that block; when it isn't (and the match is
    // dual-class) we render both stacked with a class heading above each.
    $visibleClassBlocks = collect($perClass)
        ->filter(fn ($block, $classValue) => ! $activeAlrhaClass || $activeAlrhaClass === $classValue)
        ->values();

    // Do we have any Hunters block? Hunters is the only class with team
    // scoring, so the Teams tab hides when there are none in scope.
    $hasHuntersBlock = collect($perClass)
        ->contains(fn ($block, $classValue) => $classValue === \App\Enums\AlrhaClass::Hunters->value
            && (! $activeAlrhaClass || $activeAlrhaClass === $classValue));
@endphp

{{-- ============================================================
     Filter bar — CLASS (dual only) + DIV + scoped CAT
     ============================================================ --}}
@if(count($alrhaClasses) > 1 || $divisions->isNotEmpty() || $categories->isNotEmpty())
    <div class="mb-4 min-w-0 space-y-2">
        @if(count($alrhaClasses) > 1)
            <div class="-mx-1 flex flex-nowrap gap-2 overflow-x-auto px-1 pb-1 sm:mx-0 sm:px-0 [scrollbar-width:thin]">
                <span class="shrink-0 self-center pr-1 text-xs text-muted/60">CLASS</span>
                <button type="button" wire:click="filterAlrhaClass(null)"
                        class="shrink-0 rounded-lg px-3 py-2 text-xs font-medium transition-colors sm:px-4 sm:text-sm {{ !$activeAlrhaClass ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
                    All
                </button>
                @foreach($alrhaClasses as $alrhaClass)
                    <button type="button" wire:click="filterAlrhaClass('{{ $alrhaClass->value }}')"
                            class="shrink-0 rounded-lg px-3 py-2 text-xs font-medium transition-colors sm:px-4 sm:text-sm {{ $activeAlrhaClass === $alrhaClass->value ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
                        {{ $alrhaClass->label() }}
                    </button>
                @endforeach
            </div>
        @endif

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
     Tab bar — Standings · Cold Bore · Teams · Categories
     ============================================================ --}}
<div class="mb-4 flex min-w-0 flex-wrap gap-2">
    <button type="button" wire:click="setTab('standings')"
            class="min-w-0 flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors sm:flex-none sm:px-5 sm:py-2.5 sm:text-sm {{ $alrhaTab === 'standings' ? 'bg-emerald-700 text-white' : 'bg-surface text-muted hover:bg-surface-2' }}">
        Standings
    </button>
    <button type="button" wire:click="setTab('cbc')"
            class="min-w-0 flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors sm:flex-none sm:px-5 sm:py-2.5 sm:text-sm {{ $alrhaTab === 'cbc' ? 'bg-emerald-700 text-white' : 'bg-surface text-muted hover:bg-surface-2' }}">
        Cold Bore Challenge
    </button>
    @if($hasHuntersBlock)
        <button type="button" wire:click="setTab('teams')"
                class="min-w-0 flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors sm:flex-none sm:px-5 sm:py-2.5 sm:text-sm {{ $alrhaTab === 'teams' ? 'bg-indigo-600 text-white' : 'bg-surface text-muted hover:bg-surface-2' }}">
            Teams
        </button>
    @endif
    <button type="button" wire:click="setTab('categories')"
            class="min-w-0 flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors sm:flex-none sm:px-5 sm:py-2.5 sm:text-sm {{ $alrhaTab === 'categories' ? 'bg-emerald-700 text-white' : 'bg-surface text-muted hover:bg-surface-2' }}">
        Categories
    </button>
    <button type="button" wire:click="setTab('prize-book')"
            class="min-w-0 flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors sm:flex-none sm:px-5 sm:py-2.5 sm:text-sm {{ $alrhaTab === 'prize-book' ? 'bg-amber-600 text-white' : 'bg-surface text-muted hover:bg-surface-2' }}">
        Prize Book
    </button>
</div>

{{-- ============================================================
     Standings tab — the ALRHA overall prize table per class.
     ============================================================ --}}
@if($alrhaTab === 'standings')
    <div class="space-y-6">
        @forelse($visibleClassBlocks as $block)
            @php
                $standings = $block['standings'] ?? [];
                $classLabel = $block['class_label'] ?? '';
            @endphp
            @if($isDualClass && ! $activeAlrhaClass)
                <div class="flex items-baseline gap-2">
                    <h2 class="text-lg font-black tracking-tight text-primary sm:text-xl">{{ $classLabel }}</h2>
                    <span class="text-xs uppercase tracking-wider text-muted">Class standings</span>
                </div>
            @endif

            <div class="overflow-x-auto rounded-2xl border border-border bg-app [-webkit-overflow-scrolling:touch]">
                <table class="w-full min-w-[38rem] text-left">
                    <thead>
                        <tr class="border-b border-border bg-surface/80">
                            <th class="px-2 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">#</th>
                            <th class="px-2 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Shooter</th>
                            <th class="px-2 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Squad</th>
                            <th class="px-2 py-2 text-right text-xs font-bold text-emerald-300 sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Points</th>
                            <th class="px-2 py-2 text-center text-xs font-bold text-green-400 sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Hits</th>
                            <th class="px-2 py-2 text-center text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">1st&nbsp;Rd</th>
                            <th class="px-2 py-2 text-right text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Furthest&nbsp;(m)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($standings as $row)
                            @php
                                $rank = (int) ($row['rank'] ?? 0);
                                $isCoached = ! empty($row['is_coached']);
                                $rowClass = match(true) {
                                    $rank === 1 => 'bg-amber-500/10 border-l-4 border-l-amber-400',
                                    $rank === 2 => 'bg-slate-400/5 border-l-4 border-l-slate-400',
                                    $rank === 3 => 'bg-orange-500/5 border-l-4 border-l-orange-600',
                                    default => 'border-l-4 border-l-transparent',
                                };
                                $rankClass = match(true) {
                                    $rank === 1 => 'text-amber-400 font-black',
                                    $rank === 2 => 'text-secondary font-bold',
                                    $rank === 3 => 'text-orange-500 font-bold',
                                    default => 'text-muted font-medium',
                                };
                                $matchComplete = $match->status === \App\Enums\MatchStatus::Completed;
                                $showPodiumCrest = $matchComplete && in_array($rank, [1, 2, 3], true) && ! $isCoached;
                                $podiumIcon = $rank === 1 ? 'medal-1' : ($rank === 2 ? 'medal-2' : 'medal-3');
                                $podiumLabel = $rank === 1 ? 'Podium Gold' : ($rank === 2 ? 'Podium Silver' : 'Podium Bronze');
                                $shooterUserId = $row['user_id'] ?? null;
                                $shooterId = (int) ($row['id'] ?? 0);
                                $hasStages = ! empty($row['stages']);
                                $isExpanded = $expandedShooterId === $shooterId;
                            @endphp
                            <tr class="{{ $rowClass }} transition-colors {{ $isCoached ? 'opacity-70' : '' }} {{ $hasStages ? 'cursor-pointer hover:bg-surface/50' : '' }}"
                                @if($hasStages) wire:click="toggleExpand({{ $shooterId }})" @endif>
                                <td class="px-2 py-2 sm:px-6 sm:py-4">
                                    @if($showPodiumCrest)
                                        <div class="flex items-center gap-2 sm:gap-3">
                                            <span class="inline-flex shrink-0 scale-[0.72] sm:scale-90 lg:scale-100" title="{{ $podiumLabel }}">
                                                <x-badge-crest :icon="$podiumIcon" tier="earned" family="prs" />
                                            </span>
                                            <span class="text-lg {{ $rankClass }} sm:text-2xl lg:text-3xl">{{ $rank }}</span>
                                        </div>
                                    @else
                                        <span class="text-lg {{ $rankClass }} sm:text-2xl lg:text-3xl">{{ $rank }}</span>
                                    @endif
                                </td>
                                <td class="max-w-[8rem] px-2 py-2 text-sm font-semibold sm:max-w-none sm:px-6 sm:py-4 sm:text-xl lg:text-2xl">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        @if($shooterUserId)
                                            <a href="{{ route('shooter.profile', $shooterUserId) }}" wire:click.stop class="line-clamp-2 text-primary hover:underline sm:line-clamp-none" title="{{ $row['name'] ?? '' }}">{{ $row['name'] ?? '' }}</a>
                                        @else
                                            <span class="line-clamp-2 text-primary sm:line-clamp-none" title="{{ $row['name'] ?? '' }}">{{ $row['name'] ?? '' }}</span>
                                        @endif
                                        @if(($row['cbc_hits'] ?? 0) > 0)
                                            {{-- Cold bore hit indicator: earned but not rolled into
                                                 the main prize total. Kept small so it doesn't
                                                 dominate the row. --}}
                                            <span class="inline-flex items-center gap-1 rounded-full border border-amber-400/50 bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-300"
                                                  title="Cold Bore Challenge — {{ (int) ($row['cbc_hits'] ?? 0) }} hit, {{ number_format((float) ($row['cbc_points'] ?? 0), 2) }} CBC pts">
                                                CBC ✓
                                            </span>
                                        @endif
                                        @if($hasStages)
                                            <span class="text-muted transition-transform {{ $isExpanded ? 'rotate-180' : '' }}" aria-hidden="true">▾</span>
                                        @endif
                                    </div>
                                    @if($isCoached)
                                        <span class="mt-0.5 block text-[10px] font-semibold uppercase tracking-wide text-amber-300/80">Coached — not eligible for prizes</span>
                                    @endif
                                </td>
                                <td class="max-w-[6rem] truncate px-2 py-2 text-xs text-muted sm:max-w-none sm:px-6 sm:py-4 sm:text-lg lg:text-xl" title="{{ $row['squad_name'] ?? '' }}">{{ $row['squad_name'] ?? '—' }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-right text-lg font-black text-emerald-300 tabular-nums sm:px-6 sm:py-4 sm:text-2xl lg:text-3xl">
                                    {{ number_format((float) ($row['total_points'] ?? 0), 2) }}
                                </td>
                                <td class="px-2 py-2 text-center text-base font-bold text-green-400 sm:px-6 sm:py-4 sm:text-xl lg:text-2xl">{{ (int) ($row['total_hits'] ?? 0) }}</td>
                                <td class="px-2 py-2 text-center text-base font-medium text-secondary tabular-nums sm:px-6 sm:py-4 sm:text-xl">{{ (int) ($row['first_round_hits'] ?? 0) }}</td>
                                <td class="px-2 py-2 text-right text-base font-medium text-secondary tabular-nums sm:px-6 sm:py-4 sm:text-xl">
                                    @php $furthest = (int) ($row['furthest_hit_m'] ?? 0); @endphp
                                    {{ $furthest > 0 ? $furthest : '—' }}
                                </td>
                            </tr>
                            @if($hasStages && $isExpanded)
                                <tr>
                                    <td colspan="7" class="bg-surface/40 px-3 py-3 sm:px-6 sm:py-4">
                                        @include('scoreboards.partials.alrha-shooter-breakdown', ['stages' => $row['stages']])
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-sm text-muted sm:px-6 sm:py-16 sm:text-2xl">
                                    No scores recorded yet
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @empty
            <div class="rounded-2xl border border-border bg-app px-6 py-16 text-center text-2xl text-muted">
                No scores recorded yet
            </div>
        @endforelse
    </div>
@endif

{{-- ============================================================
     Cold Bore Challenge — separate prize table per class.
     ============================================================ --}}
@if($alrhaTab === 'cbc')
    <div class="space-y-6">
        @forelse($visibleClassBlocks as $block)
            @php
                $cbcRows = $block['cbc'] ?? [];
                $classLabel = $block['class_label'] ?? '';
                $classEnum = \App\Enums\AlrhaClass::tryFrom($block['class'] ?? '');
                $cbcTargetName = $classEnum?->coldBoreTargetName() ?? 'Cold Bore Challenge';
            @endphp

            <section>
                <div class="mb-3 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <h2 class="text-lg font-black tracking-tight text-primary sm:text-xl">
                        {{ $isDualClass ? "$classLabel — " : '' }}Cold Bore Challenge
                    </h2>
                    <span class="text-xs uppercase tracking-wider text-muted">{{ $cbcTargetName }}</span>
                </div>

                <div class="overflow-x-auto rounded-2xl border border-emerald-700/40 bg-app [-webkit-overflow-scrolling:touch]">
                    <table class="w-full min-w-[32rem] text-left">
                        <thead>
                            <tr class="border-b border-border bg-surface/80">
                                <th class="px-2 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">#</th>
                                <th class="px-2 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Shooter</th>
                                <th class="px-2 py-2 text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Squad</th>
                                <th class="px-2 py-2 text-center text-xs font-bold text-green-400 sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Hits</th>
                                <th class="px-2 py-2 text-right text-xs font-bold text-emerald-300 sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Points</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($cbcRows as $row)
                                @php
                                    $rank = (int) ($row['rank'] ?? 0);
                                    $rowClass = match(true) {
                                        $rank === 1 => 'bg-amber-500/10 border-l-4 border-l-amber-400',
                                        $rank === 2 => 'bg-slate-400/5 border-l-4 border-l-slate-400',
                                        $rank === 3 => 'bg-orange-500/5 border-l-4 border-l-orange-600',
                                        default => 'border-l-4 border-l-transparent',
                                    };
                                @endphp
                                <tr class="{{ $rowClass }} transition-colors">
                                    <td class="px-2 py-2 text-base font-black text-muted tabular-nums sm:px-6 sm:py-4 sm:text-xl">{{ $rank }}</td>
                                    <td class="max-w-[8rem] truncate px-2 py-2 text-sm font-semibold text-primary sm:max-w-none sm:px-6 sm:py-4 sm:text-lg" title="{{ $row['name'] ?? '' }}">{{ $row['name'] ?? '' }}</td>
                                    <td class="max-w-[6rem] truncate px-2 py-2 text-xs text-muted sm:max-w-none sm:px-6 sm:py-4 sm:text-base">{{ $row['squad_name'] ?? '—' }}</td>
                                    <td class="px-2 py-2 text-center text-sm font-bold text-green-400 sm:px-6 sm:py-4 sm:text-lg">{{ (int) ($row['cbc_hits'] ?? 0) }}</td>
                                    <td class="px-2 py-2 text-right text-base font-black text-emerald-300 tabular-nums sm:px-6 sm:py-4 sm:text-xl">
                                        {{ number_format((float) ($row['cbc_points'] ?? 0), 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-sm text-muted sm:py-14 sm:text-xl">No Cold Bore hits recorded yet</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <div class="rounded-2xl border border-border bg-app px-6 py-16 text-center text-2xl text-muted">
                Cold Bore Challenge is not scored on this match.
            </div>
        @endforelse

        <p class="text-center text-xs text-muted/70">
            CBC hits are peeled off the main total — a strong CBC does not
            skew the overall standings.
        </p>
    </div>
@endif

{{-- ============================================================
     Teams tab — Hunters only, two-shooter teams.
     ============================================================ --}}
@if($alrhaTab === 'teams')
    @php
        $huntersBlock = collect($perClass)->firstWhere('class', \App\Enums\AlrhaClass::Hunters->value);
        $teamRows = $huntersBlock['teams'] ?? [];
        // Index Hunters standings by shooter id so we can expand a team
        // card into per-member stage breakdowns without going back to the
        // service.
        $huntersStandingsById = [];
        foreach ($huntersBlock['standings'] ?? [] as $stRow) {
            if (! empty($stRow['id'])) {
                $huntersStandingsById[(int) $stRow['id']] = $stRow;
            }
        }
    @endphp

    <section>
        <div class="mb-3 flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <h2 class="text-lg font-black tracking-tight text-primary sm:text-xl">LR Hunters — Teams</h2>
            <span class="text-xs uppercase tracking-wider text-muted">Two-shooter team totals</span>
        </div>

        <div class="space-y-3">
            @forelse($teamRows as $team)
                @php
                    $rank = (int) ($team['rank'] ?? 0);
                    $ringClass = match(true) {
                        $rank === 1 => 'ring-amber-400/40 bg-amber-500/5',
                        $rank === 2 => 'ring-slate-400/30 bg-slate-400/5',
                        $rank === 3 => 'ring-orange-500/25 bg-orange-500/5',
                        default => 'ring-border/60',
                    };
                    $rankClass = match(true) {
                        $rank === 1 => 'text-amber-400',
                        $rank === 2 => 'text-secondary',
                        $rank === 3 => 'text-orange-500',
                        default => 'text-muted',
                    };
                    $teamId = (int) ($team['team_id'] ?? 0);
                    // Reuse $expandedShooterId as the toggle key by
                    // negating the team id so team expansions don't
                    // collide with shooter row expansions on the
                    // Standings tab.
                    $teamToggleKey = $teamId > 0 ? -$teamId : 0;
                    $isTeamExpanded = $teamToggleKey !== 0 && $expandedShooterId === $teamToggleKey;
                    $s1Id = (int) ($team['shooter_1_id'] ?? 0);
                    $s2Id = (int) ($team['shooter_2_id'] ?? 0);
                    $s1Row = $s1Id > 0 ? ($huntersStandingsById[$s1Id] ?? null) : null;
                    $s2Row = $s2Id > 0 ? ($huntersStandingsById[$s2Id] ?? null) : null;
                    $canExpand = ($s1Row && ! empty($s1Row['stages'])) || ($s2Row && ! empty($s2Row['stages']));
                @endphp
                <div class="rounded-2xl border border-border {{ $ringClass }} ring-1 px-4 py-3 sm:px-5 sm:py-4 {{ $canExpand ? 'cursor-pointer hover:bg-surface/40' : '' }}"
                    @if($canExpand) wire:click="toggleExpand({{ $teamToggleKey }})" @endif>
                    @php
                        // Prize table ranks Hunters *teams*, so the team's
                        // registered name is the primary label. The pair
                        // concat lives below as supporting context so
                        // spectators can see who's on the team without
                        // making the pair name look like the team name.
                        $teamName = trim((string) ($team['team'] ?? ''));
                        if ($teamName === '' && $teamId > 0) {
                            $teamName = 'Team ' . $teamId;
                        }
                        $pairLabel = null;
                        if (! empty($team['shooter_1_name'])) {
                            $pairLabel = $team['shooter_1_name'];
                            if (! empty($team['shooter_2_name'])) {
                                $pairLabel .= ' & ' . $team['shooter_2_name'];
                            }
                        }
                    @endphp
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <span class="text-xl font-black tabular-nums {{ $rankClass }} sm:text-2xl">#{{ $rank }}</span>
                        <span class="flex min-w-0 flex-col">
                            <span class="text-base font-semibold text-primary sm:text-lg">
                                {{ $teamName !== '' ? $teamName : 'Unassigned team' }}
                            </span>
                            @if($pairLabel)
                                <span class="text-xs text-muted sm:text-sm">{{ $pairLabel }}</span>
                            @endif
                        </span>
                        @if($canExpand)
                            <span class="text-muted transition-transform {{ $isTeamExpanded ? 'rotate-180' : '' }}" aria-hidden="true">▾</span>
                        @endif
                        <span class="ml-auto flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                            <span class="tabular-nums text-emerald-300">
                                <span class="font-black text-lg sm:text-xl">{{ number_format((float) ($team['team_total_points'] ?? 0), 2) }}</span>
                                <span class="ml-1 text-xs uppercase tracking-wide text-muted">pts</span>
                            </span>
                            <span class="tabular-nums text-green-400">
                                <span class="font-bold">{{ (int) ($team['team_total_hits'] ?? 0) }}</span>
                                <span class="ml-1 text-xs uppercase tracking-wide text-muted">hits</span>
                            </span>
                            <span class="tabular-nums text-secondary">
                                <span class="font-bold">{{ (int) ($team['first_round_hits'] ?? 0) }}</span>
                                <span class="ml-1 text-xs uppercase tracking-wide text-muted">1st&nbsp;rd</span>
                            </span>
                            @php $furthest = (int) ($team['furthest_hit_m'] ?? 0); @endphp
                            @if($furthest > 0)
                                <span class="tabular-nums text-secondary">
                                    <span class="font-bold">{{ $furthest }}m</span>
                                    <span class="ml-1 text-xs uppercase tracking-wide text-muted">furthest</span>
                                </span>
                            @endif
                        </span>
                    </div>
                    <div class="mt-1.5 text-xs text-muted">
                        <span class="inline-flex items-center gap-1"><span class="opacity-60">S1</span> {{ number_format((float) ($team['shooter_1_score'] ?? 0), 2) }} pts</span>
                        @if(! empty($team['shooter_2_name']))
                            <span class="mx-1 opacity-50">&bull;</span>
                            <span class="inline-flex items-center gap-1"><span class="opacity-60">S2</span> {{ number_format((float) ($team['shooter_2_score'] ?? 0), 2) }} pts</span>
                        @endif
                    </div>

                    @if($isTeamExpanded)
                        <div class="mt-3 space-y-4" wire:click.stop>
                            @if($s1Row)
                                <div>
                                    <div class="mb-2 text-xs font-bold uppercase tracking-wider text-muted">{{ $team['shooter_1_name'] ?? 'Shooter 1' }}</div>
                                    @include('scoreboards.partials.alrha-shooter-breakdown', ['stages' => $s1Row['stages'] ?? []])
                                </div>
                            @endif
                            @if($s2Row)
                                <div>
                                    <div class="mb-2 text-xs font-bold uppercase tracking-wider text-muted">{{ $team['shooter_2_name'] ?? 'Shooter 2' }}</div>
                                    @include('scoreboards.partials.alrha-shooter-breakdown', ['stages' => $s2Row['stages'] ?? []])
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-border bg-surface/40 p-8 text-center text-muted">
                    No Hunters teams set up yet.
                </div>
            @endforelse
        </div>
    </section>
@endif

{{-- ============================================================
     Categories tab — per-class category slices (Open/Ladies/Junior).
     ============================================================ --}}
@if($alrhaTab === 'categories')
    <div class="space-y-8">
        @forelse($visibleClassBlocks as $block)
            @php
                $classLabel = $block['class_label'] ?? '';
                $catSlices = $block['categories'] ?? [];
            @endphp
            <section>
                @if($isDualClass && ! $activeAlrhaClass)
                    <h2 class="mb-3 text-lg font-black tracking-tight text-primary sm:text-xl">{{ $classLabel }}</h2>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach($catSlices as $slice)
                        <div class="rounded-2xl border border-border bg-app px-4 py-4 sm:px-5 sm:py-5">
                            <h3 class="mb-3 flex items-baseline gap-2 text-base font-black text-primary sm:text-lg">
                                {{ $slice['name'] ?? '' }}
                                <span class="text-xs font-medium uppercase tracking-wider text-muted">Prize table</span>
                            </h3>
                            @if(empty($slice['rows']))
                                <p class="text-sm text-muted">No eligible shooters yet.</p>
                            @else
                                <ol class="space-y-1.5 text-sm">
                                    @foreach($slice['rows'] as $row)
                                        @php
                                            $rank = (int) ($row['rank'] ?? 0);
                                            $rankClass = match(true) {
                                                $rank === 1 => 'text-amber-400 font-black',
                                                $rank === 2 => 'text-secondary font-bold',
                                                $rank === 3 => 'text-orange-500 font-bold',
                                                default => 'text-muted font-medium',
                                            };
                                        @endphp
                                        <li class="flex items-baseline gap-3">
                                            <span class="w-6 shrink-0 text-right tabular-nums {{ $rankClass }}">{{ $rank }}</span>
                                            <span class="min-w-0 flex-1 truncate text-primary" title="{{ $row['name'] ?? '' }}">{{ $row['name'] ?? '' }}</span>
                                            <span class="shrink-0 text-right font-bold text-emerald-300 tabular-nums">{{ number_format((float) ($row['total_points'] ?? 0), 2) }}</span>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="rounded-2xl border border-border bg-app px-6 py-16 text-center text-2xl text-muted">
                Categories are not configured on this match.
            </div>
        @endforelse
    </div>
@endif

{{-- ============================================================
     Prize Book — the year-end printout view.

     One accordion section per prize table, in the canonical ALRHA
     order (Hunter Team → Hunter Individual → Hunter categories →
     Varmint Open → Varmint categories → CBC per class). Each section
     is an Alpine-driven collapsible so a spectator or match director
     can browse the whole prize giving without switching tabs.

     Sections that would be empty (e.g. Ladies category with no ladies
     entered) are skipped so the accordion doesn't read as broken.
     ============================================================ --}}
@if($alrhaTab === 'prize-book')
    @php
        // Build the accordion sections in the exact printed-programme
        // order. Each section is a self-contained payload the row
        // partial can render — we don't inline table logic here so the
        // list stays scannable.
        $prizeSections = [];
        foreach ($visibleClassBlocks as $block) {
            $classValue = $block['class'] ?? null;
            $classLabel = $block['class_label'] ?? '';
            $classShort = $classValue === \App\Enums\AlrhaClass::Hunters->value ? 'Hunter' : 'Varmint';

            // Team prize (Hunters only).
            if ($classValue === \App\Enums\AlrhaClass::Hunters->value && ! empty($block['teams'])) {
                $prizeSections[] = [
                    'kind' => 'teams',
                    'title' => 'Hunter Team',
                    'subtitle' => 'Two-shooter team totals',
                    'rows' => $block['teams'],
                ];
            }

            // Individual overall for this class.
            if (! empty($block['standings'])) {
                $prizeSections[] = [
                    'kind' => 'standings',
                    'title' => "{$classShort} Individual",
                    'subtitle' => "{$classLabel} — overall prize table",
                    'rows' => $block['standings'],
                ];
            }

            // Per-category slices. Skip the "Open" duplicate of the
            // overall individual for Hunters (Hunters has no Ladies,
            // and Open == overall for the class); keep Junior always,
            // and for Varmint keep Open/Ladies/Junior so the reference
            // layout (Varmint Open / Varmint Junior / Varmint Ladies)
            // is reproduced.
            foreach ($block['categories'] ?? [] as $slice) {
                $slug = $slice['slug'] ?? null;
                if ($classValue === \App\Enums\AlrhaClass::Hunters->value && $slug === 'open') {
                    continue;
                }
                if (empty($slice['rows'])) {
                    continue;
                }
                $prizeSections[] = [
                    'kind' => 'category',
                    'title' => "{$classShort} " . ($slug === 'open' ? 'Open' : ($slug === 'ladies' ? 'Ladies' : ($slug === 'junior' ? 'Junior' : ucfirst((string) $slug)))),
                    'subtitle' => "{$classLabel} — " . strtolower($slice['name'] ?? '') . ' prize table',
                    'rows' => $slice['rows'],
                ];
            }

            // Cold Bore Challenge for this class.
            if (! empty($block['cbc'])) {
                $classEnum = \App\Enums\AlrhaClass::tryFrom($classValue ?? '');
                $prizeSections[] = [
                    'kind' => 'cbc',
                    'title' => "{$classShort} Cold Bore Challenge",
                    'subtitle' => $classEnum?->coldBoreTargetName() ?? 'Cold Bore Challenge',
                    'rows' => $block['cbc'],
                ];
            }
        }
    @endphp

    <div class="space-y-2" x-data="{ open: null }">
        @forelse($prizeSections as $index => $section)
            <div class="rounded-xl border border-border bg-app">
                <button type="button"
                        @click="open = open === {{ $index }} ? null : {{ $index }}"
                        class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left sm:px-5 sm:py-4"
                        :aria-expanded="open === {{ $index }}">
                    <div class="min-w-0">
                        <div class="text-base font-bold text-primary sm:text-lg">{{ $section['title'] }}</div>
                        @if(! empty($section['subtitle']))
                            <div class="mt-0.5 text-xs text-muted">{{ $section['subtitle'] }}</div>
                        @endif
                    </div>
                    <span class="shrink-0 text-muted transition-transform" :class="open === {{ $index }} ? 'rotate-180' : ''" aria-hidden="true">▾</span>
                </button>
                <div x-show="open === {{ $index }}" x-cloak class="border-t border-border/60 px-3 py-3 sm:px-5 sm:py-4">
                    @switch($section['kind'])
                        @case('teams')
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[30rem] text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-border/60 text-xs uppercase tracking-wider text-muted">
                                            <th class="py-2 pr-3">#</th>
                                            <th class="py-2 pr-3">Team</th>
                                            <th class="py-2 pr-3 text-right">Team&nbsp;Pts</th>
                                            <th class="py-2 pr-3 text-right">Hits</th>
                                            <th class="py-2 text-right">1st&nbsp;Rd</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/50">
                                        @foreach($section['rows'] as $team)
                                            @php
                                                $tRank = (int) ($team['rank'] ?? 0);
                                                $tTeamId = (int) ($team['team_id'] ?? 0);
                                                $tTeamName = trim((string) ($team['team'] ?? ''));
                                                if ($tTeamName === '' && $tTeamId > 0) {
                                                    $tTeamName = 'Team ' . $tTeamId;
                                                }
                                                $tPairLabel = null;
                                                if (! empty($team['shooter_1_name'])) {
                                                    $tPairLabel = $team['shooter_1_name'];
                                                    if (! empty($team['shooter_2_name'])) {
                                                        $tPairLabel .= ' & ' . $team['shooter_2_name'];
                                                    }
                                                }
                                            @endphp
                                            <tr>
                                                <td class="py-2 pr-3 font-bold tabular-nums text-muted">{{ $tRank }}</td>
                                                <td class="py-2 pr-3">
                                                    <div class="text-primary">{{ $tTeamName !== '' ? $tTeamName : 'Unassigned team' }}</div>
                                                    @if($tPairLabel)
                                                        <div class="text-xs text-muted">{{ $tPairLabel }}</div>
                                                    @endif
                                                </td>
                                                <td class="py-2 pr-3 text-right font-bold tabular-nums text-emerald-300">{{ number_format((float) ($team['team_total_points'] ?? 0), 2) }}</td>
                                                <td class="py-2 pr-3 text-right tabular-nums text-green-400">{{ (int) ($team['team_total_hits'] ?? 0) }}</td>
                                                <td class="py-2 text-right tabular-nums text-secondary">{{ (int) ($team['first_round_hits'] ?? 0) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @break

                        @case('standings')
                        @case('category')
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[26rem] text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-border/60 text-xs uppercase tracking-wider text-muted">
                                            <th class="py-2 pr-3">#</th>
                                            <th class="py-2 pr-3">Shooter</th>
                                            <th class="py-2 pr-3">Squad</th>
                                            <th class="py-2 pr-3 text-right">Points</th>
                                            <th class="py-2 text-right">Hits</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/50">
                                        @foreach($section['rows'] as $row)
                                            @php
                                                $rRank = (int) ($row['rank'] ?? 0);
                                                $rIsCoached = ! empty($row['is_coached']);
                                            @endphp
                                            <tr class="{{ $rIsCoached ? 'opacity-70' : '' }}">
                                                <td class="py-2 pr-3 font-bold tabular-nums text-muted">{{ $rRank }}</td>
                                                <td class="py-2 pr-3 text-primary">
                                                    {{ $row['name'] ?? '' }}
                                                    @if($rIsCoached)
                                                        <span class="ml-1 text-[10px] uppercase tracking-wide text-amber-300/80">coached</span>
                                                    @endif
                                                </td>
                                                <td class="py-2 pr-3 text-xs text-muted">{{ $row['squad_name'] ?? '—' }}</td>
                                                <td class="py-2 pr-3 text-right font-bold tabular-nums text-emerald-300">{{ number_format((float) ($row['total_points'] ?? 0), 2) }}</td>
                                                <td class="py-2 text-right tabular-nums text-green-400">{{ (int) ($row['total_hits'] ?? 0) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @break

                        @case('cbc')
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[26rem] text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-border/60 text-xs uppercase tracking-wider text-muted">
                                            <th class="py-2 pr-3">#</th>
                                            <th class="py-2 pr-3">Shooter</th>
                                            <th class="py-2 pr-3">Squad</th>
                                            <th class="py-2 pr-3 text-right">Hits</th>
                                            <th class="py-2 text-right">Points</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/50">
                                        @foreach($section['rows'] as $row)
                                            @php $cRank = (int) ($row['rank'] ?? 0); @endphp
                                            <tr>
                                                <td class="py-2 pr-3 font-bold tabular-nums text-muted">{{ $cRank }}</td>
                                                <td class="py-2 pr-3 text-primary">{{ $row['name'] ?? '' }}</td>
                                                <td class="py-2 pr-3 text-xs text-muted">{{ $row['squad_name'] ?? '—' }}</td>
                                                <td class="py-2 pr-3 text-right tabular-nums text-green-400">{{ (int) ($row['cbc_hits'] ?? 0) }}</td>
                                                <td class="py-2 text-right font-bold tabular-nums text-emerald-300">{{ number_format((float) ($row['cbc_points'] ?? 0), 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @break
                    @endswitch
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-border bg-app px-6 py-16 text-center text-2xl text-muted">
                No prize tables to show yet — no scores recorded.
            </div>
        @endforelse
    </div>
@endif
