{{--
    x-section-header — header for a sub-section within a page.

    DeadCenter UX Standard §1 (#2 hierarchy) + §4.2. Use this to break a
    long page into scannable sections without promoting each one into its
    own `x-panel`. Pairs naturally with `x-data-table` for list sections.

    Props
    ─────
    title        string   Required. Section title.
    description  ?string  Short supporting description.
    eyebrow      ?string  Small uppercase kicker (e.g. 'Step 2').

    Slots
    ─────
    $actions  Right-aligned actions (e.g. 'View all →', filters).
--}}
@props([
    'title',
    'description' => null,
    'eyebrow' => null,
])

<div {{ $attributes->merge(['class' => 'mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between sm:gap-4']) }}>
    <div class="min-w-0 space-y-1">
        @if($eyebrow)
            <p class="text-eyebrow uppercase tracking-[0.18em] text-accent">{{ $eyebrow }}</p>
        @endif
        <h2 class="text-section-title text-primary">{{ $title }}</h2>
        @if($description)
            <p class="max-w-2xl text-meta text-muted">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
            {{ $actions }}
        </div>
    @endisset
</div>
