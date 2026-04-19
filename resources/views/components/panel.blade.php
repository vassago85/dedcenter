{{--
    x-panel — generic card / panel primitive.

    DeadCenter UX Standard §7 (Cards): use sparingly. Not every block needs
    to be a boxed card. Reach for `x-panel` when a group of related content
    or actions genuinely benefits from being visually separated from the
    rest of the page.

    Props
    ─────
    title     ?string   Panel title (rendered as H2/card-title).
    subtitle  ?string   One-line supporting description.
    padding   bool      When true (default) applies uniform inner padding.
                        Set to false to flush-mount tables / lists.
    hover     bool      Subtle lift + accent ring on hover. Default false.
    tone      string    Visual tone: 'default' | 'muted' | 'accent' | 'warning'.

    Slots
    ─────
    $header   Extra markup inside the header (below title/subtitle).
    $actions  Right-aligned action area in the header (buttons, kebab menus).
    $footer   Flush-mounted footer bar (e.g. 'View all →').
--}}
@props([
    'title' => null,
    'subtitle' => null,
    'padding' => true,
    'hover' => false,
    'tone' => 'default',
])

@php
    $toneClasses = match ($tone) {
        'muted'   => 'bg-sidebar/60',
        'accent'  => 'border-accent/40 bg-surface',
        'warning' => 'border-amber-500/40 bg-surface',
        default   => 'bg-surface',
    };
    $hoverClasses = $hover
        ? 'transition-all hover:border-accent/60 hover:shadow-lg hover:shadow-accent/10'
        : '';
@endphp

<section {{ $attributes->merge(['class' => "rounded-xl border border-border shadow-sm {$toneClasses} {$hoverClasses}"]) }}>
    @if($title || $subtitle || isset($header) || isset($actions))
        <div class="flex items-start justify-between gap-3 border-b border-border/70 px-6 py-4">
            <div class="min-w-0">
                @if($title)
                    <h2 class="text-card-title text-primary">{{ $title }}</h2>
                @endif
                @if($subtitle)
                    <p class="mt-0.5 text-meta text-muted">{{ $subtitle }}</p>
                @endif
                @isset($header){{ $header }}@endisset
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="{{ $padding ? 'p-6' : '' }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="rounded-b-xl border-t border-border/70 bg-surface-2/60 px-6 py-3">
            {{ $footer }}
        </div>
    @endisset
</section>
