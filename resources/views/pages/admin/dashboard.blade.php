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
            <x-app-page-header
                title="Platform Admin Dashboard"
                subtitle="Logged in as {{ auth()->user()->roleLabel() }}. Manage organizations, matches, and platform operations."
                :crumbs="[
                    ['label' => 'Platform Admin'],
                    ['label' => 'Dashboard'],
                ]"
            />
        </div>
        <a href="{{ route('admin.matches.create') }}" class="shrink-0 inline-flex items-center gap-2 rounded-lg px-3 py-2 sm:px-4 sm:py-2.5 text-xs sm:text-sm font-semibold text-white transition-colors" style="background:#ff2b2b;">
            <x-icon name="plus" class="h-4 w-4" />
            <span class="hidden sm:inline">New Match</span>
            <span class="sm:hidden">New</span>
        </a>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5 sm:gap-4">
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
                <x-icon name="file-text" class="h-5 w-5 text-secondary" />
            </div>
            <div>
                <p class="text-sm font-medium text-white">Manage Matches</p>
                <p class="text-xs text-muted">Create and edit matches</p>
            </div>
        </a>
        <a href="{{ route('admin.registrations') }}" class="flex items-center gap-3 rounded-xl border border-border bg-surface p-4 hover:border-slate-500 transition-colors">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2">
                <x-icon name="clipboard-list" class="h-5 w-5 text-secondary" />
            </div>
            <div>
                <p class="text-sm font-medium text-white">Registrations</p>
                <p class="text-xs text-muted">Review payment proofs</p>
            </div>
        </a>
        <a href="{{ route('admin.settings') }}" class="flex items-center gap-3 rounded-xl border border-border bg-surface p-4 hover:border-slate-500 transition-colors">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2">
                <x-icon name="settings" class="h-5 w-5 text-secondary" />
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
                            <th class="px-3 py-3 font-medium sm:px-6">Name</th>
                            <th class="hidden px-3 py-3 font-medium md:table-cell sm:px-6">Organization</th>
                            <th class="px-3 py-3 font-medium sm:px-6">Date</th>
                            <th class="px-3 py-3 font-medium sm:px-6">Status</th>
                            <th class="hidden px-3 py-3 font-medium text-right lg:table-cell sm:px-6">Fee</th>
                            <th class="hidden px-3 py-3 font-medium text-right lg:table-cell sm:px-6">Reg</th>
                            <th class="hidden px-3 py-3 font-medium text-right lg:table-cell sm:px-6">Shooters</th>
                            <th class="px-3 py-3 font-medium sm:px-6"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        @foreach($recentMatches as $match)
                            <tr class="hover:bg-surface-2/30 transition-colors">
                                <td class="px-3 py-3 font-medium text-white sm:px-6">{{ $match->name }}</td>
                                <td class="hidden px-3 py-3 text-secondary text-xs md:table-cell sm:px-6">{{ $match->organization?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-secondary sm:px-6">{{ $match->date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-3 py-3 sm:px-6">
                                    <flux:badge size="sm" color="{{ $match->status->color() }}">{{ $match->status->label() }}</flux:badge>
                                </td>
                                <td class="hidden whitespace-nowrap px-3 py-3 text-right text-secondary lg:table-cell sm:px-6">{{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free' }}</td>
                                <td class="hidden px-3 py-3 text-right text-secondary lg:table-cell sm:px-6">{{ $match->registrations_count }}</td>
                                <td class="hidden px-3 py-3 text-right text-secondary lg:table-cell sm:px-6">{{ $match->shooters_count }}</td>
                                <td class="px-3 py-3 text-right sm:px-6">
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
