{{--
    x-stat-card — single stat tile.

    DeadCenter UX Standard §7C: stats must be elegant and decision-useful,
    not random admin widgets. Use when a number actually drives a choice.

    Props
    ─────
    label         string   Required. All-caps kicker above the value.
    value         string   Required. The number / primary stat.
    color         string   Accent for the value + indicator dot.
                           Palette: slate | accent | red | green | emerald |
                           amber | yellow | orange | blue | sky | indigo |
                           purple | cyan | teal.
    href          ?string  Makes the whole card a link; shows arrow glyph.
    helper        ?string  Short helper line under the value.
    helperColor   string   Tone for helper (same palette as `color`).
    trend         ?string  'up' | 'down' — shows direction glyph + pct.
    trendValue    ?string  e.g. '+12%'.

    Slots
    ─────
    $icon    Optional Lucide icon in the top-right.
--}}
@props([
    'label',
    'value',
    'color' => 'slate',
    'href' => null,
    'helper' => null,
    'helperColor' => 'slate',
    'trend' => null,
    'trendValue' => null,
])

@php
    $accentMap = [
        'slate'   => 'text-primary',
        'accent'  => 'text-accent',
        'red'     => 'text-rose-300',
        'green'   => 'text-emerald-300',
        'emerald' => 'text-emerald-300',
        'amber'   => 'text-amber-300',
        'yellow'  => 'text-amber-300',
        'orange'  => 'text-orange-300',
        'blue'    => 'text-sky-300',
        'sky'     => 'text-sky-300',
        'indigo'  => 'text-indigo-300',
        'purple'  => 'text-purple-300',
        'cyan'    => 'text-cyan-300',
        'teal'    => 'text-teal-300',
    ];
    $dotMap = [
        'slate'   => 'bg-border',
        'accent'  => 'bg-accent',
        'red'     => 'bg-rose-400',
        'green'   => 'bg-emerald-400', 'emerald' => 'bg-emerald-400',
        'amber'   => 'bg-amber-400',   'yellow'  => 'bg-amber-400',
        'orange'  => 'bg-orange-400',
        'blue'    => 'bg-sky-400',     'sky'     => 'bg-sky-400',
        'indigo'  => 'bg-indigo-400',
        'purple'  => 'bg-purple-400',
        'cyan'    => 'bg-cyan-400',
        'teal'    => 'bg-teal-400',
    ];
    $helperTextMap = [
        'slate'   => 'text-muted',
        'accent'  => 'text-accent',
        'red'     => 'text-rose-300',
        'green'   => 'text-emerald-300', 'emerald' => 'text-emerald-300',
        'amber'   => 'text-amber-300',   'yellow'  => 'text-amber-300',
        'orange'  => 'text-orange-300',
        'blue'    => 'text-sky-300',     'sky'     => 'text-sky-300',
        'indigo'  => 'text-indigo-300',
        'purple'  => 'text-purple-300',
        'cyan'    => 'text-cyan-300',
        'teal'    => 'text-teal-300',
    ];

    $valueClass  = $accentMap[$color] ?? $accentMap['slate'];
    $dotClass    = $dotMap[$color] ?? $dotMap['slate'];
    $helperClass = $helperTextMap[$helperColor] ?? $helperTextMap['slate'];

    $tag = $href ? 'a' : 'div';
    $hoverClasses = $href
        ? 'transition-all hover:border-accent/60 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-accent/10'
        : '';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => "group relative block rounded-xl border border-border bg-surface p-5 shadow-sm {$hoverClasses}"]) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="flex min-w-0 items-center gap-2">
            <span class="h-1.5 w-1.5 rounded-full {{ $dotClass }}"></span>
            <p class="truncate text-label uppercase text-muted">{{ $label }}</p>
        </div>
        @isset($icon)
            <span class="text-muted transition-colors group-hover:text-secondary">{{ $icon }}</span>
        @else
            @if($href)
                <x-icon name="arrow-up-right" class="h-4 w-4 text-muted transition-all group-hover:translate-x-0.5 group-hover:text-accent" />
            @endif
        @endisset
    </div>
    <div class="mt-3 flex items-baseline gap-2">
        <span class="text-3xl font-semibold tracking-tight tabular-nums {{ $valueClass }}">{{ $value }}</span>
        @if($trend && $trendValue)
            <span class="inline-flex items-center gap-0.5 text-meta font-semibold {{ $trend === 'up' ? 'text-emerald-300' : 'text-rose-300' }}">
                <x-icon :name="$trend === 'up' ? 'trending-up' : 'trending-down'" class="h-3 w-3" />
                {{ $trendValue }}
            </span>
        @endif
    </div>
    @if($helper)
        <p class="mt-1.5 text-meta font-medium {{ $helperClass }}">{{ $helper }}</p>
    @endif
</{{ $tag }}>
