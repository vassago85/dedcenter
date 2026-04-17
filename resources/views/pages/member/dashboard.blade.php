<?php

use App\Models\ShootingMatch;
use App\Models\Shooter;
use App\Models\MatchRegistration;
use App\Models\UserAchievement;
use App\Enums\MatchStatus;
use Illuminate\Support\Facades\DB;
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

        // Performance stats
        $completedShooterIds = Shooter::where('user_id', $userId)
            ->whereHas('squad.match', fn ($q) => $q->where('status', MatchStatus::Completed))
            ->pluck('id');

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

        // Content sections
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
        );
    }
}; ?>

<div class="space-y-8">
    @php
        $pendingOrgs = auth()->user()->organizations()->wherePivot('is_owner', true)->get()->filter(fn($o) => $o->status === 'pending');
    @endphp
    @if($pendingOrgs->isNotEmpty())
        <div class="mb-6 rounded-xl border border-amber-600/30 bg-amber-900/10 px-5 py-4">
            <div class="flex items-start gap-3">
                <x-icon name="circle-alert" class="h-5 w-5 text-amber-500 mt-0.5 shrink-0" />
                <div>
                    <p class="text-sm font-semibold text-amber-400">Organization Pending Review</p>
                    <p class="mt-1 text-sm text-muted">Your organization{{ $pendingOrgs->count() > 1 ? 's' : '' }}
                        {{ $pendingOrgs->pluck('name')->map(fn($n) => '"'.$n.'"')->join(', ') }}
                        {{ $pendingOrgs->count() > 1 ? 'are' : 'is' }} awaiting approval. You'll be notified when approved.</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Header --}}
    <div>
        <flux:heading size="xl">Shooter Dashboard</flux:heading>
        <p class="mt-1 text-sm text-muted">Your match day companion &mdash; track results, find upcoming matches, and follow standings.</p>
    </div>

    <div class="rounded-xl border border-border bg-surface p-5">
        <p class="text-xs font-semibold uppercase tracking-wider text-muted">What should I do next?</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-3">
            <a href="{{ route('browse-events') }}" class="rounded-lg border border-border bg-surface-2 px-4 py-3 text-sm font-medium text-primary transition-colors hover:border-accent/40">
                Find a match
            </a>
            @if($registrationsNeedingAction->isNotEmpty())
                <a href="{{ route('matches.show', $registrationsNeedingAction->first()->match) }}" class="rounded-lg border border-amber-600/30 bg-amber-900/10 px-4 py-3 text-sm font-medium text-amber-300 transition-colors hover:border-amber-500/50">
                    Complete registration payment
                </a>
            @else
                <a href="{{ route('matches') }}" class="rounded-lg border border-border bg-surface-2 px-4 py-3 text-sm font-medium text-primary transition-colors hover:border-accent/40">
                    View my upcoming matches
                </a>
            @endif
            @if($nextSquaddingMatch)
                <a href="{{ route('matches.squadding', $nextSquaddingMatch) }}" class="rounded-lg border border-border bg-surface-2 px-4 py-3 text-sm font-medium text-primary transition-colors hover:border-accent/40">
                    Choose my squad
                </a>
            @else
                <a href="{{ route('events', ['tab' => 'past']) }}" class="rounded-lg border border-border bg-surface-2 px-4 py-3 text-sm font-medium text-primary transition-colors hover:border-accent/40">
                    Review latest results
                </a>
            @endif
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <a href="{{ route('matches') }}" class="group rounded-xl border border-border bg-surface p-4 text-center transition-all hover:border-accent/50 hover:bg-surface-2/50">
            <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2 text-muted group-hover:text-accent transition-colors">
                <x-icon name="calendar" class="h-5 w-5" />
            </div>
            <span class="text-xs font-semibold text-primary">Upcoming Matches</span>
            <p class="mt-0.5 text-[10px] text-muted">View registered events</p>
        </a>

        <a href="{{ route('matches') }}" class="group rounded-xl border border-border bg-surface p-4 text-center transition-all hover:border-accent/50 hover:bg-surface-2/50">
            <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2 text-muted group-hover:text-accent transition-colors">
                <x-icon name="trophy" class="h-5 w-5" />
            </div>
            <span class="text-xs font-semibold text-primary">My Results</span>
            <p class="mt-0.5 text-[10px] text-muted">Past match scores</p>
        </a>

        @if($primaryOrg)
            <a href="{{ route('leaderboard', $primaryOrg) }}" class="group rounded-xl border border-border bg-surface p-4 text-center transition-all hover:border-accent/50 hover:bg-surface-2/50">
                <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2 text-muted group-hover:text-accent transition-colors">
                    <x-icon name="chart-column" class="h-5 w-5" />
                </div>
                <span class="text-xs font-semibold text-primary">Season Standings</span>
                <p class="mt-0.5 text-[10px] text-muted">Leaderboard rankings</p>
            </a>
        @else
            <div class="rounded-xl border border-border/50 bg-surface/50 p-4 text-center opacity-50">
                <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2 text-muted">
                    <x-icon name="chart-column" class="h-5 w-5" />
                </div>
                <span class="text-xs font-semibold text-muted">Season Standings</span>
                <p class="mt-0.5 text-[10px] text-muted">Join a club first</p>
            </div>
        @endif

        <a href="{{ route('settings') }}" class="group rounded-xl border border-border bg-surface p-4 text-center transition-all hover:border-accent/50 hover:bg-surface-2/50">
            <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2 text-muted group-hover:text-accent transition-colors">
                <x-icon name="user" class="h-5 w-5" />
            </div>
            <span class="text-xs font-semibold text-primary">My Profile</span>
            <p class="mt-0.5 text-[10px] text-muted">Account & settings</p>
        </a>
    </div>

    {{-- Performance Summary --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-muted">Matches Shot</p>
            <p class="mt-2 text-3xl font-bold text-primary">{{ $matchesShot }}</p>
            <p class="mt-1 text-[10px] text-muted">Completed events</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-muted">Podiums</p>
            <p class="mt-2 text-3xl font-bold {{ $podiumCount > 0 ? 'text-amber-400' : 'text-primary' }}">{{ $podiumCount }}</p>
            <p class="mt-1 text-[10px] text-muted">Top 3 finishes</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-muted">Best Finish</p>
            <p class="mt-2 text-3xl font-bold {{ $bestFinish === 1 ? 'text-amber-400' : 'text-primary' }}">
                @if($bestFinish)
                    {{ $bestFinish }}{{ match($bestFinish) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' } }}
                @else
                    &mdash;
                @endif
            </p>
            <p class="mt-1 text-[10px] text-muted">Overall placement</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-muted">Achievements</p>
            <p class="mt-2 text-3xl font-bold {{ $achievementCount > 0 ? 'text-accent' : 'text-primary' }}">{{ $achievementCount }}</p>
            <p class="mt-1 text-[10px] text-muted">Badges earned</p>
        </div>
    </div>

    {{-- Live Now --}}
    @if($liveMatches->isNotEmpty())
    <div class="rounded-xl border border-green-700/50 bg-gradient-to-br from-green-900/20 to-surface">
        <div class="border-b border-green-700/30 px-6 py-4 flex items-center gap-3">
            <span class="relative flex h-3 w-3">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                <span class="relative inline-flex h-3 w-3 rounded-full bg-green-500"></span>
            </span>
            <h2 class="text-lg font-semibold text-primary">Live Now</h2>
            <span class="text-xs text-muted">{{ $liveMatches->count() }} {{ Str::plural('match', $liveMatches->count()) }} in progress</span>
        </div>
        <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($liveMatches as $lm)
                <x-match-card :match="$lm" />
            @endforeach
        </div>
    </div>
    @endif

    {{-- Upcoming Matches --}}
    <div class="rounded-xl border border-border bg-surface">
        <div class="border-b border-border px-6 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-primary">Upcoming Matches</h2>
                <p class="mt-0.5 text-xs text-muted">Matches you've registered for</p>
            </div>
            <flux:button href="{{ route('matches') }}" size="sm" variant="ghost" class="!text-accent hover:!text-accent-hover">View All</flux:button>
        </div>

        @if($upcomingMatches->isEmpty())
            <div class="px-6 py-10 text-center">
                <x-icon name="calendar" class="mx-auto h-10 w-10 text-muted/50" />
                <p class="mt-3 text-sm font-medium text-muted">No upcoming matches</p>
                <p class="mt-1 text-xs text-muted">Browse available matches to register for your next event.</p>
                <flux:button href="{{ route('browse-events') }}" variant="primary" size="sm" class="mt-4 !bg-accent hover:!bg-accent-hover">
                    Find Matches
                </flux:button>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">
                @foreach($upcomingMatches as $match)
                    <x-match-card :match="$match" />
                @endforeach
            </div>
        @endif
        <div class="px-6 pb-4 text-center">
            <a href="{{ route('browse-events') }}" class="text-xs font-medium text-accent hover:underline">Browse All Events &rarr;</a>
        </div>
    </div>

    {{-- Recent Results --}}
    <div class="rounded-xl border border-border bg-surface">
        <div class="border-b border-border px-6 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-primary">Recent Results</h2>
                <p class="mt-0.5 text-xs text-muted">Your completed matches</p>
            </div>
            <flux:button href="{{ route('matches') }}" size="sm" variant="ghost" class="!text-accent hover:!text-accent-hover">View All</flux:button>
        </div>

        @if($recentResults->isEmpty())
            <div class="px-6 py-10 text-center">
                <x-icon name="trophy" class="mx-auto h-10 w-10 text-muted/50" />
                <p class="mt-3 text-sm font-medium text-muted">No results yet</p>
                <p class="mt-1 text-xs text-muted">Your completed match results will appear here.</p>
                <p class="mt-2 text-xs text-secondary">Looking for all past results? Go to <a href="{{ route('browse-events') }}" class="font-semibold text-accent hover:underline">Find a Match</a> and select the <span class="font-semibold">Past Results</span> tab.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">
                @foreach($recentResults as $match)
                    <a href="{{ route('scoreboard', $match) }}" class="group flex items-center gap-4 rounded-xl border border-border bg-surface-2/30 p-4 transition-all hover:border-accent/40 hover:bg-surface-2/50">
                        <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg bg-surface-2">
                            <x-icon name="trophy" class="h-5 w-5 text-muted" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-primary group-hover:text-accent transition-colors">{{ $match->name }}</p>
                            <div class="mt-0.5 flex items-center gap-2 text-xs text-muted">
                                <span>{{ $match->date?->format('d M Y') }}</span>
                                @if($match->organization)
                                    <span>&bull; {{ $match->organization->name }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex-shrink-0">
                            <span class="text-xs font-medium text-accent group-hover:text-accent-hover transition-colors">View Results &rarr;</span>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="px-6 pb-4 text-center">
                <p class="text-xs text-secondary">Browse all completed events and scoreboards on the <a href="{{ route('browse-events') }}" class="font-semibold text-accent hover:underline">Find a Match</a> page under <span class="font-semibold">Past Results</span>.</p>
            </div>
        @endif
    </div>

    {{-- My Organizations --}}
    @if($myOrgs->isNotEmpty())
    <div class="rounded-xl border border-border bg-surface">
        <div class="border-b border-border px-6 py-4">
            <h2 class="text-lg font-semibold text-primary">My Organizations</h2>
            <p class="mt-0.5 text-xs text-muted">Clubs and leagues you belong to</p>
        </div>
        <div class="divide-y divide-border">
            @foreach($myOrgs as $org)
                <a href="{{ route('org.dashboard', $org) }}" class="flex items-center gap-4 px-6 py-3.5 hover:bg-surface-2/30 transition-colors">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-surface-2 text-xs font-bold uppercase text-muted">
                        @if($org->logoUrl())
                            <img src="{{ $org->logoUrl() }}" alt="{{ $org->name }}" class="h-9 w-9 rounded-lg object-cover" />
                        @else
                            {{ substr($org->name, 0, 2) }}
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <span class="text-sm font-medium text-primary">{{ $org->name }}</span>
                        <span class="ml-2 text-xs text-muted capitalize">{{ $org->type }}</span>
                    </div>
                    @php
                        $primaryRole = $org->pivot->is_owner ? 'Owner' : ($org->pivot->is_match_director ? 'MD' : ($org->pivot->is_range_officer ? 'RO' : 'Shooter'));
                        $primaryColor = $org->pivot->is_owner ? 'amber' : ($org->pivot->is_match_director ? 'blue' : ($org->pivot->is_range_officer ? 'green' : 'zinc'));
                    @endphp
                    <flux:badge size="sm" color="{{ $primaryColor }}">{{ $primaryRole }}</flux:badge>
                </a>
            @endforeach
        </div>
    </div>
    @endif
</div>
