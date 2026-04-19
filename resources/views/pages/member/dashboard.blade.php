<?php

use App\Models\ShootingMatch;
use App\Models\Shooter;
use App\Models\MatchRegistration;
use App\Models\UserAchievement;
use App\Enums\MatchStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Shooter Dashboard')]
    class extends Component {
    public function with(): array
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $userId = $user->id;

        $myMatchIds = MatchRegistration::where('user_id', $userId)->pluck('match_id');

        $matchesShot = Shooter::where('user_id', $userId)
            ->whereHas('squad.match', fn ($q) => $q->where('status', MatchStatus::Completed))
            ->distinct('squad_id')
            ->count();

        $podiumBadges = UserAchievement::where('user_id', $userId)
            ->whereHas('achievement', fn ($q) => $q->whereIn('slug', ['podium-gold', 'podium-silver', 'podium-bronze']))
            ->get();

        $podiumCount = $podiumBadges->count();

        $bestFinish = null;
        foreach ($podiumBadges as $badge) {
            $rank = $badge->metadata['rank'] ?? null;
            if ($rank !== null && ($bestFinish === null || $rank < $bestFinish)) {
                $bestFinish = (int) $rank;
            }
        }

        $achievementCount = UserAchievement::where('user_id', $userId)->count();

        $liveMatches = ShootingMatch::with('organization')
            ->activeLiveToday()
            ->orderBy('date', 'desc')
            ->get();

        $upcomingMatches = ShootingMatch::with('organization')
            ->whereIn('id', $myMatchIds)
            ->whereIn('status', [
                MatchStatus::PreRegistration,
                MatchStatus::RegistrationOpen,
                MatchStatus::RegistrationClosed,
                MatchStatus::SquaddingOpen,
                MatchStatus::SquaddingClosed,
                MatchStatus::Ready,
                MatchStatus::Active,
            ])
            ->where('date', '>=', now()->startOfDay())
            ->withCount('shooters')
            ->orderBy('date')
            ->take(4)
            ->get();

        $recentResults = ShootingMatch::with('organization')
            ->where('status', MatchStatus::Completed)
            ->whereHas('shooters', fn ($q) => $q->where('user_id', $userId))
            ->withCount('shooters')
            ->latest('date')
            ->take(4)
            ->get();

        $myOrgs = $user->organizations()->withPivot('is_owner', 'is_match_director', 'is_range_officer', 'is_shooter')->get();
        $primaryOrg = $myOrgs->first();
        $registrationsNeedingAction = MatchRegistration::with('match.organization')
            ->where('user_id', $userId)
            ->whereIn('payment_status', ['pending_payment', 'proof_submitted'])
            ->latest('created_at')
            ->take(3)
            ->get();

        $nextSquaddingMatch = $upcomingMatches->firstWhere('status', MatchStatus::SquaddingOpen);
        $pendingOrgs = $myOrgs->filter(fn ($o) => $o->pivot->is_owner && $o->status === 'pending');

        return compact(
            'matchesShot',
            'podiumCount',
            'bestFinish',
            'achievementCount',
            'liveMatches',
            'upcomingMatches',
            'recentResults',
            'myOrgs',
            'primaryOrg',
            'registrationsNeedingAction',
            'nextSquaddingMatch',
            'pendingOrgs',
        );
    }
}; ?>

<div class="space-y-6">
    @if($pendingOrgs->isNotEmpty())
        <div class="flex items-start gap-3 rounded-xl border border-amber-600/40 bg-amber-900/20 px-5 py-4">
            <x-icon name="circle-alert" class="mt-0.5 h-5 w-5 shrink-0 text-amber-300" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-amber-200">Organization pending review</p>
                <p class="mt-1 text-sm text-amber-100/80">
                    {{ $pendingOrgs->pluck('name')->map(fn($n) => '"'.$n.'"')->join(', ') }}
                    {{ $pendingOrgs->count() > 1 ? 'are' : 'is' }} awaiting approval. You'll be notified when approved.
                </p>
            </div>
        </div>
    @endif

    <x-app-page-header
        eyebrow="Match day · Results · Standings"
        title="Welcome back, {{ explode(' ', auth()->user()->name)[0] }}."
        subtitle="Your shooting dashboard — upcoming events, recent results, and season standings.">
        <x-slot:actions>
            <a href="{{ route('browse-events') }}" class="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-accent px-4 text-sm font-semibold text-white transition-colors hover:bg-accent-hover">
                <x-icon name="plus" class="h-4 w-4" />
                Find a match
            </a>
            @if($registrationsNeedingAction->isNotEmpty())
                <a href="{{ route('matches.show', $registrationsNeedingAction->first()->match) }}" class="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-amber-500/50 bg-amber-900/20 px-4 text-sm font-semibold text-amber-200 transition-colors hover:border-amber-400 hover:text-amber-100">
                    <x-icon name="credit-card" class="h-4 w-4" />
                    Complete payment
                </a>
            @elseif($nextSquaddingMatch)
                <a href="{{ route('matches.squadding', $nextSquaddingMatch) }}" class="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-border bg-surface px-4 text-sm font-semibold text-secondary transition-colors hover:border-accent hover:text-primary">
                    <x-icon name="users" class="h-4 w-4" />
                    Choose my squad
                </a>
            @endif
        </x-slot:actions>
    </x-app-page-header>

    {{-- Stat strip --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-stat-card
            label="Matches shot"
            :value="$matchesShot"
            color="accent"
            helper="Completed events"
            :href="route('matches')" />

        <x-stat-card
            label="Podiums"
            :value="$podiumCount"
            :color="$podiumCount > 0 ? 'amber' : 'slate'"
            helper="Top 3 finishes" />

        <x-stat-card
            label="Best finish"
            :value="$bestFinish ? $bestFinish . match($bestFinish) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' } : '—'"
            :color="$bestFinish === 1 ? 'amber' : ($bestFinish && $bestFinish <= 3 ? 'accent' : 'slate')"
            helper="Overall placement" />

        <x-stat-card
            label="Achievements"
            :value="$achievementCount"
            :color="$achievementCount > 0 ? 'accent' : 'slate'"
            helper="Badges earned" />
    </div>

    {{-- Live now strip --}}
    @if($liveMatches->isNotEmpty())
        <x-panel :padding="false">
            <x-slot:header>
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[0.25em] text-emerald-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 node-pulse"></span>
                        Live now
                    </span>
                    <span class="text-xs text-muted">{{ $liveMatches->count() }} {{ \Illuminate\Support\Str::plural('match', $liveMatches->count()) }} in progress</span>
                </div>
            </x-slot:header>
            <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($liveMatches as $lm)
                    <x-match-card :match="$lm" />
                @endforeach
            </div>
        </x-panel>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        {{-- Upcoming --}}
        <div class="xl:col-span-2">
            <x-panel title="Upcoming matches" subtitle="Events you've registered for" :padding="false">
                <x-slot:actions>
                    <a href="{{ route('matches') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-muted transition-colors hover:text-primary">
                        View all
                        <x-icon name="arrow-right" class="h-3.5 w-3.5" />
                    </a>
                </x-slot:actions>

                @if($upcomingMatches->isEmpty())
                    <x-empty-state
                        title="No upcoming matches"
                        description="Browse available matches to register for your next event.">
                        <x-slot:icon>
                            <x-icon name="calendar" class="h-6 w-6" />
                        </x-slot:icon>
                        <x-slot:actions>
                            <a href="{{ route('browse-events') }}" class="inline-flex min-h-[36px] items-center gap-1.5 rounded-lg bg-accent px-3 text-xs font-semibold text-white hover:bg-accent-hover">
                                Find matches
                            </a>
                        </x-slot:actions>
                    </x-empty-state>
                @else
                    <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">
                        @foreach($upcomingMatches as $match)
                            <x-match-card :match="$match" />
                        @endforeach
                    </div>
                @endif
            </x-panel>
        </div>

        {{-- Side panel --}}
        <div class="xl:col-span-1 space-y-6">
            @if($myOrgs->isNotEmpty())
                <x-panel title="My organizations" subtitle="Clubs and leagues you belong to" :padding="false">
                    <ul class="divide-y divide-border/70">
                        @foreach($myOrgs as $org)
                            @php
                                $primaryRole = $org->pivot->is_owner ? 'Owner' : ($org->pivot->is_match_director ? 'MD' : ($org->pivot->is_range_officer ? 'RO' : 'Shooter'));
                                $primaryColor = $org->pivot->is_owner ? 'amber' : ($org->pivot->is_match_director ? 'blue' : ($org->pivot->is_range_officer ? 'green' : 'zinc'));
                            @endphp
                            <li>
                                <a href="{{ $org->pivot->is_owner || $org->pivot->is_match_director ? route('org.dashboard', $org) : route('leaderboard', $org) }}"
                                   class="flex items-center gap-3 px-5 py-3.5 transition-colors hover:bg-surface-2/60">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-surface-2 text-xs font-bold uppercase text-muted">
                                        @if($org->logoUrl())
                                            <img src="{{ $org->logoUrl() }}" alt="{{ $org->name }}" class="h-9 w-9 rounded-lg object-cover" />
                                        @else
                                            {{ substr($org->name, 0, 2) }}
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-primary">{{ $org->name }}</p>
                                        <p class="truncate text-[11px] capitalize text-muted">{{ $org->type }}</p>
                                    </div>
                                    <flux:badge size="sm" color="{{ $primaryColor }}">{{ $primaryRole }}</flux:badge>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-panel>
            @endif

            <x-panel title="Quick actions" :padding="true">
                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ route('matches') }}" class="flex flex-col items-center gap-2 rounded-lg border border-border bg-surface-2/40 p-3 text-center transition-all hover:border-accent/60">
                        <x-icon name="trophy" class="h-5 w-5 text-muted" />
                        <span class="text-xs font-semibold text-primary">My results</span>
                    </a>
                    @if($primaryOrg)
                        <a href="{{ route('leaderboard', $primaryOrg) }}" class="flex flex-col items-center gap-2 rounded-lg border border-border bg-surface-2/40 p-3 text-center transition-all hover:border-accent/60">
                            <x-icon name="chart-column" class="h-5 w-5 text-muted" />
                            <span class="text-xs font-semibold text-primary">Standings</span>
                        </a>
                    @endif
                    <a href="{{ route('equipment') }}" class="flex flex-col items-center gap-2 rounded-lg border border-border bg-surface-2/40 p-3 text-center transition-all hover:border-accent/60">
                        <x-icon name="target" class="h-5 w-5 text-muted" />
                        <span class="text-xs font-semibold text-primary">Equipment</span>
                    </a>
                    <a href="{{ route('settings') }}" class="flex flex-col items-center gap-2 rounded-lg border border-border bg-surface-2/40 p-3 text-center transition-all hover:border-accent/60">
                        <x-icon name="user" class="h-5 w-5 text-muted" />
                        <span class="text-xs font-semibold text-primary">Profile</span>
                    </a>
                </div>
            </x-panel>
        </div>
    </div>

    {{-- Recent results --}}
    <x-panel title="Recent results" subtitle="Your completed matches" :padding="false">
        <x-slot:actions>
            <a href="{{ route('matches') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-muted transition-colors hover:text-primary">
                View all
                <x-icon name="arrow-right" class="h-3.5 w-3.5" />
            </a>
        </x-slot:actions>

        @if($recentResults->isEmpty())
            <x-empty-state
                title="No results yet"
                description="Completed match results will appear here.">
                <x-slot:icon>
                    <x-icon name="trophy" class="h-6 w-6" />
                </x-slot:icon>
            </x-empty-state>
        @else
            <ul class="divide-y divide-border/70">
                @foreach($recentResults as $match)
                    <li class="group px-5 py-4 transition-colors hover:bg-surface-2/50">
                        <div class="flex items-center gap-4">
                            <a href="{{ route('scoreboard', $match) }}" class="flex min-w-0 flex-1 items-center gap-3">
                                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-surface-2 text-muted">
                                    <x-icon name="trophy" class="h-4 w-4" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-primary transition-colors group-hover:text-accent">{{ $match->name }}</p>
                                    <p class="mt-0.5 truncate text-xs text-muted">
                                        {{ $match->date?->format('d M Y') }}
                                        @if($match->organization) · {{ $match->organization->name }} @endif
                                    </p>
                                </div>
                            </a>
                            <div class="flex flex-shrink-0 items-center gap-2">
                                <a href="{{ route('matches.my-report', $match) }}"
                                   title="Download your match report (PDF)"
                                   class="inline-flex items-center gap-1 rounded-md border border-border bg-surface-2 px-2.5 py-1 text-xs font-medium text-secondary transition-colors hover:border-accent/60 hover:text-accent">
                                    <x-icon name="download" class="h-3.5 w-3.5" />
                                    <span class="hidden sm:inline">Report</span>
                                </a>
                                <a href="{{ route('scoreboard', $match) }}"
                                   class="inline-flex items-center gap-1 text-xs font-semibold text-accent transition-colors hover:text-accent-hover">
                                    View
                                    <x-icon name="arrow-right" class="h-3.5 w-3.5" />
                                </a>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-panel>
</div>
