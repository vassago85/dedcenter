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

        $standings = (new SeasonStandingsService())->calculateForOrganizations($orgIds);

        $standings = collect($standings)->map(function ($entry) {
            $byMatch = [];
            foreach ($entry['match_results'] as $r) {
                $byMatch[$r['match_id']] = $r;
            }
            $entry['scores_by_match'] = $byMatch;
            return $entry;
        })->values();

        $seasonCap = $matches
            ->pluck('leaderboard_points')
            ->map(fn ($v) => (int) ($v ?? 100))
            ->sortDesc()
            ->take(3)
            ->sum();

        return [
            'leaderboard' => $standings,
            'matches' => $matches,
            'seasonCap' => $seasonCap ?: 300,
        ];
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <x-app-page-header
                :title="$organization->name . ' Standings'"
                subtitle="Best 3 match scores counted. Regular matches = 100 points, season final = 200."
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
                            <th class="px-4 py-3 font-medium text-right">Best 3 / {{ $seasonCap }}</th>
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
                                <td class="px-4 py-3 text-right font-bold text-amber-400">{{ $entry['best3_total'] }}</td>
                                <td class="px-4 py-3 text-right text-muted">
                                    {{ $entry['counting_results'] }}/{{ $entry['matches_played'] }}
                                </td>
                                @foreach($matches as $match)
                                    @php
                                        $result = $entry['scores_by_match'][$match->id] ?? null;
                                        $counted = $result && $result['counted'];
                                    @endphp
                                    <td class="px-3 py-3 text-right text-xs {{ $result === null ? 'text-slate-700' : ($counted ? 'text-green-400 font-medium' : 'text-muted line-through decoration-muted/50') }}">
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
            <p>* Green values count toward the best-3 total. Struck-through values are dropped.</p>
            <p>* Season-final matches (worth 200 points) are highlighted in amber.</p>
        </div>
    @endif
</div>
