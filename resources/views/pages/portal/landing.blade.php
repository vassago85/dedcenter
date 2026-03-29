<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\MatchRegistration;
use App\Enums\MatchStatus;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.portal')]
    class extends Component {
    public Organization $organization;

    public function getTitle(): string
    {
        return $this->organization->name;
    }

    public function with(): array
    {
        $org = $this->organization;

        $orgIds = collect([$org->id]);
        if ($org->isLeague()) {
            $orgIds = $orgIds->merge($org->children()->pluck('id'));
        }

        $upcomingMatches = ShootingMatch::whereIn('organization_id', $orgIds)
            ->where('status', MatchStatus::Active)
            ->where('date', '>=', now()->startOfDay())
            ->orderBy('date')
            ->take(6)
            ->get();

        $completedCount = ShootingMatch::whereIn('organization_id', $orgIds)
            ->where('status', MatchStatus::Completed)
            ->count();

        // Top 5 for leaderboard preview
        $matchIds = ShootingMatch::whereIn('organization_id', $orgIds)
            ->where('status', MatchStatus::Completed)
            ->pluck('id');

        $topShooters = collect();
        if ($matchIds->isNotEmpty()) {
            $topShooters = DB::table('shooters')
                ->join('squads', 'shooters.squad_id', '=', 'squads.id')
                ->join('scores', 'scores.shooter_id', '=', 'shooters.id')
                ->join('gongs', 'scores.gong_id', '=', 'gongs.id')
                ->whereIn('squads.match_id', $matchIds)
                ->where('scores.is_hit', true)
                ->select(
                    'shooters.name as shooter_name',
                    'shooters.user_id',
                    DB::raw('SUM(gongs.multiplier) as total_score'),
                    DB::raw('COUNT(DISTINCT squads.match_id) as match_count')
                )
                ->groupBy('shooters.name', 'shooters.user_id')
                ->orderByDesc('total_score')
                ->limit(5)
                ->get();
        }

        return [
            'upcomingMatches' => $upcomingMatches,
            'completedCount' => $completedCount,
            'topShooters' => $topShooters,
            'totalMatches' => ShootingMatch::whereIn('organization_id', $orgIds)->count(),
        ];
    }
}; ?>

<div>
    {{-- Hero --}}
    <div class="relative overflow-hidden portal-bg-secondary">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0" style="background: radial-gradient(ellipse at 30% 50%, var(--portal-primary), transparent 70%);"></div>
        </div>
        <div class="relative mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8 lg:py-36">
            <div class="max-w-2xl">
                <h1 class="text-4xl font-bold tracking-tight text-white sm:text-5xl lg:text-6xl">
                    {{ $organization->hero_text ?? $organization->name }}
                </h1>
                @if($organization->hero_description ?? $organization->description)
                    <p class="mt-6 text-lg text-slate-300 leading-relaxed">
                        {{ $organization->hero_description ?? $organization->description }}
                    </p>
                @endif
                <div class="mt-10 flex flex-wrap gap-4">
                    <a href="{{ route('portal.matches', $organization) }}"
                       class="portal-bg-primary portal-bg-primary-hover inline-flex items-center rounded-xl px-6 py-3 text-sm font-semibold text-white shadow-lg transition-colors">
                        View Matches
                    </a>
                    <a href="{{ route('portal.leaderboard', $organization) }}"
                       class="inline-flex items-center rounded-xl border border-white/20 bg-white/5 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10 transition-colors">
                        Leaderboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="mx-auto max-w-6xl px-4 -mt-8 sm:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-white/10 bg-slate-900 p-6 text-center">
                <p class="text-3xl font-bold text-white">{{ $totalMatches }}</p>
                <p class="mt-1 text-sm text-slate-400">Total Matches</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-900 p-6 text-center">
                <p class="text-3xl font-bold portal-primary">{{ $upcomingMatches->count() }}</p>
                <p class="mt-1 text-sm text-slate-400">Upcoming</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-900 p-6 text-center hidden sm:block">
                <p class="text-3xl font-bold text-white">{{ $completedCount }}</p>
                <p class="mt-1 text-sm text-slate-400">Completed</p>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8 space-y-16">
        {{-- Upcoming Matches --}}
        <section>
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-white">Upcoming Matches</h2>
                <a href="{{ route('portal.matches', $organization) }}" class="text-sm font-medium portal-primary hover:underline">View all &rarr;</a>
            </div>

            @if($upcomingMatches->isEmpty())
                <div class="rounded-xl border border-white/10 bg-slate-900 px-6 py-12 text-center">
                    <p class="text-slate-400">No upcoming matches scheduled. Check back soon!</p>
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($upcomingMatches as $match)
                        <a href="{{ route('portal.matches.show', [$organization, $match]) }}"
                           class="rounded-xl border border-white/10 bg-slate-900 p-6 hover:portal-border-primary transition-colors block group">
                            <h3 class="text-lg font-semibold text-white group-hover:portal-primary transition-colors">{{ $match->name }}</h3>
                            <div class="mt-3 space-y-1.5 text-sm text-slate-400">
                                @if($match->date)
                                    <div class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                                        {{ $match->date->format('d M Y') }}
                                    </div>
                                @endif
                                @if($match->location)
                                    <div class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                                        {{ $match->location }}
                                    </div>
                                @endif
                            </div>
                            <div class="mt-4 flex items-center justify-between">
                                <span class="text-lg font-bold {{ $match->entry_fee ? 'text-white' : 'text-green-400' }}">
                                    {{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free' }}
                                </span>
                                <span class="text-sm font-medium portal-primary">Register &rarr;</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Leaderboard Preview --}}
        @if($topShooters->isNotEmpty())
        <section>
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-white">Leaderboard</h2>
                <a href="{{ route('portal.leaderboard', $organization) }}" class="text-sm font-medium portal-primary hover:underline">Full standings &rarr;</a>
            </div>

            <div class="rounded-xl border border-white/10 bg-slate-900 overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-left text-slate-400">
                            <th class="px-6 py-3 font-medium w-12">#</th>
                            <th class="px-6 py-3 font-medium">Shooter</th>
                            <th class="px-6 py-3 font-medium text-right">Matches</th>
                            <th class="px-6 py-3 font-medium text-right">Score</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($topShooters as $i => $shooter)
                            <tr class="hover:bg-white/5 transition-colors">
                                <td class="px-6 py-3 font-bold {{ $i === 0 ? 'text-amber-400' : ($i === 1 ? 'text-slate-300' : ($i === 2 ? 'text-amber-700' : 'text-slate-500')) }}">
                                    {{ $i + 1 }}
                                </td>
                                <td class="px-6 py-3 font-medium text-white">{{ $shooter->shooter_name }}</td>
                                <td class="px-6 py-3 text-right text-slate-400">{{ $shooter->match_count }}</td>
                                <td class="px-6 py-3 text-right font-bold text-white">{{ number_format($shooter->total_score, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($organization->best_of)
                <p class="mt-2 text-xs text-slate-500">Best {{ $organization->best_of }} scores counted. <a href="{{ route('portal.leaderboard', $organization) }}" class="portal-primary hover:underline">See full breakdown.</a></p>
            @endif
        </section>
        @endif
    </div>
</div>
