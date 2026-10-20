{{--
    x-data-table — standard platform data table.

    DeadCenter UX Standard §7 (Tables): scannable, disciplined, consistent
    across the platform. Use this for every list/table instead of inlining
    a new <table> per page.

    Usage
    ─────
        <x-data-table :count="$matches->count()">
            <x-slot:toolbar>          (optional: filter bar, search, bulk)
                <x-filter-bar ... />
            </x-slot:toolbar>

            <x-slot:columns>          (table thead cells)
                <th>Match</th>
                <th class="hidden md:table-cell">Date</th>
                <th>Status</th>
                <th class="w-0 text-right"></th>   (action column)
            </x-slot:columns>

            <x-slot:rows>             (table tbody rows)
                @foreach($matches as $match)
                    <tr>...</tr>
                @endforeach
            </x-slot:rows>

            <x-slot:empty>            (optional empty state)
                <x-empty-state ... />
            </x-slot:empty>

            <x-slot:footer>           (optional pagination)
                @{{ $matches->links() }}
            </x-slot:footer>
        </x-data-table>

    Props
    ─────
    count    int       Row count. When 0 the `empty` slot is rendered
                       instead of the table body.
    sticky   bool      Sticky thead. Default true.
    dense    bool      Tighter row padding for admin/dense tables. Default false.
    targets  ?string   Livewire wire:target list. While those requests run,
                       body rows are replaced with x-skeleton rows.
    skeletonColumns int  Cells per skeleton row. Default 4.

    NOTE: don't nest blade-style comments inside this block — Blade's
    comment parser is non-greedy, and a nested closing sequence ends the
    outer comment early, turning the rest of the docblock into live
    template code (which then fails to compile because tags no longer
    balance). Stick to plain parentheticals above.
--}}
@props([
    'count' => 1,
    'sticky' => true,
    'dense' => false,
    'targets' => null,
    'skeletonColumns' => 4,
])

@php
    $cellPadY = $dense ? 'py-2.5' : 'py-3.5';
@endphp

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-border bg-surface shadow-sm']) }}>
    @isset($toolbar)
        <div class="border-b border-border/70 bg-surface-2/40 px-4 py-3">
            {{ $toolbar }}
        </div>
    @endisset

    <div wire:offline>
        <x-error-state
            size="sm"
            title="This table didn't refresh"
            description="You're offline. Reconnect, then try again.">
            <x-slot:icon>
                <x-icon name="circle-alert" class="h-5 w-5" />
            </x-slot:icon>
            <x-slot:actions>
                <button type="button" wire:click="$refresh"
                        class="inline-flex min-h-[36px] items-center rounded-lg bg-accent px-3 text-xs font-semibold text-white hover:bg-accent-hover focus:outline-none focus:ring-2 focus:ring-accent">
                    Try again
                </button>
            </x-slot:actions>
        </x-error-state>
    </div>

    @if($count === 0 && isset($empty))
        {{ $empty }}
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-body text-secondary"
                style="--dc-cell-pad-y: {{ $dense ? '0.625rem' : '0.875rem' }};">
                <thead @class([
                    'bg-surface-2/40 text-left text-label uppercase text-muted',
                    'sticky top-0 z-10' => $sticky,
                ])>
                    <tr class="[&>th]:px-4 [&>th]:py-3 [&>th]:font-semibold [&>th]:tracking-[0.1em] [&>th]:border-b [&>th]:border-border/60 [&>th:first-child]:pl-6 [&>th:last-child]:pr-6">
                        {{ $columns }}
                    </tr>
                </thead>
                <tbody @if($targets) wire:loading.delay.remove wire:target="{{ $targets }}" @endif
                    class="divide-y divide-border/60 [&>tr]:transition-colors [&>tr:hover]:bg-surface-2/30 [&>tr>td]:px-4 [&>tr>td]:{{ $cellPadY }} [&>tr>td]:align-middle [&>tr>td:first-child]:pl-6 [&>tr>td:last-child]:pr-6">
                    {{ $rows }}
                </tbody>
                @if($targets)
                    <tbody wire:loading.delay wire:target="{{ $targets }}" aria-busy="true" aria-live="polite">
                        @for ($i = 0; $i < 5; $i++)
                            <x-skeleton variant="row" :columns="$skeletonColumns" />
                        @endfor
                    </tbody>
                @endif
            </table>
        </div>
    @endif

    @isset($footer)
        <div class="border-t border-border/70 bg-surface-2/40 px-6 py-3">
            {{ $footer }}
        </div>
    @endisset
</div>
