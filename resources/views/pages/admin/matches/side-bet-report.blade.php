<?php

use App\Models\ShootingMatch;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    class extends Component {
    public ShootingMatch $match;

    public function with(): array
    {
        if (!$this->match->side_bet_enabled || !$this->match->royal_flush_enabled) {
            return ['entries' => collect(), 'enabled' => false];
        }

        $sideBetIds = $this->match->sideBetShooters()->pluck('shooters.id')->toArray();
        if (empty($sideBetIds)) {
            return ['entries' => collect(), 'enabled' => true];
        }

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
        if (empty($gongIds)) {
            return ['entries' => collect(), 'enabled' => true];
        }

        $shooters = $this->match->shooters()
            ->with('squad')
            ->whereIn('shooters.id', $sideBetIds)
            ->get();

        $shooterIds = $shooters->pluck('id')->toArray();

        $hits = \App\Models\Score::whereIn('gong_id', $gongIds)
            ->whereIn('shooter_id', $shooterIds)
            ->where('is_hit', true)
            ->select('shooter_id', 'gong_id')
            ->get();

        $totalScores = DB::table('scores')
            ->join('gongs', 'scores.gong_id', '=', 'gongs.id')
            ->join('target_sets', 'gongs.target_set_id', '=', 'target_sets.id')
            ->whereIn('scores.shooter_id', $shooterIds)
            ->where('scores.is_hit', true)
            ->groupBy('scores.shooter_id')
            ->selectRaw('scores.shooter_id, COALESCE(SUM(COALESCE(target_sets.distance_multiplier, 1) * gongs.multiplier), 0) as total_score')
            ->pluck('total_score', 'scores.shooter_id')
            ->toArray();

        $profiles = [];
        foreach ($shooters as $s) {
            $profiles[$s->id] = ['shooter' => $s, 'total_score' => round((float) ($totalScores[$s->id] ?? 0), 2), 'ranks' => []];
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
                    if (($ad[$i] ?? 0) !== ($bd[$i] ?? 0)) return ($bd[$i] ?? 0) <=> ($ad[$i] ?? 0);
                }
            }
            return $b['total_score'] <=> $a['total_score'];
        });

        $entries = collect($profileList)->map(fn ($p, $i) => (object) [
            'rank' => $i + 1,
            'name' => $p['shooter']->name,
            'squad_name' => $p['shooter']->squad?->name ?? '—',
            'small_gong_hits' => $p['ranks'][0]['count'] ?? 0,
            'distances' => $p['ranks'][0]['distances'] ?? [],
            'total_score' => $p['total_score'],
        ]);

        return ['entries' => $entries, 'enabled' => true];
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-primary">Side Bet Report</h1>
            <p class="text-sm text-muted">{{ $match->name }} &mdash; {{ $match->date?->format('d M Y') }}</p>
        </div>
        <button onclick="window.print()" class="rounded-lg bg-surface px-4 py-2 text-sm font-medium text-primary hover:bg-surface-2 transition-colors print:hidden">
            Print Report
        </button>
    </div>

    @if(!$enabled)
        <div class="rounded-xl border border-border bg-surface p-8 text-center">
            <p class="text-lg text-muted">Side Bet is not enabled for this match.</p>
            <p class="text-sm text-muted mt-1">Enable Royal Flush and Side Bet in the match settings first.</p>
        </div>
    @elseif($entries->isEmpty())
        <div class="rounded-xl border border-border bg-surface p-8 text-center">
            <p class="text-lg text-muted">No shooters registered for the Side Bet yet.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-amber-700/50 bg-surface">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-border bg-surface-2/50">
                        <th class="px-4 py-3 text-center w-12 font-bold text-secondary">#</th>
                        <th class="px-4 py-3 font-bold text-secondary">Shooter</th>
                        <th class="px-4 py-3 font-bold text-secondary">Squad</th>
                        <th class="px-4 py-3 text-center font-bold text-amber-400">Small Gong Hits</th>
                        <th class="px-4 py-3 font-bold text-secondary">Distances</th>
                        <th class="px-4 py-3 text-right font-bold text-secondary">Match Score</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($entries as $entry)
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
                        <tr class="{{ $rowClass }}">
                            <td class="px-4 py-3 text-center text-lg {{ $rankClass }}">{{ $entry->rank }}</td>
                            <td class="px-4 py-3 font-semibold text-primary">{{ $entry->name }}</td>
                            <td class="px-4 py-3 text-muted">{{ $entry->squad_name }}</td>
                            <td class="px-4 py-3 text-center text-lg font-bold text-amber-400">{{ $entry->small_gong_hits }}</td>
                            <td class="px-4 py-3 text-secondary">
                                @if(!empty($entry->distances))
                                    {{ implode('m, ', $entry->distances) }}m
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-bold tabular-nums">{{ $entry->total_score }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-xs text-muted print:block">
            Ranked by smallest-gong hits first; ties break on furthest distance at that gong, then cascade down through every gong size.
            &bull; {{ $entries->count() }} participants &bull; Generated {{ now()->format('d M Y H:i') }}
        </p>
    @endif
</div>
