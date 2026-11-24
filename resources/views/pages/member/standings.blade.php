<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Standings')]
    class extends Component {
    public function with(): array
    {
        $organizations = auth()->user()
            ->organizations()
            ->withPivot('is_owner', 'is_match_director', 'is_range_officer', 'is_shooter')
            ->orderBy('name')
            ->get();

        return ['organizations' => $organizations];
    }
}; ?>

<div class="space-y-6">
    <x-app-page-header
        title="Standings"
        subtitle="Season leaderboards for the organizations you belong to.">
    </x-app-page-header>

    @if($organizations->isEmpty())
        <x-empty-state
            title="No standings yet"
            description="Join an organization to follow its season leaderboard. Browse clubs and leagues, or find a match to shoot.">
            <x-slot:icon>
                <x-icon name="chart-column" class="h-6 w-6" />
            </x-slot:icon>
            <x-slot:actions>
                <a href="{{ route('organizations') }}" class="inline-flex min-h-[40px] items-center rounded-lg bg-accent px-4 text-sm font-semibold text-white hover:bg-accent-hover">
                    Browse organizations
                </a>
            </x-slot:actions>
        </x-empty-state>
    @else
        <div class="overflow-hidden rounded-xl border border-border bg-surface">
            <ul class="divide-y divide-border">
                @foreach($organizations as $org)
                    <li wire:key="standings-{{ $org->id }}" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-primary">{{ $org->name }}</p>
                            <p class="mt-0.5 text-xs text-muted">{{ org_membership_label($org->pivot) }} · {{ ucfirst($org->type ?? 'organization') }}</p>
                        </div>
                        <a href="{{ route('leaderboard', $org) }}"
                           class="inline-flex min-h-[40px] shrink-0 items-center justify-center rounded-lg border border-border px-3 text-sm font-semibold text-secondary transition-colors hover:border-accent hover:text-primary">
                            View leaderboard
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
