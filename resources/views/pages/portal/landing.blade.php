<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\MatchRegistration;
use App\Enums\MatchStatus;
use App\Services\SeasonStandingsService;
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
            ->whereNot('status', MatchStatus::Draft)
            ->whereNot('status', MatchStatus::Completed)
            ->where('date', '>=', now()->startOfDay())
            ->orderBy('date')
            ->take(6)
            ->get();

        $completedCount = ShootingMatch::whereIn('organization_id', $orgIds)
            ->where('status', MatchStatus::Completed)
            ->count();

        $recentResults = ShootingMatch::whereIn('organization_id', $orgIds)
            ->where('status', MatchStatus::Completed)
            ->orderByDesc('date')
            ->take(4)
            ->get();

        $registrationsOpen = ShootingMatch::whereIn('organization_id', $orgIds)
            ->where('status', MatchStatus::RegistrationOpen)
            ->count();

        // Top 5 for leaderboard preview — use the same service the full
        // leaderboard uses so the preview and the full page always agree.
        $standings = app(SeasonStandingsService::class)->calculateForOrganizations($orgIds->all());
        $topShooters = collect(array_slice($standings, 0, 5))->map(function ($row) {
            return (object) [
                'shooter_name' => $row['name'],
                'user_id' => $row['user_id'],
                'total_score' => (int) $row['season_total'],
                'match_count' => (int) $row['matches_played'],
            ];
        });
        $bestOf = $org->best_of > 0 ? (int) $org->best_of : SeasonStandingsService::DEFAULT_BEST_OF;

        return [
            'upcomingMatches' => $upcomingMatches,
            'completedCount' => $completedCount,
            'topShooters' => $topShooters,
            'totalMatches' => ShootingMatch::whereIn('organization_id', $orgIds)->count(),
            'recentResults' => $recentResults,
            'registrationsOpen' => $registrationsOpen,
            'bestOf' => $bestOf,
            'totalSeasonMatches' => $completedCount,
        ];
    }
}; ?>

<div>
    {{-- Hero --}}
    <div class="relative overflow-hidden portal-bg-secondary">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0" style="background: radial-gradient(ellipse at 30% 50%, var(--portal-primary), transparent 70%);"></div>
        </div>
        @php
            $portalHeroLogoUrl = $organization->logoUrl();
        @endphp
        <div class="relative mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8 lg:py-36">
            <div class="grid gap-10 lg:items-center{{ $portalHeroLogoUrl ? ' lg:grid-cols-2 lg:gap-12 xl:gap-16' : '' }}">
                <div>
                    <x-portal-ad-slot class="mb-8 max-w-2xl" :organization="$organization" placement="portal_home_hero" variant="cover" />
                    <div class="max-w-2xl">
                        <h1 class="text-4xl font-bold tracking-tight text-primary sm:text-5xl lg:text-6xl">
                            {{ $organization->hero_text ?? $organization->name }}
                        </h1>
                        @if($organization->hero_description ?? $organization->description)
                            <p class="mt-6 text-lg text-secondary leading-relaxed">
                                {{ $organization->hero_description ?? $organization->description }}
                            </p>
                        @endif
                        <div class="mt-10 flex flex-wrap gap-4">
                            <a href="{{ route('portal.matches', $organization) }}"
                               class="portal-bg-primary portal-bg-primary-hover inline-flex items-center rounded-xl px-6 py-3 text-sm font-semibold text-primary shadow-lg transition-colors">
                                View Matches
                            </a>
                            <a href="{{ route('portal.leaderboard', $organization) }}"
                               class="inline-flex items-center rounded-xl border border-white/20 bg-white/5 px-6 py-3 text-sm font-semibold text-primary hover:bg-white/10 transition-colors">
                                Leaderboard
                            </a>
                        </div>
                    </div>
                </div>
                @if($portalHeroLogoUrl)
                    <div class="flex justify-center lg:justify-end lg:self-center">
                        <div class="flex w-full max-w-xs items-center justify-center rounded-2xl border border-white/10 bg-white/[0.04] px-8 py-10 sm:max-w-sm sm:px-10 sm:py-12 lg:max-w-md">
                            <img
                                src="{{ $portalHeroLogoUrl }}"
                                alt="{{ $organization->name }} logo"
                                class="max-h-36 w-auto max-w-full object-contain object-center sm:max-h-44 lg:max-h-56"
                                decoding="async"
                            />
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 pb-6">
        <x-portal-ad-slot :organization="$organization" placement="portal_home_strip" variant="block" />
    </div>

    {{-- Stats --}}
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 pb-4">
        <div class="grid grid-cols-2 gap-3 sm:gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-white/10 bg-app px-4 py-5 text-center sm:p-6">
                <p class="text-2xl font-bold text-primary sm:text-3xl">{{ $totalMatches }}</p>
                <p class="mt-1 text-xs text-muted sm:text-sm">Total Matches</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-app px-4 py-5 text-center sm:p-6">
                <p class="text-2xl font-bold portal-primary sm:text-3xl">{{ $upcomingMatches->count() }}</p>
                <p class="mt-1 text-xs text-muted sm:text-sm">Upcoming</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-app px-4 py-5 text-center sm:p-6">
                <p class="text-2xl font-bold text-primary sm:text-3xl">{{ $completedCount }}</p>
                <p class="mt-1 text-xs text-muted sm:text-sm">Completed</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-app px-4 py-5 text-center sm:p-6">
                <p class="text-2xl font-bold portal-primary sm:text-3xl">{{ $registrationsOpen }}</p>
                <p class="mt-1 text-xs text-muted sm:text-sm">Open for Registration</p>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8 space-y-16">
        {{-- Upcoming Matches --}}
        <section>
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-primary">Upcoming Matches</h2>
                <a href="{{ route('portal.matches', $organization) }}" class="text-sm font-medium portal-primary hover:underline">View all &rarr;</a>
            </div>

            @if($upcomingMatches->isEmpty())
                <div class="rounded-xl border border-white/10 bg-app px-6 py-12 text-center">
                    <p class="text-muted">No upcoming matches scheduled. Check back soon!</p>
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($upcomingMatches as $match)
                        <a href="{{ route('portal.matches.show', [$organization, $match]) }}"
                           class="rounded-xl border border-white/10 bg-app p-6 hover:portal-border-primary transition-colors block group">
                            <h3 class="text-lg font-semibold text-primary group-hover:portal-primary transition-colors">{{ $match->name }}</h3>
                            <div class="mt-3 space-y-1.5 text-sm text-muted">
                                @if($match->date)
                                    <div class="flex items-center gap-2">
                                        <x-icon name="calendar" class="h-4 w-4 shrink-0" />
                                        {{ $match->date->format('d M Y') }}
                                    </div>
                                @endif
                                @if($match->location)
                                    <div class="flex items-center gap-2">
                                        <x-icon name="map-pin" class="h-4 w-4 shrink-0" />
                                        {{ $match->location }}
                                    </div>
                                @endif
                            </div>
                            <div class="mt-4 flex items-center justify-between">
                                <span class="text-lg font-bold {{ $match->entry_fee ? 'text-primary' : 'text-green-400' }}">
                                    {{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free' }}
                                </span>
                                @php
                                    $canRegisterNow = $match->canRegister() && ! $match->isRegistrationPastDeadline();
                                    $statusLabel = match(true) {
                                        $canRegisterNow => 'Register →',
                                        $match->status === MatchStatus::Active => 'Live Now →',
                                        $match->status === MatchStatus::Ready => 'Starting Soon',
                                        $match->status === MatchStatus::SquaddingOpen => 'Squadding →',
                                        $match->status === MatchStatus::SquaddingClosed => 'Squads Locked',
                                        $match->status === MatchStatus::RegistrationClosed => 'Registration Closed',
                                        $match->isRegistrationPastDeadline() => 'Registration Closed',
                                        default => 'View Details →',
                                    };
                                    $statusClass = match(true) {
                                        $canRegisterNow => 'portal-primary',
                                        $match->status === MatchStatus::Active => 'text-red-400',
                                        default => 'text-muted',
                                    };
                                @endphp
                                <span class="text-sm font-medium {{ $statusClass }}">{{ $statusLabel }}</span>
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
                <h2 class="text-2xl font-bold text-primary">Leaderboard</h2>
                <a href="{{ route('portal.leaderboard', $organization) }}" class="text-sm font-medium portal-primary hover:underline">Full standings &rarr;</a>
            </div>

            <div class="rounded-xl border border-white/10 bg-app overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-left text-muted">
                            <th class="px-6 py-3 font-medium w-12">#</th>
                            <th class="px-6 py-3 font-medium">Shooter</th>
                            <th class="px-6 py-3 font-medium text-right">Matches</th>
                            <th class="px-6 py-3 font-medium text-right">Score</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($topShooters as $i => $shooter)
                            <tr class="hover:bg-white/5 transition-colors">
                                <td class="px-6 py-3 font-bold {{ $i === 0 ? 'text-amber-400' : ($i === 1 ? 'text-secondary' : ($i === 2 ? 'text-amber-700' : 'text-muted')) }}">
                                    {{ $i + 1 }}
                                </td>
                                <td class="px-6 py-3 font-medium text-primary">{{ $shooter->shooter_name }}</td>
                                <td class="px-6 py-3 text-right text-muted">{{ $shooter->match_count }}</td>
                                <td class="px-6 py-3 text-right font-bold text-primary">{{ $shooter->total_score }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="mt-2 text-xs text-muted">
                Best {{ $bestOf }} of {{ $totalSeasonMatches }} match{{ $totalSeasonMatches === 1 ? '' : 'es' }} counted{{ $organization->uses_relative_scoring ? ' (relative, out of 100 per match)' : '' }}.
                <a href="{{ route('portal.leaderboard', $organization) }}" class="portal-primary hover:underline">See full breakdown.</a>
            </p>
        </section>
        @endif

        <section>
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-2xl font-bold text-primary">Latest Results</h2>
                <a href="{{ route('portal.matches', $organization) }}" class="text-sm font-medium portal-primary hover:underline">View all match results &rarr;</a>
            </div>
            @if($recentResults->isEmpty())
                <div class="rounded-xl border border-white/10 bg-app px-6 py-10 text-center">
                    <p class="text-muted">No completed matches yet. Upcoming events will appear here once scoring closes.</p>
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach($recentResults as $result)
                        <a href="{{ route('portal.matches.show', [$organization, $result]) }}" class="rounded-xl border border-white/10 bg-app p-5 transition-colors hover:portal-border-primary">
                            <p class="text-sm font-semibold text-primary">{{ $result->name }}</p>
                            <p class="mt-1 text-xs text-muted">{{ $result->date?->format('d M Y') }}{{ $result->location ? ' • ' . $result->location : '' }}</p>
                            <p class="mt-3 text-sm portal-primary">View result details &rarr;</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>
