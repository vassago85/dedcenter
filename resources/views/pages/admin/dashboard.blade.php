<?php

use App\Models\ShootingMatch;
use App\Models\User;
use App\Models\Organization;
use App\Models\MatchRegistration;
use App\Models\ShooterAccountClaim;
use App\Enums\MatchStatus;
use App\Enums\ShooterClaimStatus;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Platform Admin Dashboard')]
    class extends Component {
    /**
     * Re-render hook fired by approve / reject actions on other pages.
     * Without this, after approving a claim and navigating back to the
     * dashboard via wire:navigate, the cached page snapshot still shows
     * the old "X pending" banner because `with()` doesn't re-run on a
     * Livewire snapshot restore. Listening for the global event lets us
     * force a fresh data pull whenever a moderator resolves something.
     */
    #[On('moderation-updated')]
    public function refreshModerationCounts(): void
    {
        // Empty body — Livewire re-runs `with()` whenever the component
        // is hydrated by an event, which is all we need.
    }

    public function with(): array
    {
        return [
            'totalMatches' => ShootingMatch::count(),
            'activeMatches' => ShootingMatch::where('status', MatchStatus::Active)->count(),
            'totalMembers' => User::where('role', 'shooter')->count(),
            'pendingRegistrations' => MatchRegistration::where('payment_status', 'proof_submitted')->count(),
            'pendingOrgs' => Organization::pending()->count(),
            'totalOrgs' => Organization::count(),
            'pendingClaims' => ShooterAccountClaim::where('status', ShooterClaimStatus::Pending)->count(),
            'recentMatches' => ShootingMatch::with('organization')
                ->withCount(['squads', 'shooters', 'registrations'])
                ->latest('date')
                ->take(10)
                ->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <x-app-page-header
        eyebrow="Platform operations"
        title="Admin dashboard"
        subtitle="Organizations, matches, and platform-wide moderation.">
        <x-slot:actions>
            <a href="{{ route('admin.matches.create') }}" class="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-accent px-4 text-sm font-semibold text-white transition-colors hover:bg-accent-hover">
                <x-icon name="plus" class="h-4 w-4" />
                New match
            </a>
            <a href="{{ route('admin.organizations') }}" class="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-border bg-surface px-4 text-sm font-semibold text-secondary transition-colors hover:border-accent hover:text-primary">
                <x-icon name="building-2" class="h-4 w-4" />
                Organizations
            </a>
        </x-slot:actions>
    </x-app-page-header>

    {{-- Moderation CTA: pending shooter claims --}}
    @if($pendingClaims > 0)
        <a href="{{ route('admin.shooter-claims') }}"
           class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-500/60 bg-amber-500/10 px-5 py-4 transition-colors hover:bg-amber-500/15">
            <div class="flex items-start gap-3">
                <x-icon name="circle-alert" class="mt-0.5 h-5 w-5 shrink-0 text-amber-300" />
                <div>
                    <p class="text-sm font-semibold text-amber-200">{{ $pendingClaims }} shooter {{ $pendingClaims === 1 ? 'claim is' : 'claims are' }} waiting for review</p>
                    <p class="text-xs text-amber-100/80">Imported shooters without email accounts have asked to link their results to a real account.</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1 rounded-lg bg-amber-400 px-3 py-1.5 text-xs font-bold text-black">
                Review now <x-icon name="chevron-right" class="h-3.5 w-3.5" />
            </span>
        </a>
    @endif

    {{-- Stat strip --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        <x-stat-card label="Total matches" :value="$totalMatches" color="slate" :href="route('admin.matches.index')" />
        <x-stat-card label="Active" :value="$activeMatches" :color="$activeMatches > 0 ? 'emerald' : 'slate'" helper="Running now" />
        <x-stat-card label="Shooters" :value="$totalMembers" color="slate" helper="Registered users" />
        <x-stat-card
            label="Organizations"
            :value="$totalOrgs"
            :color="$pendingOrgs > 0 ? 'amber' : 'slate'"
            :helper="$pendingOrgs > 0 ? $pendingOrgs.' pending' : null"
            :href="route('admin.organizations')" />
        <x-stat-card
            label="Pending payments"
            :value="$pendingRegistrations"
            :color="$pendingRegistrations > 0 ? 'accent' : 'slate'"
            :helper="$pendingRegistrations > 0 ? 'Proof submitted' : 'All clear'"
            :href="route('admin.registrations')" />
        <x-stat-card
            label="Pending claims"
            :value="$pendingClaims"
            :color="$pendingClaims > 0 ? 'amber' : 'slate'"
            :helper="$pendingClaims > 0 ? 'Need review' : 'All reviewed'"
            :href="route('admin.shooter-claims')" />
    </div>

    {{-- Quick links --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <a href="{{ route('admin.matches.index') }}" class="group flex items-center gap-3 rounded-xl border border-border bg-surface p-4 transition-all hover:border-accent/60">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2 text-muted transition-colors group-hover:text-accent">
                <x-icon name="file-text" class="h-5 w-5" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-primary">Manage matches</p>
                <p class="text-xs text-muted">Create and edit matches</p>
            </div>
            <x-icon name="arrow-right" class="h-4 w-4 text-muted transition-transform group-hover:translate-x-0.5 group-hover:text-accent" />
        </a>
        <a href="{{ route('admin.registrations') }}" class="group flex items-center gap-3 rounded-xl border border-border bg-surface p-4 transition-all hover:border-accent/60">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2 text-muted transition-colors group-hover:text-accent">
                <x-icon name="clipboard-list" class="h-5 w-5" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-primary">Registrations</p>
                <p class="text-xs text-muted">Review payment proofs</p>
            </div>
            <x-icon name="arrow-right" class="h-4 w-4 text-muted transition-transform group-hover:translate-x-0.5 group-hover:text-accent" />
        </a>
        <a href="{{ route('admin.settings') }}" class="group flex items-center gap-3 rounded-xl border border-border bg-surface p-4 transition-all hover:border-accent/60">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2 text-muted transition-colors group-hover:text-accent">
                <x-icon name="settings" class="h-5 w-5" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-primary">Platform settings</p>
                <p class="text-xs text-muted">Bank details &amp; configuration</p>
            </div>
            <x-icon name="arrow-right" class="h-4 w-4 text-muted transition-transform group-hover:translate-x-0.5 group-hover:text-accent" />
        </a>
    </div>

    {{--
        wire:navigate keeps a snapshot of this page in memory so back-nav
        feels instant. Downside: the moderation counters above
        (`pendingClaims`, `pendingOrgs`, `pendingRegistrations`) become
        stale the moment another page resolves one of them — exactly the
        "I approved a claim but the banner still says 1 pending" bug.
        Refreshing on every `livewire:navigated` event re-runs `with()`
        and updates the banner + stat cards. The cost is ~7 lightweight
        COUNT queries per navigation to the dashboard, which is fine on
        a page admins use sparingly.
    --}}
    <div x-data
         x-init="document.addEventListener('livewire:navigated', () => $wire.$refresh())"></div>

    {{-- Recent matches --}}
    <x-panel title="Recent matches" subtitle="Latest matches across all organizations" :padding="false">
        <x-slot:actions>
            <a href="{{ route('admin.matches.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-muted transition-colors hover:text-primary">
                View all
                <x-icon name="arrow-right" class="h-3.5 w-3.5" />
            </a>
        </x-slot:actions>

        @if($recentMatches->isEmpty())
            <x-empty-state
                title="No matches yet"
                description="Create the first match to get the platform running.">
                <x-slot:icon>
                    <x-icon name="file-text" class="h-6 w-6" />
                </x-slot:icon>
                <x-slot:actions>
                    <a href="{{ route('admin.matches.create') }}" class="inline-flex min-h-[36px] items-center gap-1.5 rounded-lg bg-accent px-3 text-xs font-semibold text-white hover:bg-accent-hover">
                        Create first match
                    </a>
                </x-slot:actions>
            </x-empty-state>
        @else
            {{-- Mobile cards --}}
            <ul class="divide-y divide-border/70 sm:hidden">
                @foreach($recentMatches as $match)
                    <li class="space-y-2 px-5 py-3.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-primary">{{ $match->name }}</p>
                                <p class="text-xs text-muted">{{ $match->organization?->name ?? '—' }}</p>
                            </div>
                            <flux:badge size="sm" color="{{ $match->status->color() }}">{{ $match->status->label() }}</flux:badge>
                        </div>
                        <div class="flex items-center justify-between text-xs text-muted">
                            <span>{{ $match->date?->format('d M Y') ?? '—' }}</span>
                            <span>{{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free' }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex gap-3 text-muted">
                                <span>{{ $match->registrations_count }} reg</span>
                                <span>{{ $match->shooters_count }} shooters</span>
                            </div>
                            <a href="{{ route('admin.matches.edit', $match) }}" class="font-semibold text-accent hover:text-accent-hover">Edit</a>
                        </div>
                    </li>
                @endforeach
            </ul>

            {{-- Desktop table --}}
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border/70 bg-surface-2/40 text-left">
                            <th class="px-6 py-3 text-[10px] font-semibold uppercase tracking-[0.15em] text-muted">Name</th>
                            <th class="hidden px-6 py-3 text-[10px] font-semibold uppercase tracking-[0.15em] text-muted md:table-cell">Organization</th>
                            <th class="px-6 py-3 text-[10px] font-semibold uppercase tracking-[0.15em] text-muted">Date</th>
                            <th class="px-6 py-3 text-[10px] font-semibold uppercase tracking-[0.15em] text-muted">Status</th>
                            <th class="hidden px-6 py-3 text-right text-[10px] font-semibold uppercase tracking-[0.15em] text-muted lg:table-cell">Fee</th>
                            <th class="hidden px-6 py-3 text-right text-[10px] font-semibold uppercase tracking-[0.15em] text-muted lg:table-cell">Reg</th>
                            <th class="hidden px-6 py-3 text-right text-[10px] font-semibold uppercase tracking-[0.15em] text-muted lg:table-cell">Shooters</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/70">
                        @foreach($recentMatches as $match)
                            <tr class="group cursor-pointer transition-colors hover:bg-surface-2/50"
                                onclick="window.location='{{ route('admin.matches.edit', $match) }}'">
                                <td class="px-6 py-3.5">
                                    <p class="text-sm font-semibold text-primary transition-colors group-hover:text-accent">{{ $match->name }}</p>
                                </td>
                                <td class="hidden px-6 py-3.5 text-xs text-muted md:table-cell">{{ $match->organization?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-secondary">{{ $match->date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-6 py-3.5">
                                    <flux:badge size="sm" color="{{ $match->status->color() }}">{{ $match->status->label() }}</flux:badge>
                                </td>
                                <td class="hidden whitespace-nowrap px-6 py-3.5 text-right text-secondary lg:table-cell">{{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free' }}</td>
                                <td class="hidden px-6 py-3.5 text-right text-secondary lg:table-cell">{{ $match->registrations_count }}</td>
                                <td class="hidden px-6 py-3.5 text-right text-secondary lg:table-cell">{{ $match->shooters_count }}</td>
                                <td class="px-6 py-3.5 text-right">
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-muted transition-colors group-hover:text-accent">
                                        Edit
                                        <x-icon name="pencil" class="h-3 w-3" />
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-panel>
</div>
