<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\MatchRegistration;
use App\Enums\MatchStatus;
use App\Services\RoyalFlushMatchBuilder;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Match Director Dashboard')]
    class extends Component {
    public Organization $organization;

    /**
     * One-click: create today's Royal Flush match with full preset applied.
     * Available only to Royal Flush organizations.
     */
    public function createTodayRoyalFlushMatch()
    {
        if (! $this->organization->isRoyalFlushOrg()) {
            Flux::toast('This organization is not a Royal Flush organization.', variant: 'danger');
            return;
        }

        if (! auth()->user()?->isOrgAdmin($this->organization)) {
            Flux::toast('Not authorized.', variant: 'danger');
            return;
        }

        $existing = $this->organization->matches()
            ->whereDate('date', today())
            ->where('royal_flush_enabled', true)
            ->first();

        if ($existing) {
            Flux::toast('A Royal Flush match already exists for today.', variant: 'warning');
            return redirect()->route('org.matches.hub', [$this->organization, $existing]);
        }

        $match = (new RoyalFlushMatchBuilder())->createForDate(
            $this->organization,
            auth()->user()
        );

        Flux::toast('Today\'s Royal Flush match created.', variant: 'success');
        return redirect()->route('org.matches.hub', [$this->organization, $match]);
    }

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
                MatchStatus::Ready,
                MatchStatus::SquaddingOpen,
                MatchStatus::SquaddingClosed,
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
                MatchStatus::SquaddingOpen, MatchStatus::SquaddingClosed => 2,
                MatchStatus::Ready => 3,
                MatchStatus::Active => 4,
                MatchStatus::Completed => $latestActiveMatch->scores_published ? 6 : 5,
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
            <x-app-page-header
                :title="$organization->name . ' Admin Dashboard'"
                subtitle="Create matches, manage squads, run scoring, and publish results."
                :crumbs="[
                    ['label' => 'Organization'],
                    ['label' => $organization->name],
                    ['label' => 'Dashboard'],
                ]"
            />
        </div>
        <flux:button href="{{ route('org.matches.create', $org) }}" variant="primary" class="!bg-accent hover:!bg-accent-hover">
            <x-icon name="plus" class="mr-2 h-4 w-4" />
            Create Match
        </flux:button>
    </div>

    {{-- Getting Started --}}
    @if($organization->matches()->count() === 0)
    <div class="mb-6 rounded-xl border border-border bg-surface p-6">
        <h2 class="text-lg font-semibold text-primary">Getting Started</h2>
        <p class="mt-1 text-sm text-muted">Follow these steps to set up your first event.</p>
        <div class="mt-4 space-y-3">
            @php
                $hasMatch = $organization->matches()->exists();
                $hasStages = $hasMatch && $organization->matches()->first()?->targetSets()->exists();
            @endphp
            <div class="flex items-center gap-3">
                <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $hasMatch ? 'bg-green-600' : 'bg-surface-2' }} text-xs font-bold">{{ $hasMatch ? '✓' : '1' }}</span>
                <span class="text-sm {{ $hasMatch ? 'text-muted line-through' : 'text-primary font-medium' }}">Create your first match</span>
                @unless($hasMatch)
                    <a href="{{ route('org.matches.create', $organization) }}" class="ml-auto text-xs font-medium text-accent hover:text-accent-hover">Create Match →</a>
                @endunless
            </div>
            <div class="flex items-center gap-3">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-surface-2 text-xs font-bold">2</span>
                <span class="text-sm text-secondary">Set up stages and targets</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-surface-2 text-xs font-bold">3</span>
                <span class="text-sm text-secondary">Open registration</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-surface-2 text-xs font-bold">4</span>
                <span class="text-sm text-secondary">Invite your team (optional)</span>
            </div>
        </div>
    </div>
    @endif

    {{-- Quick Actions --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
        <a href="{{ route('org.matches.create', $org) }}" class="group rounded-xl border border-border bg-surface p-4 transition-all hover:border-accent/50 hover:bg-surface-2/50">
            <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-surface-2 text-muted group-hover:text-accent transition-colors">
                <x-icon name="plus" class="h-5 w-5" />
            </div>
            <span class="text-sm font-semibold text-primary">Create Match</span>
            <p class="mt-0.5 text-xs text-muted">Set up a new event</p>
        </a>

        @if($org->isRoyalFlushOrg())
            <button type="button" wire:click="createTodayRoyalFlushMatch"
                    wire:confirm="Create today's Royal Flush match with the 400/500/600/700m preset? This will open squadding immediately."
                    class="group rounded-xl border border-amber-600/40 bg-amber-900/10 p-4 text-left transition-all hover:border-amber-500 hover:bg-amber-900/20">
                <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500/20 text-amber-400 group-hover:text-amber-300 transition-colors">
                    <x-icon name="bolt" class="h-5 w-5" />
                </div>
                <span class="text-sm font-semibold text-primary">Create Today's Royal Flush</span>
                <p class="mt-0.5 text-xs text-muted">One-click, preset applied</p>
            </button>
        @endif

        @if($latestActiveMatch)
            <a href="{{ route('org.matches.edit', [$org, $latestActiveMatch]) }}" class="group rounded-xl border border-border bg-surface p-4 transition-all hover:border-accent/50 hover:bg-surface-2/50">
                <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-surface-2 text-muted group-hover:text-accent transition-colors">
                    <x-icon name="tag" class="h-5 w-5" />
                </div>
                <span class="text-sm font-semibold text-primary">Manage Stages</span>
                <p class="mt-0.5 text-xs text-muted">Configure target sets</p>
            </a>

            <a href="{{ route('org.matches.squadding', [$org, $latestActiveMatch]) }}" class="group rounded-xl border border-border bg-surface p-4 transition-all hover:border-accent/50 hover:bg-surface-2/50">
                <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-surface-2 text-muted group-hover:text-accent transition-colors">
                    <x-icon name="users" class="h-5 w-5" />
                </div>
                <span class="text-sm font-semibold text-primary">Manage Squads</span>
                <p class="mt-0.5 text-xs text-muted">Assign shooters to squads</p>
            </a>

            @if($org->isRoyalFlushOrg())
                <a href="{{ route('org.matches.side-bet', [$org, $latestActiveMatch]) }}" class="group rounded-xl border border-amber-600/40 bg-amber-900/10 p-4 transition-all hover:border-amber-500/70 hover:bg-amber-900/20">
                    <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-amber-600/20 text-amber-400 group-hover:bg-amber-600/30 transition-colors">
                        <x-icon name="check" class="h-5 w-5" />
                    </div>
                    <span class="text-sm font-semibold text-primary">Side Bet Buy-In</span>
                    <p class="mt-0.5 text-xs text-muted">
                        @if($latestActiveMatch->side_bet_enabled)
                            Tap shooters to add to pot
                        @else
                            Enable &amp; collect buy-ins
                        @endif
                    </p>
                </a>
            @endif
        @else
            <div class="rounded-xl border border-border/50 bg-surface/50 p-4 opacity-50">
                <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-surface-2 text-muted">
                    <x-icon name="tag" class="h-5 w-5" />
                </div>
                <span class="text-sm font-semibold text-muted">Manage Stages</span>
                <p class="mt-0.5 text-xs text-muted">Create a match first</p>
            </div>
            <div class="rounded-xl border border-border/50 bg-surface/50 p-4 opacity-50">
                <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-surface-2 text-muted">
                    <x-icon name="users" class="h-5 w-5" />
                </div>
                <span class="text-sm font-semibold text-muted">Manage Squads</span>
                <p class="mt-0.5 text-xs text-muted">Create a match first</p>
            </div>
        @endif

        <a href="https://{{ config('domains.app') }}/score" target="_blank" class="group rounded-xl border border-border bg-surface p-4 transition-all hover:border-red-500/50 hover:bg-surface-2/50">
            <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-red-600/10 text-red-500 group-hover:bg-red-600/20 transition-colors">
                <x-icon name="play" class="h-5 w-5" />
            </div>
            <span class="text-sm font-semibold text-primary">Open Scoring</span>
            <p class="mt-0.5 text-xs text-muted">Launch scoring app</p>
        </a>

        <a href="{{ route('org.registrations', $org) }}" class="group rounded-xl border border-border bg-surface p-4 transition-all hover:border-accent/50 hover:bg-surface-2/50">
            <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-surface-2 text-muted group-hover:text-accent transition-colors">
                <x-icon name="clipboard-list" class="h-5 w-5" />
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
                    <x-icon name="upload" class="h-5 w-5" />
                </div>
                <span class="text-sm font-semibold text-primary">Publish Results</span>
                <p class="mt-0.5 text-xs text-muted">Finalize and share scores</p>
            </a>
        @else
            <div class="rounded-xl border border-border/50 bg-surface/50 p-4 opacity-50">
                <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-surface-2 text-muted">
                    <x-icon name="upload" class="h-5 w-5" />
                </div>
                <span class="text-sm font-semibold text-muted">Publish Results</span>
                <p class="mt-0.5 text-xs text-muted">No active match</p>
            </div>
        @endif
    </div>

    {{-- Match Overview Stats --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
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
            ['label' => 'Ready', 'desc' => 'Tablets can sync'],
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
                            <x-icon name="check" class="h-4 w-4" />
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
                <x-icon name="tag" class="mx-auto h-10 w-10 text-muted/50" />
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
            <x-icon name="chart-column" class="h-4 w-4" />
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
                <x-icon name="file-text" class="mx-auto h-10 w-10 text-muted/50" />
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
                            <th class="px-3 py-3 text-xs font-medium uppercase tracking-wide sm:px-6">Name</th>
                            <th class="px-3 py-3 text-xs font-medium uppercase tracking-wide sm:px-6">Date</th>
                            <th class="px-3 py-3 text-xs font-medium uppercase tracking-wide sm:px-6">Status</th>
                            <th class="hidden px-3 py-3 text-xs font-medium uppercase tracking-wide text-right md:table-cell sm:px-6">Fee</th>
                            <th class="hidden px-3 py-3 text-xs font-medium uppercase tracking-wide text-right lg:table-cell sm:px-6">Registrations</th>
                            <th class="hidden px-3 py-3 text-xs font-medium uppercase tracking-wide text-right lg:table-cell sm:px-6">Shooters</th>
                            <th class="px-3 py-3 text-xs font-medium uppercase tracking-wide sm:px-6"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($recentMatches as $match)
                            <tr class="hover:bg-surface-2/30 transition-colors">
                                <td class="px-3 py-3 font-medium text-primary sm:px-6">{{ $match->name }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-secondary sm:px-6">{{ $match->date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-3 py-3 sm:px-6">
                                    <flux:badge size="sm" color="{{ $match->status->color() }}">{{ $match->status->label() }}</flux:badge>
                                </td>
                                <td class="hidden whitespace-nowrap px-3 py-3 text-right text-secondary md:table-cell sm:px-6">{{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free' }}</td>
                                <td class="hidden px-3 py-3 text-right text-secondary lg:table-cell sm:px-6">{{ $match->registrations_count }}</td>
                                <td class="hidden px-3 py-3 text-right text-secondary lg:table-cell sm:px-6">{{ $match->shooters_count }}</td>
                                <td class="px-3 py-3 text-right sm:px-6">
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
