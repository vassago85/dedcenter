<?php

use App\Models\ShootingMatch;
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
        $divisions = $this->match->divisions()->orderBy('sort_order')->get();
        $categories = $this->match->categories()->orderBy('sort_order')->get();

        $shooterTimes = [];
        $tbHits = [];
        $tbTimes = [];

        $totalTargets = 0;

        if ($isPrs) {
            $targetSets = $this->match->targetSets()->get();
            $targetSetIds = $targetSets->pluck('id');
            $tiebreakerStage = $targetSets->firstWhere('is_tiebreaker', true);
            $totalTargets = \App\Models\Gong::whereIn('target_set_id', $targetSetIds)->count();

            $shooterTimes = StageTime::query()
                ->whereIn('target_set_id', $targetSetIds)
                ->select('shooter_id', DB::raw('SUM(time_seconds) as total_time'))
                ->groupBy('shooter_id')
                ->pluck('total_time', 'shooter_id')
                ->toArray();

            if ($tiebreakerStage) {
                $tbGongIds = \App\Models\Gong::where('target_set_id', $tiebreakerStage->id)->pluck('id');
                $tbHits = \App\Models\Score::whereIn('gong_id', $tbGongIds)
                    ->where('is_hit', true)
                    ->select('shooter_id', DB::raw('COUNT(*) as hit_count'))
                    ->groupBy('shooter_id')
                    ->pluck('hit_count', 'shooter_id')
                    ->map(fn ($v) => (int) $v)
                    ->toArray();
                $tbTimes = StageTime::where('target_set_id', $tiebreakerStage->id)
                    ->pluck('time_seconds', 'shooter_id')
                    ->map(fn ($v) => (float) $v)
                    ->toArray();
            }
        }

        $query = $this->match->shooters()
            ->with(['squad', 'division'])
            ->withCount([
                'scores as hits_count' => fn ($q) => $q->where('is_hit', true),
                'scores as misses_count' => fn ($q) => $q->where('is_hit', false),
            ]);

        if ($this->activeDivision) {
            $query->where('shooters.match_division_id', $this->activeDivision);
        }

        if ($this->activeCategory) {
            $catShooterIds = \Illuminate\Support\Facades\DB::table('match_category_shooter')
                ->where('match_category_id', $this->activeCategory)
                ->pluck('shooter_id')->toArray();
            $query->whereIn('shooters.id', $catShooterIds);
        }

        $shooters = $query->get()
            ->map(function ($shooter) use ($isPrs, $shooterTimes, $tbHits, $tbTimes, $totalTargets) {
                if ($isPrs) {
                    $shooter->total_score = $shooter->hits_count;
                    $shooter->total_time = (float) ($shooterTimes[$shooter->id] ?? 0);
                    $shooter->tb_hits = $tbHits[$shooter->id] ?? 0;
                    $shooter->tb_time = (float) ($tbTimes[$shooter->id] ?? 0);
                    $shooter->not_taken = $totalTargets - $shooter->hits_count - $shooter->misses_count;
                } else {
                    $shooter->total_score = (float) $shooter->scores()
                        ->where('is_hit', true)
                        ->join('gongs', 'scores.gong_id', '=', 'gongs.id')
                        ->sum('gongs.multiplier');
                    $shooter->total_time = 0;
                }
                return $shooter;
            });

        if ($isPrs) {
            $shooters = $shooters->sort(function ($a, $b) {
                if ($a->total_score !== $b->total_score) return $b->total_score <=> $a->total_score;
                if ($a->tb_hits !== $b->tb_hits) return $b->tb_hits <=> $a->tb_hits;
                if ($a->tb_time !== $b->tb_time) return $a->tb_time <=> $b->tb_time;
                return $a->total_time <=> $b->total_time;
            })->values();
        } else {
            $shooters = $shooters->sortByDesc('total_score')->values();
        }

        $sideBetEnabled = !$isPrs && $this->match->side_bet_enabled;
        $sideBetEntries = collect();

        if ($sideBetEnabled) {
            $tSets = $this->match->targetSets()
                ->orderByDesc('distance_meters')
                ->with(['gongs' => fn ($q) => $q->orderByDesc('multiplier')])
                ->get();
            $gongRankMap = []; $maxRanks = 0;
            foreach ($tSets as $ts) {
                $rank = 0;
                foreach ($ts->gongs as $gong) {
                    $gongRankMap[$gong->id] = ['rank' => $rank, 'distance' => $ts->distance_meters];
                    $rank++;
                }
                $maxRanks = max($maxRanks, $rank);
            }
            $gongIds = array_keys($gongRankMap);
            $allShooterIds = $shooters->pluck('id')->toArray();
            $hits = \App\Models\Score::whereIn('gong_id', $gongIds)->whereIn('shooter_id', $allShooterIds)->where('is_hit', true)->select('shooter_id', 'gong_id')->get();
            $profiles = [];
            foreach ($shooters as $s) {
                $profiles[$s->id] = ['shooter' => $s, 'ranks' => []];
                for ($r = 0; $r < $maxRanks; $r++) $profiles[$s->id]['ranks'][$r] = ['count' => 0, 'distances' => []];
            }
            foreach ($hits as $hit) {
                if (!isset($gongRankMap[$hit->gong_id], $profiles[$hit->shooter_id])) continue;
                $info = $gongRankMap[$hit->gong_id];
                $profiles[$hit->shooter_id]['ranks'][$info['rank']]['count']++;
                $profiles[$hit->shooter_id]['ranks'][$info['rank']]['distances'][] = $info['distance'];
            }
            foreach ($profiles as &$p) { for ($r = 0; $r < $maxRanks; $r++) rsort($p['ranks'][$r]['distances']); }
            unset($p);
            $profileList = array_values($profiles);
            usort($profileList, function ($a, $b) use ($maxRanks) {
                for ($r = 0; $r < $maxRanks; $r++) {
                    if ($a['ranks'][$r]['count'] !== $b['ranks'][$r]['count']) return $b['ranks'][$r]['count'] <=> $a['ranks'][$r]['count'];
                    $ad = $a['ranks'][$r]['distances']; $bd = $b['ranks'][$r]['distances'];
                    for ($i = 0; $i < max(count($ad), count($bd)); $i++) {
                        if (($ad[$i] ?? 0) !== ($bd[$i] ?? 0)) return ($bd[$i] ?? 0) <=> ($ad[$i] ?? 0);
                    }
                }
                return 0;
            });
            $sideBetEntries = collect($profileList)->map(fn ($p, $i) => (object) [
                'rank' => $i + 1, 'name' => $p['shooter']->name, 'squad_name' => $p['shooter']->squad?->name ?? '—',
                'small_gong_hits' => $p['ranks'][0]['count'] ?? 0, 'distances' => $p['ranks'][0]['distances'] ?? [],
            ]);
        }

        $royalFlushEnabled = !$isPrs && (bool) $this->match->royal_flush_enabled;
        $royalFlushEntries = collect();

        if ($royalFlushEnabled) {
            $rfTs = $this->match->targetSets()->orderByDesc('distance_meters')->with('gongs')->get();
            $rfGongIds = $rfTs->flatMap(fn ($ts) => $ts->gongs->pluck('id'))->toArray();
            $rfAllIds = $shooters->pluck('id')->toArray();
            $rfHits = \App\Models\Score::whereIn('gong_id', $rfGongIds)->whereIn('shooter_id', $rfAllIds)->where('is_hit', true)->select('shooter_id', 'gong_id')->get();
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
                $am = !empty($a['flush_distances']) ? max($a['flush_distances']) : 0;
                $bm = !empty($b['flush_distances']) ? max($b['flush_distances']) : 0;
                if ($am !== $bm) return $bm <=> $am;
                return $b['shooter']->total_score <=> $a['shooter']->total_score;
            });
            $royalFlushEntries = collect($rfProfiles)->map(fn ($p, $i) => (object) [
                'rank' => $i + 1, 'name' => $p['shooter']->name, 'squad_name' => $p['shooter']->squad?->name ?? '—',
                'flush_count' => $p['flush_count'], 'flush_distances' => $p['flush_distances'], 'total_score' => $p['shooter']->total_score,
            ]);
        }

        return [
            'shooters' => $shooters,
            'isPrs' => $isPrs,
            'divisions' => $divisions,
            'categories' => $categories,
            'sideBetEnabled' => $sideBetEnabled,
            'sideBetEntries' => $sideBetEntries,
            'royalFlushEnabled' => $royalFlushEnabled,
            'royalFlushEntries' => $royalFlushEntries,
        ];
    }
}; ?>

<div wire:poll.10s class="min-h-screen bg-app text-primary">
    {{-- Header --}}
    <div class="sticky top-0 z-10 border-b border-border bg-app/95 backdrop-blur-sm px-4 py-3">
        <div class="flex items-center justify-between">
            <div class="min-w-0 flex-1">
                <h1 class="text-lg font-bold truncate">{{ $match->name }}</h1>
                <p class="text-xs text-muted">
                    {{ $match->date?->format('d M Y') }}
                    @if($match->location) &mdash; {{ $match->location }} @endif
                </p>
                <x-sponsor-block placement="global_results" :match-id="$match->id" variant="inline" />
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
                                class="flex-shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-medium transition-colors {{ !$activeCategory ? 'bg-blue-600 text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
                            All
                        </button>
                        @foreach($categories as $cat)
                            <button wire:click="filterCategory({{ $cat->id }})"
                                    class="flex-shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-medium transition-colors {{ $activeCategory === $cat->id ? 'bg-blue-600 text-primary' : 'bg-surface text-muted hover:bg-surface-2' }}">
                                {{ $cat->name }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>

    @if($sideBetEnabled || $royalFlushEnabled)
        <div class="px-3 pt-2 flex gap-1.5">
            <button wire:click="setTab('main')"
                    class="flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors {{ $activeTab === 'main' ? 'bg-accent text-primary' : 'bg-surface text-muted' }}">
                Scoreboard
            </button>
            @if($sideBetEnabled)
                <button wire:click="setTab('sidebet')"
                        class="flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors {{ $activeTab === 'sidebet' ? 'bg-amber-600 text-primary' : 'bg-surface text-muted' }}">
                    Side Bet
                </button>
            @endif
            @if($royalFlushEnabled)
                <button wire:click="setTab('royalflush')"
                        class="flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors {{ $activeTab === 'royalflush' ? 'bg-amber-600 text-primary' : 'bg-surface text-muted' }}">
                    Royal Flush
                </button>
            @endif
        </div>
    @endif

    @if($sideBetEnabled && $activeTab === 'sidebet')
        <div class="px-3 py-3 space-y-1.5">
            @forelse($sideBetEntries as $entry)
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
                        <p class="text-sm font-semibold text-primary truncate">{{ $entry->name }}</p>
                        <span class="text-[10px] text-muted">{{ $entry->squad_name }}</span>
                    </div>
                    <div class="flex items-center gap-2.5 flex-shrink-0">
                        <div class="text-center">
                            <p class="text-base font-bold text-amber-400">{{ $entry->small_gong_hits }}</p>
                            <p class="text-[9px] text-muted/60">hits</p>
                        </div>
                        <div class="text-center min-w-[3rem]">
                            <p class="text-[10px] text-secondary">
                                @if(!empty($entry->distances))
                                    {{ implode(', ', array_map(fn($d) => $d . 'm', $entry->distances)) }}
                                @else
                                    —
                                @endif
                            </p>
                            <p class="text-[9px] text-muted/60">distances</p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-lg border border-border bg-app px-6 py-12 text-center">
                    <p class="text-muted">No side bet scores yet.</p>
                </div>
            @endforelse
        </div>
    @elseif($royalFlushEnabled && $activeTab === 'royalflush')
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
                        <p class="text-sm font-semibold text-primary truncate">{{ $entry->name }}</p>
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
                $rank = $index + 1;
                $borderColor = match($rank) {
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
            <div class="flex items-center gap-3 rounded-lg border-l-4 {{ $borderColor }} bg-app px-3 py-2.5">
                {{-- Rank --}}
                @if($rank <= 3)
                    <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $rankBg }}">{{ $rank }}</span>
                @else
                    <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center text-sm text-muted font-medium">{{ $rank }}</span>
                @endif

                {{-- Name + Division --}}
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-primary truncate">{{ $shooter->name }}</p>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] text-muted">{{ $shooter->squad?->name ?? '' }}</span>
                        @if($shooter->division)
                            <span class="rounded bg-surface px-1 py-0.5 text-[9px] font-medium text-muted">{{ $shooter->division->name }}</span>
                        @endif
                    </div>
                </div>

                {{-- Stats --}}
                <div class="flex items-center gap-2.5 flex-shrink-0">
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
                    @if($isPrs && $shooter->total_time > 0)
                        <div class="text-center">
                            <p class="text-xs font-mono text-secondary">{{ sprintf('%02d:%05.2f', floor($shooter->total_time / 60), fmod($shooter->total_time, 60)) }}</p>
                            <p class="text-[9px] text-muted/60">time</p>
                        </div>
                    @endif
                    <div class="text-center min-w-[2rem]">
                        <p class="text-base font-bold text-amber-400">{{ $isPrs ? $shooter->total_score : number_format($shooter->total_score, 1) }}</p>
                        <p class="text-[9px] text-muted/60">score</p>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-border bg-app px-6 py-12 text-center">
                <p class="text-muted">No scores recorded yet.</p>
            </div>
        @endforelse
    </div>

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
