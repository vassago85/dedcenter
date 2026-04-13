<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\Shooter;
use App\Models\Score;
use App\Enums\MatchStatus;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    class extends Component {
    public Organization $organization;
    public string $divisionFilter = '';
    public string $categoryFilter = '';

    public function getTitle(): string
    {
        return $this->organization->name . ' Leaderboard — DeadCenter';
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

        $usedDivisionIds = DB::table('shooters')
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->whereIn('squads.match_id', $matchIds)
            ->whereNotNull('shooters.match_division_id')
            ->distinct()
            ->pluck('shooters.match_division_id');

        $allDivisions = DB::table('match_divisions')
            ->whereIn('id', $usedDivisionIds)
            ->select('id', 'name')
            ->distinct()
            ->orderBy('name')
            ->get();

        $divisionNames = $allDivisions->pluck('name')->unique()->sort()->values();

        $shooterIdsInMatches = DB::table('shooters')
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->whereIn('squads.match_id', $matchIds)
            ->pluck('shooters.id');

        $usedCategoryIds = DB::table('match_category_shooter')
            ->whereIn('shooter_id', $shooterIdsInMatches)
            ->distinct()
            ->pluck('match_category_id');

        $allCategories = DB::table('match_categories')
            ->whereIn('id', $usedCategoryIds)
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
                $grouped[$key] = [
                    'name' => $row->shooter_name,
                    'user_id' => $row->user_id,
                    'scores' => [],
                ];
            }
            $score = $prsMatchIds->contains($row->match_id) ? (float) $row->hit_count : (float) $row->match_score;
            $grouped[$key]['scores'][$row->match_id] = $score;
        }

        $leaderboard = collect($grouped)->map(function ($entry) use ($bestOf, $matches) {
            $allScores = collect($entry['scores'])->sortDesc();

            $topScores = $bestOf
                ? $allScores->take($bestOf)
                : $allScores;

            return [
                'name' => $entry['name'],
                'user_id' => $entry['user_id'],
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

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <x-app-page-header
                :title="$organization->name . ' Standings'"
                :subtitle="$bestOf ? 'Ranking based on completed matches. Best ' . $bestOf . ' scores counted.' : 'Ranking based on completed matches. All scores counted.'"
                :crumbs="[
                    ['label' => 'Shooter Mode', 'href' => route('dashboard')],
                    ['label' => 'Standings'],
                ]"
            />
        </div>
        <x-powered-by-block feature="leaderboard" variant="block" />
    </div>

    @if($divisionNames->isNotEmpty() || $categoryNames->isNotEmpty())
        <div class="space-y-2">
            @if($divisionNames->isNotEmpty())
                <div class="flex flex-wrap gap-2 items-center">
                    <span class="text-[10px] text-muted/60">DIV</span>
                    <button wire:click="$set('divisionFilter', '')"
                            class="rounded-full px-3 py-1 text-xs font-medium transition-colors {{ $divisionFilter === '' ? 'bg-accent text-primary' : 'bg-surface-2 text-muted hover:bg-surface-2' }}">
                        All
                    </button>
                    @foreach($divisionNames as $dn)
                        <button wire:click="$set('divisionFilter', '{{ $dn }}')"
                                class="rounded-full px-3 py-1 text-xs font-medium transition-colors {{ $divisionFilter === $dn ? 'bg-accent text-primary' : 'bg-surface-2 text-muted hover:bg-surface-2' }}">
                            {{ $dn }}
                        </button>
                    @endforeach
                </div>
            @endif
            @if($categoryNames->isNotEmpty())
                <div class="flex flex-wrap gap-2 items-center">
                    <span class="text-[10px] text-muted/60">CAT</span>
                    <button wire:click="$set('categoryFilter', '')"
                            class="rounded-full px-2.5 py-0.5 text-[10px] font-medium transition-colors {{ $categoryFilter === '' ? 'bg-blue-600 text-primary' : 'bg-surface-2 text-muted hover:bg-surface-2' }}">
                        All
                    </button>
                    @foreach($categoryNames as $cn)
                        <button wire:click="$set('categoryFilter', '{{ $cn }}')"
                                class="rounded-full px-2.5 py-0.5 text-[10px] font-medium transition-colors {{ $categoryFilter === $cn ? 'bg-blue-600 text-primary' : 'bg-surface-2 text-muted hover:bg-surface-2' }}">
                            {{ $cn }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

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
                            <th class="px-4 py-3 font-medium text-right">Total</th>
                            <th class="px-4 py-3 font-medium text-right">Matches</th>
                            @foreach($matches as $match)
                                <th class="px-3 py-3 font-medium text-right text-xs whitespace-nowrap" title="{{ $match->name }}">
                                    {{ Str::limit($match->name, 12) }}
                                    <br><span class="text-muted">{{ $match->date?->format('d/m') }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($leaderboard as $rank => $entry)
                            <tr class="hover:bg-surface-2/30 transition-colors {{ $rank < 3 ? 'bg-surface-2/10' : '' }}">
                                <td class="px-4 py-3 font-bold {{ $rank === 0 ? 'text-amber-400' : ($rank === 1 ? 'text-secondary' : ($rank === 2 ? 'text-amber-700' : 'text-muted')) }}">
                                    {{ $rank + 1 }}
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
                                <td class="px-4 py-3 text-right font-bold text-primary">{{ number_format($entry['total_score'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-muted">
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
                                    <td class="px-3 py-3 text-right text-xs {{ $score !== null ? ($isTop ? 'text-green-400 font-medium' : 'text-muted') : 'text-slate-700' }}">
                                        {{ $score !== null ? number_format($score, 2) : '—' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="text-xs text-muted space-y-1">
            @if($bestOf)
                <p>* Green scores are the top {{ $bestOf }} counted toward the total.</p>
            @endif
            <p>Scores are computed as the sum of gong multipliers for successful hits.</p>
        </div>
    @endif
</div>
