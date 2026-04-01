<?php

use App\Models\ShootingMatch;
use App\Models\MatchRegistration;
use App\Enums\MatchStatus;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Browse Matches')]
    class extends Component {
    public string $search = '';
    public string $statusFilter = 'all';

    public function with(): array
    {
        $visibleStatuses = [
            MatchStatus::PreRegistration,
            MatchStatus::RegistrationOpen,
            MatchStatus::RegistrationClosed,
            MatchStatus::SquaddingOpen,
            MatchStatus::Active,
            MatchStatus::Completed,
        ];

        $matches = ShootingMatch::with('organization')
            ->whereIn('status', $visibleStatuses)
            ->when($this->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($this->statusFilter !== 'all', fn ($q) =>
                $q->where('status', $this->statusFilter)
            )
            ->orderByDesc('date')
            ->get();

        $myRegistrations = auth()->check()
            ? MatchRegistration::where('user_id', auth()->id())
                ->whereIn('match_id', $matches->pluck('id'))
                ->pluck('payment_status', 'match_id')
            : collect();

        return ['matches' => $matches, 'myRegistrations' => $myRegistrations];
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">Browse Matches</flux:heading>
        <p class="mt-1 text-sm text-muted">Find matches, register, or watch live scores.</p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <div class="max-w-sm flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search matches..." icon="magnifying-glass" />
        </div>
        <div class="flex gap-1.5 overflow-x-auto text-xs">
            @foreach(['all' => 'All', 'pre_registration' => 'Pre-Reg', 'registration_open' => 'Open', 'registration_closed' => 'Closed', 'squadding_open' => 'Squadding', 'active' => 'Live', 'completed' => 'Completed'] as $val => $label)
                <button wire:click="$set('statusFilter', '{{ $val }}')"
                        class="rounded-full px-3 py-1 font-medium transition-colors {{ $statusFilter === $val ? 'bg-accent text-white' : 'bg-surface-2 text-muted hover:text-secondary' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    @if($matches->isEmpty())
        <div class="rounded-xl border border-border bg-surface px-6 py-12 text-center">
            <p class="text-muted">
                @if($search)
                    No matches found for "{{ $search }}".
                @else
                    No matches available right now. Check back soon!
                @endif
            </p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach($matches as $match)
                @php $myStatus = $myRegistrations[$match->id] ?? null; @endphp
                <div wire:key="match-{{ $match->id }}"
                     class="rounded-xl border {{ $match->status === MatchStatus::Active ? 'border-green-700/40' : 'border-border' }} bg-surface p-6 flex flex-col transition-colors">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="text-lg font-semibold text-primary">{{ $match->name }}</h3>
                        <div class="flex items-center gap-1.5 shrink-0">
                            @if($match->status === MatchStatus::Active)
                                <span class="relative flex h-2 w-2">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                                    <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                                </span>
                                <span class="text-xs font-medium text-green-400">Live</span>
                            @else
                                <flux:badge size="sm" color="{{ $match->status->color() }}">{{ $match->status->label() }}</flux:badge>
                            @endif
                        </div>
                    </div>

                    @if($match->organization)
                        <p class="mt-1 text-xs text-muted">{{ $match->organization->name }}</p>
                    @endif

                    <div class="mt-3 space-y-1.5 text-sm text-muted">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                            </svg>
                            {{ $match->date?->format('d M Y') }}
                        </div>
                        @if($match->location)
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                </svg>
                                {{ $match->location }}
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <span class="text-lg font-bold {{ $match->entry_fee ? 'text-primary' : 'text-green-400' }}">
                            {{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free' }}
                        </span>
                        @if($myStatus)
                            @if($myStatus === 'confirmed')
                                <flux:badge size="sm" color="green">Registered</flux:badge>
                            @elseif($myStatus === 'pre_registered')
                                <flux:badge size="sm" color="violet">Pre-Registered</flux:badge>
                            @elseif($myStatus === 'pending_payment')
                                <flux:badge size="sm" color="amber">Payment Pending</flux:badge>
                            @elseif($myStatus === 'proof_submitted')
                                <flux:badge size="sm" color="blue">Under Review</flux:badge>
                            @endif
                        @endif
                    </div>

                    <div class="mt-4 flex items-center gap-2">
                        @if(in_array($match->status, [MatchStatus::Active, MatchStatus::Completed]))
                            <a href="{{ route('live', $match) }}"
                               class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $match->status === MatchStatus::Active ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-surface-2 text-secondary hover:bg-surface-2' }}">
                                {{ $match->status === MatchStatus::Active ? 'Watch Live' : 'View Results' }}
                            </a>
                        @endif

                        @if($match->canRegister() && !$myStatus)
                            <a href="{{ route('matches.show', $match) }}"
                               class="inline-flex items-center rounded-lg bg-accent px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-accent-hover">
                                {{ $match->isPreRegistration() ? 'Pre-Register' : 'Register' }}
                            </a>
                        @elseif($match->isRegistrationOpen() && $myStatus === 'pre_registered')
                            <a href="{{ route('matches.show', $match) }}"
                               class="inline-flex items-center rounded-lg bg-sky-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-sky-700">
                                Complete Registration
                            </a>
                        @elseif($match->isSquaddingOpen() && $myStatus === 'confirmed')
                            <a href="{{ route('matches.squadding', $match) }}"
                               class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700">
                                Pick Squad
                            </a>
                        @elseif($myStatus === 'pending_payment')
                            <a href="{{ route('matches.show', $match) }}"
                               class="inline-flex items-center rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700">
                                Upload Payment
                            </a>
                        @elseif(!$myStatus && !$match->is_completed)
                            <a href="{{ route('matches.show', $match) }}"
                               class="inline-flex items-center rounded-lg bg-surface-2 px-3 py-2 text-sm font-medium text-secondary transition-colors hover:bg-surface-2">
                                View Details
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
