<?php

use App\Models\Organization;
use App\Models\MatchRegistration;
use App\Enums\MatchStatus;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Organization Dashboard')]
    class extends Component {
    public Organization $organization;

    public function with(): array
    {
        $matchIds = $this->organization->matches()->pluck('id');

        return [
            'totalMatches' => $this->organization->matches()->count(),
            'activeMatches' => $this->organization->matches()->where('status', MatchStatus::Active)->count(),
            'pendingRegistrations' => MatchRegistration::whereIn('match_id', $matchIds)
                ->where('payment_status', 'proof_submitted')->count(),
            'totalAdmins' => $this->organization->admins()->count(),
            'childCount' => $this->organization->children()->count(),
            'recentMatches' => $this->organization->matches()
                ->withCount(['shooters', 'registrations'])
                ->latest('date')
                ->take(10)
                ->get(),
        ];
    }
}; ?>

<div class="space-y-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ $organization->name }}</flux:heading>
            <p class="mt-1 text-sm text-slate-400 capitalize">{{ $organization->type }} Dashboard</p>
        </div>
        <flux:button href="{{ route('org.matches.create', $organization) }}" variant="primary" class="!bg-red-600 hover:!bg-red-700">
            <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            New Match
        </flux:button>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-700 bg-slate-800 p-6">
            <p class="text-sm font-medium text-slate-400">Matches</p>
            <p class="mt-2 text-3xl font-bold text-white">{{ $totalMatches }}</p>
        </div>
        <div class="rounded-xl border border-slate-700 bg-slate-800 p-6">
            <p class="text-sm font-medium text-slate-400">Active</p>
            <p class="mt-2 text-3xl font-bold text-green-400">{{ $activeMatches }}</p>
        </div>
        <a href="{{ route('org.registrations', $organization) }}" class="rounded-xl border border-slate-700 bg-slate-800 p-6 hover:border-red-600/50 transition-colors">
            <p class="text-sm font-medium text-slate-400">Pending Approvals</p>
            <p class="mt-2 text-3xl font-bold {{ $pendingRegistrations > 0 ? 'text-red-400' : 'text-white' }}">{{ $pendingRegistrations }}</p>
        </a>
        <div class="rounded-xl border border-slate-700 bg-slate-800 p-6">
            <p class="text-sm font-medium text-slate-400">Admins</p>
            <p class="mt-2 text-3xl font-bold text-white">{{ $totalAdmins }}</p>
        </div>
    </div>

    @if($organization->isLeague() && $childCount > 0)
    <div class="rounded-xl border border-slate-700 bg-slate-800 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-400">Clubs in League</p>
                <p class="mt-1 text-2xl font-bold text-white">{{ $childCount }}</p>
            </div>
            <flux:button href="{{ route('org.clubs', $organization) }}" size="sm" variant="ghost">Manage Clubs</flux:button>
        </div>
    </div>
    @endif

    @if($organization->best_of)
    <div class="flex items-center gap-4">
        <a href="{{ route('leaderboard', $organization) }}" target="_blank"
           class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 transition-colors">
            View Leaderboard (Best of {{ $organization->best_of }})
        </a>
    </div>
    @endif

    {{-- Recent matches --}}
    <div class="rounded-xl border border-slate-700 bg-slate-800">
        <div class="border-b border-slate-700 px-6 py-4">
            <h2 class="text-lg font-semibold text-white">Recent Matches</h2>
        </div>

        @if($recentMatches->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="text-slate-400">No matches yet.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-700 text-left text-slate-400">
                            <th class="px-6 py-3 font-medium">Name</th>
                            <th class="px-6 py-3 font-medium">Date</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                            <th class="px-6 py-3 font-medium text-right">Fee</th>
                            <th class="px-6 py-3 font-medium text-right">Registrations</th>
                            <th class="px-6 py-3 font-medium text-right">Shooters</th>
                            <th class="px-6 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        @foreach($recentMatches as $match)
                            <tr class="hover:bg-slate-700/30 transition-colors">
                                <td class="px-6 py-3 font-medium text-white">{{ $match->name }}</td>
                                <td class="px-6 py-3 text-slate-300">{{ $match->date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    @switch($match->status)
                                        @case(MatchStatus::Draft) <flux:badge size="sm" color="zinc">Draft</flux:badge> @break
                                        @case(MatchStatus::Active) <flux:badge size="sm" color="green">Active</flux:badge> @break
                                        @case(MatchStatus::Completed) <flux:badge size="sm" color="blue">Completed</flux:badge> @break
                                    @endswitch
                                </td>
                                <td class="px-6 py-3 text-right text-slate-300">{{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free' }}</td>
                                <td class="px-6 py-3 text-right text-slate-300">{{ $match->registrations_count }}</td>
                                <td class="px-6 py-3 text-right text-slate-300">{{ $match->shooters_count }}</td>
                                <td class="px-6 py-3 text-right">
                                    <flux:button href="{{ route('org.matches.edit', [$organization, $match]) }}" size="sm" variant="ghost">Edit</flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
