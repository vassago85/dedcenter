@props(['icon', 'tier' => 'repeatable', 'family' => 'prs'])

@php
    $styles = [
        'prs' => [
            'match_special' => [
                'outer' => 'border-blue-400/40 shadow-[0_0_18px_rgba(96,165,250,0.2)]',
                'inner' => 'bg-gradient-to-br from-blue-500/20 via-slate-800 to-slate-900 border-blue-500/25',
                'ring'  => 'ring-1 ring-blue-400/20',
                'icon'  => 'text-blue-300',
            ],
            'lifetime' => [
                'outer' => 'border-slate-400/30',
                'inner' => 'bg-gradient-to-br from-slate-600/20 via-slate-800 to-slate-900 border-slate-500/20',
                'ring'  => '',
                'icon'  => 'text-slate-300',
            ],
            'repeatable' => [
                'outer' => 'border-slate-500/20',
                'inner' => 'bg-gradient-to-br from-slate-700/30 via-slate-800 to-slate-900 border-slate-600/15',
                'ring'  => '',
                'icon'  => 'text-slate-400',
            ],
        ],
        'royal_flush' => [
            'match_special' => [
                'outer' => 'border-amber-400/40 shadow-[0_0_18px_rgba(251,191,36,0.18)]',
                'inner' => 'bg-gradient-to-br from-amber-500/20 via-stone-800 to-stone-900 border-amber-500/25',
                'ring'  => 'ring-1 ring-amber-400/20',
                'icon'  => 'text-amber-300',
            ],
            'lifetime' => [
                'outer' => 'border-amber-500/25',
                'inner' => 'bg-gradient-to-br from-amber-600/15 via-stone-800 to-stone-900 border-amber-500/15',
                'ring'  => '',
                'icon'  => 'text-amber-400',
            ],
            'repeatable' => [
                'outer' => 'border-amber-600/15',
                'inner' => 'bg-gradient-to-br from-amber-700/10 via-stone-800 to-stone-900 border-amber-700/10',
                'ring'  => '',
                'icon'  => 'text-amber-500',
            ],
        ],
    ];

    $s = $styles[$family][$tier] ?? $styles['prs']['repeatable'];
    $isSpecial = $tier === 'match_special';
    $outerSize = $isSpecial ? 'h-16 w-16 sm:h-20 sm:w-20' : 'h-14 w-14 sm:h-16 sm:w-16';
    $iconSize  = $isSpecial ? 'h-7 w-7 sm:h-8 sm:w-8' : 'h-5 w-5 sm:h-6 sm:w-6';
@endphp

<div class="relative flex-shrink-0">
    {{-- Outer ring --}}
    <div class="flex items-center justify-center rounded-full border-2 {{ $outerSize }} {{ $s['outer'] }} {{ $s['ring'] }}">
        {{-- Inner medallion --}}
        <div class="flex h-[calc(100%-6px)] w-[calc(100%-6px)] items-center justify-center rounded-full border {{ $s['inner'] }}">
            <x-badge-icon :name="$icon" :class="$iconSize . ' ' . $s['icon']" />
        </div>
    </div>
    @if($isSpecial)
        {{-- Subtle radial highlight behind the crest for match specials --}}
        <div class="pointer-events-none absolute inset-0 -z-10 scale-150 rounded-full opacity-40 blur-2xl
            {{ $family === 'royal_flush' ? 'bg-amber-500/10' : 'bg-blue-500/10' }}"></div>
    @endif
</div>
