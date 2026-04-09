@props(['icon', 'tier' => 'earned', 'family' => 'prs'])

@php
    $isPrs = $family === 'prs';
    $isDist = str_starts_with($icon, 'dist-');

    $sizes = [
        'featured'  => ['outer' => 'h-[4.5rem] w-[4.5rem] sm:h-20 sm:w-20', 'icon' => 'h-7 w-7 sm:h-8 sm:w-8'],
        'elite'     => ['outer' => 'h-16 w-16 sm:h-[4.5rem] sm:w-[4.5rem]', 'icon' => 'h-6 w-6 sm:h-7 sm:w-7'],
        'milestone' => ['outer' => 'h-14 w-14 sm:h-16 sm:w-16',             'icon' => 'h-5 w-5 sm:h-6 sm:w-6'],
        'earned'    => ['outer' => 'h-12 w-12 sm:h-14 sm:w-14',             'icon' => 'h-5 w-5 sm:h-5 sm:w-5'],
    ];

    $distanceStyles = [
        'dist-700' => [
            'outer'   => 'border-red-400/40 shadow-[0_0_30px_rgba(248,113,113,0.18)]',
            'ring'    => 'ring-1 ring-red-300/25',
            'inner'   => 'bg-gradient-to-b from-red-400/20 to-orange-500/10 border-red-400/30',
            'core'    => 'bg-gradient-to-b from-white/10 to-white/4',
            'icon'    => 'text-red-200',
            'glow'    => 'bg-red-400/15',
        ],
        'dist-600' => [
            'outer'   => 'border-orange-400/30',
            'ring'    => 'ring-1 ring-orange-400/15',
            'inner'   => 'bg-gradient-to-b from-orange-400/16 to-amber-500/8 border-orange-400/22',
            'core'    => 'bg-gradient-to-b from-white/8 to-white/3',
            'icon'    => 'text-orange-200',
            'glow'    => '',
        ],
        'dist-500' => [
            'outer'   => 'border-yellow-400/22',
            'ring'    => '',
            'inner'   => 'bg-gradient-to-b from-yellow-400/12 to-amber-500/5 border-yellow-400/16',
            'core'    => '',
            'icon'    => 'text-yellow-300',
            'glow'    => '',
        ],
        'dist-400' => [
            'outer'   => 'border-emerald-400/18',
            'ring'    => '',
            'inner'   => 'bg-gradient-to-b from-emerald-400/10 to-green-500/4 border-emerald-400/12',
            'core'    => '',
            'icon'    => 'text-emerald-300/80',
            'glow'    => '',
        ],
    ];

    $prsStyles = [
        'featured' => [
            'outer'   => 'border-sky-400/35 shadow-[0_0_30px_rgba(56,189,248,0.15)]',
            'ring'    => 'ring-1 ring-sky-300/20',
            'inner'   => 'bg-gradient-to-b from-sky-400/18 to-sky-600/8 border-sky-400/25',
            'core'    => 'bg-gradient-to-b from-white/10 to-white/4',
            'icon'    => 'text-sky-200',
            'glow'    => 'bg-sky-400/12',
        ],
        'elite' => [
            'outer'   => 'border-sky-400/25',
            'ring'    => 'ring-1 ring-sky-400/12',
            'inner'   => 'bg-gradient-to-b from-sky-400/14 to-sky-500/6 border-sky-400/18',
            'core'    => 'bg-gradient-to-b from-white/8 to-white/3',
            'icon'    => 'text-sky-200',
            'glow'    => '',
        ],
        'milestone' => [
            'outer'   => 'border-sky-300/20',
            'ring'    => '',
            'inner'   => 'bg-gradient-to-b from-sky-400/10 to-sky-500/4 border-sky-300/15',
            'core'    => '',
            'icon'    => 'text-sky-300',
            'glow'    => '',
        ],
        'earned' => [
            'outer'   => 'border-white/12',
            'ring'    => '',
            'inner'   => 'bg-gradient-to-b from-white/7 to-white/3 border-white/8',
            'core'    => '',
            'icon'    => 'text-sky-300/80',
            'glow'    => '',
        ],
    ];

    $rfStyles = [
        'featured' => [
            'outer'   => 'border-amber-400/35 shadow-[0_0_30px_rgba(251,191,36,0.14)]',
            'ring'    => 'ring-1 ring-amber-300/20',
            'inner'   => 'bg-gradient-to-b from-amber-400/18 to-orange-500/8 border-amber-400/25',
            'core'    => 'bg-gradient-to-b from-white/10 to-white/4',
            'icon'    => 'text-amber-200',
            'glow'    => 'bg-amber-400/12',
        ],
        'elite' => [
            'outer'   => 'border-amber-400/25',
            'ring'    => 'ring-1 ring-amber-400/12',
            'inner'   => 'bg-gradient-to-b from-amber-400/14 to-orange-500/6 border-amber-400/18',
            'core'    => 'bg-gradient-to-b from-white/8 to-white/3',
            'icon'    => 'text-amber-200',
            'glow'    => '',
        ],
        'milestone' => [
            'outer'   => 'border-amber-400/18',
            'ring'    => '',
            'inner'   => 'bg-gradient-to-b from-amber-400/10 to-orange-500/4 border-amber-400/12',
            'core'    => '',
            'icon'    => 'text-amber-300',
            'glow'    => '',
        ],
        'earned' => [
            'outer'   => 'border-white/12',
            'ring'    => '',
            'inner'   => 'bg-gradient-to-b from-white/7 to-white/3 border-white/8',
            'core'    => '',
            'icon'    => 'text-amber-300/80',
            'glow'    => '',
        ],
    ];

    $medalStyles = [
        'medal-1' => [
            'outer'   => 'border-amber-400/40 shadow-[0_0_25px_rgba(251,191,36,0.18)]',
            'ring'    => 'ring-1 ring-amber-300/25',
            'inner'   => 'bg-gradient-to-b from-amber-400/22 to-yellow-600/10 border-amber-400/30',
            'core'    => 'bg-gradient-to-b from-white/12 to-white/4',
            'icon'    => 'text-amber-300',
            'glow'    => 'bg-amber-400/15',
        ],
        'medal-2' => [
            'outer'   => 'border-slate-300/35 shadow-[0_0_25px_rgba(203,213,225,0.12)]',
            'ring'    => 'ring-1 ring-slate-300/20',
            'inner'   => 'bg-gradient-to-b from-slate-300/20 to-slate-400/8 border-slate-300/25',
            'core'    => 'bg-gradient-to-b from-white/10 to-white/4',
            'icon'    => 'text-slate-300',
            'glow'    => 'bg-slate-300/10',
        ],
        'medal-3' => [
            'outer'   => 'border-orange-400/35 shadow-[0_0_25px_rgba(251,146,60,0.14)]',
            'ring'    => 'ring-1 ring-orange-300/20',
            'inner'   => 'bg-gradient-to-b from-orange-400/20 to-amber-700/10 border-orange-400/28',
            'core'    => 'bg-gradient-to-b from-white/10 to-white/4',
            'icon'    => 'text-orange-300',
            'glow'    => 'bg-orange-400/12',
        ],
    ];

    $styles = $isPrs ? $prsStyles : $rfStyles;
    $isMedal = isset($medalStyles[$icon]);
    $s = $isMedal ? $medalStyles[$icon] : (($isDist && isset($distanceStyles[$icon])) ? $distanceStyles[$icon] : ($styles[$tier] ?? $styles['earned']));
    $sz = $sizes[$tier] ?? $sizes['earned'];
    $isFeatured = $tier === 'featured';
    $isElite = $tier === 'elite';
@endphp

<div class="relative flex-shrink-0">
    {{-- Outer ring --}}
    <div class="grid place-items-center rounded-2xl border-2 {{ $sz['outer'] }} {{ $s['outer'] }} {{ $s['ring'] }}">
        {{-- Inner medallion --}}
        <div class="absolute inset-[3px] rounded-[calc(1rem-1px)] border {{ $s['inner'] }}"></div>

        @if($s['core'])
            {{-- Core highlight layer --}}
            <div class="absolute inset-[6px] rounded-[calc(1rem-3px)] {{ $s['core'] }}"></div>
        @endif

        {{-- Icon --}}
        <x-badge-icon
            :name="$icon"
            :class="$sz['icon'] . ' relative z-10 ' . $s['icon'] . ' transition-transform duration-300 group-hover:scale-[1.03]'"
        />
    </div>

    @if($s['glow'])
        <div class="pointer-events-none absolute inset-0 -z-10 scale-[1.6] rounded-2xl opacity-50 blur-2xl {{ $s['glow'] }}"></div>
    @endif
</div>
