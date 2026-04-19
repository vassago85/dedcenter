<?php

use App\Models\Organization;
use App\Models\MatchRegistration;
use App\Enums\MatchStatus;
use App\Services\RoyalFlushMatchBuilder;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Organization Dashboard')]
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

        $activeMatches = $org->matches()->where('status', MatchStatus::Active)->count();
        $openRegistrations = $org->matches()->where('status', MatchStatus::RegistrationOpen)->count();
        $pendingSquads = $org->matches()->where('status', MatchStatus::SquaddingOpen)->count();
        $resultsReady = $org->matches()->where('status', MatchStatus::Completed)->where('scores_published', true)->count();

        $pendingRegistrations = MatchRegistration::whereIn('match_id', $matchIds)
            ->where('payment_status', 'proof_submitted')->count();

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
            'activeMatches',
            'openRegistrations',
            'pendingSquads',
            'resultsReady',
            'pendingRegistrations',
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
    $hasMatches = $recentMatches->isNotEmpty();
@endphp

<div class="space-y-6">
    <x-app-page-header
        eyebrow="Match Director"
        :title="$organization->name . ' dashboard'"
        subtitle="Plan matches, manage squads, run scoring, and publish results.">
        <x-slot:actions>
            <a href="{{ route('org.matches.create', $org) }}" class="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-accent px-4 text-sm font-semibold text-white transition-colors hover:bg-accent-hover">
                <x-icon name="plus" class="h-4 w-4" />
                Create match
            </a>
            @if($org->isRoyalFlushOrg())
                <button type="button" wire:click="createTodayRoyalFlushMatch"
                        wire:confirm="Create today's Royal Flush match with the 400/500/600/700m preset? This will open squadding immediately."
                        class="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-amber-500/50 bg-amber-900/20 px-4 text-sm font-semibold text-amber-200 transition-colors hover:border-amber-400 hover:text-amber-100">
                    <x-icon name="flame" class="h-4 w-4" />
                    Royal Flush today
                </button>
            @endif
        </x-slot:actions>
    </x-app-page-header>

    {{-- Getting started (only when no matches exist yet) --}}
    @unless($hasMatches)
        <x-panel title="Getting started" subtitle="Follow these steps to set up your first event.">
            <ol class="space-y-3 text-sm">
                <li class="flex items-center gap-3">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-surface-2 text-xs font-bold text-muted">1</span>
                    <span class="font-medium text-primary">Create your first match</span>
                    <a href="{{ route('org.matches.create', $org) }}" class="ml-auto text-xs font-semibold text-accent hover:text-accent-hover">Create →</a>
                </li>
                <li class="flex items-center gap-3">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-surface-2 text-xs font-bold text-muted">2</span>
                    <span class="text-secondary">Set up stages and targets</span>
                </li>
                <li class="flex items-center gap-3">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-surface-2 text-xs font-bold text-muted">3</span>
                    <span class="text-secondary">Open registration</span>
                </li>
                <li class="flex items-center gap-3">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-surface-2 text-xs font-bold text-muted">4</span>
                    <span class="text-secondary">Invite your team (optional)</span>
                </li>
            </ol>
        </x-panel>
    @endunless

    {{-- Stat strip --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
        <x-stat-card label="Active" :value="$activeMatches" :color="$activeMatches > 0 ? 'emerald' : 'slate'" helper="Currently running" />
        <x-stat-card label="Open registration" :value="$openRegistrations" :color="$openRegistrations > 0 ? 'sky' : 'slate'" helper="Accepting sign-ups" />
        <x-stat-card label="Squadding" :value="$pendingSquads" :color="$pendingSquads > 0 ? 'indigo' : 'slate'" helper="Awaiting squads" />
        <x-stat-card label="Pending payments" :value="$pendingRegistrations" :color="$pendingRegistrations > 0 ? 'amber' : 'slate'"
            :helper="$pendingRegistrations > 0 ? 'Proof submitted' : 'All clear'"
            :href="route('org.registrations', $org)" />
        <x-stat-card label="Published" :value="$resultsReady" color="slate" helper="Public scoreboards" />
    </div>

    {{-- Workflow strip (only when there's a live-ish match) --}}
    @if($latestActiveMatch)
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
        <x-panel title="Match workflow" :subtitle="$latestActiveMatch->name">
            <x-slot:actions>
                <a href="{{ route('org.matches.hub', [$org, $latestActiveMatch]) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-muted transition-colors hover:text-primary">
                    Open match
                    <x-icon name="arrow-right" class="h-3.5 w-3.5" />
                </a>
            </x-slot:actions>
            <div class="flex items-start justify-between gap-1 overflow-x-auto pb-2">
                @foreach($steps as $i => $step)
                    @php
                        $isActive = $workflowStep !== null && $i === $workflowStep;
                        $isDone = $workflowStep !== null && $i < $workflowStep;
                    @endphp
                    <div class="flex min-w-[72px] flex-1 flex-col items-center text-center">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition-colors
                            {{ $isActive ? 'bg-accent text-white' : ($isDone ? 'bg-emerald-500/90 text-white' : 'bg-surface-2 text-muted') }}">
                            @if($isDone)
                                <x-icon name="check" class="h-4 w-4" />
                            @else
                                {{ $i + 1 }}
                            @endif
                        </div>
                        <p class="mt-1.5 text-xs font-semibold {{ $isActive ? 'text-accent' : ($isDone ? 'text-emerald-300' : 'text-muted') }}">{{ $step['label'] }}</p>
                        <p class="text-[10px] text-muted">{{ $step['desc'] }}</p>
                    </div>
                    @if(! $loop->last)
                        <div class="mt-4 h-px min-w-[12px] flex-1 {{ $isDone ? 'bg-emerald-500/40' : 'bg-border' }}"></div>
                    @endif
                @endforeach
            </div>
        </x-panel>
    @endif

    {{-- Main grid: stage preview + quick actions --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            @if($latestActiveMatch)
                <x-panel title="Stage preview" :subtitle="$latestActiveMatch->name" :padding="false">
                    <x-slot:actions>
                        <a href="{{ route('org.matches.edit', [$org, $latestActiveMatch]) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-muted transition-colors hover:text-primary">
                            Edit match
                            <x-icon name="pencil" class="h-3.5 w-3.5" />
                        </a>
                    </x-slot:actions>

                    @if($stagePreview->isEmpty())
                        <x-empty-state
                            title="No stages configured yet"
                            description="Add target sets to this match to get started.">
                            <x-slot:icon>
                                <x-icon name="tag" class="h-6 w-6" />
                            </x-slot:icon>
                        </x-empty-state>
                    @else
                        <ul class="divide-y divide-border/70">
                            @foreach($stagePreview as $stage)
                                <li class="flex items-center gap-4 px-6 py-3">
                                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-surface-2 text-xs font-bold text-muted">
                                        {{ $stage->stage_number ?? $loop->iteration }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-primary">{{ $stage->display_name }}</p>
                                        @if($stage->distance_meters)
                                            <p class="text-xs text-muted">{{ $stage->distance_meters }}m</p>
                                        @endif
                                    </div>
                                    @if($stage->total_shots)
                                        <span class="text-xs text-muted">{{ $stage->total_shots }} shots</span>
                                    @endif
                                    @if($stage->is_tiebreaker)
                                        <flux:badge size="sm" color="amber">Tiebreaker</flux:badge>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-panel>
            @else
                <x-panel title="No active match" subtitle="Create a match to begin.">
                    <x-empty-state
                        title="Nothing scheduled"
                        description="Set up a new event to manage squads, run scoring, and publish results.">
                        <x-slot:icon>
                            <x-icon name="calendar" class="h-6 w-6" />
                        </x-slot:icon>
                        <x-slot:actions>
                            <a href="{{ route('org.matches.create', $org) }}" class="inline-flex min-h-[36px] items-center gap-1.5 rounded-lg bg-accent px-3 text-xs font-semibold text-white hover:bg-accent-hover">
                                Create match
                            </a>
                        </x-slot:actions>
                    </x-empty-state>
                </x-panel>
            @endif
        </div>

        <div class="xl:col-span-1">
            <x-panel title="Quick actions" :padding="true">
                <div class="space-y-2">
                    @if($latestActiveMatch)
                        <a href="{{ route('org.matches.squadding', [$org, $latestActiveMatch]) }}" class="flex items-center gap-3 rounded-lg border border-border bg-surface-2/40 p-3 transition-all hover:border-accent/60">
                            <x-icon name="users" class="h-4 w-4 text-muted" />
                            <span class="flex-1 text-sm font-semibold text-primary">Manage squads</span>
                            <x-icon name="arrow-right" class="h-4 w-4 text-muted" />
                        </a>
                        @if($org->isRoyalFlushOrg())
                            <a href="{{ route('org.matches.side-bet', [$org, $latestActiveMatch]) }}" class="flex items-center gap-3 rounded-lg border border-amber-500/50 bg-amber-900/20 p-3 transition-all hover:border-amber-400">
                                <x-icon name="banknote" class="h-4 w-4 text-amber-300" />
                                <span class="flex-1 text-sm font-semibold text-primary">Side bet buy-in</span>
                                <x-icon name="arrow-right" class="h-4 w-4 text-muted" />
                            </a>
                        @endif
                    @endif
                    <a href="https://{{ config('domains.app') }}/score" target="_blank" class="flex items-center gap-3 rounded-lg border border-border bg-surface-2/40 p-3 transition-all hover:border-accent">
                        <x-icon name="play" class="h-4 w-4 text-accent" />
                        <span class="flex-1 text-sm font-semibold text-primary">Open scoring app</span>
                        <x-icon name="external-link" class="h-4 w-4 text-muted" />
                    </a>
                    <a href="{{ route('org.registrations', $org) }}" class="flex items-center gap-3 rounded-lg border border-border bg-surface-2/40 p-3 transition-all hover:border-accent/60">
                        <x-icon name="clipboard-list" class="h-4 w-4 text-muted" />
                        <span class="flex-1 text-sm font-semibold text-primary">Registrations</span>
                        @if($pendingRegistrations > 0)
                            <span class="inline-flex h-5 items-center rounded-full bg-amber-500/20 px-2 text-[10px] font-semibold text-amber-300">{{ $pendingRegistrations }}</span>
                        @endif
                    </a>
                    @if($organization->best_of)
                        <a href="{{ route('leaderboard', $org) }}" target="_blank" class="flex items-center gap-3 rounded-lg border border-border bg-surface-2/40 p-3 transition-all hover:border-accent/60">
                            <x-icon name="chart-column" class="h-4 w-4 text-muted" />
                            <span class="flex-1 text-sm font-semibold text-primary">Leaderboard (best of {{ $organization->best_of }})</span>
                            <x-icon name="external-link" class="h-4 w-4 text-muted" />
                        </a>
                    @endif
                    @if($organization->isLeague() && $childCount > 0)
                        <a href="{{ route('org.clubs', $org) }}" class="flex items-center gap-3 rounded-lg border border-border bg-surface-2/40 p-3 transition-all hover:border-accent/60">
                            <x-icon name="building-2" class="h-4 w-4 text-muted" />
                            <span class="flex-1 text-sm font-semibold text-primary">Clubs in league</span>
                            <span class="text-xs text-muted">{{ $childCount }}</span>
                        </a>
                    @endif
                </div>
            </x-panel>
        </div>
    </div>

    {{-- Recent matches --}}
    <x-panel title="Recent matches" subtitle="All matches for this organization" :padding="false">
        <x-slot:actions>
            <a href="{{ route('org.matches.index', $org) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-muted transition-colors hover:text-primary">
                View all
                <x-icon name="arrow-right" class="h-3.5 w-3.5" />
            </a>
        </x-slot:actions>

        @if($recentMatches->isEmpty())
            <x-empty-state
                title="No matches yet"
                description="Create your first match to get started.">
                <x-slot:icon>
                    <x-icon name="file-text" class="h-6 w-6" />
                </x-slot:icon>
                <x-slot:actions>
                    <a href="{{ route('org.matches.create', $org) }}" class="inline-flex min-h-[36px] items-center gap-1.5 rounded-lg bg-accent px-3 text-xs font-semibold text-white hover:bg-accent-hover">
                        Create match
                    </a>
                </x-slot:actions>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border/70 bg-surface-2/40 text-left">
                            <th class="px-6 py-3 text-[10px] font-semibold uppercase tracking-[0.15em] text-muted">Name</th>
                            <th class="px-6 py-3 text-[10px] font-semibold uppercase tracking-[0.15em] text-muted">Date</th>
                            <th class="px-6 py-3 text-[10px] font-semibold uppercase tracking-[0.15em] text-muted">Status</th>
                            <th class="hidden px-6 py-3 text-right text-[10px] font-semibold uppercase tracking-[0.15em] text-muted md:table-cell">Fee</th>
                            <th class="hidden px-6 py-3 text-right text-[10px] font-semibold uppercase tracking-[0.15em] text-muted lg:table-cell">Registrations</th>
                            <th class="hidden px-6 py-3 text-right text-[10px] font-semibold uppercase tracking-[0.15em] text-muted lg:table-cell">Shooters</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/70">
                        @foreach($recentMatches as $match)
                            <tr class="group cursor-pointer transition-colors hover:bg-surface-2/50"
                                onclick="window.location='{{ route('org.matches.hub', [$org, $match]) }}'">
                                <td class="px-6 py-3.5">
                                    <p class="text-sm font-semibold text-primary transition-colors group-hover:text-accent">{{ $match->name }}</p>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-secondary">{{ $match->date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-6 py-3.5">
                                    <flux:badge size="sm" color="{{ $match->status->color() }}">{{ $match->status->label() }}</flux:badge>
                                </td>
                                <td class="hidden whitespace-nowrap px-6 py-3.5 text-right text-secondary md:table-cell">{{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free' }}</td>
                                <td class="hidden px-6 py-3.5 text-right text-secondary lg:table-cell">{{ $match->registrations_count }}</td>
                                <td class="hidden px-6 py-3.5 text-right text-secondary lg:table-cell">{{ $match->shooters_count }}</td>
                                <td class="px-6 py-3.5 text-right">
                                    <a href="{{ route('org.matches.edit', [$org, $match]) }}" onclick="event.stopPropagation()" class="inline-flex items-center gap-1 text-xs font-semibold text-muted transition-colors hover:text-primary">
                                        Edit
                                        <x-icon name="pencil" class="h-3 w-3" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-panel>
</div>
