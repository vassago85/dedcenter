<?php

use App\Models\ShootingMatch;
use App\Models\Shooter;
use App\Models\User;
use App\Models\Organization;
use App\Models\MatchRegistration;
use App\Enums\MatchStatus;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Admin Dashboard')]
    class extends Component {
    public function with(): array
    {
        return [
            'totalMatches' => ShootingMatch::count(),
            'activeMatches' => ShootingMatch::where('status', MatchStatus::Active)->count(),
            'totalMembers' => User::where('role', 'shooter')->count(),
            'pendingRegistrations' => MatchRegistration::where('payment_status', 'proof_submitted')->count(),
            'pendingOrgs' => Organization::pending()->count(),
            'totalOrgs' => Organization::count(),
            'recentMatches' => ShootingMatch::with('organization')
                ->withCount(['squads', 'shooters', 'registrations'])
                ->latest('date')
                ->take(10)
                ->get(),
        ];
    }
}; ?>

<div class="space-y-8">
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-white">Dashboard</h1>
            <p class="mt-1 text-sm text-secondary">Logged in as <span class="font-medium text-amber-400">{{ auth()->user()->roleLabel() }}</span></p>
        </div>
        <a href="{{ route('admin.matches.create') }}" class="shrink-0 inline-flex items-center gap-2 rounded-lg px-3 py-2 sm:px-4 sm:py-2.5 text-xs sm:text-sm font-semibold text-white transition-colors" style="background:#ff2b2b;">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            <span class="hidden sm:inline">New Match</span>
            <span class="sm:hidden">New</span>
        </a>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 sm:gap-4">
        <div class="rounded-xl border border-border bg-surface p-4 sm:p-6">
            <p class="text-xs sm:text-sm font-medium text-muted">Total Matches</p>
            <p class="mt-1 sm:mt-2 text-2xl sm:text-3xl font-bold text-white">{{ $totalMatches }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 sm:p-6">
            <p class="text-xs sm:text-sm font-medium text-muted">Active Matches</p>
            <p class="mt-1 sm:mt-2 text-2xl sm:text-3xl font-bold text-green-400">{{ $activeMatches }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4 sm:p-6">
            <p class="text-xs sm:text-sm font-medium text-muted">Registered Shooters</p>
            <p class="mt-1 sm:mt-2 text-2xl sm:text-3xl font-bold text-white">{{ $totalMembers }}</p>
        </div>
        <a href="{{ route('admin.organizations') }}" class="rounded-xl border border-border bg-surface p-4 sm:p-6 hover:border-amber-600/50 transition-colors">
            <p class="text-xs sm:text-sm font-medium text-muted">Organizations</p>
            <p class="mt-1 sm:mt-2 text-2xl sm:text-3xl font-bold text-white">{{ $totalOrgs }}</p>
            @if($pendingOrgs > 0)
                <p class="mt-1 text-xs text-amber-400">{{ $pendingOrgs }} pending</p>
            @endif
        </a>
        <a href="{{ route('admin.registrations') }}" class="rounded-xl border border-border bg-surface p-4 sm:p-6 hover:border-red-600/50 transition-colors col-span-2 sm:col-span-1">
            <p class="text-xs sm:text-sm font-medium text-muted">Pending Approvals</p>
            <p class="mt-1 sm:mt-2 text-2xl sm:text-3xl font-bold {{ $pendingRegistrations > 0 ? 'text-accent' : 'text-white' }}">{{ $pendingRegistrations }}</p>
        </a>
    </div>

    {{-- Quick links --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <a href="{{ route('admin.matches.index') }}" class="flex items-center gap-3 rounded-xl border border-border bg-surface p-4 hover:border-slate-500 transition-colors">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2">
                <svg class="h-5 w-5 text-secondary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75H16.5C17.3284 3.75 18 4.42157 18 5.25V18.75C18 19.5784 17.3284 20.25 16.5 20.25H7.5C6.67157 20.25 6 19.5784 6 18.75V5.25C6 4.42157 6.67157 3.75 7.5 3.75Z" />
                </svg>
            </div>
            <div>
                <p class="text-sm font-medium text-white">Manage Matches</p>
                <p class="text-xs text-muted">Create and edit matches</p>
            </div>
        </a>
        <a href="{{ route('admin.registrations') }}" class="flex items-center gap-3 rounded-xl border border-border bg-surface p-4 hover:border-slate-500 transition-colors">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2">
                <svg class="h-5 w-5 text-secondary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15a2.25 2.25 0 0 1 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" />
                </svg>
            </div>
            <div>
                <p class="text-sm font-medium text-white">Registrations</p>
                <p class="text-xs text-muted">Review payment proofs</p>
            </div>
        </a>
        <a href="{{ route('admin.settings') }}" class="flex items-center gap-3 rounded-xl border border-border bg-surface p-4 hover:border-slate-500 transition-colors">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2">
                <svg class="h-5 w-5 text-secondary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
            </div>
            <div>
                <p class="text-sm font-medium text-white">Settings</p>
                <p class="text-xs text-muted">Bank details &amp; configuration</p>
            </div>
        </a>
    </div>

    {{-- Recent matches --}}
    <div class="rounded-xl border border-border bg-surface">
        <div class="border-b border-border px-4 sm:px-6 py-4">
            <h2 class="text-lg font-semibold text-white">Recent Matches</h2>
        </div>

        @if($recentMatches->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="text-muted">No matches yet.</p>
                <a href="{{ route('admin.matches.create') }}" class="mt-4 inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background:#ff2b2b;">
                    Create Your First Match
                </a>
            </div>
        @else
            {{-- Mobile cards --}}
            <div class="divide-y divide-border sm:hidden">
                @foreach($recentMatches as $match)
                    <div class="p-4 space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-white truncate">{{ $match->name }}</p>
                                <p class="text-xs text-muted">{{ $match->organization?->name ?? '—' }}</p>
                            </div>
                            <flux:badge size="sm" color="{{ $match->status->color() }}">{{ $match->status->label() }}</flux:badge>
                        </div>
                        <div class="flex items-center justify-between text-xs text-secondary">
                            <span>{{ $match->date?->format('d M Y') ?? '—' }}</span>
                            <span>{{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex gap-3 text-xs text-muted">
                                <span>{{ $match->registrations_count }} reg</span>
                                <span>{{ $match->shooters_count }} shooters</span>
                            </div>
                            <a href="{{ route('admin.matches.edit', $match) }}" class="text-xs font-medium text-secondary hover:text-white">Edit</a>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Desktop table --}}
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-muted">
                            <th class="px-6 py-3 font-medium">Name</th>
                            <th class="px-6 py-3 font-medium">Organization</th>
                            <th class="px-6 py-3 font-medium">Date</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                            <th class="px-6 py-3 font-medium text-right">Fee</th>
                            <th class="px-6 py-3 font-medium text-right">Reg</th>
                            <th class="px-6 py-3 font-medium text-right">Shooters</th>
                            <th class="px-6 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        @foreach($recentMatches as $match)
                            <tr class="hover:bg-surface-2/30 transition-colors">
                                <td class="px-6 py-3 font-medium text-white">{{ $match->name }}</td>
                                <td class="px-6 py-3 text-secondary text-xs">{{ $match->organization?->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-secondary">{{ $match->date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    <flux:badge size="sm" color="{{ $match->status->color() }}">{{ $match->status->label() }}</flux:badge>
                                </td>
                                <td class="px-6 py-3 text-right text-secondary">{{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free' }}</td>
                                <td class="px-6 py-3 text-right text-secondary">{{ $match->registrations_count }}</td>
                                <td class="px-6 py-3 text-right text-secondary">{{ $match->shooters_count }}</td>
                                <td class="px-6 py-3 text-right">
                                    <a href="{{ route('admin.matches.edit', $match) }}" class="text-sm font-medium text-secondary hover:text-white">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
