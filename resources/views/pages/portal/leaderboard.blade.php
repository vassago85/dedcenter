<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Enums\MatchStatus;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.portal')]
    class extends Component {
    public Organization $organization;
    public string $divisionFilter = '';
    public string $categoryFilter = '';

    public function getTitle(): string
    {
        return 'Leaderboard — ' . $this->organization->name;
    }

    public function with(): array
    {
        $org = $this->organization;
        $bestOf = $org->best_of;

        $orgIds = collect([$org->id]);
        if ($org->isLeague()) {
            $orgIds = $orgIds->merge($org->children()->pluck('id'));
        }

        $matches = ShootingMatch::whereIn('organization_id', $orgIds)
            ->where('status', MatchStatus::Completed)
            ->orderBy('date')
            ->get();

        $matchIds = $matches->pluck('id');
        $prsMatchIds = $matches->where('scoring_type', 'prs')->pluck('id');

        $allDivisions = DB::table('match_divisions')
            ->whereIn('match_id', $matchIds)
            ->select('id', 'name')
            ->distinct()
            ->orderBy('name')
            ->get();

        $divisionNames = $allDivisions->pluck('name')->unique()->sort()->values();

        $allCategories = DB::table('match_categories')
            ->whereIn('match_id', $matchIds)
            ->select('id', 'name')
            ->distinct()
            ->orderBy('name')
            ->get();
        $categoryNames = $allCategories->pluck('name')->unique()->sort()->values();

        $query = DB::table('shooters')
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->join('scores', 'scores.shooter_id', '=', 'shooters.id')
            ->join('gongs', 'scores.gong_id', '=', 'gongs.id')
            ->whereIn('squads.match_id', $matchIds)
            ->where('scores.is_hit', true);

        if ($this->divisionFilter !== '') {
            $query->join('match_divisions', 'shooters.match_division_id', '=', 'match_divisions.id')
                  ->where('match_divisions.name', $this->divisionFilter);
        }

        if ($this->categoryFilter !== '') {
            $catIds = $allCategories->where('name', $this->categoryFilter)->pluck('id');
            $catShooterIds = DB::table('match_category_shooter')
                ->whereIn('match_category_id', $catIds)
                ->pluck('shooter_id');
            $query->whereIn('shooters.id', $catShooterIds);
        }

        $shooterMatchScores = $query->select(
                'shooters.name as shooter_name',
                'shooters.user_id',
                'squads.match_id',
                DB::raw('SUM(gongs.multiplier) as match_score'),
                DB::raw('COUNT(*) as hit_count')
            )
            ->groupBy('shooters.name', 'shooters.user_id', 'squads.match_id')
            ->get();

        $grouped = [];
        foreach ($shooterMatchScores as $row) {
            $key = $row->user_id ? "uid:{$row->user_id}" : "name:" . strtolower($row->shooter_name);
            if (! isset($grouped[$key])) {
                $grouped[$key] = ['name' => $row->shooter_name, 'user_id' => $row->user_id, 'scores' => []];
            }
            $score = $prsMatchIds->contains($row->match_id) ? (float) $row->hit_count : (float) $row->match_score;
            $grouped[$key]['scores'][$row->match_id] = $score;
        }

        $leaderboard = collect($grouped)->map(function ($entry) use ($bestOf) {
            $allScores = collect($entry['scores'])->sortDesc();
            $topScores = $bestOf ? $allScores->take($bestOf) : $allScores;

            return [
                'name' => $entry['name'],
                'total_score' => $topScores->sum(),
                'match_count' => $allScores->count(),
                'counted_matches' => $topScores->count(),
                'scores_by_match' => $entry['scores'],
            ];
        })->sortByDesc('total_score')->values();

        return [
            'leaderboard' => $leaderboard,
            'matches' => $matches,
            'bestOf' => $bestOf,
            'divisionNames' => $divisionNames,
            'categoryNames' => $categoryNames,
        ];
    }
}; ?>

<div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8 space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-white">Leaderboard</h1>
        <p class="mt-1 text-sm text-slate-400">
            {{ $organization->name }} —
            @if($bestOf)
                Best {{ $bestOf }} {{ Str::plural('score', $bestOf) }} counted.
            @else
                All scores counted.
            @endif
        </p>
    </div>

    @if($divisionNames->isNotEmpty() || $categoryNames->isNotEmpty())
        <div class="space-y-2">
            @if($divisionNames->isNotEmpty())
                <div class="flex flex-wrap gap-2 items-center">
                    <span class="text-[10px] text-slate-600">DIV</span>
                    <button wire:click="$set('divisionFilter', '')"
                            class="rounded-full px-3 py-1 text-xs font-medium transition-colors {{ $divisionFilter === '' ? 'bg-red-600 text-white' : 'bg-white/10 text-slate-400 hover:bg-white/20' }}">
                        All
                    </button>
                    @foreach($divisionNames as $dn)
                        <button wire:click="$set('divisionFilter', '{{ $dn }}')"
                                class="rounded-full px-3 py-1 text-xs font-medium transition-colors {{ $divisionFilter === $dn ? 'bg-red-600 text-white' : 'bg-white/10 text-slate-400 hover:bg-white/20' }}">
                            {{ $dn }}
                        </button>
                    @endforeach
                </div>
            @endif
            @if($categoryNames->isNotEmpty())
                <div class="flex flex-wrap gap-2 items-center">
                    <span class="text-[10px] text-slate-600">CAT</span>
                    <button wire:click="$set('categoryFilter', '')"
                            class="rounded-full px-2.5 py-0.5 text-[10px] font-medium transition-colors {{ $categoryFilter === '' ? 'bg-blue-600 text-white' : 'bg-white/10 text-slate-400 hover:bg-white/20' }}">
                        All
                    </button>
                    @foreach($categoryNames as $cn)
                        <button wire:click="$set('categoryFilter', '{{ $cn }}')"
                                class="rounded-full px-2.5 py-0.5 text-[10px] font-medium transition-colors {{ $categoryFilter === $cn ? 'bg-blue-600 text-white' : 'bg-white/10 text-slate-400 hover:bg-white/20' }}">
                            {{ $cn }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @if($leaderboard->isEmpty())
        <div class="rounded-xl border border-white/10 bg-slate-900 px-6 py-12 text-center">
            <p class="text-slate-400">No completed matches with scores yet.</p>
        </div>
    @else
        <div class="rounded-xl border border-white/10 bg-slate-900 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-left text-slate-400">
                            <th class="px-4 py-3 font-medium w-12">#</th>
                            <th class="px-4 py-3 font-medium">Shooter</th>
                            <th class="px-4 py-3 font-medium text-right">Total</th>
                            <th class="px-4 py-3 font-medium text-right">Matches</th>
                            @foreach($matches as $match)
                                <th class="px-3 py-3 font-medium text-right text-xs whitespace-nowrap" title="{{ $match->name }}">
                                    {{ Str::limit($match->name, 12) }}
                                    <br><span class="text-slate-500">{{ $match->date?->format('d/m') }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($leaderboard as $rank => $entry)
                            <tr class="hover:bg-white/5 transition-colors {{ $rank < 3 ? 'bg-white/[0.02]' : '' }}">
                                <td class="px-4 py-3 font-bold {{ $rank === 0 ? 'text-amber-400' : ($rank === 1 ? 'text-slate-300' : ($rank === 2 ? 'text-amber-700' : 'text-slate-500')) }}">
                                    {{ $rank + 1 }}
                                </td>
                                <td class="px-4 py-3 font-medium text-white">{{ $entry['name'] }}</td>
                                <td class="px-4 py-3 text-right font-bold text-white">{{ number_format($entry['total_score'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-slate-400">
                                    {{ $entry['counted_matches'] }}{{ $bestOf ? '/' . $entry['match_count'] : '' }}
                                </td>
                                @foreach($matches as $match)
                                    @php
                                        $score = $entry['scores_by_match'][$match->id] ?? null;
                                        $isTop = false;
                                        if ($bestOf && $score !== null) {
                                            $sorted = collect($entry['scores_by_match'])->sortDesc()->take($bestOf);
                                            $isTop = $sorted->contains($score);
                                        }
                                    @endphp
                                    <td class="px-3 py-3 text-right text-xs {{ $score !== null ? ($isTop ? 'portal-primary font-medium' : 'text-slate-500') : 'text-slate-700' }}">
                                        {{ $score !== null ? number_format($score, 2) : '—' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="text-xs text-slate-500 space-y-1">
            @if($bestOf)
                <p>* Highlighted scores are the top {{ $bestOf }} counted toward the total.</p>
            @endif
            <p>Scores = sum of gong multipliers for successful hits.</p>
        </div>
    @endif
</div>
