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

<x-page-shell>
    <x-app-page-header
        eyebrow="Royal Flush · Side Bet"
        title="Side Bet Report"
        :subtitle="$match->name.' — '.($match->date?->format('d M Y') ?? '')"
        :crumbs="[
            ['label' => 'Matches', 'href' => route('org.matches.index', $match->organization)],
            ['label' => $match->name, 'href' => route('org.matches.edit', [$match->organization, $match])],
            ['label' => 'Side Bet Report'],
        ]"
    >
        <x-slot:actions>
            <button
                type="button"
                onclick="window.print()"
                class="inline-flex items-center gap-2 rounded-lg border border-border bg-surface px-3.5 py-2 text-body font-semibold text-primary transition-colors hover:bg-surface-2 print:hidden"
            >
                <x-icon name="printer" class="h-4 w-4" />
                Print Report
            </button>
        </x-slot:actions>
    </x-app-page-header>

    <x-panel tone="warning" title="Side Bet tiebreaker rules" subtitle="Applied in order, cascading down through every gong size.">
        <ol class="ml-5 list-decimal space-y-1.5 text-body text-secondary leading-relaxed">
            <li>Rank by count of <strong class="text-primary">smallest-gong</strong> (highest-value) hits.</li>
            <li>If tied, the shooter who hit the <strong class="text-primary">furthest distance</strong> at that gong ranks higher; then second-furthest; etc.</li>
            <li>If still tied, drop to the <strong class="text-primary">next gong size</strong> and repeat (count → distances).</li>
            <li>Cascade continues all the way down through every gong size.</li>
        </ol>
    </x-panel>

    @if(!$enabled)
        <x-panel>
            <x-empty-state
                title="Side Bet is not enabled"
                description="Enable Royal Flush and Side Bet in the match settings first."
            >
                <x-slot:icon>
                    <x-icon name="info" class="h-6 w-6" />
                </x-slot:icon>
            </x-empty-state>
        </x-panel>
    @elseif($entries->isEmpty())
        <x-panel>
            <x-empty-state
                title="No participants yet"
                description="No shooters registered for the Side Bet yet."
            >
                <x-slot:icon>
                    <x-icon name="users" class="h-6 w-6" />
                </x-slot:icon>
            </x-empty-state>
        </x-panel>
    @else
        <x-data-table :count="$entries->count()">
            <x-slot:columns>
                <th class="w-12 text-center">#</th>
                <th>Shooter</th>
                <th>Squad</th>
                <th class="text-center">Small Gong Hits</th>
                <th>Distances</th>
                <th class="text-right">Match Score</th>
            </x-slot:columns>

            <x-slot:rows>
                @foreach($entries as $entry)
                    @php
                        $rowClass = match($entry->rank) {
                            1 => 'bg-amber-500/10 [&>td:first-child]:border-l-4 [&>td:first-child]:border-l-amber-400',
                            2 => 'bg-slate-400/5 [&>td:first-child]:border-l-4 [&>td:first-child]:border-l-slate-400',
                            3 => 'bg-orange-500/5 [&>td:first-child]:border-l-4 [&>td:first-child]:border-l-orange-600',
                            default => '',
                        };
                        $rankClass = match($entry->rank) {
                            1 => 'text-amber-400 font-black',
                            2 => 'text-secondary font-bold',
                            3 => 'text-orange-500 font-bold',
                            default => 'text-muted font-medium',
                        };
                    @endphp
                    <tr class="{{ $rowClass }}">
                        <td class="text-center text-lg {{ $rankClass }}">{{ $entry->rank }}</td>
                        <td class="font-semibold text-primary">{{ $entry->name }}</td>
                        <td class="text-muted">{{ $entry->squad_name }}</td>
                        <td class="text-center text-lg font-bold text-amber-400 tabular-nums">{{ $entry->small_gong_hits }}</td>
                        <td class="text-secondary">
                            @if(!empty($entry->distances))
                                {{ implode('m, ', $entry->distances) }}m
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right font-bold tabular-nums text-primary">{{ $entry->total_score }}</td>
                    </tr>
                @endforeach
            </x-slot:rows>

            <x-slot:footer>
                <p class="text-meta text-muted">
                    Ranked by smallest-gong hits first; ties break on furthest distance at that gong, then cascade down through every gong size.
                    &bull; {{ $entries->count() }} participants &bull; Generated {{ now()->format('d M Y H:i') }}
                </p>
            </x-slot:footer>
        </x-data-table>
    @endif
</x-page-shell>
