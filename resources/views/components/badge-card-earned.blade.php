@props([
    'achievement',
    'icon' => 'target',
    'tier' => 'earned',
    'family' => 'prs',
    'count' => 1,
    'lastAwarded' => null,
    'matchName' => null,
])

@php
    $isPrs = $family === 'prs';

    $tierLabels = [
        'featured'  => 'Signature Badge',
        'elite'     => 'Elite Achievement',
        'milestone' => 'Lifetime Milestone',
        'earned'    => 'Repeatable',
    ];

    $prs = [
        'featured' => [
            'card'     => 'border-sky-400/25 bg-zinc-950/90',
            'hover'    => 'hover:border-sky-300/40 hover:shadow-[0_18px_45px_rgba(0,0,0,0.45),0_0_30px_rgba(56,189,248,0.08)]',
            'accent'   => 'from-transparent via-sky-400/40 to-transparent',
            'overline' => 'text-sky-400/70',
            'title'    => 'text-white',
            'chip'     => 'border-sky-400/20 bg-sky-400/8 text-sky-300/80',
            'count'    => 'text-sky-300',
        ],
        'elite' => [
            'card'     => 'border-sky-400/15 bg-zinc-950/90',
            'hover'    => 'hover:border-sky-400/30 hover:shadow-[0_18px_40px_rgba(0,0,0,0.4),0_0_20px_rgba(56,189,248,0.06)]',
            'accent'   => 'from-transparent via-sky-400/25 to-transparent',
            'overline' => 'text-sky-400/60',
            'title'    => 'text-white/95',
            'chip'     => 'border-sky-400/15 bg-sky-400/6 text-sky-300/70',
            'count'    => 'text-sky-300/80',
        ],
        'milestone' => [
            'card'     => 'border-white/10 bg-zinc-950/90',
            'hover'    => 'hover:border-sky-300/20 hover:shadow-[0_18px_40px_rgba(0,0,0,0.4)]',
            'accent'   => '',
            'overline' => 'text-sky-300/50',
            'title'    => 'text-white/90',
            'chip'     => 'border-white/10 bg-white/5 text-white/55',
            'count'    => 'text-white/60',
        ],
        'earned' => [
            'card'     => 'border-white/8 bg-zinc-950/90',
            'hover'    => 'hover:border-sky-400/15 hover:shadow-[0_14px_35px_rgba(0,0,0,0.4)]',
            'accent'   => '',
            'overline' => 'text-white/35',
            'title'    => 'text-white/85',
            'chip'     => 'border-white/8 bg-white/4 text-white/50',
            'count'    => 'text-white/50',
        ],
    ];

    $rf = [
        'featured' => [
            'card'     => 'border-amber-400/25 bg-zinc-950/90',
            'hover'    => 'hover:border-amber-300/40 hover:shadow-[0_18px_45px_rgba(0,0,0,0.45),0_0_30px_rgba(251,191,36,0.08)]',
            'accent'   => 'from-transparent via-amber-400/40 to-transparent',
            'overline' => 'text-amber-400/70',
            'title'    => 'text-white',
            'chip'     => 'border-amber-400/20 bg-amber-400/8 text-amber-300/80',
            'count'    => 'text-amber-300',
        ],
        'elite' => [
            'card'     => 'border-amber-400/15 bg-zinc-950/90',
            'hover'    => 'hover:border-amber-400/30 hover:shadow-[0_18px_40px_rgba(0,0,0,0.4),0_0_20px_rgba(251,191,36,0.06)]',
            'accent'   => 'from-transparent via-amber-400/25 to-transparent',
            'overline' => 'text-amber-400/60',
            'title'    => 'text-white/95',
            'chip'     => 'border-amber-400/15 bg-amber-400/6 text-amber-300/70',
            'count'    => 'text-amber-300/80',
        ],
        'milestone' => [
            'card'     => 'border-white/10 bg-zinc-950/90',
            'hover'    => 'hover:border-amber-400/20 hover:shadow-[0_18px_40px_rgba(0,0,0,0.4)]',
            'accent'   => '',
            'overline' => 'text-amber-400/50',
            'title'    => 'text-white/90',
            'chip'     => 'border-white/10 bg-white/5 text-white/55',
            'count'    => 'text-white/60',
        ],
        'earned' => [
            'card'     => 'border-white/8 bg-zinc-950/90',
            'hover'    => 'hover:border-amber-400/15 hover:shadow-[0_14px_35px_rgba(0,0,0,0.4)]',
            'accent'   => '',
            'overline' => 'text-white/35',
            'title'    => 'text-white/85',
            'chip'     => 'border-white/8 bg-white/4 text-white/50',
            'count'    => 'text-white/50',
        ],
    ];

    $styles = $isPrs ? $prs : $rf;
    $s = $styles[$tier] ?? $styles['earned'];
    $isFeatured = $tier === 'featured';
    $titleSize = $isFeatured ? 'text-xl sm:text-2xl' : 'text-lg sm:text-xl';
@endphp

<div class="group relative overflow-hidden rounded-3xl border p-6 sm:p-7
    shadow-[0_10px_30px_rgba(0,0,0,0.35)]
    transition-all duration-300 ease-out
    hover:-translate-y-1
    {{ $s['card'] }} {{ $s['hover'] }}">

    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(255,255,255,0.06),transparent_50%)]"></div>
    <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(180deg,rgba(255,255,255,0.025)_0%,transparent_40%,transparent_80%,rgba(255,255,255,0.015)_100%)]"></div>

    @if($s['accent'])
        <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r {{ $s['accent'] }}"></div>
    @endif

    <div class="relative flex items-start gap-5 sm:gap-6">
        <x-badge-crest :icon="$icon" :tier="$tier" :family="$family" />

        <div class="min-w-0 flex-1 space-y-2">
            <span class="text-[11px] font-semibold uppercase tracking-[0.16em] {{ $s['overline'] }}">
                {{ $tierLabels[$tier] ?? 'Badge' }}
            </span>

            <h3 class="font-semibold leading-tight {{ $titleSize }} {{ $s['title'] }}">
                {{ $achievement->label }}
                @if($count > 1)
                    <span class="ml-1.5 text-sm font-medium {{ $s['count'] }}">&times;{{ $count }}</span>
                @endif
            </h3>

            <p class="text-sm leading-6 text-zinc-400">{{ $achievement->description }}</p>

            <div class="flex flex-wrap items-center gap-2 pt-1">
                @if($matchName)
                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-medium {{ $s['chip'] }}">
                        {{ $matchName }}
                    </span>
                @endif
                @if($lastAwarded)
                    <span class="text-[11px] text-zinc-500">
                        {{ $lastAwarded->format('d M Y') }}
                    </span>
                @endif
            </div>
        </div>
    </div>
</div>
