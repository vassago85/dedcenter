<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Enums\MatchStatus;
use App\Services\SeasonStandingsService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    class extends Component {
    public Organization $organization;

    public function getTitle(): string
    {
        return $this->organization->name . ' Leaderboard — DeadCenter';
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

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <x-app-page-header
                :title="$organization->name . ' Standings'"
                :subtitle="$usesRelative
                    ? 'Best ' . $bestOf . ' of ' . $matches->count() . ' match' . ($matches->count() === 1 ? '' : 'es') . ' counted. Regular matches = 100 points, season final = 200.'
                    : 'Best ' . $bestOf . ' of ' . $matches->count() . ' match' . ($matches->count() === 1 ? '' : 'es') . ' counted (raw weighted totals, rounded).'"
                :crumbs="[
                    ['label' => 'Shooter Mode', 'href' => route('dashboard')],
                    ['label' => 'Standings'],
                ]"
            />
        </div>
        <x-powered-by-block feature="leaderboard" variant="block" />
    </div>

    @if($leaderboard->isEmpty())
        <div class="rounded-xl border border-border bg-surface px-6 py-12 text-center">
            <p class="text-muted">No completed matches with scores yet.</p>
        </div>
    @else
        <div class="rounded-xl border border-border bg-surface overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-muted">
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
                    <tbody class="divide-y divide-border">
                        @foreach($leaderboard as $entry)
                            @php $rank = $entry['rank']; @endphp
                            <tr class="hover:bg-surface-2/30 transition-colors {{ $rank <= 3 ? 'bg-surface-2/10' : '' }}">
                                <td class="px-4 py-3 font-bold {{ $rank === 1 ? 'text-amber-400' : ($rank === 2 ? 'text-secondary' : ($rank === 3 ? 'text-amber-700' : 'text-muted')) }}">
                                    {{ $rank }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        @if($entry['user_id'])
                                            <a href="{{ route('shooter.profile', $entry['user_id']) }}" class="font-medium text-primary hover:underline">{{ $entry['name'] }}</a>
                                        @else
                                            <span class="font-medium text-primary">{{ $entry['name'] }}</span>
                                        @endif
                                    </div>
                                    @if($entry['user_id'])
                                        <div class="mt-0.5">
                                            <x-badge-flair :userId="$entry['user_id']" :limit="4" />
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-amber-400">{{ $entry['season_total'] ?? $entry['best3_total'] }}</td>
                                <td class="px-4 py-3 text-right text-muted">
                                    {{ $entry['counting_results'] }}/{{ $entry['matches_played'] }}
                                </td>
                                @foreach($matches as $match)
                                    @php
                                        $result = $entry['scores_by_match'][$match->id] ?? null;
                                        $counted = $result && $result['counted'];
                                        $cartridge = $result['cartridge'] ?? null;
                                        $cellTitle = $cartridge
                                            ? $match->name . ' · ' . $cartridge
                                            : $match->name;
                                    @endphp
                                    <td class="px-3 py-3 text-right text-xs whitespace-nowrap {{ $result === null ? 'text-slate-700' : ($counted ? 'text-green-400 font-medium' : 'text-muted line-through decoration-muted/50') }}" title="{{ $cellTitle }}">
                                        {{ $result === null ? '—' : $result['relative_score'] }}
                                        @if($cartridge)
                                            <div class="mt-0.5 text-[10px] font-normal text-muted/70 truncate max-w-[110px] ml-auto" title="{{ $cartridge }}">{{ $cartridge }}</div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="text-xs text-muted space-y-1">
            <p>* Green values count toward the best-{{ $bestOf }} total. Struck-through values are dropped.</p>
            @if($usesRelative)
                <p>* Season-final matches (worth 200 points) are highlighted in amber.</p>
            @endif
        </div>
    @endif
</div>
