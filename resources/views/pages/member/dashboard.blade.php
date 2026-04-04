<?php

use App\Models\ShootingMatch;
use App\Models\Shooter;
use App\Models\MatchRegistration;
use App\Models\UserAchievement;
use App\Enums\MatchStatus;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Shooter Dashboard')]
    class extends Component {
    public function with(): array
    {
        $user = auth()->user();
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

        $myOrgs = $user->organizations()->withPivot('role')->get();
        $primaryOrg = $myOrgs->first();

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
        );
    }
}; ?>

<div class="space-y-8">
    @php
        $pendingOrgs = auth()->user()->organizations()->wherePivot('role', 'owner')->get()->filter(fn($o) => $o->status === 'pending');
    @endphp
    @if($pendingOrgs->isNotEmpty())
        <div class="mb-6 rounded-xl border border-amber-600/30 bg-amber-900/10 px-5 py-4">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 text-amber-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
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

    {{-- Quick Actions --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <a href="{{ route('matches') }}" class="group rounded-xl border border-border bg-surface p-4 text-center transition-all hover:border-accent/50 hover:bg-surface-2/50">
            <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2 text-muted group-hover:text-accent transition-colors">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
            </div>
            <span class="text-xs font-semibold text-primary">Upcoming Matches</span>
            <p class="mt-0.5 text-[10px] text-muted">View registered events</p>
        </a>

        <a href="{{ route('matches') }}" class="group rounded-xl border border-border bg-surface p-4 text-center transition-all hover:border-accent/50 hover:bg-surface-2/50">
            <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2 text-muted group-hover:text-accent transition-colors">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.996.178-1.768.65-2.08 1.293m0 0c-.353.725-.172 1.578.405 2.37.577.792 1.476 1.468 2.597 1.9M3.17 5.53c-.272.14-.52.3-.747.477m12.326-1.77c.996.178 1.768.65 2.08 1.293m0 0c.353.725.172 1.578-.405 2.37-.577.792-1.476 1.468-2.597 1.9m2.675-3.563c.272.14.52.3.747.477" /></svg>
            </div>
            <span class="text-xs font-semibold text-primary">My Results</span>
            <p class="mt-0.5 text-[10px] text-muted">Past match scores</p>
        </a>

        @if($primaryOrg)
            <a href="{{ route('leaderboard', $primaryOrg) }}" class="group rounded-xl border border-border bg-surface p-4 text-center transition-all hover:border-accent/50 hover:bg-surface-2/50">
                <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2 text-muted group-hover:text-accent transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                </div>
                <span class="text-xs font-semibold text-primary">Season Standings</span>
                <p class="mt-0.5 text-[10px] text-muted">Leaderboard rankings</p>
            </a>
        @else
            <div class="rounded-xl border border-border/50 bg-surface/50 p-4 text-center opacity-50">
                <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2 text-muted">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                </div>
                <span class="text-xs font-semibold text-muted">Season Standings</span>
                <p class="mt-0.5 text-[10px] text-muted">Join a club first</p>
            </div>
        @endif

        <a href="{{ route('settings') }}" class="group rounded-xl border border-border bg-surface p-4 text-center transition-all hover:border-accent/50 hover:bg-surface-2/50">
            <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2 text-muted group-hover:text-accent transition-colors">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
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
            <flux:button href="{{ route('matches') }}" size="sm" variant="ghost">View All</flux:button>
        </div>

        @if($upcomingMatches->isEmpty())
            <div class="px-6 py-10 text-center">
                <svg class="mx-auto h-10 w-10 text-muted/50" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                <p class="mt-3 text-sm font-medium text-muted">No upcoming matches</p>
                <p class="mt-1 text-xs text-muted">Browse available matches to register for your next event.</p>
                <flux:button href="{{ route('matches') }}" variant="primary" size="sm" class="mt-4 !bg-accent hover:!bg-accent-hover">
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
            <a href="{{ route('events') }}" class="text-xs font-medium text-accent hover:underline">Browse All Events &rarr;</a>
        </div>
    </div>

    {{-- Recent Results --}}
    <div class="rounded-xl border border-border bg-surface">
        <div class="border-b border-border px-6 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-primary">Recent Results</h2>
                <p class="mt-0.5 text-xs text-muted">Your completed matches</p>
            </div>
            <flux:button href="{{ route('matches') }}" size="sm" variant="ghost">View All</flux:button>
        </div>

        @if($recentResults->isEmpty())
            <div class="px-6 py-10 text-center">
                <svg class="mx-auto h-10 w-10 text-muted/50" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.996.178-1.768.65-2.08 1.293" /></svg>
                <p class="mt-3 text-sm font-medium text-muted">No results yet</p>
                <p class="mt-1 text-xs text-muted">Your completed match results will appear here.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">
                @foreach($recentResults as $match)
                    <a href="{{ route('scoreboard', $match) }}" class="group flex items-center gap-4 rounded-xl border border-border bg-surface-2/30 p-4 transition-all hover:border-accent/40 hover:bg-surface-2/50">
                        <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg bg-surface-2">
                            <svg class="h-5 w-5 text-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497" /></svg>
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
                        @if($org->logo_path)
                            <img src="{{ $org->logo_path }}" alt="{{ $org->name }}" class="h-9 w-9 rounded-lg object-cover" />
                        @else
                            {{ substr($org->name, 0, 2) }}
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <span class="text-sm font-medium text-primary">{{ $org->name }}</span>
                        <span class="ml-2 text-xs text-muted capitalize">{{ $org->type }}</span>
                    </div>
                    <flux:badge size="sm" color="{{ $org->pivot->role === 'owner' ? 'amber' : 'blue' }}">{{ $org->pivot->role }}</flux:badge>
                </a>
            @endforeach
        </div>
    </div>
    @endif
</div>
