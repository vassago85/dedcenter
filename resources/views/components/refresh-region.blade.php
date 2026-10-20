{{--
    x-refresh-region — loading and error around a list that re-queries.

    While the named Livewire targets are in flight, the slot is replaced
    with x-skeleton. When the browser is offline, x-error-state explains
    the failure and offers a retry. The slot still holds the empty state
    for a successful query that returned nothing.

    Props
    ─────
    targets  string  Comma-separated wire:target list (search, filters, paging).
    rows     int     Skeleton cards to show. Default 3.
--}}
@props([
    'targets',
    'rows' => 3,
])

<div {{ $attributes->merge(['class' => 'space-y-3']) }}>
    <div wire:offline>
        <x-error-state
            size="sm"
            title="This list didn't refresh"
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

    <div wire:loading.delay wire:target="{{ $targets }}" class="space-y-3" aria-busy="true" aria-live="polite">
        @for ($i = 0; $i < $rows; $i++)
            <x-skeleton variant="card" :rows="2" />
        @endfor
    </div>

    <div wire:loading.delay.remove wire:target="{{ $targets }}">
        {{ $slot }}
    </div>
</div>
