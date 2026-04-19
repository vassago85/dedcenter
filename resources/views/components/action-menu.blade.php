{{--
    x-action-menu — standard kebab / overflow dropdown.

    DeadCenter UX Standard §1 (#5 actions match state) + §7 (#5 move
    secondary actions out of the way). Wraps related secondary actions
    into a single trigger so tables/pages don't drown in buttons.

    Usage
    ─────
        <x-action-menu>
            <x-action-menu.item :href="route('…')" icon="pencil">Edit</x-action-menu.item>
            <x-action-menu.item :href="route('…')" icon="download">Export standings</x-action-menu.item>
            <x-action-menu.divider />
            <x-action-menu.item tone="danger" icon="trash-2" method="DELETE" :action="route('…')">
                Delete
            </x-action-menu.item>
        </x-action-menu>

    Props
    ─────
    align    string   'left' | 'right'. Default 'right'.
    icon     string   Icon name for the trigger. Default 'more-horizontal'.
    label    ?string  Sr-only / tooltip label for the trigger. Default 'Open menu'.
    width    string   Tailwind width class. Default 'w-56'.
--}}
@props([
    'align' => 'right',
    'icon' => 'more-horizontal',
    'label' => 'Open menu',
    'width' => 'w-56',
])

@php
    $alignClass = $align === 'left' ? 'left-0' : 'right-0';
@endphp

<div class="relative inline-block" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
    <button type="button"
        @click="open = !open"
        :aria-expanded="open.toString()"
        aria-haspopup="menu"
        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted transition-colors hover:bg-surface-2 hover:text-primary focus:outline-none focus:ring-2 focus:ring-accent">
        <span class="sr-only">{{ $label }}</span>
        <x-icon :name="$icon" class="h-4 w-4" />
    </button>
    <div x-show="open"
        x-cloak
        x-transition.opacity.duration.100ms
        role="menu"
        class="absolute {{ $alignClass }} z-40 mt-1 {{ $width }} overflow-hidden rounded-xl border border-border bg-sidebar py-1 shadow-xl shadow-black/40">
        {{ $slot }}
    </div>
</div>
