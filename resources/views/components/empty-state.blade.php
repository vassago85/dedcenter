{{--
    x-empty-state — standard empty-state block.

    DeadCenter UX Standard §12: tell the user WHAT this area is for,
    WHY it's empty, and WHAT to do next. Always fill both title and
    description. Always include a primary action when one exists.

    Props
    ─────
    title        string   Required. "Nothing here yet" style — one line.
    description  ?string  Short explanation + the next step.
    size         string   'sm' | 'md' | 'lg'. Default 'md'. Use 'sm' inside
                          panels/tables; 'lg' for full-page empty screens.

    Slots
    ─────
    $icon     Optional icon bubble (use x-icon).
    $actions  Action row (primary button + optional secondary).
--}}
@props([
    'title' => 'Nothing here yet',
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

<div {{ $attributes->merge(['class' => "flex flex-col items-center justify-center gap-4 text-center {$pad}"]) }}>
    @isset($icon)
        <div class="flex {{ $iconBox }} items-center justify-center rounded-full border border-border bg-surface-2 text-muted">
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
