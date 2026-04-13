<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Enums\MatchStatus;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.portal')]
    class extends Component {
    public Organization $organization;
    public string $search = '';
    public string $status = 'active';

    public function getTitle(): string
    {
        return 'Matches — ' . $this->organization->name;
    }

    public function with(): array
    {
        $orgIds = collect([$this->organization->id]);
        if ($this->organization->isLeague()) {
            $orgIds = $orgIds->merge($this->organization->children()->pluck('id'));
        }

        $matches = ShootingMatch::whereIn('organization_id', $orgIds)
            ->whereNot('status', MatchStatus::Draft)
            ->when($this->status === 'active', fn ($q) => $q->whereNot('status', MatchStatus::Completed))
            ->when($this->status === 'completed', fn ($q) => $q->where('status', MatchStatus::Completed))
            ->when($this->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('date', $this->status === 'completed' ? 'desc' : 'asc')
            ->get();

        return ['matches' => $matches];
    }
}; ?>

<div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-8 lg:flex-row lg:items-start">
        <div class="min-w-0 flex-1 space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-primary">Club Matches</h1>
            <p class="mt-1 text-sm text-muted">{{ $organization->name }} &mdash; upcoming fixtures and completed results.</p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="max-w-sm flex-1">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by match name..." icon="magnifying-glass" />
            </div>
            <div class="flex gap-2">
                @foreach(['active' => 'Upcoming', 'completed' => 'Completed', 'all' => 'All'] as $value => $label)
                    <button wire:click="$set('status', '{{ $value }}')"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors {{ $status === $value ? 'portal-bg-primary text-primary' : 'bg-white/10 text-secondary hover:bg-white/15' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        @if($matches->isEmpty())
                <div class="rounded-xl border border-white/10 bg-app px-6 py-12 text-center">
                <p class="text-muted">{{ $search ? "No matches found for \"{$search}\"." : 'No matches to show yet. Check back soon for the next event.' }}</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach($matches as $match)
                    <a href="{{ route('portal.matches.show', [$organization, $match]) }}"
                       class="rounded-xl border border-white/10 bg-app p-6 hover:portal-border-primary transition-colors block group">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="text-lg font-semibold text-primary group-hover:portal-primary transition-colors">{{ $match->name }}</h3>
                            <flux:badge size="sm" color="{{ $match->status->color() }}" class="shrink-0">{{ $match->status->label() }}</flux:badge>
                        </div>
                        <div class="mt-3 space-y-1.5 text-sm text-muted">
                            @if($match->date)
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                                    {{ $match->date->format('d M Y') }}
                                </div>
                            @endif
                            @if($match->location)
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                                    {{ $match->location }}
                                </div>
                            @endif
                        </div>
                        <div class="mt-4 flex items-center justify-between">
                            <span class="text-lg font-bold {{ $match->entry_fee ? 'text-primary' : 'text-green-400' }}">
                                {{ $match->entry_fee ? 'R'.number_format($match->entry_fee, 2) : 'Free' }}
                            </span>
                            @if($match->status !== MatchStatus::Completed)
                                <span class="text-sm font-medium portal-primary">Register &rarr;</span>
                            @else
                                <span class="text-sm text-muted">View results</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
        </div>

        <aside class="w-full shrink-0 lg:w-72 lg:pt-2">
            <x-portal-ad-slot :organization="$organization" placement="portal_matches_sidebar" variant="block" />
        </aside>
    </div>
</div>
