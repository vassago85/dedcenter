<?php

use App\Models\ShootingMatch;
use App\Models\Score;
use App\Enums\MatchStatus;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    class extends Component {

    public ShootingMatch $match;
    public string $activeTab = 'leaderboard';
    public int $dayFilter = 0;
    public ?int $activeDivision = null;
    public ?int $activeCategory = null;

    public function mount(ShootingMatch $match): void
    {
        $this->match = $match;
    }

    public function getTitle(): string
    {
        return $this->match->name . ' — DeadCenter';
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function setDayFilter(int $day): void
    {
        $this->dayFilter = $day;
    }

    public function filterDivision(?int $id): void
    {
        $this->activeDivision = $id;
    }

    public function filterCategory(?int $id): void
    {
        $this->activeCategory = $id;
    }

    private function buildScoreboardData(): array
    {
        $isPrs = $this->match->isPrs();
        $isMultiDay = $this->match->isMultiDay();
        $dayFiltered = $this->dayFilter > 0 && $isMultiDay;

        $usedDivisionIds = $this->match->shooters()->whereNotNull('match_division_id')->distinct()->pluck('match_division_id')->toArray();
        $divisions = $this->match->divisions()->whereIn('id', $usedDivisionIds)->orderBy('sort_order')->get();

        $usedCategoryIds = DB::table('match_category_shooter')
            ->whereIn('shooter_id', $this->match->shooters()->pluck('shooters.id'))
            ->distinct()
            ->pluck('match_category_id')
            ->toArray();
        $categories = $this->match->categories()->whereIn('id', $usedCategoryIds)->orderBy('sort_order')->get();

        $targetSetsQuery = $this->match->targetSets()->with('gongs')->orderBy('sort_order');
        if ($dayFiltered) {
            $targetSetsQuery->where('day_number', $this->dayFilter);
        }
        $targetSets = $targetSetsQuery->get();
        $targetSetIds = $targetSets->pluck('id');
        $allGongIds = $targetSets->flatMap(fn ($ts) => $ts->gongs->pluck('id'))->toArray();

        $shooterTimes = [];
        $tbHits = [];
        $tbTimes = [];
        $totalTargets = 0;
        $prsHitsMap = [];
        $prsMissesMap = [];

        if ($isPrs) {
            $totalTargets = \App\Models\Gong::whereIn('target_set_id', $targetSetIds)->count();
            $tiebreakerStage = $targetSets->firstWhere('is_tiebreaker', true);

            $prsBaseQuery = \App\Models\PrsStageResult::where('match_id', $this->match->id);
            if ($dayFiltered) {
                $prsBaseQuery->whereIn('stage_id', $targetSetIds);
            }

            $shooterTimes = (clone $prsBaseQuery)
                ->whereNotNull('official_time_seconds')
                ->select('shooter_id', DB::raw('SUM(official_time_seconds) as total_time'))
                ->groupBy('shooter_id')
                ->pluck('total_time', 'shooter_id')
                ->toArray();

            if ($tiebreakerStage) {
                $tbResults = \App\Models\PrsStageResult::where('match_id', $this->match->id)
                    ->where('stage_id', $tiebreakerStage->id)->get();
                $tbHits = $tbResults->pluck('hits', 'shooter_id')->map(fn ($v) => (int) $v)->toArray();
                $tbTimes = $tbResults->filter(fn ($r) => $r->official_time_seconds !== null)
                    ->pluck('official_time_seconds', 'shooter_id')
                    ->map(fn ($v) => (float) $v)->toArray();
            }

            $prsAgg = (clone $prsBaseQuery)
                ->select('shooter_id', DB::raw('SUM(hits) as total_hits'), DB::raw('SUM(misses) as total_misses'))
                ->groupBy('shooter_id')->get();
            foreach ($prsAgg as $row) {
                $prsHitsMap[$row->shooter_id] = (int) $row->total_hits;
                $prsMissesMap[$row->shooter_id] = (int) $row->total_misses;
            }
        }

        $shooterQuery = $this->match->shooters()->with(['squad', 'division']);

        if (!$isPrs) {
            if ($dayFiltered) {
                $shooterQuery->withCount([
                    'scores as hits_count' => fn ($q) => $q->where('is_hit', true)->whereIn('gong_id', $allGongIds),
                    'scores as misses_count' => fn ($q) => $q->where('is_hit', false)->whereIn('gong_id', $allGongIds),
                ]);
            } else {
                $shooterQuery->withCount([
                    'scores as hits_count' => fn ($q) => $q->where('is_hit', true),
                    'scores as misses_count' => fn ($q) => $q->where('is_hit', false),
                ]);
            }
        }

        if ($this->activeDivision) {
            $shooterQuery->where('shooters.match_division_id', $this->activeDivision);
        }
        if ($this->activeCategory) {
            $catShooterIds = DB::table('match_category_shooter')
                ->where('match_category_id', $this->activeCategory)
                ->pluck('shooter_id')->toArray();
            $shooterQuery->whereIn('shooters.id', $catShooterIds);
        }

        $shooters = $shooterQuery->get()
            ->map(function ($shooter) use ($isPrs, $shooterTimes, $tbHits, $tbTimes, $totalTargets, $prsHitsMap, $prsMissesMap, $dayFiltered, $allGongIds) {
                if ($isPrs) {
                    $shooter->hits_count = $prsHitsMap[$shooter->id] ?? 0;
                    $shooter->misses_count = $prsMissesMap[$shooter->id] ?? 0;
                    $shooter->display_score = $shooter->hits_count;
                    $shooter->display_time = (float) ($shooterTimes[$shooter->id] ?? 0);
                    $shooter->tb_hits = $tbHits[$shooter->id] ?? 0;
                    $shooter->tb_time = (float) ($tbTimes[$shooter->id] ?? 0);
                    $shooter->not_taken = max(0, $totalTargets - $shooter->hits_count - $shooter->misses_count);
                } else {
                    $scoreQuery = $shooter->scores()->where('is_hit', true)
                        ->join('gongs', 'scores.gong_id', '=', 'gongs.id');
                    if ($dayFiltered) {
                        $scoreQuery->whereIn('scores.gong_id', $allGongIds);
                    }
                    $shooter->display_score = (float) $scoreQuery->sum('gongs.multiplier');
                    $shooter->display_time = 0;
                }
                return $shooter;
            });

        if ($isPrs) {
            $shooters = $shooters->sort(function ($a, $b) {
                if ($a->display_score !== $b->display_score) return $b->display_score <=> $a->display_score;
                if ($a->tb_hits !== $b->tb_hits) return $b->tb_hits <=> $a->tb_hits;
                if ($a->tb_time !== $b->tb_time) return $a->tb_time <=> $b->tb_time;
                return $a->display_time <=> $b->display_time;
            })->values();

            $maxHits = (int) $shooters->max('hits_count');
            foreach ($shooters as $s) {
                $s->prs_points = $maxHits > 0 ? round($s->hits_count / $maxHits * 100, 2) : 0.0;
            }
        } else {
            $shooters = $shooters->sortByDesc('display_score')->values();
        }

        $teamLeaderboard = collect();
        if ($this->match->isTeamEvent()) {
            $teamData = $this->match->teams()
                ->with(['shooters' => fn ($q) => $q->with('squad')])
                ->orderBy('sort_order')->get();

            $teamLeaderboard = $teamData->map(function ($team) use ($shooters, $isPrs) {
                $memberScores = $team->shooters->map(function ($member) use ($shooters) {
                    $scored = $shooters->firstWhere('id', $member->id);
                    return (object) ['name' => $member->name, 'score' => $scored?->display_score ?? 0];
                })->sortByDesc('score')->values();

                return (object) [
                    'team' => $team,
                    'members' => $memberScores,
                    'total_score' => $memberScores->sum('score'),
                    'member_count' => $memberScores->count(),
                ];
            })->sortByDesc('total_score')->values();
        }

        $matchBadges = collect();
        $competitionType = $isPrs ? 'prs' : ($this->match->royal_flush_enabled ? 'royal_flush' : null);
        if ($competitionType) {
            $matchBadges = \App\Models\UserAchievement::where('match_id', $this->match->id)
                ->whereHas('achievement', fn ($q) => $q->where('competition_type', $competitionType))
                ->with(['achievement', 'user:id,name', 'shooter:id,name', 'stage:id,label,stage_number,distance_meters,is_tiebreaker'])
                ->orderBy('awarded_at')
                ->get();
        }

        return compact('shooters', 'divisions', 'categories', 'teamLeaderboard', 'matchBadges');
    }

    public function with(): array
    {
        $this->match->loadMissing(['organization', 'staff']);

        $isCompleted = $this->match->status === MatchStatus::Completed;
        $isActive = $this->match->status === MatchStatus::Active;
        $isPrs = $this->match->isPrs();
        $isMultiDay = $this->match->isMultiDay();
        $isTeamEvent = $this->match->isTeamEvent();
        $matchDays = $isMultiDay ? ($this->match->match_days ?? 2) : 1;

        $registrants = $this->match->registrations()
            ->where('payment_status', 'confirmed')
            ->with('user:id,name')
            ->get();

        $registration = null;
        $myShooter = null;
        if (auth()->check()) {
            $registration = \App\Models\MatchRegistration::where('match_id', $this->match->id)
                ->where('user_id', auth()->id())->first();
            $myShooter = \App\Models\Shooter::where('user_id', auth()->id())
                ->whereIn('squad_id', $this->match->squads()->pluck('id'))->first();
        }

        $squads = collect();
        $showSquads = in_array($this->match->status, [
            MatchStatus::SquaddingOpen, MatchStatus::SquaddingClosed, MatchStatus::Active, MatchStatus::Completed,
        ]);
        if ($showSquads) {
            $squads = $this->match->squads()
                ->with(['shooters' => fn ($q) => $q->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get()
                ->reject(fn ($s) => in_array($s->name, ['Default', 'Unassigned']));
        }

        $teams = $isTeamEvent
            ? $this->match->teams()->withCount('shooters')->orderBy('sort_order')->get()
            : collect();

        $targetSets = $this->match->targetSets()->with('gongs')->orderBy('sort_order')->get();

        $scoreboardData = [
            'shooters' => collect(),
            'divisions' => collect(),
            'categories' => collect(),
            'teamLeaderboard' => collect(),
            'matchBadges' => collect(),
        ];
        if ($isActive || $isCompleted) {
            $scoreboardData = $this->buildScoreboardData();
        }

        return [
            'isCompleted' => $isCompleted,
            'isActive' => $isActive,
            'isPrs' => $isPrs,
            'isMultiDay' => $isMultiDay,
            'isTeamEvent' => $isTeamEvent,
            'matchDays' => $matchDays,
            'registrants' => $registrants,
            'registration' => $registration,
            'myShooter' => $myShooter,
            'squads' => $squads,
            'showSquads' => $showSquads,
            'teams' => $teams,
            'targetSets' => $targetSets,
            ...$scoreboardData,
        ];
    }
}; ?>

<div @if($isActive) wire:poll.30s @endif class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 space-y-8">

    {{-- ══════════ HEADER ══════════ --}}
    <div>
        <a href="{{ route('events') }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 py-2 text-base font-medium text-muted transition-colors hover:bg-white/5 hover:text-primary focus:outline-none focus:ring-2 focus:ring-accent">
            <x-icon name="chevron-left" class="mr-1.5 h-4 w-4" />
            Events
        </a>

        <div class="mt-4 flex flex-wrap items-center gap-2 sm:gap-3">
            <h1 class="text-2xl font-black leading-tight tracking-tight text-primary break-words sm:text-3xl lg:text-4xl">{{ $match->name }}</h1>

            @if($isPrs)
                <span class="shrink-0 rounded bg-amber-600 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-white">PRS</span>
            @elseif($match->isElr())
                <span class="shrink-0 rounded bg-emerald-600 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-white">ELR</span>
            @endif

            @if($isActive)
                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-green-500/10 px-2.5 py-1">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                    </span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-green-400 sm:text-xs">Live</span>
                </span>
            @elseif($isCompleted)
                <flux:badge size="sm" color="zinc">Completed</flux:badge>
            @else
                <flux:badge size="sm" color="{{ $match->status->color() }}">{{ $match->status->label() }}</flux:badge>
            @endif
        </div>

        <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-2 text-base text-muted">
            @if($match->date)
                <span>{{ $match->date->format('d M Y') }}</span>
            @endif
            @if($match->location)
                <span>&middot; {{ $match->location }}</span>
            @endif
            @if($match->organization)
                <span>&middot; {{ $match->organization->name }}</span>
            @endif
            @if($match->scoring_type)
                <span>&middot; {{ ucfirst($match->scoring_type) }} scoring</span>
            @endif
        </div>
    </div>

    {{-- ══════════ ACTIVE: LIVE BANNER ══════════ --}}
    @if($isActive)
        <a href="{{ route('live', $match) }}"
           class="flex min-h-[44px] items-center justify-between gap-4 rounded-xl border border-green-700/50 bg-gradient-to-r from-green-900/30 to-surface px-6 py-4 transition-colors hover:border-green-600/60 focus:outline-none focus:ring-2 focus:ring-accent">
            <div class="flex items-center gap-3">
                <span class="relative flex h-3 w-3">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex h-3 w-3 rounded-full bg-green-500"></span>
                </span>
                <span class="text-base font-semibold text-green-400">Match is live &mdash; Watch Live Scores</span>
            </div>
            <x-icon name="chevron-right" class="h-5 w-5 text-green-400" />
        </a>
    @endif

    {{-- ══════════ MATCH INFO ══════════ --}}
    <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
        <div class="flex items-start justify-between gap-4">
            <h2 class="text-lg font-semibold text-primary">Match Information</h2>
            <span class="text-2xl font-bold whitespace-nowrap {{ $match->entry_fee ? 'text-primary' : 'text-green-400' }}">
                {{ $match->entry_fee ? 'R' . number_format($match->entry_fee, 2) : 'Free Entry' }}
            </span>
        </div>

        @php $eventBlurb = $match->public_bio ?: $match->notes; @endphp
        @if($eventBlurb)
            <p class="text-base text-secondary whitespace-pre-line leading-relaxed">{{ $eventBlurb }}</p>
        @endif

        @if($match->staff->isNotEmpty())
            <div class="rounded-lg border border-border bg-surface-2/40 p-4">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-muted mb-2">Event Team</h3>
                <ul class="space-y-1.5 text-sm">
                    @foreach($match->staff as $u)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-primary">{{ $u->name }}</span>
                            @if($u->pivot->role === 'match_director')
                                <span class="text-xs text-blue-400">Match Director</span>
                            @else
                                <span class="text-xs text-emerald-400">Range Officer</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($targetSets->isNotEmpty())
            <div class="space-y-2">
                <h3 class="text-sm font-medium text-muted">Target Sets</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($targetSets as $ts)
                        <div class="rounded-lg border border-border bg-surface-2/50 px-3 py-2">
                            <span class="text-sm font-medium text-primary">{{ $ts->label }}</span>
                            <span class="ml-1 text-xs text-muted">({{ $ts->gongs->count() }} targets)</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- ══════════ YOUR RESULT (completed + auth + participated) ══════════ --}}
    @if($isCompleted && auth()->check())
        @php
            $myResult = $shooters->first(fn ($s) => $s->user_id === auth()->id());
            $myRank = $myResult ? $shooters->search(fn ($s) => $s->id === $myResult->id) + 1 : null;
        @endphp
        @if($myResult)
            <div class="rounded-xl border border-green-700/50 bg-gradient-to-r from-green-900/20 to-surface px-6 py-5 space-y-4">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-green-400">Your Result</p>
                        <p class="mt-1 text-xs text-muted">
                            {{ $myResult->hits_count }} hits &middot; {{ $myResult->misses_count }} misses
                            @if($myResult->squad) &middot; {{ $myResult->squad->name }} @endif
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-3xl font-black tabular-nums {{ $myRank <= 3 ? 'text-amber-400' : 'text-primary' }}">#{{ $myRank }}</p>
                        <p class="text-sm font-bold text-secondary tabular-nums">
                            {{ $isPrs ? number_format($myResult->prs_points ?? 0, 2) . ' pts' : number_format($myResult->display_score, 1) . ' pts' }}
                        </p>
                    </div>
                </div>
                <a href="{{ route('matches.report.download', $match) }}"
                   class="inline-flex min-h-[44px] items-center gap-2 rounded-lg bg-green-600 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                    <x-icon name="download" class="h-4.5 w-4.5" />
                    Download My Match Report (PDF)
                </a>
            </div>
        @endif
    @endif

    {{-- ══════════ DOWNLOAD RESULTS (completed) ══════════ --}}
    @if($isCompleted)
        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-surface px-5 py-3">
            <span class="text-sm font-medium text-secondary">Download Results:</span>
            <a href="{{ route('scoreboard.export.standings', $match) }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-2 px-3 py-1.5 text-xs font-medium text-primary transition-colors hover:bg-accent hover:text-white">
                <x-icon name="download" class="h-3.5 w-3.5" />
                Standings (CSV)
            </a>
            <a href="{{ route('scoreboard.export.detailed', $match) }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-2 px-3 py-1.5 text-xs font-medium text-primary transition-colors hover:bg-accent hover:text-white">
                <x-icon name="download" class="h-3.5 w-3.5" />
                Full Results (CSV)
            </a>
            @if($match->royal_flush_enabled)
                <a href="{{ route('scoreboard.export.rf-shots', $match) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-2 px-3 py-1.5 text-xs font-medium text-primary transition-colors hover:bg-accent hover:text-white">
                    <x-icon name="download" class="h-3.5 w-3.5" />
                    RF Shots (CSV, 1/0)
                </a>
            @endif
            <a href="{{ route('scoreboard', $match) }}" target="_blank"
               class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-2 px-3 py-1.5 text-xs font-medium text-primary transition-colors hover:bg-accent hover:text-white">
                <x-icon name="external-link" class="h-3.5 w-3.5" />
                Full Scoreboard
            </a>
        </div>
    @endif

    {{-- ══════════ SCOREBOARD (active / completed) ══════════ --}}
    @if($isActive || $isCompleted)
        <div class="space-y-4">

            {{-- Day filter tabs (multi-day) --}}
            @if($isMultiDay)
                <div x-data="{ day: $wire.entangle('dayFilter') }" class="flex flex-wrap gap-2">
                    <button type="button" @click="day = 0"
                            :class="day === 0 ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2'"
                            class="min-h-[44px] rounded-lg px-4 py-2 text-sm font-bold transition-colors focus:outline-none focus:ring-2 focus:ring-accent">Overall</button>
                    @for($d = 1; $d <= $matchDays; $d++)
                        <button type="button" @click="day = {{ $d }}"
                                :class="day === {{ $d }} ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2'"
                                class="min-h-[44px] rounded-lg px-4 py-2 text-sm font-bold transition-colors focus:outline-none focus:ring-2 focus:ring-accent">Day {{ $d }}</button>
                    @endfor
                </div>
            @endif

            {{-- Division / Category filters --}}
            @if($divisions->isNotEmpty() || $categories->isNotEmpty())
                <div class="space-y-2">
                    @if($divisions->isNotEmpty())
                        <div class="flex flex-nowrap gap-2 overflow-x-auto pb-1 [scrollbar-width:thin]">
                            <span class="shrink-0 self-center pr-1 text-xs text-muted/60">DIV</span>
                            <button type="button" wire:click="filterDivision(null)"
                                    class="shrink-0 min-h-[44px] rounded-lg px-3 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-accent {{ !$activeDivision ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">All</button>
                            @foreach($divisions as $div)
                                <button type="button" wire:click="filterDivision({{ $div->id }})"
                                        class="shrink-0 min-h-[44px] rounded-lg px-3 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-accent {{ $activeDivision === $div->id ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">{{ $div->name }}</button>
                            @endforeach
                        </div>
                    @endif
                    @if($categories->isNotEmpty())
                        <div class="flex flex-nowrap gap-2 overflow-x-auto pb-1 [scrollbar-width:thin]">
                            <span class="shrink-0 self-center pr-1 text-xs text-muted/60">CAT</span>
                            <button type="button" wire:click="filterCategory(null)"
                                    class="shrink-0 min-h-[44px] rounded-lg px-3 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-accent {{ !$activeCategory ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">All</button>
                            @foreach($categories as $cat)
                                <button type="button" wire:click="filterCategory({{ $cat->id }})"
                                        class="shrink-0 min-h-[44px] rounded-lg px-3 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-accent {{ $activeCategory === $cat->id ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">{{ $cat->name }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            {{-- Tab bar --}}
            @if($isTeamEvent || $matchBadges->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="setTab('leaderboard')"
                            class="min-h-[44px] rounded-lg px-4 py-2 text-sm font-bold transition-colors focus:outline-none focus:ring-2 focus:ring-accent sm:px-5 sm:text-base {{ $activeTab === 'leaderboard' ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">Leaderboard</button>
                    @if($isTeamEvent)
                        <button type="button" wire:click="setTab('teams')"
                                class="min-h-[44px] rounded-lg px-4 py-2 text-sm font-bold transition-colors focus:outline-none focus:ring-2 focus:ring-accent sm:px-5 sm:text-base {{ $activeTab === 'teams' ? 'bg-indigo-600 text-white' : 'bg-surface text-muted hover:bg-surface-2' }}">Teams</button>
                    @endif
                    @if($matchBadges->isNotEmpty())
                        <button type="button" wire:click="setTab('badges')"
                                class="min-h-[44px] rounded-lg px-4 py-2 text-sm font-bold transition-colors focus:outline-none focus:ring-2 focus:ring-accent sm:px-5 sm:text-base {{ $activeTab === 'badges' ? ($isPrs ? 'bg-sky-600' : 'bg-amber-600') . ' text-white' : 'bg-surface text-muted hover:bg-surface-2' }}">Badges</button>
                    @endif
                </div>
            @endif

            {{-- ── LEADERBOARD TAB ── --}}
            @if($activeTab === 'leaderboard')
                <div class="overflow-x-auto rounded-2xl border border-border bg-surface [-webkit-overflow-scrolling:touch]">
                    <table class="w-full min-w-[36rem] text-left">
                        <thead>
                            <tr class="border-b border-border bg-surface-2/50">
                                <th class="px-3 py-2.5 text-xs font-bold text-secondary sm:px-4 sm:text-sm">#</th>
                                <th class="px-3 py-2.5 text-xs font-bold text-secondary sm:px-4 sm:text-sm">Shooter</th>
                                <th class="px-3 py-2.5 text-xs font-bold text-secondary sm:px-4 sm:text-sm">Squad</th>
                                @if($divisions->isNotEmpty())
                                    <th class="px-3 py-2.5 text-xs font-bold text-secondary sm:px-4 sm:text-sm">Div</th>
                                @endif
                                <th class="px-3 py-2.5 text-center text-xs font-bold text-green-400 sm:px-4 sm:text-sm">Hits</th>
                                <th class="px-3 py-2.5 text-center text-xs font-bold text-accent sm:px-4 sm:text-sm">Miss</th>
                                @if($isPrs)
                                    <th class="px-3 py-2.5 text-center text-xs font-bold text-amber-400/60 sm:px-4 sm:text-sm">N/T</th>
                                    <th class="px-3 py-2.5 text-right text-xs font-bold text-secondary sm:px-4 sm:text-sm">TB&nbsp;Time</th>
                                @endif
                                <th class="px-3 py-2.5 text-right text-xs font-bold text-amber-400 sm:px-4 sm:text-sm">{{ $isPrs ? 'Pts' : 'Score' }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/50">
                            @forelse($shooters as $index => $shooter)
                                @php
                                    $rank = $index + 1;
                                    $isMe = auth()->check() && $shooter->user_id === auth()->id();
                                    $rowBorder = match($rank) {
                                        1 => 'border-l-4 border-l-amber-400 bg-amber-500/5',
                                        2 => 'border-l-4 border-l-slate-400 bg-slate-400/5',
                                        3 => 'border-l-4 border-l-orange-600 bg-orange-500/5',
                                        default => 'border-l-4 border-l-transparent',
                                    };
                                    $rankClass = match($rank) {
                                        1 => 'text-amber-400 font-black',
                                        2 => 'text-secondary font-bold',
                                        3 => 'text-orange-500 font-bold',
                                        default => 'text-muted font-medium',
                                    };
                                @endphp
                                <tr class="{{ $rowBorder }} {{ $isMe ? 'ring-1 ring-inset ring-green-500/30 !bg-green-900/10' : '' }} transition-colors">
                                    <td class="px-3 py-2.5 text-base {{ $rankClass }} sm:px-4 sm:text-lg">{{ $rank }}</td>
                                    <td class="max-w-[10rem] px-3 py-2.5 sm:max-w-none sm:px-4">
                                        <div class="flex items-center gap-2">
                                            @if($isMe)
                                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-green-400"></span>
                                            @endif
                                            @if($shooter->user_id)
                                                <a href="{{ route('shooter.profile', $shooter->user_id) }}" class="inline-flex min-h-[44px] max-w-full items-center truncate rounded text-base font-semibold text-primary underline-offset-2 hover:underline focus:outline-none focus:ring-2 focus:ring-accent" title="{{ $shooter->name }}">{{ $shooter->name }}</a>
                                            @else
                                                <span class="truncate text-base font-semibold text-primary" title="{{ $shooter->name }}">{{ $shooter->name }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="max-w-[5rem] truncate px-3 py-2.5 text-xs text-muted sm:max-w-none sm:px-4 sm:text-sm" title="{{ $shooter->squad?->name ?? '—' }}">{{ $shooter->squad?->name ?? '—' }}</td>
                                    @if($divisions->isNotEmpty())
                                        <td class="max-w-[4rem] truncate px-3 py-2.5 text-xs text-muted sm:max-w-none sm:px-4 sm:text-sm" title="{{ $shooter->division?->name ?? '—' }}">{{ $shooter->division?->name ?? '—' }}</td>
                                    @endif
                                    <td class="px-3 py-2.5 text-center text-sm font-bold text-green-400 sm:px-4 sm:text-base">{{ $shooter->hits_count }}</td>
                                    <td class="px-3 py-2.5 text-center text-sm font-bold text-accent sm:px-4 sm:text-base">{{ $shooter->misses_count }}</td>
                                    @if($isPrs)
                                        <td class="px-3 py-2.5 text-center text-sm font-bold text-amber-400/60 sm:px-4 sm:text-base">{{ $shooter->not_taken ?? 0 }}</td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono text-xs text-secondary sm:px-4 sm:text-sm">
                                            {{ $shooter->tb_time > 0 ? number_format($shooter->tb_time, 1) . 's' : '—' }}
                                        </td>
                                    @endif
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right text-base font-black text-amber-400 tabular-nums sm:px-4 sm:text-lg">
                                        {{ $isPrs ? number_format($shooter->prs_points ?? 0, 2) : number_format($shooter->display_score, 1) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ ($isPrs ? 9 : 6) + ($divisions->isNotEmpty() ? 1 : 0) }}" class="px-6 py-16 text-center text-muted">
                                        No scores recorded yet
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($isActive)
                    <p class="text-center text-xs text-muted/50">Auto-refreshes every 30 seconds</p>
                @endif
            @endif

            {{-- ── TEAMS TAB ── --}}
            @if($activeTab === 'teams' && $isTeamEvent)
                <div class="space-y-3">
                    @forelse($teamLeaderboard as $index => $entry)
                        <div class="rounded-2xl border border-border bg-surface overflow-hidden" x-data="{ open: false }">
                            <button type="button" @click="open = !open" class="flex min-h-[44px] w-full items-center justify-between px-4 py-3 text-left transition-colors hover:bg-surface-2/50 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-inset sm:px-6 sm:py-4">
                                <div class="flex items-center gap-3 min-w-0">
                                    @php
                                        $medal = match($index) { 0 => 'text-amber-400', 1 => 'text-zinc-300', 2 => 'text-amber-700', default => 'text-muted' };
                                    @endphp
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-2 text-sm font-black {{ $medal }} sm:h-10 sm:w-10 sm:text-base">{{ $index + 1 }}</span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-primary sm:text-base">{{ $entry->team->name }}</p>
                                        <p class="text-xs text-muted">{{ $entry->member_count }} {{ Str::plural('member', $entry->member_count) }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-lg font-black text-amber-400 tabular-nums sm:text-2xl">
                                        {{ $isPrs ? $entry->total_score : number_format($entry->total_score, 1) }}
                                    </span>
                                    <x-icon name="chevron-down" x-bind:class="open && 'rotate-180'" class="h-5 w-5 text-muted transition-transform" />
                                </div>
                            </button>
                            <div x-show="open" x-collapse>
                                <div class="border-t border-border px-4 py-3 sm:px-6">
                                    <table class="w-full text-sm">
                                        <thead><tr class="text-left text-muted"><th class="pb-1 font-medium">Shooter</th><th class="pb-1 font-medium text-right">Score</th></tr></thead>
                                        <tbody class="divide-y divide-border/30">
                                            @foreach($entry->members as $member)
                                                <tr>
                                                    <td class="py-1.5 text-secondary">{{ $member->name }}</td>
                                                    <td class="py-1.5 text-right font-bold tabular-nums text-primary">{{ $isPrs ? $member->score : number_format($member->score, 1) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-border bg-surface/50 p-8 text-center">
                            <p class="text-muted">No teams set up for this event.</p>
                        </div>
                    @endforelse
                </div>
            @endif

            {{-- ── BADGES TAB ── --}}
            @if($activeTab === 'badges' && $matchBadges->isNotEmpty())
                @php
                    $badgesByCategory = $matchBadges->groupBy(fn ($ua) => $ua->achievement?->category ?? 'unknown');
                    $matchSpecials = $badgesByCategory->get('match_special', collect());
                    $lifetimeBadges = $badgesByCategory->get('lifetime', collect());
                    $repeatableBadges = $badgesByCategory->get('repeatable', collect());
                    $bcfg = \App\Http\Controllers\BadgeGalleryController::BADGE_CONFIG;
                    $accentColor = $isPrs ? 'sky' : 'amber';
                    $family = $isPrs ? 'prs' : 'royal_flush';
                @endphp

                <div class="space-y-6">
                    {{-- Signature badge --}}
                    @php
                        $sigSlug = $isPrs ? 'deadcenter' : 'winning-hand';
                        $signatureBadge = $matchSpecials->first(fn ($ua) => $ua->achievement?->slug === $sigSlug);
                    @endphp
                    @if($signatureBadge)
                        <section>
                            <div class="mb-3 flex items-center gap-2">
                                <x-badge-icon name="sparkles" class="h-3.5 w-3.5 text-{{ $accentColor }}-400" />
                                <span class="text-xs font-bold uppercase tracking-wider text-{{ $accentColor }}-400">Signature Badge</span>
                            </div>
                            <div class="overflow-hidden rounded-2xl border-2 border-{{ $accentColor }}-500/40 bg-gradient-to-br from-{{ $accentColor }}-900/20 via-surface to-surface">
                                <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:gap-5 sm:p-6">
                                    @php $sigCfg = $bcfg[$sigSlug] ?? []; @endphp
                                    <x-badge-crest :icon="$sigCfg['icon'] ?? ($isPrs ? 'deadcenter' : 'spade')" tier="featured" :family="$family" />
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-xl font-semibold text-{{ $accentColor }}-300 sm:text-2xl">{{ $signatureBadge->achievement->label }}</h3>
                                        <p class="mt-1 text-sm text-secondary">{{ $signatureBadge->achievement->description }}</p>
                                        <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                                            @if($signatureBadge->user_id)
                                                <a href="{{ route('shooter.profile', $signatureBadge->user_id) }}" class="inline-flex min-h-[44px] items-center font-bold text-primary underline-offset-2 hover:underline focus:outline-none focus:ring-2 focus:ring-accent rounded">{{ $signatureBadge->shooter?->name ?? $signatureBadge->user?->name ?? 'Unknown' }}</a>
                                            @else
                                                <span class="font-bold text-primary">{{ $signatureBadge->shooter?->name ?? 'Unknown' }}</span>
                                            @endif
                                            @if($signatureBadge->stage)
                                                <span class="text-muted">&bull;</span>
                                                <span class="text-muted">{{ $signatureBadge->stage->label ?? 'Stage ' . $signatureBadge->stage->stage_number }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    @endif

                    {{-- Lifetime badges --}}
                    @if($lifetimeBadges->isNotEmpty())
                        <section>
                            <div class="mb-3 flex items-center gap-2">
                                <x-badge-icon name="award" class="h-3.5 w-3.5 text-{{ $accentColor }}-400/70" />
                                <span class="text-xs font-bold uppercase tracking-wider text-{{ $accentColor }}-400/70">Lifetime Achievements</span>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach($lifetimeBadges->sortBy(fn ($ua) => $ua->achievement?->sort_order ?? 99) as $ua)
                                    @php
                                        $badge = $ua->achievement;
                                        $cfg = $bcfg[$badge->slug] ?? [];
                                    @endphp
                                    <div class="flex items-center gap-4 rounded-2xl border border-{{ $accentColor }}-400/15 bg-{{ $accentColor }}-900/10 px-4 py-4">
                                        <x-badge-crest :icon="$cfg['icon'] ?? 'target'" :tier="$cfg['tier'] ?? 'earned'" :family="$family" />
                                        <div class="min-w-0 flex-1">
                                            <span class="text-base font-bold text-{{ $accentColor }}-200">{{ $badge->label }}</span>
                                            <p class="mt-0.5 text-xs text-muted leading-snug">{{ $badge->description }}</p>
                                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                                @if($ua->user_id)
                                                    <a href="{{ route('shooter.profile', $ua->user_id) }}" class="inline-flex min-h-[44px] items-center font-semibold text-primary underline-offset-2 hover:underline focus:outline-none focus:ring-2 focus:ring-accent rounded">{{ $ua->shooter?->name ?? $ua->user?->name ?? 'Unknown' }}</a>
                                                @else
                                                    <span class="font-semibold text-primary">{{ $ua->shooter?->name ?? 'Unknown' }}</span>
                                                @endif
                                                @if($ua->stage)
                                                    <span class="text-muted">&bull;</span>
                                                    <span class="text-muted">{{ $ua->stage->label ?? 'Stage ' . $ua->stage->stage_number }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    {{-- Stackable badges --}}
                    @if($repeatableBadges->isNotEmpty())
                        <section>
                            <div class="mb-3 flex items-center gap-2">
                                <x-badge-icon name="layers" class="h-3.5 w-3.5 text-{{ $accentColor }}-500/60" />
                                <span class="text-xs font-bold uppercase tracking-wider text-{{ $accentColor }}-500/60">Stackable Badges</span>
                            </div>
                            @php $grouped = $repeatableBadges->groupBy(fn ($ua) => $ua->achievement?->slug ?? 'unknown'); @endphp
                            <div class="space-y-4">
                                @foreach($grouped as $slug => $entries)
                                    @php
                                        $badge = $entries->first()?->achievement;
                                        if (!$badge) continue;
                                        $cfg = $bcfg[$badge->slug] ?? [];
                                    @endphp
                                    <div class="overflow-hidden rounded-2xl border border-{{ $accentColor }}-500/12 bg-{{ $accentColor }}-900/5">
                                        <div class="flex items-center gap-4 border-b border-{{ $accentColor }}-500/10 px-4 py-3">
                                            <x-badge-crest :icon="$cfg['icon'] ?? 'target'" :tier="$cfg['tier'] ?? 'earned'" :family="$family" />
                                            <div class="min-w-0 flex-1">
                                                <span class="text-base font-bold text-{{ $accentColor }}-200">{{ $badge->label }}</span>
                                                <p class="mt-0.5 text-xs text-muted leading-snug">{{ $badge->description }}</p>
                                            </div>
                                            <span class="rounded-full bg-{{ $accentColor }}-600/20 px-2.5 py-1 text-xs font-bold tabular-nums text-{{ $accentColor }}-400">{{ $entries->count() }}&times;</span>
                                        </div>
                                        <div class="divide-y divide-border/30">
                                            @foreach($entries->sortBy(fn ($ua) => $ua->shooter?->name ?? $ua->user?->name ?? '') as $ua)
                                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 text-sm">
                                                    @if($ua->user_id)
                                                        <a href="{{ route('shooter.profile', $ua->user_id) }}" class="inline-flex min-h-[44px] items-center font-medium text-primary underline-offset-2 hover:underline focus:outline-none focus:ring-2 focus:ring-accent rounded">{{ $ua->shooter?->name ?? $ua->user?->name ?? 'Unknown' }}</a>
                                                    @else
                                                        <span class="font-medium text-primary">{{ $ua->shooter?->name ?? 'Unknown' }}</span>
                                                    @endif
                                                    @if($ua->stage)
                                                        <span class="text-xs text-muted">{{ $ua->stage->label ?? 'Stage ' . $ua->stage->stage_number }}</span>
                                                    @endif
                                                    @if(isset($ua->metadata['streak']))
                                                        <span class="text-xs tabular-nums text-muted">{{ $ua->metadata['streak'] }} consecutive hits</span>
                                                    @endif
                                                    @if(isset($ua->metadata['time']))
                                                        <span class="text-xs tabular-nums text-muted">{{ number_format($ua->metadata['time'], 2) }}s</span>
                                                    @endif
                                                    @if(isset($ua->metadata['distance_meters']))
                                                        <span class="text-xs tabular-nums text-muted">{{ $ua->metadata['distance_meters'] }}m</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>
            @endif

        </div>

        {{-- Scoreboard link --}}
        <div class="flex flex-wrap items-center justify-center gap-3">
            <flux:button href="{{ route('live', $match) }}" variant="primary" size="sm" class="min-h-[44px] focus:ring-2 focus:ring-accent focus:outline-none {{ $isActive ? '!bg-green-600 hover:!bg-green-700' : '' }}">
                {{ $isActive ? 'Full Live Scoreboard' : 'TV Scoreboard' }}
            </flux:button>
            @if(!$isActive)
                <flux:button href="{{ route('scoreboard', $match) }}" variant="ghost" size="sm" class="min-h-[44px] focus:ring-2 focus:ring-accent focus:outline-none">
                    Scoreboard View
                </flux:button>
            @endif
        </div>
    @endif

    {{-- ══════════ REGISTRATION (pre-match statuses) ══════════ --}}
    @if(!$isActive && !$isCompleted)
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-primary">Registration</h2>
                <flux:badge size="sm" color="{{ $match->status->color() }}">{{ $match->status->label() }}</flux:badge>
            </div>

            @guest
                <p class="text-base text-muted">Sign in or create an account to register for this match.</p>
                <div class="flex flex-wrap gap-3">
                    <flux:button href="{{ route('login') }}" variant="primary" class="min-h-[44px] focus:ring-2 focus:ring-accent focus:outline-none">Sign In</flux:button>
                    <flux:button href="{{ route('register') }}" variant="ghost" class="min-h-[44px] focus:ring-2 focus:ring-accent focus:outline-none">Create Account</flux:button>
                </div>
            @endguest

            @auth
                @if($registration && $registration->isConfirmed())
                    <div class="rounded-lg border border-green-800 bg-green-900/20 p-4">
                        <div class="flex items-center gap-2">
                            <x-icon name="circle-check" class="h-5 w-5 text-green-400" />
                            <span class="text-sm font-medium text-green-400">Your registration is confirmed!</span>
                        </div>
                        <p class="mt-1 text-xs text-muted">Reference: {{ $registration->payment_reference }}</p>
                    </div>

                @elseif($registration && $registration->isPreRegistered())
                    <div class="rounded-lg border border-violet-800 bg-violet-900/20 p-4">
                        <div class="flex items-center gap-2">
                            <x-icon name="circle-check" class="h-5 w-5 text-violet-400" />
                            <span class="text-sm font-medium text-violet-400">You're pre-registered!</span>
                        </div>
                        <p class="mt-1 text-xs text-muted">You'll be notified when full registration opens.</p>
                    </div>
                    @if($match->isRegistrationOpen())
                        <flux:button href="{{ route('matches.show', $match) }}" variant="primary" class="min-h-[44px] !bg-accent hover:!bg-accent-hover focus:ring-2 focus:ring-accent focus:outline-none">
                            Complete Registration
                        </flux:button>
                    @endif

                @elseif($registration && $registration->isProofSubmitted())
                    <div class="rounded-lg border border-blue-800 bg-blue-900/20 p-4">
                        <div class="flex items-center gap-2">
                            <x-icon name="clock" class="h-5 w-5 text-blue-400" />
                            <span class="text-sm font-medium text-blue-400">Proof of payment under review.</span>
                        </div>
                        <p class="mt-1 text-xs text-muted">Reference: {{ $registration->payment_reference }}</p>
                    </div>

                @elseif($registration && $registration->isPending())
                    <div class="rounded-lg border border-amber-800 bg-amber-900/20 p-4">
                        <p class="text-base font-medium text-amber-400">Payment required — upload proof of payment to confirm.</p>
                        <p class="mt-1 text-xs text-muted">Reference: {{ $registration->payment_reference }}</p>
                    </div>
                    <flux:button href="{{ route('matches.show', $match) }}" variant="primary" class="min-h-[44px] !bg-accent hover:!bg-accent-hover focus:ring-2 focus:ring-accent focus:outline-none">
                        Upload Proof of Payment
                    </flux:button>

                @elseif($registration && $registration->isRejected())
                    <div class="rounded-lg border border-red-800 bg-red-900/20 p-4">
                        <div class="flex items-center gap-2">
                            <x-icon name="circle-x" class="h-5 w-5 text-red-400" />
                            <span class="text-sm font-medium text-red-400">Registration was rejected.</span>
                        </div>
                        @if($registration->admin_notes)
                            <p class="mt-1 text-xs text-muted">Reason: {{ $registration->admin_notes }}</p>
                        @endif
                    </div>

                @elseif($match->isRegistrationClosed())
                    <div class="rounded-lg border border-amber-800 bg-amber-900/20 p-4">
                        <p class="text-base font-medium text-amber-400">Registration is closed.</p>
                        <p class="mt-1 text-xs text-muted">This match is no longer accepting new entries.</p>
                    </div>

                @elseif($match->isPreRegistration())
                    <p class="text-base text-muted">Express your interest in this match. You'll be notified when full registration opens.</p>
                    <flux:button href="{{ route('matches.show', $match) }}" variant="primary" class="min-h-[44px] !bg-violet-600 hover:!bg-violet-700 focus:ring-2 focus:ring-accent focus:outline-none">
                        Show Interest
                    </flux:button>

                @elseif($match->isRegistrationOpen() && ! $match->isRegistrationPastDeadline())
                    <p class="text-base text-muted">Registration is open. Complete your equipment details and register.</p>
                    @php $closes = $match->registration_closes_at ?? $match->defaultRegistrationCloseDate(); @endphp
                    @if($closes)
                        <p class="text-xs text-muted">Closes <strong class="text-sky-300">{{ $closes->format('j M Y, H:i') }}</strong></p>
                    @endif
                    <flux:button href="{{ route('matches.show', $match) }}" variant="primary" class="min-h-[44px] !bg-accent hover:!bg-accent-hover focus:ring-2 focus:ring-accent focus:outline-none">
                        Register
                    </flux:button>

                @elseif($match->isRegistrationOpen() && $match->isRegistrationPastDeadline())
                    <p class="text-base text-muted">Registration has closed for this match.</p>

                @else
                    <p class="text-base text-muted">Registration is not yet open for this match.</p>
                @endif
            @endauth
        </div>
    @endif

    {{-- ══════════ REGISTERED SHOOTERS ══════════ --}}
    @if($registrants->isNotEmpty() && !$match->isPreRegistration())
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-semibold text-primary">Registered Shooters</h2>
                <span class="rounded-full bg-surface-2 px-2.5 py-0.5 text-xs font-bold text-secondary">{{ $registrants->count() }}</span>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach($registrants as $reg)
                    <span class="rounded-lg border border-border bg-surface-2/50 px-3 py-1.5 text-sm text-secondary">{{ $reg->user?->name ?? 'Unknown' }}</span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ══════════ SQUADS ══════════ --}}
    @if($showSquads && $squads->isNotEmpty())
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <h2 class="text-lg font-semibold text-primary">Squads</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach($squads as $squad)
                    <div class="rounded-lg border border-border bg-surface-2/40 p-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-primary">{{ $squad->name }}</h3>
                            <span class="text-xs text-muted">{{ $squad->shooters->count() }} shooters</span>
                        </div>
                        @if($squad->shooters->isNotEmpty())
                            <ul class="space-y-1">
                                @foreach($squad->shooters as $shooter)
                                    @php $isMe = auth()->check() && $shooter->user_id === auth()->id(); @endphp
                                    <li class="flex items-center gap-2 text-sm {{ $isMe ? 'text-green-400 font-medium' : 'text-secondary' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $isMe ? 'bg-green-400' : 'bg-surface-2' }}"></span>
                                        {{ $shooter->name }}
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-xs text-muted">No shooters assigned yet.</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ══════════ TEAMS (pre-match: view + link) ══════════ --}}
    @if($isTeamEvent && !$isActive && !$isCompleted && ($teams->isNotEmpty() || ($registration && $registration->isConfirmed())))
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <h2 class="text-lg font-semibold text-primary">Teams</h2>
            @php $myTeamId = $myShooter?->team_id; @endphp

            @if($myTeamId)
                @php $currentTeam = $teams->firstWhere('id', $myTeamId); @endphp
                @if($currentTeam)
                    <div class="rounded-lg border border-green-800 bg-green-900/20 p-4">
                        <p class="text-sm font-medium text-green-400">You're on team: {{ $currentTeam->name }}</p>
                        <p class="text-xs text-muted mt-1">{{ $currentTeam->shooters_count }}/{{ $currentTeam->effectiveMaxSize() }} members</p>
                    </div>
                @endif
            @endif

            @if($teams->isNotEmpty())
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach($teams as $team)
                        @php $isMyTeam = $myTeamId === $team->id; @endphp
                        <div class="rounded-lg border {{ $isMyTeam ? 'border-green-600 ring-2 ring-green-600/30' : 'border-border' }} bg-surface-2/40 p-4 {{ $team->isFull() && !$isMyTeam ? 'opacity-50' : '' }}">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold text-primary">{{ $team->name }}</span>
                                <span class="text-xs text-muted">{{ $team->shooters_count }}/{{ $team->effectiveMaxSize() }}</span>
                            </div>
                            @if($isMyTeam)
                                <p class="mt-1 text-center text-xs text-green-400/60">Your team</p>
                            @elseif($team->isFull())
                                <p class="mt-1 text-center text-xs text-muted">Full</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @auth
                @if($registration && $registration->isConfirmed() && $myShooter && !$myTeamId)
                    <p class="text-sm text-muted">
                        <a href="{{ route('matches.show', $match) }}" class="text-accent underline-offset-2 hover:underline focus:outline-none focus:ring-2 focus:ring-accent rounded">Go to your registration</a> to join or create a team.
                    </p>
                @endif
            @endauth
        </div>
    @endif
</div>
