{{--
    x-action-menu.item — single row inside an x-action-menu.

    Props
    ─────
    href    ?string  Makes the item a link. Omit for a button.
    icon    ?string  Optional Lucide icon name shown before the label.
    tone    string   'default' | 'danger'. Default 'default'.
    action  ?string  When set + used with `method`, renders a POST form.
    method  ?string  HTTP verb for the form (POST, DELETE, PATCH, PUT).
    type    string   When it's a plain button: default 'button'.
--}}
@props([
    'href' => null,
    'icon' => null,
    'tone' => 'default',
    'action' => null,
    'method' => null,
    'type' => 'button',
])

@php
    $base = 'flex w-full items-center gap-2 px-3 py-2 text-left text-body transition-colors';
    $toneClass = $tone === 'danger'
        ? 'text-rose-300 hover:bg-rose-500/10 hover:text-rose-200'
        : 'text-secondary hover:bg-surface-2 hover:text-primary';
@endphp

@if($action && $method)
    <form method="POST" action="{{ $action }}" class="m-0 p-0" role="none">
        @csrf
        @if(strtoupper($method) !== 'POST')
            @method($method)
        @endif
        <button type="submit" role="menuitem" {{ $attributes->merge(['class' => "{$base} {$toneClass}"]) }}>
            @if($icon)<x-icon :name="$icon" class="h-4 w-4 shrink-0" />@endif
            <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
        </button>
    </form>
@elseif($href)
    <a href="{{ $href }}" role="menuitem" {{ $attributes->merge(['class' => "{$base} {$toneClass}"]) }}>
        @if($icon)<x-icon :name="$icon" class="h-4 w-4 shrink-0" />@endif
        <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    </a>
@else
    <button type="{{ $type }}" role="menuitem" {{ $attributes->merge(['class' => "{$base} {$toneClass}"]) }}>
        @if($icon)<x-icon :name="$icon" class="h-4 w-4 shrink-0" />@endif
        <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    </button>
@endif
