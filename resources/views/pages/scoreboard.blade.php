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

        $shooterQuery = $this->match->shooters()
            ->with(['squad', 'division'])
            ->withCount([
                'scores as hits_count' => fn ($q) => $q->where('is_hit', true),
                'scores as misses_count' => fn ($q) => $q->where('is_hit', false),
            ]);

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
            $targetSets = $this->match->targetSets()
                ->orderByDesc('distance_meters')
                ->with(['gongs' => fn ($q) => $q->orderByDesc('multiplier')])
                ->get();

            $gongRankMap = [];
            $maxRanks = 0;
            foreach ($targetSets as $ts) {
                $rank = 0;
                foreach ($ts->gongs as $gong) {
                    $gongRankMap[$gong->id] = ['rank' => $rank, 'distance' => $ts->distance_meters];
                    $rank++;
                }
                $maxRanks = max($maxRanks, $rank);
            }

            $gongIds = array_keys($gongRankMap);
            $allShooterIds = $shooters->pluck('id')->toArray();

            $hits = \App\Models\Score::whereIn('gong_id', $gongIds)
                ->whereIn('shooter_id', $allShooterIds)
                ->where('is_hit', true)
                ->select('shooter_id', 'gong_id')
                ->get();

            $profiles = [];
            foreach ($shooters as $s) {
                $profiles[$s->id] = ['shooter' => $s, 'ranks' => []];
                for ($r = 0; $r < $maxRanks; $r++) {
                    $profiles[$s->id]['ranks'][$r] = ['count' => 0, 'distances' => []];
                }
            }

            foreach ($hits as $hit) {
                if (!isset($gongRankMap[$hit->gong_id], $profiles[$hit->shooter_id])) continue;
                $info = $gongRankMap[$hit->gong_id];
                $profiles[$hit->shooter_id]['ranks'][$info['rank']]['count']++;
                $profiles[$hit->shooter_id]['ranks'][$info['rank']]['distances'][] = $info['distance'];
            }

            foreach ($profiles as &$p) {
                for ($r = 0; $r < $maxRanks; $r++) rsort($p['ranks'][$r]['distances']);
            }
            unset($p);

            $profileList = array_values($profiles);
            usort($profileList, function ($a, $b) use ($maxRanks) {
                for ($r = 0; $r < $maxRanks; $r++) {
                    if ($a['ranks'][$r]['count'] !== $b['ranks'][$r]['count']) return $b['ranks'][$r]['count'] <=> $a['ranks'][$r]['count'];
                    $ad = $a['ranks'][$r]['distances']; $bd = $b['ranks'][$r]['distances'];
                    for ($i = 0; $i < max(count($ad), count($bd)); $i++) {
                        $av = $ad[$i] ?? 0; $bv = $bd[$i] ?? 0;
                        if ($av !== $bv) return $bv <=> $av;
                    }
                }
                return 0;
            });

            $sideBetEntries = collect($profileList)->map(fn ($p, $i) => (object) [
                'rank' => $i + 1,
                'name' => $p['shooter']->name,
                'squad_name' => $p['shooter']->squad?->name ?? '—',
                'small_gong_hits' => $p['ranks'][0]['count'] ?? 0,
                'distances' => $p['ranks'][0]['distances'] ?? [],
            ]);
        }

        return [
            'shooters' => $shooters,
            'isPrs' => $isPrs,
            'divisions' => $divisions,
            'categories' => $categories,
            'sideBetEnabled' => $sideBetEnabled,
            'sideBetEntries' => $sideBetEntries,
        ];
    }
}; ?>

<div wire:poll.10s class="min-h-screen bg-slate-950 text-white p-6 lg:p-10">
    <div class="mb-8 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-4xl font-black tracking-tight lg:text-5xl">{{ $match->name }}</h1>
                @if($isPrs)
                    <span class="rounded bg-amber-600 px-2 py-1 text-xs font-bold uppercase">PRS</span>
                @endif
            </div>
            <p class="mt-1 text-lg text-slate-400">
                {{ $match->date?->format('d M Y') }}
                @if($match->location) &mdash; {{ $match->location }} @endif
            </p>
        </div>
        <x-app-logo size="lg" class="opacity-60" />
    </div>

    @if($divisions->isNotEmpty() || $categories->isNotEmpty())
        <div class="mb-4 space-y-2">
            @if($divisions->isNotEmpty())
                <div class="flex gap-2 overflow-x-auto">
                    <span class="self-center text-xs text-slate-600 pr-1">DIV</span>
                    <button wire:click="filterDivision(null)"
                            class="rounded-lg px-4 py-2 text-sm font-medium transition-colors {{ !$activeDivision ? 'bg-red-600 text-white' : 'bg-slate-800 text-slate-400 hover:bg-slate-700' }}">
                        All
                    </button>
                    @foreach($divisions as $div)
                        <button wire:click="filterDivision({{ $div->id }})"
                                class="rounded-lg px-4 py-2 text-sm font-medium transition-colors {{ $activeDivision === $div->id ? 'bg-red-600 text-white' : 'bg-slate-800 text-slate-400 hover:bg-slate-700' }}">
                            {{ $div->name }}
                        </button>
                    @endforeach
                </div>
            @endif
            @if($categories->isNotEmpty())
                <div class="flex gap-2 overflow-x-auto">
                    <span class="self-center text-xs text-slate-600 pr-1">CAT</span>
                    <button wire:click="filterCategory(null)"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors {{ !$activeCategory ? 'bg-blue-600 text-white' : 'bg-slate-800 text-slate-400 hover:bg-slate-700' }}">
                        All
                    </button>
                    @foreach($categories as $cat)
                        <button wire:click="filterCategory({{ $cat->id }})"
                                class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors {{ $activeCategory === $cat->id ? 'bg-blue-600 text-white' : 'bg-slate-800 text-slate-400 hover:bg-slate-700' }}">
                            {{ $cat->name }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @if($sideBetEnabled)
        <div class="mb-4 flex gap-2">
            <button wire:click="setTab('main')"
                    class="rounded-lg px-5 py-2.5 text-sm font-bold transition-colors {{ $activeTab === 'main' ? 'bg-red-600 text-white' : 'bg-slate-800 text-slate-400 hover:bg-slate-700' }}">
                Main Scoreboard
            </button>
            <button wire:click="setTab('sidebet')"
                    class="rounded-lg px-5 py-2.5 text-sm font-bold transition-colors {{ $activeTab === 'sidebet' ? 'bg-amber-600 text-white' : 'bg-slate-800 text-slate-400 hover:bg-slate-700' }}">
                Side Bet
            </button>
        </div>
    @endif

    @if($sideBetEnabled && $activeTab === 'sidebet')
        <div class="overflow-hidden rounded-2xl border border-amber-700/50 bg-slate-900">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-slate-700 bg-slate-800/80">
                        <th class="px-6 py-4 text-lg font-bold text-slate-300 lg:text-xl">#</th>
                        <th class="px-6 py-4 text-lg font-bold text-slate-300 lg:text-xl">Shooter</th>
                        <th class="px-6 py-4 text-lg font-bold text-slate-300 lg:text-xl">Squad</th>
                        <th class="px-6 py-4 text-center text-lg font-bold text-amber-400 lg:text-xl">Small Gong Hits</th>
                        <th class="px-6 py-4 text-lg font-bold text-slate-300 lg:text-xl">Distances</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($sideBetEntries as $entry)
                        @php
                            $rowClass = match($entry->rank) {
                                1 => 'bg-amber-500/10 border-l-4 border-l-amber-400',
                                2 => 'bg-slate-400/5 border-l-4 border-l-slate-400',
                                3 => 'bg-orange-500/5 border-l-4 border-l-orange-600',
                                default => 'border-l-4 border-l-transparent',
                            };
                            $rankClass = match($entry->rank) {
                                1 => 'text-amber-400 font-black',
                                2 => 'text-slate-300 font-bold',
                                3 => 'text-orange-500 font-bold',
                                default => 'text-slate-500 font-medium',
                            };
                        @endphp
                        <tr class="{{ $rowClass }} transition-colors">
                            <td class="px-6 py-4 text-2xl {{ $rankClass }} lg:text-3xl">{{ $entry->rank }}</td>
                            <td class="px-6 py-4 text-xl font-semibold text-white lg:text-2xl">{{ $entry->name }}</td>
                            <td class="px-6 py-4 text-lg text-slate-400 lg:text-xl">{{ $entry->squad_name }}</td>
                            <td class="px-6 py-4 text-center text-2xl font-black text-amber-400 lg:text-3xl">{{ $entry->small_gong_hits }}</td>
                            <td class="px-6 py-4 text-lg text-slate-300 lg:text-xl">
                                @if(!empty($entry->distances))
                                    {{ implode('m, ', $entry->distances) }}m
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center text-2xl text-slate-500">No side bet scores yet</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
    <div class="overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-slate-700 bg-slate-800/80">
                    <th class="px-6 py-4 text-lg font-bold text-slate-300 lg:text-xl">#</th>
                    <th class="px-6 py-4 text-lg font-bold text-slate-300 lg:text-xl">Shooter</th>
                    <th class="px-6 py-4 text-lg font-bold text-slate-300 lg:text-xl">Squad</th>
                    @if($divisions->isNotEmpty())
                        <th class="px-6 py-4 text-lg font-bold text-slate-300 lg:text-xl">Division</th>
                    @endif
                    <th class="px-6 py-4 text-center text-lg font-bold text-green-400 lg:text-xl">Hits</th>
                    <th class="px-6 py-4 text-center text-lg font-bold text-red-400 lg:text-xl">Misses</th>
                    @if($isPrs)
                        <th class="px-6 py-4 text-center text-lg font-bold text-amber-400/60 lg:text-xl">N/T</th>
                    @endif
                    @if($isPrs)
                        <th class="px-6 py-4 text-right text-lg font-bold text-slate-300 lg:text-xl">Time</th>
                    @endif
                    <th class="px-6 py-4 text-right text-lg font-bold text-amber-400 lg:text-xl">Score</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
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
                            2 => 'text-slate-300 font-bold',
                            3 => 'text-orange-500 font-bold',
                            default => 'text-slate-500 font-medium',
                        };
                    @endphp
                    <tr class="{{ $rowClass }} transition-colors">
                        <td class="px-6 py-4 text-2xl {{ $rankClass }} lg:text-3xl">{{ $rank }}</td>
                        <td class="px-6 py-4 text-xl font-semibold text-white lg:text-2xl">{{ $shooter->name }}</td>
                        <td class="px-6 py-4 text-lg text-slate-400 lg:text-xl">{{ $shooter->squad?->name ?? '—' }}</td>
                        @if($divisions->isNotEmpty())
                            <td class="px-6 py-4 text-lg text-slate-400 lg:text-xl">{{ $shooter->division?->name ?? '—' }}</td>
                        @endif
                        <td class="px-6 py-4 text-center text-xl font-bold text-green-400 lg:text-2xl">{{ $shooter->hits_count }}</td>
                        <td class="px-6 py-4 text-center text-xl font-bold text-red-400 lg:text-2xl">{{ $shooter->misses_count }}</td>
                        @if($isPrs)
                            <td class="px-6 py-4 text-center text-xl font-bold text-amber-400/60 lg:text-2xl">{{ $shooter->not_taken ?? 0 }}</td>
                        @endif
                        @if($isPrs)
                            <td class="px-6 py-4 text-right text-xl font-mono text-slate-300 lg:text-2xl">
                                @if($shooter->total_time > 0)
                                    {{ sprintf('%02d:%05.2f', floor($shooter->total_time / 60), fmod($shooter->total_time, 60)) }}
                                @else
                                    —
                                @endif
                            </td>
                        @endif
                        <td class="px-6 py-4 text-right text-2xl font-black text-amber-400 lg:text-3xl">
                            {{ $isPrs ? $shooter->total_score : number_format($shooter->total_score, 1) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ ($isPrs ? 8 : 6) + ($divisions->isNotEmpty() ? 1 : 0) }}" class="px-6 py-16 text-center text-2xl text-slate-500">
                            No scores recorded yet
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @endif

    <div class="mt-6 flex items-center justify-between text-sm text-slate-600">
        <span>
            Auto-refreshes every 10 seconds
            @if($isPrs) &bull; Ranked by total hits, then tiebreaker stage hits, then tiebreaker stage time @endif
            @if($sideBetEnabled && $activeTab === 'sidebet') &bull; Ranked by smallest gong hits, furthest distance tiebreaker @endif
        </span>
        <span>&copy; {{ date('Y') }} DeadCenter</span>
    </div>
</div>
