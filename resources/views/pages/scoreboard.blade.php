<?php

use App\Models\ShootingMatch;
use App\Models\Score;
use App\Models\StageTime;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.scoreboard')]
    class extends Component {
    public ShootingMatch $match;
    public ?int $activeDivision = null;
    public ?int $activeCategory = null;
    public string $activeTab = 'main';
    public ?int $rfDistanceFilter = null;
    public ?int $expandedShooterId = null;

    public function toggleExpand(int $id): void
    {
        $this->expandedShooterId = $this->expandedShooterId === $id ? null : $id;
    }

    public function filterDivision(?int $id): void
    {
        $this->activeDivision = $id;
    }

    public function filterCategory(?int $id): void
    {
        $this->activeCategory = $id;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function with(): array
    {
        $isPrs = $this->match->isPrs();

        $usedDivisionIds = $this->match->shooters()->whereNotNull('match_division_id')->distinct()->pluck('match_division_id')->toArray();
        $divisions = $this->match->divisions()->whereIn('id', $usedDivisionIds)->orderBy('sort_order')->get();

        $usedCategoryIds = DB::table('match_category_shooter')
            ->whereIn('shooter_id', $this->match->shooters()->pluck('shooters.id'))
            ->distinct()
            ->pluck('match_category_id')
            ->toArray();
        $categories = $this->match->categories()->whereIn('id', $usedCategoryIds)->orderBy('sort_order')->get();

        $shooterTimes = [];
        $tbHits = [];
        $tbTimes = [];
        $totalTargets = 0;

        if ($isPrs) {
            $targetSets = $this->match->targetSets()->get();
            $targetSetIds = $targetSets->pluck('id');
            $tiebreakerStage = $targetSets->firstWhere('is_tiebreaker', true);
            $totalTargets = \App\Models\Gong::whereIn('target_set_id', $targetSetIds)->count();

            $shooterTimes = \App\Models\PrsStageResult::where('match_id', $this->match->id)
                ->whereNotNull('official_time_seconds')
                ->select('shooter_id', DB::raw('SUM(official_time_seconds) as total_time'))
                ->groupBy('shooter_id')
                ->pluck('total_time', 'shooter_id')
                ->toArray();

            if ($tiebreakerStage) {
                $tbResults = \App\Models\PrsStageResult::where('match_id', $this->match->id)
                    ->where('stage_id', $tiebreakerStage->id)
                    ->get();

                $tbHits = $tbResults->pluck('hits', 'shooter_id')
                    ->map(fn ($v) => (int) $v)
                    ->toArray();

                $tbTimes = $tbResults
                    ->filter(fn ($r) => $r->official_time_seconds !== null)
                    ->pluck('official_time_seconds', 'shooter_id')
                    ->map(fn ($v) => (float) $v)
                    ->toArray();
            }
        }

        $shooterQuery = $this->match->shooters()
            ->with(['squad', 'division']);

        if (!$isPrs) {
            $shooterQuery->withCount([
                'scores as hits_count' => fn ($q) => $q->where('is_hit', true),
                'scores as misses_count' => fn ($q) => $q->where('is_hit', false),
            ]);
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

        $prsHitsMap = [];
        $prsMissesMap = [];
        if ($isPrs) {
            $prsAgg = \App\Models\PrsStageResult::where('match_id', $this->match->id)
                ->select('shooter_id', DB::raw('SUM(hits) as total_hits'), DB::raw('SUM(misses) as total_misses'))
                ->groupBy('shooter_id')
                ->get();
            foreach ($prsAgg as $row) {
                $prsHitsMap[$row->shooter_id] = (int) $row->total_hits;
                $prsMissesMap[$row->shooter_id] = (int) $row->total_misses;
            }
        }

        $prsShots = collect();
        $prsTargetSets = collect();
        if ($isPrs) {
            foreach ($targetSets as $ts) {
                $ts->gongs_count = \App\Models\Gong::where('target_set_id', $ts->id)->count();
            }
            $prsTargetSets = $targetSets;
            $prsShots = \App\Models\PrsShotScore::where('match_id', $this->match->id)
                ->orderBy('shot_number')
                ->get()
                ->groupBy('shooter_id');
        }

        $shooters = $shooterQuery->get()
            ->map(function ($shooter) use ($isPrs, $shooterTimes, $tbHits, $tbTimes, $totalTargets, $prsHitsMap, $prsMissesMap, $prsShots) {
                if ($isPrs) {
                    $shooter->hits_count = $prsHitsMap[$shooter->id] ?? 0;
                    $shooter->misses_count = $prsMissesMap[$shooter->id] ?? 0;
                    $shooter->display_score = $shooter->hits_count;
                    $shooter->display_time = (float) ($shooterTimes[$shooter->id] ?? 0);
                    $shooter->tb_hits = $tbHits[$shooter->id] ?? 0;
                    $shooter->tb_time = (float) ($tbTimes[$shooter->id] ?? 0);
                    $shooter->not_taken = $totalTargets - $shooter->hits_count - $shooter->misses_count;

                    $shooterShotList = $prsShots->get($shooter->id, collect());
                    $grid = [];
                    foreach ($shooterShotList as $shot) {
                        $result = $shot->result instanceof \BackedEnum ? $shot->result->value : (string) $shot->result;
                        $grid[$shot->stage_id][$shot->shot_number] = $result;
                    }
                    $shooter->shot_grid = $grid;
                } else {
                    $shooter->display_score = (float) $shooter->scores()
                        ->where('is_hit', true)
                        ->join('gongs', 'scores.gong_id', '=', 'gongs.id')
                        ->sum('gongs.multiplier');
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

            $maxPrsHits = (int) $shooters->max('hits_count');
            foreach ($shooters as $s) {
                $s->prs_points = $maxPrsHits > 0
                    ? round($s->hits_count / $maxPrsHits * 100, 2)
                    : 0.0;
            }
        } else {
            $shooters = $shooters->sortByDesc('display_score')->values();
        }

        $royalFlushEnabled = !$isPrs && (bool) $this->match->royal_flush_enabled;
        $royalFlushEntries = collect();
        $rfDistances = collect();

        if ($royalFlushEnabled) {
            $rfTargetSets = $this->match->targetSets()
                ->orderByDesc('distance_meters')
                ->with('gongs')
                ->get();

            $rfDistances = $rfTargetSets->pluck('distance_meters')->map(fn ($d) => (int) $d)->unique()->sortDesc()->values();

            $allShooterIds = $shooters->pluck('id')->toArray();
            $rfGongIds = $rfTargetSets->flatMap(fn ($ts) => $ts->gongs->pluck('id'))->toArray();

            $rfHits = \App\Models\Score::whereIn('gong_id', $rfGongIds)
                ->whereIn('shooter_id', $allShooterIds)
                ->where('is_hit', true)
                ->select('shooter_id', 'gong_id')
                ->get();

            $gongToTs = [];
            foreach ($rfTargetSets as $ts) {
                foreach ($ts->gongs as $g) {
                    $gongToTs[$g->id] = $ts->id;
                }
            }

            $hitCountByShooterTs = [];
            foreach ($rfHits as $hit) {
                $tsId = $gongToTs[$hit->gong_id] ?? null;
                if ($tsId === null) continue;
                $hitCountByShooterTs[$hit->shooter_id][$tsId] =
                    ($hitCountByShooterTs[$hit->shooter_id][$tsId] ?? 0) + 1;
            }

            $rfProfiles = [];
            foreach ($shooters as $s) {
                $flushDistances = [];
                foreach ($rfTargetSets as $ts) {
                    $gongCount = $ts->gongs->count();
                    $hitsAtTs = $hitCountByShooterTs[$s->id][$ts->id] ?? 0;
                    if ($gongCount > 0 && $hitsAtTs >= $gongCount) {
                        $flushDistances[] = (int) $ts->distance_meters;
                    }
                }
                $rfProfiles[] = [
                    'shooter' => $s,
                    'flush_count' => count($flushDistances),
                    'flush_distances' => $flushDistances,
                ];
            }

            if ($this->rfDistanceFilter) {
                $rfProfiles = array_filter($rfProfiles, fn ($p) => in_array($this->rfDistanceFilter, $p['flush_distances']));
            }

            usort($rfProfiles, function ($a, $b) {
                if ($a['flush_count'] !== $b['flush_count']) return $b['flush_count'] <=> $a['flush_count'];
                $aMax = !empty($a['flush_distances']) ? max($a['flush_distances']) : 0;
                $bMax = !empty($b['flush_distances']) ? max($b['flush_distances']) : 0;
                if ($aMax !== $bMax) return $bMax <=> $aMax;
                return $b['shooter']->display_score <=> $a['shooter']->display_score;
            });

            $royalFlushEntries = collect(array_values($rfProfiles))->map(fn ($p, $i) => (object) [
                'rank' => $i + 1,
                'name' => $p['shooter']->name,
                'user_id' => $p['shooter']->user_id,
                'squad_name' => $p['shooter']->squad?->name ?? '—',
                'flush_count' => $p['flush_count'],
                'flush_distances' => $p['flush_distances'],
                'total_score' => $p['shooter']->display_score,
            ]);
        }

        $isStandard = !$isPrs && !$this->match->isElr();
        $detailedData = collect();
        $targetSetDetails = collect();

        if ($isStandard) {
            $targetSetsForDetail = $this->match->targetSets()
                ->orderBy('sort_order')
                ->with(['gongs' => fn ($q) => $q->orderBy('number')])
                ->get();

            $targetSetDetails = $targetSetsForDetail;

            $allGongIds = $targetSetsForDetail->flatMap(fn ($ts) => $ts->gongs->pluck('id'));
            $allScores = Score::whereIn('gong_id', $allGongIds)
                ->whereIn('shooter_id', $shooters->pluck('id'))
                ->get()
                ->keyBy(fn ($s) => "{$s->shooter_id}-{$s->gong_id}");

            $detailedData = $shooters->map(function ($shooter) use ($targetSetsForDetail, $allScores) {
                $distances = [];
                $totalHits = 0;
                $totalMisses = 0;
                $totalScore = 0;

                foreach ($targetSetsForDetail as $ts) {
                    $distMult = (float) ($ts->distance_multiplier ?? 1);
                    $gongs = [];
                    $distHits = 0;
                    $distMisses = 0;
                    $distSubtotal = 0;

                    foreach ($ts->gongs as $gong) {
                        $score = $allScores->get("{$shooter->id}-{$gong->id}");
                        $points = round($distMult * $gong->multiplier, 2);
                        $isHit = $score ? (bool) $score->is_hit : null;

                        if ($score) {
                            if ($isHit) {
                                $distHits++;
                                $totalHits++;
                                $distSubtotal += $points;
                                $totalScore += $points;
                            } else {
                                $distMisses++;
                                $totalMisses++;
                            }
                        }

                        $gongs[] = (object) [
                            'gong_id' => $gong->id,
                            'number' => $gong->number,
                            'label' => $gong->label,
                            'multiplier' => $gong->multiplier,
                            'points' => $points,
                            'is_hit' => $isHit,
                        ];
                    }

                    $distances[$ts->id] = (object) [
                        'target_set_id' => $ts->id,
                        'label' => $ts->label,
                        'distance_meters' => $ts->distance_meters,
                        'hits' => $distHits,
                        'misses' => $distMisses,
                        'subtotal' => round($distSubtotal, 2),
                        'gongs' => $gongs,
                    ];
                }

                return (object) [
                    'shooter' => $shooter,
                    'distances' => $distances,
                    'total_hits' => $totalHits,
                    'total_misses' => $totalMisses,
                    'total_score' => round($totalScore, 2),
                ];
            })->sortByDesc('total_score')->values();
        }

        $matchBadges = collect();
        if ($isPrs || $royalFlushEnabled) {
            $competitionType = $isPrs ? 'prs' : 'royal_flush';
            $matchBadges = \App\Models\UserAchievement::where('match_id', $this->match->id)
                ->whereHas('achievement', fn ($q) => $q->where('competition_type', $competitionType))
                ->with([
                    'achievement',
                    'user:id,name',
                    'stage:id,label,stage_number,distance_meters,is_tiebreaker',
                    'shooter:id,name',
                ])
                ->orderBy('awarded_at')
                ->get();
        }

        // Custom registration field values visible on scoreboard
        $scoreboardFields = $this->match->customFields()
            ->where('show_on_scoreboard', true)
            ->orderBy('sort_order')
            ->get();

        $customFieldMap = [];
        if ($scoreboardFields->isNotEmpty()) {
            $registrations = \App\Models\MatchRegistration::where('match_id', $this->match->id)
                ->with(['customValues' => fn ($q) => $q->whereIn('match_custom_field_id', $scoreboardFields->pluck('id'))])
                ->get()
                ->keyBy('user_id');

            foreach ($shooters as $s) {
                if (! $s->user_id) continue;
                $reg = $registrations->get($s->user_id);
                if (! $reg) continue;
                $values = [];
                foreach ($reg->customValues as $cv) {
                    $field = $scoreboardFields->firstWhere('id', $cv->match_custom_field_id);
                    if ($field && $cv->value) {
                        $values[] = ['label' => $field->label, 'value' => $cv->value];
                    }
                }
                if (! empty($values)) {
                    $customFieldMap[$s->id] = $values;
                }
            }
        }

        $isTeamEvent = $this->match->isTeamEvent();
        $teamLeaderboard = collect();
        if ($isTeamEvent) {
            $teams = $this->match->teams()
                ->with(['shooters' => fn ($q) => $q->with('squad')])
                ->orderBy('sort_order')
                ->get();

            $teamLeaderboard = $teams->map(function ($team) use ($shooters, $isPrs) {
                $memberScores = $team->shooters->map(function ($member) use ($shooters, $isPrs) {
                    $scored = $shooters->firstWhere('id', $member->id);
                    return (object) [
                        'name' => $member->name,
                        'score' => $scored?->display_score ?? 0,
                    ];
                })->sortByDesc('score')->values();

                return (object) [
                    'team' => $team,
                    'members' => $memberScores,
                    'total_score' => $memberScores->sum('score'),
                    'member_count' => $memberScores->count(),
                ];
            })->sortByDesc('total_score')->values();
        }

        return [
            'shooters' => $shooters,
            'isPrs' => $isPrs,
            'isStandard' => $isStandard,
            'divisions' => $divisions,
            'categories' => $categories,
            'royalFlushEnabled' => $royalFlushEnabled,
            'rfDistances' => $rfDistances,
            'royalFlushEntries' => $royalFlushEntries,
            'detailedData' => $detailedData,
            'targetSetDetails' => $targetSetDetails,
            'prsTargetSets' => $prsTargetSets,
            'matchBadges' => $matchBadges,
            'customFieldMap' => $customFieldMap,
            'isTeamEvent' => $isTeamEvent,
            'teamLeaderboard' => $teamLeaderboard,
        ];
    }
}; ?>

<div wire:poll.5s class="scoreboard-page min-h-screen min-w-0 max-w-full overflow-x-hidden bg-app text-primary px-3 py-4 sm:px-6 lg:p-10">
    <div class="mb-6 flex flex-col gap-4 sm:mb-8 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <h1 class="max-w-full text-2xl font-black leading-tight tracking-tight break-words sm:text-4xl lg:text-5xl">{{ $match->name }}</h1>
                @if($isPrs)
                    <span class="shrink-0 rounded bg-amber-600 px-2 py-1 text-xs font-bold uppercase">PRS</span>
                @endif
                @if($match->status === \App\Enums\MatchStatus::Active)
                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-red-500/10 px-2.5 py-1 sm:px-3">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
                            <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-600"></span>
                        </span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-red-500 sm:text-xs">Live</span>
                    </span>
                @endif
            </div>
            <p class="mt-2 text-sm text-muted sm:mt-1 sm:text-lg">
                {{ $match->date?->format('d M Y') }}
                @if($match->location) &mdash; {{ $match->location }} @endif
            </p>
            <p class="mt-0.5 text-xs text-muted/50">Last updated: {{ now()->format('H:i:s') }}</p>
            <x-powered-by-block feature="results" :match-id="$match->id" variant="inline" />
            @if($match->status === \App\Enums\MatchStatus::Completed)
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('scoreboard.export.standings', $match) }}"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-secondary transition-colors hover:bg-surface-2 hover:text-primary">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                        Standings CSV
                    </a>
                    <a href="{{ route('scoreboard.export.detailed', $match) }}"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-secondary transition-colors hover:bg-surface-2 hover:text-primary">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                        Full Results CSV
                    </a>
                </div>
            @endif
        </div>
        <x-app-logo
            size="lg"
            class="shrink-0 self-start opacity-60 max-sm:origin-top-left max-sm:scale-[0.82]"
        />
    </div>

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

    @if($isStandard || $royalFlushEnabled || $isTeamEvent)
        <div class="mb-4 flex min-w-0 flex-wrap gap-2">
            <button type="button" wire:click="setTab('main')"
                    class="min-w-0 flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors sm:flex-none sm:px-5 sm:py-2.5 sm:text-sm {{ $activeTab === 'main' ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
                Leaderboard
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
                <button type="button" wire:click="setTab('badges')"
                        class="min-w-0 flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors sm:flex-none sm:px-5 sm:py-2.5 sm:text-sm {{ $activeTab === 'badges' ? 'bg-amber-600 text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
                    Badges Awarded
                </button>
            @endif
        </div>
    @endif

    @if($isTeamEvent && $activeTab === 'teams')
        <div class="space-y-4">
            @forelse($teamLeaderboard as $index => $entry)
                <div class="rounded-2xl border border-border bg-surface overflow-hidden" x-data="{ open: false }">
                    <button @click="open = !open" class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-surface-2/50 transition-colors sm:px-6 sm:py-4">
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
                            <span class="text-lg font-black text-amber-400 tabular-nums sm:text-2xl">{{ $isPrs ? $entry->total_score : number_format($entry->total_score, 1) }}</span>
                            <svg class="h-5 w-5 text-muted transition-transform" :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </div>
                    </button>
                    <div x-show="open" x-collapse>
                        <div class="border-t border-border px-4 py-3 sm:px-6">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-muted"><th class="pb-1 font-medium">Shooter</th><th class="pb-1 font-medium text-right">Score</th></tr>
                                </thead>
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
    @elseif($isStandard && $activeTab === 'detailed')
        <div class="space-y-3">
            @forelse($detailedData as $index => $entry)
                @php
                    $rank = $index + 1;
                    $isExpanded = $expandedShooterId === $entry->shooter->id;
                @endphp
                <div class="overflow-hidden rounded-2xl border border-border bg-app">
                    <button type="button" wire:click="toggleExpand({{ $entry->shooter->id }})"
                            class="flex w-full items-center gap-3 px-3 py-3 text-left transition-colors hover:bg-surface/50 sm:gap-4 sm:px-6 sm:py-4">
                        @if($rank <= 3)
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
                            <p class="truncate text-xl font-semibold text-primary">{{ $entry->shooter->name }}</p>
                            <p class="text-sm text-muted">
                                {{ $entry->shooter->squad?->name ?? '—' }}
                                &middot; {{ $entry->total_hits }} hits &middot; {{ $entry->total_misses }} misses
                            </p>
                        </div>

                        <span class="text-lg font-black text-amber-400 tabular-nums sm:text-2xl">{{ number_format($entry->total_score, 1) }}</span>

                        <svg class="h-5 w-5 flex-shrink-0 text-muted transition-transform sm:h-6 sm:w-6 {{ $isExpanded ? 'rotate-180' : '' }}"
                             fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    @if($isExpanded)
                        <div class="border-t border-border">
                            @foreach($targetSetDetails as $ts)
                                @php $dist = $entry->distances[$ts->id] ?? null; @endphp
                                @if($dist)
                                    <div class="border-b border-border/50 last:border-b-0">
                                        <div class="flex items-center justify-between bg-surface/40 px-6 py-3">
                                            <span class="text-base font-semibold text-primary">{{ $ts->label }} ({{ $ts->distance_meters }}m)</span>
                                            <div class="flex items-center gap-4 text-sm">
                                                <span class="text-green-400 font-medium">{{ $dist->hits }} hits</span>
                                                <span class="text-accent font-medium">{{ $dist->misses }} miss</span>
                                                <span class="font-bold text-amber-400">{{ number_format($dist->subtotal, 1) }} pts</span>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 gap-px bg-border/30 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                                            @foreach($dist->gongs as $gong)
                                                <div class="flex items-center justify-between bg-app px-4 py-3 text-sm">
                                                    <span class="text-muted">
                                                        #{{ $gong->number }}
                                                        @if($gong->label) <span class="text-secondary">({{ $gong->label }})</span> @endif
                                                    </span>
                                                    @if($gong->is_hit === true)
                                                        <span class="font-bold text-green-400">HIT +{{ number_format($gong->points, 1) }}</span>
                                                    @elseif($gong->is_hit === false)
                                                        <span class="font-bold text-accent">MISS</span>
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
            <div class="mb-4 flex flex-wrap gap-2">
                <button type="button" wire:click="$set('rfDistanceFilter', null)"
                        class="rounded-full px-4 py-1.5 text-xs font-bold transition-colors {{ $rfDistanceFilter === null ? 'bg-amber-600 text-white' : 'bg-surface text-muted hover:bg-surface-2' }}">
                    All
                </button>
                @foreach($rfDistances as $dist)
                    <button type="button" wire:click="$set('rfDistanceFilter', {{ $dist }})"
                            class="rounded-full px-4 py-1.5 text-xs font-bold transition-colors {{ $rfDistanceFilter === $dist ? 'bg-amber-600 text-white' : 'bg-surface text-muted hover:bg-surface-2' }}">
                        {{ $dist }}m
                    </button>
                @endforeach
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
                        @endphp
                        <tr class="{{ $rowClass }} transition-colors">
                            <td class="px-3 py-2 text-lg {{ $rankClass }} sm:px-6 sm:py-4 sm:text-2xl lg:text-3xl">{{ $entry->rank }}</td>
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
    @elseif($royalFlushEnabled && $activeTab === 'badges')
        @php
            $badgesByCategory = $matchBadges->groupBy(fn ($ua) => $ua->achievement?->category ?? 'unknown');
            $matchSpecials = $badgesByCategory->get('match_special', collect());
            $lifetimeBadges = $badgesByCategory->get('lifetime', collect());
            $repeatableBadges = $badgesByCategory->get('repeatable', collect());
            $hasBadges = $matchBadges->isNotEmpty();
            $bcfg = \App\Http\Controllers\BadgeGalleryController::BADGE_CONFIG;
        @endphp

        @if(!$hasBadges)
            <div class="flex flex-col items-center justify-center rounded-2xl border border-border bg-surface/30 px-6 py-16 text-center">
                <x-badge-icon name="award" class="mb-3 h-10 w-10 text-muted opacity-40" />
                <h3 class="text-lg font-bold text-primary">No badges awarded yet</h3>
                <p class="mt-2 max-w-sm text-sm text-muted">Badges earned during this match will appear here once scoring has been finalized.</p>
            </div>
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
                @elseif($this->match->side_bet_enabled)
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
                                <div class="flex items-center gap-4 rounded-2xl border border-amber-400/15 bg-amber-900/8 px-4 py-4">
                                    <x-badge-crest :icon="$icon" :tier="$tier" family="royal_flush" />
                                    <div class="min-w-0 flex-1">
                                        <span class="text-base font-bold text-amber-200">{{ $badge->label }}</span>
                                        <p class="mt-0.5 text-xs text-muted leading-snug">{{ $badge->description }}</p>
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
                                    <div class="flex items-center gap-4 border-b border-amber-500/10 px-4 py-3">
                                        <x-badge-crest :icon="$icon" :tier="$tier" family="royal_flush" />
                                        <div class="min-w-0 flex-1">
                                            <span class="text-base font-bold text-amber-200">{{ $badge->label }}</span>
                                            <p class="mt-0.5 text-xs text-muted leading-snug">{{ $badge->description }}</p>
                                        </div>
                                        <span class="rounded-full bg-amber-600/20 px-2.5 py-1 text-xs font-bold tabular-nums text-amber-400">{{ $entries->count() }}&times;</span>
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

            </div>
        @endif
    @else
    @if($isPrs)
    <div x-data="{ prsTab: 'leaderboard' }" class="min-w-0">
        <div class="mb-4 flex min-w-0 gap-1.5">
            <button type="button" @click="prsTab = 'leaderboard'" :class="prsTab === 'leaderboard' ? 'bg-red-600 text-white' : 'bg-zinc-800 text-zinc-400 hover:bg-zinc-700'" class="min-w-0 flex-1 rounded-lg px-2 py-2 text-[11px] font-bold transition-colors sm:px-3 sm:text-xs">Leaderboard</button>
            <button type="button" @click="prsTab = 'scoresheet'" :class="prsTab === 'scoresheet' ? 'bg-red-600 text-white' : 'bg-zinc-800 text-zinc-400 hover:bg-zinc-700'" class="min-w-0 flex-1 rounded-lg px-2 py-2 text-[11px] font-bold transition-colors sm:px-3 sm:text-xs">Score Sheet</button>
            <button type="button" @click="prsTab = 'badges'" :class="prsTab === 'badges' ? 'bg-amber-600 text-white' : 'bg-zinc-800 text-zinc-400 hover:bg-zinc-700'" class="min-w-0 flex-1 rounded-lg px-2 py-2 text-[11px] font-bold transition-colors sm:px-3 sm:text-xs">Badges Awarded</button>
            @if($isTeamEvent)
                <button type="button" @click="prsTab = 'teams'" :class="prsTab === 'teams' ? 'bg-indigo-600 text-white' : 'bg-zinc-800 text-zinc-400 hover:bg-zinc-700'" class="min-w-0 flex-1 rounded-lg px-2 py-2 text-[11px] font-bold transition-colors sm:px-3 sm:text-xs">Teams</button>
            @endif
        </div>
        <div x-show="prsTab === 'leaderboard'">
    @endif
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
                    <th class="px-2 py-2 text-center text-xs font-bold text-green-400 sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Hits</th>
                    <th class="px-2 py-2 text-center text-xs font-bold text-accent sm:px-6 sm:py-4 sm:text-lg lg:text-xl">Miss</th>
                    @if($isPrs)
                        <th class="px-2 py-2 text-center text-xs font-bold text-amber-400/60 sm:px-6 sm:py-4 sm:text-lg lg:text-xl">N/T</th>
                    @endif
                    @if($isPrs)
                        <th class="px-2 py-2 text-right text-xs font-bold text-secondary sm:px-6 sm:py-4 sm:text-lg lg:text-xl">TB Time</th>
                    @endif
                    <th class="px-2 py-2 text-right text-xs font-bold text-amber-400 sm:px-6 sm:py-4 sm:text-lg lg:text-xl">{{ $isPrs ? 'Pts' : 'Score' }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($shooters as $index => $shooter)
                    @php
                        $rank = $index + 1;
                        $rowClass = match($rank) {
                            1 => 'bg-amber-500/10 border-l-4 border-l-amber-400',
                            2 => 'bg-slate-400/5 border-l-4 border-l-slate-400',
                            3 => 'bg-orange-500/5 border-l-4 border-l-orange-600',
                            default => 'border-l-4 border-l-transparent',
                        };
                        $rankClass = match($rank) {
                            1 => 'text-amber-400 font-black',
                            2 => 'text-secondary font-bold',
                            3 => 'text-orange-500 font-bold',
                            default => 'text-muted font-medium',
                        };
                    @endphp
                    <tr class="{{ $rowClass }} transition-colors">
                        <td class="px-2 py-2 text-lg {{ $rankClass }} sm:px-6 sm:py-4 sm:text-2xl lg:text-3xl">{{ $rank }}</td>
                        <td class="max-w-[7rem] px-2 py-2 text-sm font-semibold sm:max-w-[12rem] sm:px-6 sm:py-4 sm:text-xl lg:max-w-none lg:text-2xl">
                            @if($shooter->user_id)
                                <a href="{{ route('shooter.profile', $shooter->user_id) }}" class="line-clamp-2 text-primary hover:underline sm:line-clamp-none" title="{{ $shooter->name }}">{{ $shooter->name }}</a>
                            @else
                                <span class="line-clamp-2 text-primary sm:line-clamp-none" title="{{ $shooter->name }}">{{ $shooter->name }}</span>
                            @endif
                            @if($shooter->user_id)
                                <x-shooter-badges :userId="$shooter->user_id" :matchId="$match->id" :competitionType="$isPrs ? 'prs' : ($royalFlushEnabled ? 'royal_flush' : null)" :compact="true" />
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
                        @if($isPrs)
                            <td class="px-2 py-2 text-center text-base font-bold text-amber-400/60 sm:px-6 sm:py-4 sm:text-xl lg:text-2xl">{{ $shooter->not_taken ?? 0 }}</td>
                        @endif
                        @if($isPrs)
                            <td class="whitespace-nowrap px-2 py-2 text-right text-xs font-mono text-secondary sm:px-6 sm:py-4 sm:text-xl lg:text-2xl">
                                @if($shooter->tb_time > 0)
                                    {{ number_format($shooter->tb_time, 1) }}s
                                @else
                                    —
                                @endif
                            </td>
                        @endif
                        <td class="whitespace-nowrap px-2 py-2 text-right text-lg font-black text-amber-400 sm:px-6 sm:py-4 sm:text-2xl lg:text-3xl">
                            {{ $isPrs ? number_format($shooter->prs_points ?? 0, 2) : number_format($shooter->display_score, 1) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ ($isPrs ? 8 : 6) + ($divisions->isNotEmpty() ? 1 : 0) }}" class="px-4 py-12 text-center text-sm text-muted sm:px-6 sm:py-16 sm:text-2xl">
                            No scores recorded yet
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($isPrs)
        </div>
        <div x-show="prsTab === 'scoresheet'" x-cloak class="min-w-0">
            <div class="overflow-x-auto rounded-2xl border border-zinc-700 bg-zinc-800/50 [-webkit-overflow-scrolling:touch]">
                <table class="w-full text-[11px] leading-tight">
                    <thead>
                        <tr class="border-b border-zinc-700">
                            <th class="relative z-0 w-10 min-w-10 bg-zinc-800 px-2 py-2 text-left text-zinc-500 sm:sticky sm:left-0 sm:z-20 sm:shadow-[4px_0_12px_-6px_rgba(0,0,0,0.65)]">#</th>
                            <th class="relative z-0 min-w-[7rem] bg-zinc-800 px-2 py-2 text-left text-zinc-500 sm:sticky sm:left-10 sm:z-20 sm:shadow-[4px_0_12px_-6px_rgba(0,0,0,0.65)]">Shooter</th>
                            @foreach($prsTargetSets as $ts)
                                <th colspan="{{ $ts->gongs_count }}" class="px-1 py-2 text-center border-l border-zinc-700/50 {{ $ts->is_tiebreaker ? 'text-amber-400 bg-amber-900/10' : 'text-zinc-500' }}">
                                    {{ $ts->label }}
                                </th>
                            @endforeach
                            <th class="px-2 py-2 text-center font-bold text-zinc-500 border-l border-zinc-700">Total</th>
                            <th class="px-2 py-2 text-center text-zinc-500">Time</th>
                        </tr>
                        <tr class="border-b border-zinc-700 text-[9px] text-zinc-600">
                            <th class="relative z-0 bg-zinc-800 sm:sticky sm:left-0 sm:z-20 sm:shadow-[4px_0_12px_-6px_rgba(0,0,0,0.65)]"></th>
                            <th class="relative z-0 bg-zinc-800 sm:sticky sm:left-10 sm:z-20 sm:shadow-[4px_0_12px_-6px_rgba(0,0,0,0.65)]"></th>
                            @foreach($prsTargetSets as $ts)
                                @for($g = 1; $g <= $ts->gongs_count; $g++)
                                    <th class="px-0.5 py-1 text-center {{ $g === 1 ? 'border-l border-zinc-700/50' : '' }}">{{ $g }}</th>
                                @endfor
                            @endforeach
                            <th class="border-l border-zinc-700"></th>
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
                                    <td class="px-0 py-1.5 text-center {{ $g === 1 ? 'border-l border-zinc-700/50' : '' }}">
                                        @if($shotResult === 'hit')
                                            <span class="inline-block h-3 w-3 rounded-full bg-green-500"></span>
                                        @elseif($shotResult === 'miss')
                                            <span class="inline-block h-3 w-3 rounded-full bg-red-500"></span>
                                        @elseif($shotResult === 'not_taken')
                                            <span class="inline-block h-3 w-3 rounded-full bg-amber-500/40"></span>
                                        @else
                                            <span class="inline-block h-3 w-3 rounded-full bg-zinc-700"></span>
                                        @endif
                                    </td>
                                @endfor
                            @endforeach
                            {{-- Total column: raw hit count (per-gong grid context) --}}
                            <td class="px-2 py-1.5 text-center font-bold text-white tabular-nums border-l border-zinc-700">{{ $shooter->hits_count }}</td>
                            <td class="px-2 py-1.5 text-center tabular-nums text-zinc-500">{{ $shooter->tb_time > 0 ? number_format($shooter->tb_time, 1) . 's' : '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ============ BADGES AWARDED TAB ============ --}}
        <div x-show="prsTab === 'badges'" x-cloak class="min-w-0">
            @php
                $badgesByCategory = $matchBadges->groupBy(fn ($ua) => $ua->achievement?->category ?? 'unknown');
                $matchSpecials = $badgesByCategory->get('match_special', collect());
                $lifetimeBadges = $badgesByCategory->get('lifetime', collect());
                $repeatableBadges = $badgesByCategory->get('repeatable', collect());
                $hasBadges = $matchBadges->isNotEmpty();
                $bcfg = \App\Http\Controllers\BadgeGalleryController::BADGE_CONFIG;
            @endphp

            @if(!$hasBadges)
                <div class="flex flex-col items-center justify-center rounded-2xl border border-zinc-700 bg-zinc-800/30 px-6 py-16 text-center">
                    <x-badge-icon name="award" class="mx-auto mb-3 h-10 w-10 text-zinc-600 opacity-40" />
                    <h3 class="text-lg font-bold text-zinc-300">No badges awarded yet</h3>
                    <p class="mt-2 max-w-sm text-sm text-zinc-500">Badges earned during this PRS match will appear here once scoring has been finalized.</p>
                </div>
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
                                    <div class="flex items-center gap-4 rounded-2xl border border-sky-400/15 bg-sky-900/8 px-4 py-4">
                                        <x-badge-crest :icon="$icon" :tier="$tier" family="prs" />
                                        <div class="min-w-0 flex-1">
                                            <span class="text-base font-bold text-sky-200">{{ $badge->label }}</span>
                                            <p class="mt-0.5 text-xs text-zinc-400 leading-snug">{{ $badge->description }}</p>
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
                                        <div class="flex items-center gap-4 border-b border-sky-500/10 px-4 py-3">
                                            <x-badge-crest :icon="$icon" :tier="$tier" family="prs" />
                                            <div class="min-w-0 flex-1">
                                                <span class="text-base font-bold text-sky-200">{{ $badge->label }}</span>
                                                <p class="mt-0.5 text-xs text-zinc-500 leading-snug">{{ $badge->description }}</p>
                                            </div>
                                            <span class="rounded-full bg-sky-600/20 px-2.5 py-1 text-xs font-bold tabular-nums text-sky-400">{{ $entries->count() }}&times;</span>
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

                </div>
            @endif
        </div>

        {{-- PRS Teams Tab --}}
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
                                <svg class="h-5 w-5 text-zinc-500 transition-transform" :class="tOpen && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
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
    @endif

    @endif

    <div class="mt-6 flex flex-col gap-2 text-xs text-muted/60 sm:flex-row sm:items-center sm:justify-between sm:text-sm">
        <span class="min-w-0 leading-snug">
            Auto-refreshes every 5 seconds
            @if($isPrs) &bull; Ranked by total hits, then tiebreaker stage hits, then tiebreaker stage time @endif
        </span>
        <span class="shrink-0">&copy; {{ date('Y') }} DeadCenter</span>
    </div>
</div>
