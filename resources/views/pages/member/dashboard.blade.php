<?php

use App\Models\ShootingMatch;
use App\Models\MatchRegistration;
use App\Enums\MatchStatus;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Dashboard')]
    class extends Component {
    public function with(): array
    {
        $user = auth()->user();

        return [
            'liveMatches' => ShootingMatch::with('organization')
                ->where('status', MatchStatus::Active)
                ->orderBy('date', 'desc')
                ->get(),
            'upcomingMatches' => ShootingMatch::with('organization')
                ->where('status', MatchStatus::Active)
                ->where('date', '>=', now()->startOfDay())
                ->orderBy('date')
                ->take(10)
                ->get(),
            'myRegistrations' => MatchRegistration::with('match.organization')
                ->where('user_id', $user->id)
                ->latest()
                ->take(10)
                ->get(),
            'myOrgs' => $user->organizations()->withPivot('role')->get(),
        ];
    }
}; ?>

<div class="space-y-8">
    <div>
        <flux:heading size="xl">Dashboard</flux:heading>
        <p class="mt-1 text-sm text-muted">Welcome back, {{ auth()->user()->name }}.</p>
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
                <div class="rounded-lg border border-border bg-surface p-4 flex flex-col" wire:key="live-{{ $lm->id }}">
                    <h3 class="font-semibold text-primary">{{ $lm->name }}</h3>
                    @if($lm->organization)
                        <p class="mt-0.5 text-xs text-muted">{{ $lm->organization->name }}</p>
                    @endif
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted">
                        @if($lm->date)
                            <span>{{ $lm->date->format('d M Y') }}</span>
                        @endif
                        @if($lm->location)
                            <span>&bull; {{ $lm->location }}</span>
                        @endif
                    </div>
                    <div class="mt-1">
                        <flux:badge size="sm" color="zinc">{{ ucfirst($lm->scoring_type) }}</flux:badge>
                    </div>
                    <div class="mt-auto pt-4">
                        <flux:button href="{{ route('live', $lm) }}" size="sm" variant="primary" class="w-full !bg-green-600 hover:!bg-green-700">
                            Watch Live
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @else
    <div class="rounded-xl border border-border/50 bg-surface/50 px-6 py-5 flex items-center gap-3">
        <span class="inline-flex h-3 w-3 rounded-full bg-surface-2"></span>
        <p class="text-sm text-muted">No matches live right now.</p>
    </div>
    @endif

    {{-- My Registrations --}}
    <div class="rounded-xl border border-border bg-surface">
        <div class="border-b border-border px-6 py-4">
            <h2 class="text-lg font-semibold text-primary">My Registrations</h2>
        </div>

        @if($myRegistrations->isEmpty())
            <div class="px-6 py-8 text-center">
                <p class="text-muted">You haven't registered for any matches yet.</p>
                <flux:button href="{{ route('matches') }}" variant="primary" class="mt-4 !bg-accent hover:!bg-accent-hover">
                    Browse Matches
                </flux:button>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-muted">
                            <th class="px-6 py-3 font-medium">Match</th>
                            <th class="px-6 py-3 font-medium">Date</th>
                            <th class="px-6 py-3 font-medium">Reference</th>
                            <th class="px-6 py-3 font-medium">Amount</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                            <th class="px-6 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($myRegistrations as $reg)
                            <tr class="hover:bg-surface-2/30 transition-colors" wire:key="reg-{{ $reg->id }}">
                                <td class="px-6 py-3 font-medium text-primary">{{ $reg->match->name }}</td>
                                <td class="px-6 py-3 text-secondary">{{ $reg->match->date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-6 py-3 font-mono text-xs text-muted">{{ $reg->payment_reference }}</td>
                                <td class="px-6 py-3 text-secondary">{{ $reg->amount ? 'R'.number_format($reg->amount, 2) : 'Free' }}</td>
                                <td class="px-6 py-3">
                                    @switch($reg->payment_status)
                                        @case('pending_payment')
                                            <flux:badge size="sm" color="amber">Awaiting Payment</flux:badge>
                                            @break
                                        @case('proof_submitted')
                                            <flux:badge size="sm" color="blue">Under Review</flux:badge>
                                            @break
                                        @case('confirmed')
                                            <flux:badge size="sm" color="green">Confirmed</flux:badge>
                                            @break
                                        @case('rejected')
                                            <flux:badge size="sm" color="red">Rejected</flux:badge>
                                            @break
                                    @endswitch
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <flux:button href="{{ route('matches.show', $reg->match) }}" size="sm" variant="ghost">
                                        View
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- My Organizations --}}
    @if($myOrgs->isNotEmpty())
    <div class="rounded-xl border border-border bg-surface">
        <div class="border-b border-border px-6 py-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-primary">My Organizations</h2>
        </div>
        <div class="divide-y divide-border">
            @foreach($myOrgs as $org)
                <a href="{{ route('org.dashboard', $org) }}" class="flex items-center justify-between px-6 py-3 hover:bg-surface-2/30 transition-colors">
                    <div>
                        <span class="font-medium text-primary">{{ $org->name }}</span>
                        <span class="ml-2 text-xs text-muted capitalize">{{ $org->type }}</span>
                    </div>
                    <flux:badge size="sm" color="{{ $org->pivot->role === 'owner' ? 'amber' : 'blue' }}">{{ $org->pivot->role }}</flux:badge>
                </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Upcoming Matches --}}
    <div class="rounded-xl border border-border bg-surface">
        <div class="border-b border-border px-6 py-4">
            <h2 class="text-lg font-semibold text-primary">Upcoming Matches</h2>
        </div>

        @if($upcomingMatches->isEmpty())
            <div class="px-6 py-8 text-center">
                <p class="text-muted">No upcoming matches at the moment.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-muted">
                            <th class="px-6 py-3 font-medium">Name</th>
                            <th class="px-6 py-3 font-medium">Organization</th>
                            <th class="px-6 py-3 font-medium">Date</th>
                            <th class="px-6 py-3 font-medium">Location</th>
                            <th class="px-6 py-3 font-medium text-right">Entry Fee</th>
                            <th class="px-6 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($upcomingMatches as $match)
                            <tr class="hover:bg-surface-2/30 transition-colors">
                                <td class="px-6 py-3 font-medium text-primary">{{ $match->name }}</td>
                                <td class="px-6 py-3 text-muted text-xs">{{ $match->organization?->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-secondary">{{ $match->date?->format('d M Y') }}</td>
                                <td class="px-6 py-3 text-secondary">{{ $match->location ?? '—' }}</td>
                                <td class="px-6 py-3 text-right text-secondary">{{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free' }}</td>
                                <td class="px-6 py-3 text-right">
                                    <flux:button href="{{ route('matches.show', $match) }}" size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                                        View &amp; Register
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
