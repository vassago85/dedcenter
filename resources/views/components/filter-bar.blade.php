{{--
    x-filter-bar — consistent search + filter chip row.

    DeadCenter UX Standard §6 (IA: consolidate related controls) + §7.
    Use on any index / list page that needs filtering. Keeps the search,
    filter chips, and trailing actions (export, bulk) visually unified.

    Usage
    ─────
        <x-filter-bar wire:model.live.debounce.250ms="search" placeholder="Search matches…">
            <x-slot:filters>
                <flux:select ... />
                <flux:select ... />
            </x-slot:filters>
            <x-slot:actions>
                <flux:button icon="download">Export</flux:button>
            </x-slot:actions>
        </x-filter-bar>

    Props
    ─────
    placeholder  string   Placeholder shown in the search input.
    showSearch   bool     Show the search input. Default true.
    searchName   string   Input name attribute (when not using Livewire).
--}}
@props([
    'placeholder' => 'Search…',
    'showSearch' => true,
    'searchName' => 'q',
])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between']) }}>
    <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
        @if($showSearch)
            <div class="relative w-full sm:max-w-sm">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
                <input
                    type="search"
                    name="{{ $searchName }}"
                    placeholder="{{ $placeholder }}"
                    {{ $attributes->only(['wire:model', 'wire:model.live', 'wire:model.live.debounce', 'wire:model.live.debounce.250ms', 'value']) }}
                    class="w-full rounded-lg border border-border bg-app pl-9 pr-3 py-2 text-body text-primary placeholder:text-muted focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30"
                />
            </div>
        @endif
        @isset($filters)
            <div class="flex flex-wrap items-center gap-2">
                {{ $filters }}
            </div>
        @endisset
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
