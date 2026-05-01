@props([
    'badge',
    'icon' => 'target',
    'tier' => 'earned',
    'family' => 'prs',
    'earnChip' => null,
    'showCriteria' => true,
])

@php
    $isPrs = $family === 'prs';
    $isDist = str_starts_with($icon, 'dist-');
    $criteria = $showCriteria ? \App\Http\Controllers\BadgeGalleryController::criteriaFor($badge->slug) : null;

    $tierLabels = [
        'featured'  => 'Signature Badge',
        'elite'     => 'Elite Achievement',
        'milestone' => 'Lifetime Milestone',
        'earned'    => 'Repeatable',
    ];

    $distanceCardStyles = [
        'dist-700' => [
            'card'     => 'border-red-400/25 bg-zinc-950/90',
            'hover'    => 'hover:border-red-300/40 hover:shadow-[0_18px_45px_rgba(0,0,0,0.45),0_0_30px_rgba(248,113,113,0.1)]',
            'accent'   => 'from-transparent via-red-400/40 to-transparent',
            'overline' => 'text-red-400/70',
            'title'    => 'text-white',
            'chip'     => 'border-red-400/20 bg-red-400/8 text-red-300/80',
        ],
        'dist-600' => [
            'card'     => 'border-orange-400/20 bg-zinc-950/90',
            'hover'    => 'hover:border-orange-400/35 hover:shadow-[0_18px_40px_rgba(0,0,0,0.4),0_0_20px_rgba(251,146,60,0.07)]',
            'accent'   => 'from-transparent via-orange-400/25 to-transparent',
            'overline' => 'text-orange-400/60',
            'title'    => 'text-white/95',
            'chip'     => 'border-orange-400/15 bg-orange-400/6 text-orange-300/70',
        ],
        'dist-500' => [
            'card'     => 'border-yellow-400/15 bg-zinc-950/90',
            'hover'    => 'hover:border-yellow-400/25 hover:shadow-[0_18px_40px_rgba(0,0,0,0.4)]',
            'accent'   => '',
            'overline' => 'text-yellow-400/50',
            'title'    => 'text-white/90',
            'chip'     => 'border-white/10 bg-white/5 text-white/55',
        ],
        'dist-400' => [
            'card'     => 'border-emerald-400/12 bg-zinc-950/90',
            'hover'    => 'hover:border-emerald-400/20 hover:shadow-[0_14px_35px_rgba(0,0,0,0.4)]',
            'accent'   => '',
            'overline' => 'text-emerald-400/45',
            'title'    => 'text-white/85',
            'chip'     => 'border-white/8 bg-white/4 text-white/50',
        ],
    ];

    $prs = [
        'featured' => [
            'card'     => 'border-sky-400/25 bg-zinc-950/90',
            'hover'    => 'hover:border-sky-300/40 hover:shadow-[0_18px_45px_rgba(0,0,0,0.45),0_0_30px_rgba(56,189,248,0.08)]',
            'accent'   => 'from-transparent via-sky-400/40 to-transparent',
            'overline' => 'text-sky-400/70',
            'title'    => 'text-white',
            'chip'     => 'border-sky-400/20 bg-sky-400/8 text-sky-300/80',
        ],
        'elite' => [
            'card'     => 'border-sky-400/15 bg-zinc-950/90',
            'hover'    => 'hover:border-sky-400/30 hover:shadow-[0_18px_40px_rgba(0,0,0,0.4),0_0_20px_rgba(56,189,248,0.06)]',
            'accent'   => 'from-transparent via-sky-400/25 to-transparent',
            'overline' => 'text-sky-400/60',
            'title'    => 'text-white/95',
            'chip'     => 'border-sky-400/15 bg-sky-400/6 text-sky-300/70',
        ],
        'milestone' => [
            'card'     => 'border-white/10 bg-zinc-950/90',
            'hover'    => 'hover:border-sky-300/20 hover:shadow-[0_18px_40px_rgba(0,0,0,0.4)]',
            'accent'   => '',
            'overline' => 'text-sky-300/50',
            'title'    => 'text-white/90',
            'chip'     => 'border-white/10 bg-white/5 text-white/55',
        ],
        'earned' => [
            'card'     => 'border-white/8 bg-zinc-950/90',
            'hover'    => 'hover:border-sky-400/15 hover:shadow-[0_14px_35px_rgba(0,0,0,0.4)]',
            'accent'   => '',
            'overline' => 'text-white/35',
            'title'    => 'text-white/85',
            'chip'     => 'border-white/8 bg-white/4 text-white/50',
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
        ],
        'elite' => [
            'card'     => 'border-amber-400/15 bg-zinc-950/90',
            'hover'    => 'hover:border-amber-400/30 hover:shadow-[0_18px_40px_rgba(0,0,0,0.4),0_0_20px_rgba(251,191,36,0.06)]',
            'accent'   => 'from-transparent via-amber-400/25 to-transparent',
            'overline' => 'text-amber-400/60',
            'title'    => 'text-white/95',
            'chip'     => 'border-amber-400/15 bg-amber-400/6 text-amber-300/70',
        ],
        'milestone' => [
            'card'     => 'border-white/10 bg-zinc-950/90',
            'hover'    => 'hover:border-amber-400/20 hover:shadow-[0_18px_40px_rgba(0,0,0,0.4)]',
            'accent'   => '',
            'overline' => 'text-amber-400/50',
            'title'    => 'text-white/90',
            'chip'     => 'border-white/10 bg-white/5 text-white/55',
        ],
        'earned' => [
            'card'     => 'border-white/8 bg-zinc-950/90',
            'hover'    => 'hover:border-amber-400/15 hover:shadow-[0_14px_35px_rgba(0,0,0,0.4)]',
            'accent'   => '',
            'overline' => 'text-white/35',
            'title'    => 'text-white/85',
            'chip'     => 'border-white/8 bg-white/4 text-white/50',
        ],
    ];

    $styles = $isPrs ? $prs : $rf;
    $s = ($isDist && isset($distanceCardStyles[$icon])) ? $distanceCardStyles[$icon] : ($styles[$tier] ?? $styles['earned']);
    $isFeatured = $tier === 'featured';
    $isElite = $tier === 'elite';
    $titleSize = 'text-lg sm:text-xl';

    $distOverlineLabels = [
        'dist-700' => 'Extreme Distance',
        'dist-600' => 'Long Distance',
        'dist-500' => 'Mid Distance',
        'dist-400' => 'Standard Distance',
    ];
@endphp

<div class="group relative flex h-full flex-col overflow-hidden rounded-3xl border p-6 sm:p-7
    shadow-[0_10px_30px_rgba(0,0,0,0.35)]
    transition-all duration-300 ease-out
    hover:-translate-y-1
    {{ $s['card'] }} {{ $s['hover'] }}">

    {{-- Radial highlight overlay --}}
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(255,255,255,0.06),transparent_50%)]"></div>
    <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(180deg,rgba(255,255,255,0.025)_0%,transparent_40%,transparent_80%,rgba(255,255,255,0.015)_100%)]"></div>

    @if($s['accent'])
        {{-- Top accent glow line --}}
        <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r {{ $s['accent'] }}"></div>
    @endif

    <div class="relative flex flex-1 items-start gap-5 sm:gap-6">
        <x-badge-crest :icon="$icon" :tier="$tier" :family="$family" />

        <div class="flex min-w-0 flex-1 flex-col space-y-2.5">
            {{-- Overline --}}
            <span class="text-[11px] font-semibold uppercase tracking-[0.16em] {{ $s['overline'] }}">
                {{ ($isDist ? ($distOverlineLabels[$icon] ?? null) : null) ?? $tierLabels[$tier] ?? 'Badge' }}
            </span>

            {{-- Title --}}
            <h3 class="font-semibold leading-tight {{ $titleSize }} {{ $s['title'] }}">
                {{ $badge->label }}
            </h3>

            {{-- Description --}}
            <p class="text-sm leading-6 text-zinc-400">
                {{ $badge->description }}
            </p>

            @if($criteria && $criteria !== $badge->description)
                <div class="mt-2 rounded-lg border border-white/6 bg-white/3 px-3 py-2">
                    <span class="block text-[10px] font-semibold uppercase tracking-[0.14em] {{ $s['overline'] }}">How to earn it</span>
                    <p class="mt-1 text-xs leading-snug text-zinc-300/90">{{ $criteria }}</p>
                </div>
            @endif

            @if($earnChip)
                <div class="mt-auto pt-1">
                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-medium uppercase tracking-wide {{ $s['chip'] }}">
                        {{ $earnChip }}
                    </span>
                </div>
            @endif
        </div>
    </div>
</div>
