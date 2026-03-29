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
        <p class="mt-1 text-sm text-slate-400">Welcome back, {{ auth()->user()->name }}.</p>
    </div>

    {{-- My Registrations --}}
    <div class="rounded-xl border border-slate-700 bg-slate-800">
        <div class="border-b border-slate-700 px-6 py-4">
            <h2 class="text-lg font-semibold text-white">My Registrations</h2>
        </div>

        @if($myRegistrations->isEmpty())
            <div class="px-6 py-8 text-center">
                <p class="text-slate-400">You haven't registered for any matches yet.</p>
                <flux:button href="{{ route('matches') }}" variant="primary" class="mt-4 !bg-red-600 hover:!bg-red-700">
                    Browse Matches
                </flux:button>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-700 text-left text-slate-400">
                            <th class="px-6 py-3 font-medium">Match</th>
                            <th class="px-6 py-3 font-medium">Date</th>
                            <th class="px-6 py-3 font-medium">Reference</th>
                            <th class="px-6 py-3 font-medium">Amount</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                            <th class="px-6 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        @foreach($myRegistrations as $reg)
                            <tr class="hover:bg-slate-700/30 transition-colors" wire:key="reg-{{ $reg->id }}">
                                <td class="px-6 py-3 font-medium text-white">{{ $reg->match->name }}</td>
                                <td class="px-6 py-3 text-slate-300">{{ $reg->match->date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-6 py-3 font-mono text-xs text-slate-400">{{ $reg->payment_reference }}</td>
                                <td class="px-6 py-3 text-slate-300">{{ $reg->amount ? 'R'.number_format($reg->amount, 2) : 'Free' }}</td>
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
    <div class="rounded-xl border border-slate-700 bg-slate-800">
        <div class="border-b border-slate-700 px-6 py-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">My Organizations</h2>
        </div>
        <div class="divide-y divide-slate-700">
            @foreach($myOrgs as $org)
                <a href="{{ route('org.dashboard', $org) }}" class="flex items-center justify-between px-6 py-3 hover:bg-slate-700/30 transition-colors">
                    <div>
                        <span class="font-medium text-white">{{ $org->name }}</span>
                        <span class="ml-2 text-xs text-slate-500 capitalize">{{ $org->type }}</span>
                    </div>
                    <flux:badge size="sm" color="{{ $org->pivot->role === 'owner' ? 'amber' : 'blue' }}">{{ $org->pivot->role }}</flux:badge>
                </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Upcoming Matches --}}
    <div class="rounded-xl border border-slate-700 bg-slate-800">
        <div class="border-b border-slate-700 px-6 py-4">
            <h2 class="text-lg font-semibold text-white">Upcoming Matches</h2>
        </div>

        @if($upcomingMatches->isEmpty())
            <div class="px-6 py-8 text-center">
                <p class="text-slate-400">No upcoming matches at the moment.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-700 text-left text-slate-400">
                            <th class="px-6 py-3 font-medium">Name</th>
                            <th class="px-6 py-3 font-medium">Organization</th>
                            <th class="px-6 py-3 font-medium">Date</th>
                            <th class="px-6 py-3 font-medium">Location</th>
                            <th class="px-6 py-3 font-medium text-right">Entry Fee</th>
                            <th class="px-6 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        @foreach($upcomingMatches as $match)
                            <tr class="hover:bg-slate-700/30 transition-colors">
                                <td class="px-6 py-3 font-medium text-white">{{ $match->name }}</td>
                                <td class="px-6 py-3 text-slate-400 text-xs">{{ $match->organization?->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-slate-300">{{ $match->date?->format('d M Y') }}</td>
                                <td class="px-6 py-3 text-slate-300">{{ $match->location ?? '—' }}</td>
                                <td class="px-6 py-3 text-right text-slate-300">{{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free' }}</td>
                                <td class="px-6 py-3 text-right">
                                    <flux:button href="{{ route('matches.show', $match) }}" size="sm" variant="primary" class="!bg-red-600 hover:!bg-red-700">
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
