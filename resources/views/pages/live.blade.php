<?php

use App\Models\ShootingMatch;
use App\Models\Score;
use App\Models\Gong;
use App\Models\PrsStageResult;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.scoreboard')]
    class extends Component {
    public ShootingMatch $match;
    public ?int $activeDivision = null;
    public ?int $activeCategory = null;
    public string $activeTab = 'main';

    public function mount(ShootingMatch $match): void
    {
        $this->match = $match;
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
        $scoresPublished = $this->match->scoresArePublic();

        if (! $scoresPublished) {
            return $this->activityData($isPrs);
        }

        return $this->leaderboardData($isPrs);
    }

    // ── Activity View data (scores hidden) ──

    private function activityData(bool $isPrs): array
    {
        $targetSets = $this->match->targetSets()
            ->orderBy('sort_order')
            ->with('gongs')
            ->get();

        $activeShooters = $this->match->shooters()->where('shooters.status', 'active')->count();
        $activeShooterIds = $this->match->shooters()->where('shooters.status', 'active')
            ->pluck('shooters.id')->toArray();
        $stageCount = $targetSets->count();
        $targetSetIds = $targetSets->pluck('id')->toArray();
        $gongCounts = $targetSets->mapWithKeys(fn ($ts) => [$ts->id => $ts->gongs->count()]);

        if ($isPrs) {
            return $this->prsActivityData($targetSets, $targetSetIds, $activeShooters, $stageCount, $isPrs);
        }

        return $this->standardActivityData($targetSets, $targetSetIds, $gongCounts, $activeShooters, $activeShooterIds, $stageCount, $isPrs);
    }

    private function prsActivityData($targetSets, $targetSetIds, $activeShooters, $stageCount, $isPrs): array
    {
        $matchId = $this->match->id;

        // Stage progress: count completed prs_stage_results per stage
        $prsCompletions = PrsStageResult::where('match_id', $matchId)
            ->whereNotNull('completed_at')
            ->select('stage_id', DB::raw('COUNT(DISTINCT shooter_id) as completed_count'))
            ->groupBy('stage_id')
            ->pluck('completed_count', 'stage_id');

        $stageProgress = $targetSets->map(fn ($ts) => (object) [
            'id' => $ts->id,
            'label' => $ts->display_name,
            'distance' => $ts->distance_meters,
            'completed' => $prsCompletions->get($ts->id, 0),
            'total' => $activeShooters,
            'percent' => $activeShooters > 0
                ? round(($prsCompletions->get($ts->id, 0) / $activeShooters) * 100)
                : 0,
        ]);

        // Recent activity: latest stage completions with shooter name
        $recentActivity = PrsStageResult::where('prs_stage_results.match_id', $matchId)
            ->whereNotNull('prs_stage_results.completed_at')
            ->join('shooters', 'prs_stage_results.shooter_id', '=', 'shooters.id')
            ->join('target_sets', 'prs_stage_results.stage_id', '=', 'target_sets.id')
            ->select(
                'shooters.name as shooter_name',
                'target_sets.label as stage_label',
                'target_sets.distance_meters',
                'target_sets.stage_number',
                'prs_stage_results.completed_at'
            )
            ->orderByDesc('prs_stage_results.completed_at')
            ->limit(10)
            ->get()
            ->map(fn ($r) => (object) [
                'name' => $r->shooter_name,
                'stage' => $r->stage_label ?: "Stage {$r->stage_number}",
                'distance' => $r->distance_meters,
                'time' => \Carbon\Carbon::parse($r->completed_at),
            ]);

        // Hit rates per stage
        $prsRates = PrsStageResult::where('match_id', $matchId)
            ->select(
                'stage_id',
                DB::raw('SUM(hits) as total_hits'),
                DB::raw('SUM(misses) as total_misses')
            )
            ->groupBy('stage_id')
            ->get();

        $totalHits = 0;
        $totalScored = 0;
        foreach ($prsRates as $r) {
            $totalHits += (int) $r->total_hits;
            $totalScored += (int) $r->total_hits + (int) $r->total_misses;
        }

        $stageHitRates = $targetSets->map(function ($ts) use ($prsRates) {
            $rate = $prsRates->firstWhere('stage_id', $ts->id);
            $hits = (int) ($rate?->total_hits ?? 0);
            $total = $hits + (int) ($rate?->total_misses ?? 0);
            return (object) [
                'id' => $ts->id,
                'label' => $ts->display_name,
                'distance' => $ts->distance_meters,
                'hit_rate' => $total > 0 ? round(($hits / $total) * 100) : 0,
                'hits' => $hits,
                'total' => $total,
            ];
        })->sortBy('hit_rate')->values();

        // Clean sweeps: stages where misses = 0 and not_taken = 0
        $cleanSweeps = PrsStageResult::where('match_id', $matchId)
            ->where('misses', 0)
            ->where(function ($q) {
                $q->whereNull('not_taken')->orWhere('not_taken', 0);
            })
            ->where('hits', '>', 0)
            ->count();

        // Fastest / average stage time (anonymized)
        $prsTimeStats = null;
        $fastest = PrsStageResult::where('match_id', $matchId)
            ->whereNotNull('raw_time_seconds')
            ->where('raw_time_seconds', '>', 0)
            ->min('raw_time_seconds');

        if ($fastest) {
            $avg = PrsStageResult::where('match_id', $matchId)
                ->whereNotNull('raw_time_seconds')
                ->where('raw_time_seconds', '>', 0)
                ->avg('raw_time_seconds');

            $prsTimeStats = (object) [
                'fastest' => round((float) $fastest, 2),
                'average' => round((float) $avg, 2),
            ];
        }

        $totalCompletions = $stageProgress->sum('completed');
        $totalPossible = $activeShooters * $stageCount;

        return [
            'scoresPublished' => false,
            'isPrs' => $isPrs,
            'activeShooters' => $activeShooters,
            'stageCount' => $stageCount,
            'overallPercent' => $totalPossible > 0 ? round(($totalCompletions / $totalPossible) * 100) : 0,
            'stageProgress' => $stageProgress,
            'recentActivity' => $recentActivity,
            'stageHitRates' => $stageHitRates,
            'totalHits' => $totalHits,
            'totalScored' => $totalScored,
            'hitRate' => $totalScored > 0 ? round(($totalHits / $totalScored) * 100) : 0,
            'cleanSweeps' => $cleanSweeps,
            'hardestStage' => $stageHitRates->first(),
            'prsTimeStats' => $prsTimeStats,
        ];
    }

    private function standardActivityData($targetSets, $targetSetIds, $gongCounts, $activeShooters, $activeShooterIds, $stageCount, $isPrs): array
    {
        if (empty($activeShooterIds) || empty($targetSetIds)) {
            return $this->emptyActivityData($isPrs, $activeShooters, $stageCount);
        }

        // Per-shooter, per-target_set: total score count + hit count (single query)
        $scoreCounts = Score::join('gongs', 'scores.gong_id', '=', 'gongs.id')
            ->whereIn('gongs.target_set_id', $targetSetIds)
            ->whereIn('scores.shooter_id', $activeShooterIds)
            ->select(
                'gongs.target_set_id',
                'scores.shooter_id',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('SUM(CASE WHEN scores.is_hit THEN 1 ELSE 0 END) as hits')
            )
            ->groupBy('gongs.target_set_id', 'scores.shooter_id')
            ->get();

        // Stage completion counts
        $completedByStage = [];
        $cleanSweeps = 0;
        foreach ($scoreCounts as $row) {
            $required = $gongCounts->get($row->target_set_id, 0);
            if ($required > 0 && $row->cnt >= $required) {
                $completedByStage[$row->target_set_id] = ($completedByStage[$row->target_set_id] ?? 0) + 1;
                if ((int) $row->hits >= $required) {
                    $cleanSweeps++;
                }
            }
        }

        $stageProgress = $targetSets->map(fn ($ts) => (object) [
            'id' => $ts->id,
            'label' => $ts->label ?: "{$ts->distance_meters}m",
            'distance' => $ts->distance_meters,
            'completed' => $completedByStage[$ts->id] ?? 0,
            'total' => $activeShooters,
            'percent' => $activeShooters > 0
                ? round((($completedByStage[$ts->id] ?? 0) / $activeShooters) * 100)
                : 0,
        ]);

        // Recent completions: shooters who finished all gongs at a stage, ordered by most recent
        $recentRaw = Score::join('gongs', 'scores.gong_id', '=', 'gongs.id')
            ->join('shooters', 'scores.shooter_id', '=', 'shooters.id')
            ->whereIn('gongs.target_set_id', $targetSetIds)
            ->whereIn('scores.shooter_id', $activeShooterIds)
            ->select(
                'gongs.target_set_id',
                'scores.shooter_id',
                'shooters.name as shooter_name',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('MAX(scores.recorded_at) as last_scored')
            )
            ->groupBy('gongs.target_set_id', 'scores.shooter_id', 'shooters.name')
            ->get();

        $tsLookup = $targetSets->keyBy('id');
        $recentActivity = $recentRaw
            ->filter(fn ($row) => ($gongCounts->get($row->target_set_id, 0)) > 0
                && $row->cnt >= $gongCounts->get($row->target_set_id, 0))
            ->sortByDesc('last_scored')
            ->take(10)
            ->map(fn ($row) => (object) [
                'name' => $row->shooter_name,
                'stage' => ($ts = $tsLookup->get($row->target_set_id))
                    ? ($ts->label ?: "{$ts->distance_meters}m")
                    : 'Unknown',
                'distance' => $tsLookup->get($row->target_set_id)?->distance_meters,
                'time' => $row->last_scored ? \Carbon\Carbon::parse($row->last_scored) : now(),
            ])
            ->values();

        // Hit rates per stage
        $hitRateRaw = Score::join('gongs', 'scores.gong_id', '=', 'gongs.id')
            ->whereIn('gongs.target_set_id', $targetSetIds)
            ->whereIn('scores.shooter_id', $activeShooterIds)
            ->select(
                'gongs.target_set_id',
                DB::raw('SUM(CASE WHEN scores.is_hit THEN 1 ELSE 0 END) as hits'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('gongs.target_set_id')
            ->get();

        $totalHits = 0;
        $totalScored = 0;
        foreach ($hitRateRaw as $r) {
            $totalHits += (int) $r->hits;
            $totalScored += (int) $r->total;
        }

        $stageHitRates = $targetSets->map(function ($ts) use ($hitRateRaw) {
            $rate = $hitRateRaw->firstWhere('target_set_id', $ts->id);
            $hits = (int) ($rate?->hits ?? 0);
            $total = (int) ($rate?->total ?? 0);
            return (object) [
                'id' => $ts->id,
                'label' => $ts->label ?: "{$ts->distance_meters}m",
                'distance' => $ts->distance_meters,
                'hit_rate' => $total > 0 ? round(($hits / $total) * 100) : 0,
                'hits' => $hits,
                'total' => $total,
            ];
        })->sortBy('hit_rate')->values();

        $totalCompletions = $stageProgress->sum('completed');
        $totalPossible = $activeShooters * $stageCount;

        return [
            'scoresPublished' => false,
            'isPrs' => $isPrs,
            'activeShooters' => $activeShooters,
            'stageCount' => $stageCount,
            'overallPercent' => $totalPossible > 0 ? round(($totalCompletions / $totalPossible) * 100) : 0,
            'stageProgress' => $stageProgress,
            'recentActivity' => $recentActivity,
            'stageHitRates' => $stageHitRates,
            'totalHits' => $totalHits,
            'totalScored' => $totalScored,
            'hitRate' => $totalScored > 0 ? round(($totalHits / $totalScored) * 100) : 0,
            'cleanSweeps' => $cleanSweeps,
            'hardestStage' => $stageHitRates->first(),
            'prsTimeStats' => null,
        ];
    }

    private function emptyActivityData(bool $isPrs, int $activeShooters, int $stageCount): array
    {
        return [
            'scoresPublished' => false,
            'isPrs' => $isPrs,
            'activeShooters' => $activeShooters,
            'stageCount' => $stageCount,
            'overallPercent' => 0,
            'stageProgress' => collect(),
            'recentActivity' => collect(),
            'stageHitRates' => collect(),
            'totalHits' => 0,
            'totalScored' => 0,
            'hitRate' => 0,
            'cleanSweeps' => 0,
            'hardestStage' => null,
            'prsTimeStats' => null,
        ];
    }

    // ── Leaderboard data (scores published) ──

    private function leaderboardData(bool $isPrs): array
    {
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
            $totalTargets = Gong::whereIn('target_set_id', $targetSetIds)->count();

            $shooterTimes = PrsStageResult::where('match_id', $this->match->id)
                ->whereNotNull('official_time_seconds')
                ->select('shooter_id', DB::raw('SUM(official_time_seconds) as total_time'))
                ->groupBy('shooter_id')
                ->pluck('total_time', 'shooter_id')
                ->toArray();

            if ($tiebreakerStage) {
                $tbResults = PrsStageResult::where('match_id', $this->match->id)
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

        $query = $this->match->shooters()
            ->with(['squad', 'division']);

        if (! $isPrs) {
            $query->withCount([
                'scores as hits_count' => fn ($q) => $q->where('is_hit', true),
                'scores as misses_count' => fn ($q) => $q->where('is_hit', false),
            ]);
        }

        if ($this->activeDivision) {
            $query->where('shooters.match_division_id', $this->activeDivision);
        }

        if ($this->activeCategory) {
            $catShooterIds = DB::table('match_category_shooter')
                ->where('match_category_id', $this->activeCategory)
                ->pluck('shooter_id')->toArray();
            $query->whereIn('shooters.id', $catShooterIds);
        }

        $prsHitsMap = [];
        $prsMissesMap = [];
        if ($isPrs) {
            $prsAgg = PrsStageResult::where('match_id', $this->match->id)
                ->select('shooter_id', DB::raw('SUM(hits) as total_hits'), DB::raw('SUM(misses) as total_misses'))
                ->groupBy('shooter_id')
                ->get();
            foreach ($prsAgg as $row) {
                $prsHitsMap[$row->shooter_id] = (int) $row->total_hits;
                $prsMissesMap[$row->shooter_id] = (int) $row->total_misses;
            }
        }

        $shooters = $query->get()
            ->map(function ($shooter) use ($isPrs, $shooterTimes, $tbHits, $tbTimes, $totalTargets, $prsHitsMap, $prsMissesMap) {
                if ($isPrs) {
                    $shooter->hits_count = $prsHitsMap[$shooter->id] ?? 0;
                    $shooter->misses_count = $prsMissesMap[$shooter->id] ?? 0;
                    $shooter->display_score = $shooter->hits_count;
                    $shooter->display_time = (float) ($shooterTimes[$shooter->id] ?? 0);
                    $shooter->tb_hits = $tbHits[$shooter->id] ?? 0;
                    $shooter->tb_time = (float) ($tbTimes[$shooter->id] ?? 0);
                    $shooter->not_taken = $totalTargets - $shooter->hits_count - $shooter->misses_count;
                } else {
                    $shooter->display_score = (float) $shooter->scores()
                        ->where('is_hit', true)
                        ->join('gongs', 'scores.gong_id', '=', 'gongs.id')
                        ->sum('gongs.multiplier');
                    $shooter->display_time = 0;
                }
                return $shooter;
            });

        $activeShooters = $shooters->where('status', '!=', 'dq');
        $dqShooters = $shooters->where('status', 'dq');

        if ($isPrs) {
            $activeShooters = $activeShooters->sort(function ($a, $b) {
                if ($a->display_score !== $b->display_score) return $b->display_score <=> $a->display_score;
                if ($a->tb_hits !== $b->tb_hits) return $b->tb_hits <=> $a->tb_hits;
                if ($a->tb_time !== $b->tb_time) return $a->tb_time <=> $b->tb_time;
                return $a->display_time <=> $b->display_time;
            })->values();

            $maxPrsHits = (int) $activeShooters->max('hits_count');
            foreach ($activeShooters as $s) {
                $s->prs_points = $maxPrsHits > 0
                    ? round($s->hits_count / $maxPrsHits * 100, 2)
                    : 0.0;
            }
            foreach ($dqShooters as $s) {
                $s->prs_points = 0.0;
            }
        } else {
            $activeShooters = $activeShooters->sortByDesc('display_score')->values();
        }

        $shooters = $activeShooters->concat($dqShooters->values())->values();

        $royalFlushEnabled = ! $isPrs && (bool) $this->match->royal_flush_enabled;
        $royalFlushEntries = collect();

        if ($royalFlushEnabled) {
            $rfTs = $this->match->targetSets()->orderByDesc('distance_meters')->with('gongs')->get();
            $rfGongIds = $rfTs->flatMap(fn ($ts) => $ts->gongs->pluck('id'))->toArray();
            $rfAllIds = $shooters->pluck('id')->toArray();
            $rfHits = Score::whereIn('gong_id', $rfGongIds)->whereIn('shooter_id', $rfAllIds)->where('is_hit', true)->select('shooter_id', 'gong_id')->get();
            $gongToTs = [];
            foreach ($rfTs as $ts) { foreach ($ts->gongs as $g) { $gongToTs[$g->id] = $ts->id; } }
            $hitCtByShTs = [];
            foreach ($rfHits as $h) {
                $tid = $gongToTs[$h->gong_id] ?? null;
                if ($tid !== null) $hitCtByShTs[$h->shooter_id][$tid] = ($hitCtByShTs[$h->shooter_id][$tid] ?? 0) + 1;
            }
            $rfProfiles = [];
            foreach ($shooters as $s) {
                $fd = [];
                foreach ($rfTs as $ts) {
                    if ($ts->gongs->count() > 0 && ($hitCtByShTs[$s->id][$ts->id] ?? 0) >= $ts->gongs->count()) {
                        $fd[] = (int) $ts->distance_meters;
                    }
                }
                $rfProfiles[] = ['shooter' => $s, 'flush_count' => count($fd), 'flush_distances' => $fd];
            }
            usort($rfProfiles, function ($a, $b) {
                if ($a['flush_count'] !== $b['flush_count']) return $b['flush_count'] <=> $a['flush_count'];
                $am = ! empty($a['flush_distances']) ? max($a['flush_distances']) : 0;
                $bm = ! empty($b['flush_distances']) ? max($b['flush_distances']) : 0;
                if ($am !== $bm) return $bm <=> $am;
                return $b['shooter']->total_score <=> $a['shooter']->total_score;
            });
            $royalFlushEntries = collect($rfProfiles)->map(fn ($p, $i) => (object) [
                'rank' => $i + 1, 'name' => $p['shooter']->name, 'user_id' => $p['shooter']->user_id,
                'squad_name' => $p['shooter']->squad?->name ?? '—',
                'flush_count' => $p['flush_count'], 'flush_distances' => $p['flush_distances'], 'total_score' => $p['shooter']->total_score,
            ]);
        }

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

        return [
            'scoresPublished' => true,
            'isPrs' => $isPrs,
            'shooters' => $shooters,
            'divisions' => $divisions,
            'categories' => $categories,
            'royalFlushEnabled' => $royalFlushEnabled,
            'royalFlushEntries' => $royalFlushEntries,
            'customFieldMap' => $customFieldMap,
        ];
    }
}; ?>

<div wire:poll.10s class="min-h-screen bg-app text-primary">

@if($scoresPublished)
    {{-- ═══════════════════════════════════════════════ --}}
    {{--               LEADERBOARD MODE                 --}}
    {{-- ═══════════════════════════════════════════════ --}}

    {{-- Header --}}
    <div class="sticky top-0 z-10 border-b border-border bg-app/95 backdrop-blur-sm px-4 py-3">
        <div class="flex items-center justify-between">
            <div class="min-w-0 flex-1">
                <h1 class="text-lg font-bold truncate">{{ $match->name }}</h1>
                <p class="text-xs text-muted">
                    {{ $match->date?->format('d M Y') }}
                    @if($match->location) &mdash; {{ $match->location }} @endif
                </p>
                <x-powered-by-block feature="results" :match-id="$match->id" variant="inline" />
            </div>
            <div class="flex items-center gap-2 ml-3 flex-shrink-0">
                @if($isPrs)
                    <span class="rounded bg-amber-600 px-1.5 py-0.5 text-[10px] font-bold uppercase">PRS</span>
                @endif
                <span class="inline-flex h-2 w-2 rounded-full bg-green-500 animate-pulse" title="Live"></span>
            </div>
        </div>

        @if($divisions->isNotEmpty() || $categories->isNotEmpty())
            <div class="mt-2 space-y-1.5">
                @if($divisions->isNotEmpty())
                    <div class="flex gap-1.5 overflow-x-auto pb-0.5 scrollbar-hide">
                        <span class="flex-shrink-0 text-[9px] text-muted/60 self-center pr-1">DIV</span>
                        <button wire:click="filterDivision(null)"
                                class="flex-shrink-0 rounded-full px-3 py-1 text-xs font-medium transition-colors {{ !$activeDivision ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
                            All
                        </button>
                        @foreach($divisions as $div)
                            <button wire:click="filterDivision({{ $div->id }})"
                                    class="flex-shrink-0 rounded-full px-3 py-1 text-xs font-medium transition-colors {{ $activeDivision === $div->id ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
                                {{ $div->name }}
                            </button>
                        @endforeach
                    </div>
                @endif
                @if($categories->isNotEmpty())
                    <div class="flex gap-1.5 overflow-x-auto pb-0.5 scrollbar-hide">
                        <span class="flex-shrink-0 text-[9px] text-muted/60 self-center pr-1">CAT</span>
                        <button wire:click="filterCategory(null)"
                                class="flex-shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-medium transition-colors {{ !$activeCategory ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
                            All
                        </button>
                        @foreach($categories as $cat)
                            <button wire:click="filterCategory({{ $cat->id }})"
                                    class="flex-shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-medium transition-colors {{ $activeCategory === $cat->id ? 'bg-accent text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
                                {{ $cat->name }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>

    @if($royalFlushEnabled)
        <div class="px-3 pt-2 flex gap-1.5">
            <button wire:click="setTab('main')"
                    class="flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors {{ $activeTab === 'main' ? 'bg-accent text-primary' : 'bg-surface text-muted' }}">
                Scoreboard
            </button>
            @if($royalFlushEnabled)
                <button wire:click="setTab('royalflush')"
                        class="flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors {{ $activeTab === 'royalflush' ? 'bg-amber-600 text-primary' : 'bg-surface text-muted' }}">
                    Royal Flush
                </button>
            @endif
        </div>
    @endif

    @if($royalFlushEnabled && $activeTab === 'royalflush')
        <div class="px-3 py-3 space-y-1.5">
            @forelse($royalFlushEntries as $entry)
                @php
                    $borderColor = match($entry->rank) { 1 => 'border-l-amber-400', 2 => 'border-l-slate-400', 3 => 'border-l-orange-600', default => 'border-l-transparent' };
                    $rankBg = match($entry->rank) { 1 => 'bg-amber-500 text-black', 2 => 'bg-slate-400 text-black', 3 => 'bg-orange-600 text-primary', default => '' };
                @endphp
                <div class="flex items-center gap-3 rounded-lg border-l-4 {{ $borderColor }} bg-app px-3 py-2.5">
                    @if($entry->rank <= 3)
                        <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $rankBg }}">{{ $entry->rank }}</span>
                    @else
                        <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center text-sm text-muted font-medium">{{ $entry->rank }}</span>
                    @endif
                    <div class="min-w-0 flex-1">
                        @if($entry->user_id ?? null)
                            <a href="{{ route('shooter.profile', $entry->user_id) }}" class="text-sm font-semibold text-primary truncate hover:underline">{{ $entry->name }}</a>
                        @else
                            <p class="text-sm font-semibold text-primary truncate">{{ $entry->name }}</p>
                        @endif
                        <span class="text-[10px] text-muted">{{ $entry->squad_name }}</span>
                    </div>
                    <div class="flex items-center gap-2.5 flex-shrink-0">
                        <div class="text-center">
                            <p class="text-base font-bold text-amber-400">{{ $entry->flush_count }}</p>
                            <p class="text-[9px] text-muted/60">flushes</p>
                        </div>
                        <div class="text-center min-w-[3rem]">
                            @if(!empty($entry->flush_distances))
                                <div class="flex flex-wrap gap-0.5 justify-center">
                                    @foreach($entry->flush_distances as $d)
                                        <span class="rounded-full bg-amber-600/20 px-1.5 py-0.5 text-[9px] font-bold text-amber-400">{{ $d }}m</span>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-[10px] text-muted">—</p>
                            @endif
                        </div>
                        <span class="text-base font-bold tabular-nums">{{ $entry->total_score }}</span>
                    </div>
                </div>
            @empty
                <div class="rounded-lg border border-border bg-app px-6 py-12 text-center">
                    <p class="text-muted">No Royal Flush data yet.</p>
                </div>
            @endforelse
        </div>
    @else
    {{-- Scoreboard cards --}}
    <div class="px-3 py-3 space-y-1.5">
        @forelse($shooters as $index => $shooter)
            @php
                $isDq = ($shooter->status ?? '') === 'dq';
                $rank = $isDq ? null : $index + 1;
                $borderColor = $isDq ? 'border-l-red-600' : match($rank) {
                    1 => 'border-l-amber-400',
                    2 => 'border-l-slate-400',
                    3 => 'border-l-orange-600',
                    default => 'border-l-transparent',
                };
                $rankBg = match($rank) {
                    1 => 'bg-amber-500 text-black',
                    2 => 'bg-slate-400 text-black',
                    3 => 'bg-orange-600 text-primary',
                    default => '',
                };
            @endphp
            <div class="flex items-center gap-3 rounded-lg border-l-4 {{ $borderColor }} bg-app px-3 py-2.5 {{ $isDq ? 'opacity-60' : '' }}">
                {{-- Rank / DQ --}}
                @if($isDq)
                    <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-red-600 text-[9px] font-black text-white">DQ</span>
                @elseif($rank <= 3)
                    <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $rankBg }}">{{ $rank }}</span>
                @else
                    <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center text-sm text-muted font-medium">{{ $rank }}</span>
                @endif

                {{-- Name + Division --}}
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        @if($shooter->user_id && !$isDq)
                            <a href="{{ route('shooter.profile', $shooter->user_id) }}" class="text-sm font-semibold truncate text-primary hover:underline">{{ $shooter->name }}</a>
                        @else
                            <p class="text-sm font-semibold truncate {{ $isDq ? 'text-muted line-through' : 'text-primary' }}">{{ $shooter->name }}</p>
                        @endif
                        @if($isDq)
                            <span class="rounded bg-red-600/20 px-1.5 py-0.5 text-[9px] font-bold text-red-400">DQ</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] text-muted">{{ $shooter->squad?->name ?? '' }}</span>
                        @if($shooter->division)
                            <span class="rounded bg-surface px-1 py-0.5 text-[9px] font-medium text-muted">{{ $shooter->division->name }}</span>
                        @endif
                        @if(isset($customFieldMap[$shooter->id]))
                            @foreach($customFieldMap[$shooter->id] as $cfv)
                                <span class="rounded bg-surface px-1 py-0.5 text-[9px] font-medium text-muted" title="{{ $cfv['label'] }}">{{ $cfv['value'] }}</span>
                            @endforeach
                        @endif
                    </div>
                </div>

                {{-- Stats --}}
                <div class="flex items-center gap-2.5 flex-shrink-0">
                    @if(!$isDq)
                        <div class="text-center">
                            <p class="text-xs text-green-400 font-bold">{{ $shooter->hits_count }}</p>
                            <p class="text-[9px] text-muted/60">hits</p>
                        </div>
                        @if($isPrs)
                            <div class="text-center">
                                <p class="text-xs text-accent">{{ $shooter->misses_count }}</p>
                                <p class="text-[9px] text-muted/60">miss</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-amber-400/50">{{ $shooter->not_taken ?? 0 }}</p>
                                <p class="text-[9px] text-muted/60">n/t</p>
                            </div>
                        @endif
                        @if($isPrs && $shooter->tb_time > 0)
                            <div class="text-center">
                                <p class="text-xs font-mono text-secondary">{{ number_format($shooter->tb_time, 1) }}s</p>
                                <p class="text-[9px] text-muted/60">TB time</p>
                            </div>
                        @endif
                        <div class="text-center min-w-[2rem]">
                            <p class="text-base font-bold text-amber-400">{{ $isPrs ? number_format($shooter->prs_points ?? 0, 2) : number_format($shooter->display_score, 1) }}</p>
                            <p class="text-[9px] text-muted/60">{{ $isPrs ? 'pts' : 'score' }}</p>
                        </div>
                    @else
                        <span class="text-xs font-bold text-red-400">Disqualified</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-border bg-app px-6 py-12 text-center">
                <p class="text-muted">No scores recorded yet.</p>
            </div>
        @endforelse
    </div>

    @endif

@else
    {{-- ═══════════════════════════════════════════════ --}}
    {{--              ACTIVITY VIEW MODE                 --}}
    {{--   (scores hidden — no rankings revealed)        --}}
    {{-- ═══════════════════════════════════════════════ --}}

    {{-- Header --}}
    <div class="sticky top-0 z-10 border-b border-border bg-app/95 backdrop-blur-sm px-4 py-3">
        <div class="flex items-center justify-between">
            <div class="min-w-0 flex-1">
                <h1 class="text-lg font-bold truncate">{{ $match->name }}</h1>
                <p class="text-xs text-muted">
                    {{ $match->date?->format('d M Y') }}
                    @if($match->location) &mdash; {{ $match->location }} @endif
                </p>
                <x-powered-by-block feature="results" :match-id="$match->id" variant="inline" />
            </div>
            <div class="flex items-center gap-2 ml-3 flex-shrink-0">
                @if($isPrs)
                    <span class="rounded bg-amber-600 px-1.5 py-0.5 text-[10px] font-bold uppercase">PRS</span>
                @endif
                <span class="inline-flex items-center gap-1.5 rounded-full bg-red-500/10 px-2.5 py-1">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-red-600"></span>
                    </span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-red-500">Live</span>
                </span>
            </div>
        </div>
    </div>

    {{-- Hero Stats --}}
    <div class="px-4 pt-4 pb-2">
        <div class="rounded-2xl border border-border bg-surface/50 p-4">
            <div class="mb-3 flex items-center gap-2">
                <span class="relative flex h-3 w-3">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-500 opacity-75"></span>
                    <span class="relative inline-flex h-3 w-3 rounded-full bg-green-500"></span>
                </span>
                <span class="text-sm font-bold uppercase tracking-wider text-green-400">Match in Progress</span>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-xl bg-app/80 px-3 py-2.5 text-center">
                    <p class="text-2xl font-black tabular-nums text-primary">{{ $activeShooters }}</p>
                    <p class="text-[10px] font-medium text-muted">Shooters</p>
                </div>
                <div class="rounded-xl bg-app/80 px-3 py-2.5 text-center">
                    <p class="text-2xl font-black tabular-nums text-primary">{{ $stageCount }}</p>
                    <p class="text-[10px] font-medium text-muted">Stages</p>
                </div>
                <div class="rounded-xl bg-app/80 px-3 py-2.5 text-center">
                    <p class="text-2xl font-black tabular-nums {{ $overallPercent >= 75 ? 'text-green-400' : ($overallPercent >= 40 ? 'text-amber-400' : 'text-primary') }}">{{ $overallPercent }}%</p>
                    <p class="text-[10px] font-medium text-muted">Complete</p>
                </div>
            </div>

            {{-- Overall progress bar --}}
            <div class="mt-3">
                <div class="h-2 w-full overflow-hidden rounded-full bg-surface">
                    <div class="h-full rounded-full bg-gradient-to-r from-green-500 to-emerald-400 transition-all duration-1000 ease-out"
                         style="width: {{ $overallPercent }}%"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Stage Progress Bars --}}
    @if($stageProgress->isNotEmpty())
    <div class="px-4 py-2">
        <h2 class="mb-2 text-xs font-bold uppercase tracking-wider text-muted/80">Stage Progress</h2>
        <div class="space-y-2">
            @foreach($stageProgress as $stage)
                <div class="rounded-xl border border-border bg-surface/30 px-3 py-2.5">
                    <div class="mb-1.5 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-primary">{{ $stage->label }}</span>
                            @if($stage->distance)
                                <span class="rounded bg-surface px-1.5 py-0.5 text-[10px] font-medium text-muted">{{ $stage->distance }}m</span>
                            @endif
                        </div>
                        <span class="text-xs font-bold tabular-nums {{ $stage->percent === 100 ? 'text-green-400' : 'text-muted' }}">
                            {{ $stage->completed }}/{{ $stage->total }}
                        </span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-surface">
                        @php
                            $barColor = match(true) {
                                $stage->percent === 100 => 'bg-green-500',
                                $stage->percent >= 60 => 'bg-emerald-500',
                                $stage->percent >= 30 => 'bg-amber-500',
                                $stage->percent > 0 => 'bg-red-500',
                                default => 'bg-transparent',
                            };
                        @endphp
                        <div class="h-full rounded-full {{ $barColor }} transition-all duration-1000 ease-out"
                             style="width: {{ $stage->percent }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Live Activity Ticker --}}
    @if($recentActivity->isNotEmpty())
    <div class="px-4 py-2">
        <h2 class="mb-2 text-xs font-bold uppercase tracking-wider text-muted/80">Live Activity</h2>
        <div class="space-y-1">
            @foreach($recentActivity as $i => $event)
                <div class="flex items-center gap-3 rounded-lg border-l-4 {{ $i === 0 ? 'border-l-green-500 bg-green-500/5' : 'border-l-border bg-surface/20' }} px-3 py-2 transition-all">
                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full {{ $i === 0 ? 'bg-green-500/20' : 'bg-surface' }}">
                        <svg class="h-4 w-4 {{ $i === 0 ? 'text-green-400' : 'text-muted' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-primary">
                            <span class="font-bold">{{ $event->name }}</span>
                            <span class="text-muted"> completed </span>
                            <span class="font-semibold text-secondary">{{ $event->stage }}</span>
                        </p>
                    </div>
                    <span class="flex-shrink-0 text-[10px] text-muted/60">{{ $event->time->diffForHumans(short: true) }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Match Insights --}}
    <div class="px-4 py-2">
        <h2 class="mb-2 text-xs font-bold uppercase tracking-wider text-muted/80">Match Insights</h2>
        <div class="grid grid-cols-2 gap-2">
            {{-- Total Hits --}}
            <div class="rounded-xl border border-border bg-surface/30 p-3 text-center">
                <div class="mb-1 flex items-center justify-center">
                    <svg class="h-5 w-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75H6A2.25 2.25 0 0 0 3.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0 1 20.25 6v1.5M20.25 16.5V18A2.25 2.25 0 0 1 18 20.25h-1.5M3.75 16.5V18A2.25 2.25 0 0 0 6 20.25h1.5" />
                    </svg>
                </div>
                <p class="text-2xl font-black tabular-nums text-green-400">{{ number_format($totalHits) }}</p>
                <p class="text-[10px] font-medium text-muted">Total Hits</p>
            </div>

            {{-- Hit Rate --}}
            <div class="rounded-xl border border-border bg-surface/30 p-3 text-center">
                <div class="mb-1 flex items-center justify-center">
                    <svg class="h-5 w-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                    </svg>
                </div>
                <p class="text-2xl font-black tabular-nums text-amber-400">{{ $hitRate }}%</p>
                <p class="text-[10px] font-medium text-muted">Hit Rate</p>
            </div>

            {{-- Clean Sweeps --}}
            <div class="rounded-xl border border-border bg-surface/30 p-3 text-center">
                <div class="mb-1 flex items-center justify-center">
                    <svg class="h-5 w-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                    </svg>
                </div>
                <p class="text-2xl font-black tabular-nums text-purple-400">{{ $cleanSweeps }}</p>
                <p class="text-[10px] font-medium text-muted">Clean Sweeps</p>
            </div>

            {{-- Hardest Stage --}}
            <div class="rounded-xl border border-border bg-surface/30 p-3 text-center">
                <div class="mb-1 flex items-center justify-center">
                    <svg class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 0 0 .495-7.468 5.99 5.99 0 0 0-1.925 3.547 5.975 5.975 0 0 1-2.133-1.001A3.75 3.75 0 0 0 12 18Z" />
                    </svg>
                </div>
                @if($hardestStage && $hardestStage->total > 0)
                    <p class="text-sm font-black text-red-400 truncate">{{ $hardestStage->label }}</p>
                    <p class="text-[10px] font-medium text-muted">{{ $hardestStage->hit_rate }}% hit rate</p>
                @else
                    <p class="text-sm font-medium text-muted">&mdash;</p>
                    <p class="text-[10px] font-medium text-muted">Hardest Stage</p>
                @endif
            </div>

            {{-- PRS Time Stats --}}
            @if($isPrs && $prsTimeStats)
                <div class="rounded-xl border border-border bg-surface/30 p-3 text-center">
                    <div class="mb-1 flex items-center justify-center">
                        <svg class="h-5 w-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <p class="text-lg font-black tabular-nums text-cyan-400">{{ number_format($prsTimeStats->fastest, 1) }}s</p>
                    <p class="text-[10px] font-medium text-muted">Fastest Stage</p>
                </div>
                <div class="rounded-xl border border-border bg-surface/30 p-3 text-center">
                    <div class="mb-1 flex items-center justify-center">
                        <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <p class="text-lg font-black tabular-nums text-slate-300">{{ number_format($prsTimeStats->average, 1) }}s</p>
                    <p class="text-[10px] font-medium text-muted">Avg Stage Time</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Stage Difficulty Ranking --}}
    @if($stageHitRates->isNotEmpty())
    <div class="px-4 py-2 pb-4">
        <h2 class="mb-2 text-xs font-bold uppercase tracking-wider text-muted/80">Stage Difficulty</h2>
        <div class="rounded-xl border border-border bg-surface/30 p-3">
            <div class="space-y-2.5">
                @foreach($stageHitRates as $stage)
                    @if($stage->total > 0)
                    <div>
                        <div class="mb-1 flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-semibold text-primary">{{ $stage->label }}</span>
                                @if($stage->distance)
                                    <span class="text-[10px] text-muted/60">{{ $stage->distance }}m</span>
                                @endif
                            </div>
                            <span class="text-xs font-bold tabular-nums {{ $stage->hit_rate >= 70 ? 'text-green-400' : ($stage->hit_rate >= 40 ? 'text-amber-400' : 'text-red-400') }}">
                                {{ $stage->hit_rate }}%
                            </span>
                        </div>
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface">
                            @php
                                $diffColor = match(true) {
                                    $stage->hit_rate >= 70 => 'bg-green-500',
                                    $stage->hit_rate >= 40 => 'bg-amber-500',
                                    default => 'bg-red-500',
                                };
                            @endphp
                            <div class="h-full rounded-full {{ $diffColor }} transition-all duration-1000 ease-out"
                                 style="width: {{ $stage->hit_rate }}%"></div>
                        </div>
                    </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
    @endif

@endif

    {{-- Footer --}}
    <div class="border-t border-border px-4 py-3 text-center">
        <div class="flex items-center justify-center gap-2 text-xs text-muted/60">
            <span class="inline-flex h-1.5 w-1.5 rounded-full bg-green-500 animate-pulse"></span>
            <span>Auto-refreshes every 10s</span>
        </div>
        <p class="mt-1 text-[10px] text-slate-700">&copy; {{ date('Y') }} <span class="font-semibold"><span class="text-muted/60">DEAD</span><span class="text-accent/40">CENTER</span></span></p>
    </div>
</div>
