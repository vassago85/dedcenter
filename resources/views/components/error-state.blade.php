{{--
    x-error-state — inline failure for a panel or list.

    DeadCenter UX Standard §12: say what failed and what to do next.
    Full-page HTTP errors stay in resources/views/errors/. Use this when
    one region fails and the rest of the page is still usable.

    Props
    ─────
    title        string   Required. One line, what failed.
    description  ?string  What to do next.
    size         string   'sm' | 'md' | 'lg'. Default 'md'.

    Slots
    ─────
    $icon     Optional icon bubble (use x-icon).
    $actions  Action row (retry, or another next step).
--}}
@props([
    'title' => 'This didn\'t load',
    'description' => null,
    'size' => 'md',
])

@php
    [$pad, $iconBox, $titleClass] = match ($size) {
        'sm' => ['px-4 py-8',  'h-10 w-10', 'text-card-title'],
        'lg' => ['px-6 py-16', 'h-16 w-16', 'text-section-title'],
        default => ['px-6 py-12', 'h-12 w-12', 'text-card-title'],
    };
@endphp

<div role="alert" {{ $attributes->merge(['class' => "flex flex-col items-center justify-center gap-4 text-center {$pad}"]) }}>
    @isset($icon)
        <div class="flex {{ $iconBox }} items-center justify-center rounded-full border border-border bg-surface-2 text-accent">
            {{ $icon }}
        </div>
    @endisset

    <div class="space-y-1.5">
        <p class="{{ $titleClass }} text-primary">{{ $title }}</p>
        @if($description)
            <p class="max-w-md text-body text-muted">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center justify-center gap-2 pt-1">{{ $actions }}</div>
    @endisset
</div>
