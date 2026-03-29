<?php

use App\Models\Organization;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Organizations')]
    class extends Component {
    public string $search = '';
    public string $type = '';

    public function with(): array
    {
        $organizations = Organization::approved()
            ->withCount(['matches', 'children', 'admins'])
            ->when($this->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($this->type, fn ($q, $t) => $q->where('type', $t))
            ->orderBy('name')
            ->get();

        return ['organizations' => $organizations];
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Organizations</flux:heading>
            <p class="mt-1 text-sm text-slate-400">Browse leagues, clubs, competitions, and challenges.</p>
        </div>
        <flux:button href="{{ route('organizations.create') }}" variant="primary" class="!bg-red-600 hover:!bg-red-700">
            <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            Create Organization
        </flux:button>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="max-w-sm flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search organizations..." icon="magnifying-glass" />
        </div>
        <div class="flex gap-2 flex-wrap">
            @foreach(['' => 'All', 'league' => 'Leagues', 'club' => 'Clubs', 'competition' => 'Competitions', 'challenge' => 'Challenges'] as $value => $label)
                <button wire:click="$set('type', '{{ $value }}')"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors {{ $type === $value ? 'bg-red-600 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    @if($organizations->isEmpty())
        <div class="rounded-xl border border-slate-700 bg-slate-800 px-6 py-12 text-center">
            <p class="text-slate-400">{{ $search ? "No organizations found for \"{$search}\"." : 'No organizations yet.' }}</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach($organizations as $org)
                <div class="rounded-xl border border-slate-700 bg-slate-800 p-6 hover:border-slate-500 transition-colors" wire:key="org-{{ $org->id }}">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-white">{{ $org->name }}</h3>
                            <flux:badge size="sm" color="{{ match($org->type) { 'league' => 'amber', 'club' => 'blue', 'competition' => 'green', 'challenge' => 'red', default => 'zinc' } }}" class="mt-1">
                                {{ ucfirst($org->type) }}
                            </flux:badge>
                        </div>
                    </div>

                    @if($org->description)
                        <p class="mt-3 text-sm text-slate-400 line-clamp-2">{{ $org->description }}</p>
                    @endif

                    <div class="mt-4 flex items-center gap-4 text-xs text-slate-500">
                        <span>{{ $org->matches_count }} {{ Str::plural('match', $org->matches_count) }}</span>
                        @if($org->isLeague())
                            <span>{{ $org->children_count }} {{ Str::plural('club', $org->children_count) }}</span>
                        @endif
                        @if($org->best_of)
                            <span>Best of {{ $org->best_of }}</span>
                        @endif
                    </div>

                    @if($org->parent)
                        <p class="mt-2 text-xs text-slate-500">Part of {{ $org->parent->name }}</p>
                    @endif

                    <div class="mt-4 flex gap-2">
                        @if($org->best_of || $org->matches_count > 0)
                            <flux:button href="{{ route('leaderboard', $org) }}" size="sm" variant="ghost">Leaderboard</flux:button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
