<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\MatchRegistration;
use App\Enums\MatchStatus;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Match Director Dashboard')]
    class extends Component {
    public Organization $organization;

    public function with(): array
    {
        $org = $this->organization;
        $matchIds = $org->matches()->pluck('id');

        $totalMatches = $org->matches()->count();
        $activeMatches = $org->matches()->where('status', MatchStatus::Active)->count();
        $openRegistrations = $org->matches()->where('status', MatchStatus::RegistrationOpen)->count();
        $pendingSquads = $org->matches()->where('status', MatchStatus::SquaddingOpen)->count();
        $scoresCaptured = $activeMatches;
        $resultsReady = $org->matches()->where('status', MatchStatus::Completed)->where('scores_published', true)->count();

        $pendingRegistrations = MatchRegistration::whereIn('match_id', $matchIds)
            ->where('payment_status', 'proof_submitted')->count();

        $totalAdmins = $org->admins()->count();
        $childCount = $org->children()->count();

        $latestActiveMatch = $org->matches()
            ->whereIn('status', [
                MatchStatus::Active,
                MatchStatus::SquaddingOpen,
                MatchStatus::RegistrationOpen,
                MatchStatus::RegistrationClosed,
            ])
            ->latest('date')
            ->first();

        $stagePreview = $latestActiveMatch
            ? $latestActiveMatch->targetSets()
                ->orderBy('stage_number')
                ->take(10)
                ->get()
            : collect();

        $recentMatches = $org->matches()
            ->withCount(['shooters', 'registrations'])
            ->latest('date')
            ->take(10)
            ->get();

        $workflowStep = null;
        if ($latestActiveMatch) {
            $workflowStep = match ($latestActiveMatch->status) {
                MatchStatus::Draft => 0,
                MatchStatus::PreRegistration, MatchStatus::RegistrationOpen, MatchStatus::RegistrationClosed => 1,
                MatchStatus::SquaddingOpen => 2,
                MatchStatus::Active => 3,
                MatchStatus::Completed => $latestActiveMatch->scores_published ? 5 : 4,
                default => null,
            };
        }

        return compact(
            'totalMatches',
            'activeMatches',
            'openRegistrations',
            'pendingSquads',
            'scoresCaptured',
            'resultsReady',
            'pendingRegistrations',
            'totalAdmins',
            'childCount',
            'latestActiveMatch',
            'stagePreview',
            'recentMatches',
            'workflowStep',
        );
    }
}; ?>

@php
    $org = $organization;
@endphp

<div class="space-y-8">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ $organization->name }}</flux:heading>
            <p class="mt-1 text-sm text-muted">Match Director Dashboard &mdash; Create matches, manage squads, run scoring, and publish results.</p>
        </div>
        <flux:button href="{{ route('org.matches.create', $org) }}" variant="primary" class="!bg-accent hover:!bg-accent-hover">
            <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Create Match
        </flux:button>
    </div>

    {{-- Quick Actions --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
        <a href="{{ route('org.matches.create', $org) }}" class="group rounded-xl border border-border bg-surface p-4 transition-all hover:border-accent/50 hover:bg-surface-2/50">
            <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-surface-2 text-muted group-hover:text-accent transition-colors">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            </div>
            <span class="text-sm font-semibold text-primary">Create Match</span>
            <p class="mt-0.5 text-xs text-muted">Set up a new event</p>
        </a>

        @if($latestActiveMatch)
            <a href="{{ route('org.matches.edit', [$org, $latestActiveMatch]) }}" class="group rounded-xl border border-border bg-surface p-4 transition-all hover:border-accent/50 hover:bg-surface-2/50">
                <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-surface-2 text-muted group-hover:text-accent transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" /></svg>
                </div>
                <span class="text-sm font-semibold text-primary">Manage Stages</span>
                <p class="mt-0.5 text-xs text-muted">Configure target sets</p>
            </a>

            <a href="{{ route('org.matches.squadding', [$org, $latestActiveMatch]) }}" class="group rounded-xl border border-border bg-surface p-4 transition-all hover:border-accent/50 hover:bg-surface-2/50">
                <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-surface-2 text-muted group-hover:text-accent transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                </div>
                <span class="text-sm font-semibold text-primary">Manage Squads</span>
                <p class="mt-0.5 text-xs text-muted">Assign shooters to squads</p>
            </a>
        @else
            <div class="rounded-xl border border-border/50 bg-surface/50 p-4 opacity-50">
                <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-surface-2 text-muted">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" /></svg>
                </div>
                <span class="text-sm font-semibold text-muted">Manage Stages</span>
                <p class="mt-0.5 text-xs text-muted">Create a match first</p>
            </div>
            <div class="rounded-xl border border-border/50 bg-surface/50 p-4 opacity-50">
                <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-surface-2 text-muted">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                </div>
                <span class="text-sm font-semibold text-muted">Manage Squads</span>
                <p class="mt-0.5 text-xs text-muted">Create a match first</p>
            </div>
        @endif

        <a href="https://{{ config('domains.app') }}/score" target="_blank" class="group rounded-xl border border-border bg-surface p-4 transition-all hover:border-red-500/50 hover:bg-surface-2/50">
            <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-red-600/10 text-red-500 group-hover:bg-red-600/20 transition-colors">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" /></svg>
            </div>
            <span class="text-sm font-semibold text-primary">Open Scoring</span>
            <p class="mt-0.5 text-xs text-muted">Launch scoring app</p>
        </a>

        <a href="{{ route('org.registrations', $org) }}" class="group rounded-xl border border-border bg-surface p-4 transition-all hover:border-accent/50 hover:bg-surface-2/50">
            <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-surface-2 text-muted group-hover:text-accent transition-colors">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15a2.25 2.25 0 0 1 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" /></svg>
            </div>
            <span class="text-sm font-semibold text-primary">View Registrations</span>
            @if($pendingRegistrations > 0)
                <p class="mt-0.5 text-xs text-accent font-medium">{{ $pendingRegistrations }} pending approval</p>
            @else
                <p class="mt-0.5 text-xs text-muted">Review sign-ups</p>
            @endif
        </a>

        @if($latestActiveMatch)
            <a href="{{ route('org.matches.edit', [$org, $latestActiveMatch]) }}" class="group rounded-xl border border-border bg-surface p-4 transition-all hover:border-accent/50 hover:bg-surface-2/50">
                <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-surface-2 text-muted group-hover:text-accent transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                </div>
                <span class="text-sm font-semibold text-primary">Publish Results</span>
                <p class="mt-0.5 text-xs text-muted">Finalize and share scores</p>
            </a>
        @else
            <div class="rounded-xl border border-border/50 bg-surface/50 p-4 opacity-50">
                <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-surface-2 text-muted">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                </div>
                <span class="text-sm font-semibold text-muted">Publish Results</span>
                <p class="mt-0.5 text-xs text-muted">No active match</p>
            </div>
        @endif
    </div>

    {{-- Match Overview Stats --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-muted">Active Matches</p>
            <p class="mt-2 text-3xl font-bold {{ $activeMatches > 0 ? 'text-green-400' : 'text-primary' }}">{{ $activeMatches }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-muted">Open Registrations</p>
            <p class="mt-2 text-3xl font-bold {{ $openRegistrations > 0 ? 'text-sky-400' : 'text-primary' }}">{{ $openRegistrations }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-muted">Pending Squads</p>
            <p class="mt-2 text-3xl font-bold {{ $pendingSquads > 0 ? 'text-indigo-400' : 'text-primary' }}">{{ $pendingSquads }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-muted">Scores Captured</p>
            <p class="mt-2 text-3xl font-bold {{ $scoresCaptured > 0 ? 'text-amber-400' : 'text-primary' }}">{{ $scoresCaptured }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-muted">Results Published</p>
            <p class="mt-2 text-3xl font-bold text-primary">{{ $resultsReady }}</p>
        </div>
    </div>

    {{-- Workflow Guide --}}
    @php
        $steps = [
            ['label' => 'Create', 'desc' => 'New match'],
            ['label' => 'Stages', 'desc' => 'Configure targets'],
            ['label' => 'Squads', 'desc' => 'Assign shooters'],
            ['label' => 'Scoring', 'desc' => 'Capture scores'],
            ['label' => 'Results', 'desc' => 'Review data'],
            ['label' => 'Publish', 'desc' => 'Share publicly'],
        ];
    @endphp
    <div class="rounded-xl border border-border bg-surface p-6">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Match Workflow</h2>
        <div class="flex items-start justify-between gap-1 overflow-x-auto">
            @foreach($steps as $i => $step)
                @php
                    $isActive = $workflowStep !== null && $i === $workflowStep;
                    $isDone = $workflowStep !== null && $i < $workflowStep;
                @endphp
                <div class="flex flex-1 flex-col items-center text-center min-w-[70px]">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition-colors
                        {{ $isActive ? 'bg-accent text-white' : ($isDone ? 'bg-green-600 text-white' : 'bg-surface-2 text-muted') }}">
                        @if($isDone)
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        @else
                            {{ $i + 1 }}
                        @endif
                    </div>
                    <p class="mt-1.5 text-xs font-semibold {{ $isActive ? 'text-accent' : ($isDone ? 'text-green-400' : 'text-muted') }}">{{ $step['label'] }}</p>
                    <p class="text-[10px] text-muted">{{ $step['desc'] }}</p>
                </div>
                @if(! $loop->last)
                    <div class="mt-4 h-px flex-1 min-w-[12px] {{ $isDone ? 'bg-green-600/50' : 'bg-border' }}"></div>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Stage Preview --}}
    @if($latestActiveMatch)
    <div class="rounded-xl border border-border bg-surface">
        <div class="border-b border-border px-6 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-primary">Stage Preview</h2>
                <p class="mt-0.5 text-xs text-muted">{{ $latestActiveMatch->name }}</p>
            </div>
            <flux:button href="{{ route('org.matches.edit', [$org, $latestActiveMatch]) }}" size="sm" variant="ghost">Edit Match</flux:button>
        </div>

        @if($stagePreview->isEmpty())
            <div class="px-6 py-8 text-center">
                <svg class="mx-auto h-10 w-10 text-muted/50" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" /></svg>
                <p class="mt-3 text-sm font-medium text-muted">No stages configured yet</p>
                <p class="mt-1 text-xs text-muted">Add target sets to this match to get started.</p>
            </div>
        @else
            <div class="divide-y divide-border">
                @foreach($stagePreview as $stage)
                    <div class="flex items-center gap-4 px-6 py-3">
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-surface-2 text-xs font-bold text-muted">
                            {{ $stage->stage_number ?? $loop->iteration }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-primary">{{ $stage->display_name }}</p>
                            @if($stage->distance_meters)
                                <p class="text-xs text-muted">{{ $stage->distance_meters }}m</p>
                            @endif
                        </div>
                        @if($stage->total_shots)
                            <span class="text-xs text-muted">{{ $stage->total_shots }} shots</span>
                        @endif
                        @if($stage->is_tiebreaker)
                            <flux:badge size="sm" color="amber">Tiebreaker Stage</flux:badge>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @endif

    {{-- League & Leaderboard --}}
    @if($organization->isLeague() && $childCount > 0)
    <div class="rounded-xl border border-border bg-surface p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-muted">Clubs in League</p>
                <p class="mt-1 text-2xl font-bold text-primary">{{ $childCount }}</p>
            </div>
            <flux:button href="{{ route('org.clubs', $org) }}" size="sm" variant="ghost">Manage Clubs</flux:button>
        </div>
    </div>
    @endif

    @if($organization->best_of)
    <div class="flex items-center gap-4">
        <a href="{{ route('leaderboard', $org) }}" target="_blank"
           class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 transition-colors">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75Z" /></svg>
            View Leaderboard (Best of {{ $organization->best_of }})
        </a>
    </div>
    @endif

    {{-- Recent Matches --}}
    <div class="rounded-xl border border-border bg-surface">
        <div class="border-b border-border px-6 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-primary">Recent Matches</h2>
                <p class="mt-0.5 text-xs text-muted">All matches for this organization</p>
            </div>
            <flux:button href="{{ route('org.matches.index', $org) }}" size="sm" variant="ghost">View All</flux:button>
        </div>

        @if($recentMatches->isEmpty())
            <div class="px-6 py-10 text-center">
                <svg class="mx-auto h-10 w-10 text-muted/50" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75H16.5C17.3284 3.75 18 4.42157 18 5.25V18.75C18 19.5784 17.3284 20.25 16.5 20.25H7.5C6.67157 20.25 6 19.5784 6 18.75V5.25C6 4.42157 6.67157 3.75 7.5 3.75Z" /></svg>
                <p class="mt-3 text-sm font-medium text-muted">No matches yet</p>
                <p class="mt-1 text-xs text-muted">Create your first match to get started.</p>
                <flux:button href="{{ route('org.matches.create', $org) }}" variant="primary" size="sm" class="mt-4 !bg-accent hover:!bg-accent-hover">
                    Create Match
                </flux:button>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-muted">
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wide">Name</th>
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wide">Date</th>
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wide">Status</th>
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wide text-right">Fee</th>
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wide text-right">Registrations</th>
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wide text-right">Shooters</th>
                            <th class="px-6 py-3 text-xs font-medium uppercase tracking-wide"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($recentMatches as $match)
                            <tr class="hover:bg-surface-2/30 transition-colors">
                                <td class="px-6 py-3 font-medium text-primary">{{ $match->name }}</td>
                                <td class="px-6 py-3 text-secondary">{{ $match->date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    <flux:badge size="sm" color="{{ $match->status->color() }}">{{ $match->status->label() }}</flux:badge>
                                </td>
                                <td class="px-6 py-3 text-right text-secondary">{{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free' }}</td>
                                <td class="px-6 py-3 text-right text-secondary">{{ $match->registrations_count }}</td>
                                <td class="px-6 py-3 text-right text-secondary">{{ $match->shooters_count }}</td>
                                <td class="px-6 py-3 text-right">
                                    <flux:button href="{{ route('org.matches.edit', [$org, $match]) }}" size="sm" variant="ghost">Edit</flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
