<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Enums\MatchStatus;
use App\Services\SeasonStandingsService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.portal')]
    class extends Component {
    public Organization $organization;

    public function getTitle(): string
    {
        return 'Leaderboard — ' . $this->organization->name;
    }

    public function with(): array
    {
        $org = $this->organization;

        $orgIds = collect([$org->id]);
        if ($org->isLeague()) {
            $orgIds = $orgIds->merge($org->children()->pluck('id'));
        }

        $matches = ShootingMatch::whereIn('organization_id', $orgIds)
            ->where('status', MatchStatus::Completed)
            ->orderBy('date')
            ->get();

        $standings = (new SeasonStandingsService())->calculateForOrganizations($orgIds->all());

        // Flatten per-match results into an index keyed by match_id for the column view.
        $standings = collect($standings)->map(function ($entry) {
            $byMatch = [];
            foreach ($entry['match_results'] as $r) {
                $byMatch[$r['match_id']] = $r;
            }
            $entry['scores_by_match'] = $byMatch;
            return $entry;
        })->values();

        $bestOf = $org->best_of > 0 ? (int) $org->best_of : SeasonStandingsService::DEFAULT_BEST_OF;
        $usesRelative = (bool) $org->uses_relative_scoring;

        // Season cap = top-N leaderboard_points values summed (relative mode only; for absolute
        // mode there's no meaningful cap so we hide it in the UI).
        $seasonCap = $matches
            ->pluck('leaderboard_points')
            ->map(fn ($v) => (int) ($v ?? 100))
            ->sortDesc()
            ->take($bestOf)
            ->sum();

        return [
            'leaderboard' => $standings,
            'matches' => $matches,
            'seasonCap' => $seasonCap ?: ($bestOf * 100),
            'bestOf' => $bestOf,
            'usesRelative' => $usesRelative,
        ];
    }
}; ?>

<div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8 space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-primary">Season Leaderboard</h1>
            <p class="mt-1 text-sm text-muted">
                {{ $organization->name }} — best {{ $bestOf }} of {{ $matches->count() }} match{{ $matches->count() === 1 ? '' : 'es' }} counted{{ $usesRelative ? ', out of ' . $seasonCap : '' }}.
            </p>
            @if($usesRelative)
                <p class="mt-1 text-xs text-muted/70">
                    Each match scored out of its own points value (regular = 100, season final = 200). Scaled score = round(shooter ÷ winner × points).
                </p>
            @else
                <p class="mt-1 text-xs text-muted/70">
                    Raw weighted totals are rounded and summed; the shooter's best {{ $bestOf }} results count toward the season total.
                </p>
            @endif
        </div>
        <x-powered-by-block feature="leaderboard" variant="block" />
    </div>

    <x-portal-ad-slot :organization="$organization" placement="portal_leaderboard_strip" variant="block" />

    @if($leaderboard->isEmpty())
        <div class="rounded-xl border border-white/10 bg-app px-6 py-12 text-center">
            <p class="text-muted">No scored results yet. Standings will appear after completed matches are published.</p>
        </div>
    @else
        <div class="rounded-xl border border-white/10 bg-app overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-left text-muted">
                            <th class="px-4 py-3 font-medium w-12">#</th>
                            <th class="px-4 py-3 font-medium">Shooter</th>
                            <th class="px-4 py-3 font-medium text-right">Best {{ $bestOf }}{{ $usesRelative ? ' / ' . $seasonCap : '' }}</th>
                            <th class="px-4 py-3 font-medium text-right">Matches</th>
                            @foreach($matches as $match)
                                @php $pv = (int) ($match->leaderboard_points ?? 100); @endphp
                                <th class="px-3 py-3 font-medium text-right text-xs whitespace-nowrap {{ $pv >= 200 ? 'text-amber-300' : '' }}" title="{{ $match->name }}">
                                    {{ Str::limit($match->name, 12) }}
                                    <br><span class="text-muted">{{ $match->date?->format('d/m') }} / {{ $pv }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($leaderboard as $entry)
                            @php $rank = $entry['rank']; @endphp
                            <tr class="hover:bg-white/5 transition-colors {{ $rank <= 3 ? 'bg-white/[0.02]' : '' }}">
                                <td class="px-4 py-3 font-bold {{ $rank === 1 ? 'text-amber-400' : ($rank === 2 ? 'text-secondary' : ($rank === 3 ? 'text-amber-700' : 'text-muted')) }}">
                                    {{ $rank }}
                                </td>
                                <td class="px-4 py-3 font-medium text-primary">{{ $entry['name'] }}</td>
                                <td class="px-4 py-3 text-right font-bold text-amber-400">{{ $entry['season_total'] ?? $entry['best3_total'] }}</td>
                                <td class="px-4 py-3 text-right text-muted">
                                    {{ $entry['counting_results'] }}/{{ $entry['matches_played'] }}
                                </td>
                                @foreach($matches as $match)
                                    @php
                                        $result = $entry['scores_by_match'][$match->id] ?? null;
                                        $counted = $result && $result['counted'];
                                    @endphp
                                    <td class="px-3 py-3 text-right text-xs {{ $result === null ? 'text-slate-700' : ($counted ? 'text-primary font-medium' : 'text-muted line-through decoration-muted/50') }}">
                                        {{ $result === null ? '—' : $result['relative_score'] }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="text-xs text-muted space-y-1">
            <p>* Bold values count toward the best-{{ $bestOf }} total. Struck-through values are dropped.</p>
            @if($usesRelative)
                <p>* Season final matches (worth 200) are highlighted in amber in the column header.</p>
            @endif
        </div>
    @endif
</div>
