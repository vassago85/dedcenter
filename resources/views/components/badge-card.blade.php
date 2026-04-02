@props([
    'badge',
    'icon' => 'target',
    'tier' => 'repeatable',
    'family' => 'prs',
    'earnChip' => null,
])

@php
    $tierLabels = [
        'match_special' => 'Match Special',
        'lifetime'      => 'Lifetime Milestone',
        'repeatable'    => 'Repeatable',
    ];

    $cardStyles = [
        'prs' => [
            'match_special' => [
                'card'  => 'border-blue-500/30 bg-gradient-to-br from-blue-950/40 via-slate-900/80 to-slate-900',
                'title' => 'text-blue-200',
                'label' => 'text-blue-400/80',
                'chip'  => 'bg-blue-500/15 text-blue-300 border-blue-500/20',
                'hover' => 'hover:border-blue-400/40 hover:shadow-blue-500/10',
            ],
            'lifetime' => [
                'card'  => 'border-slate-500/25 bg-gradient-to-br from-slate-800/60 via-slate-900/80 to-slate-950',
                'title' => 'text-slate-200',
                'label' => 'text-slate-400/80',
                'chip'  => 'bg-slate-500/15 text-slate-300 border-slate-500/20',
                'hover' => 'hover:border-slate-400/30 hover:shadow-slate-500/8',
            ],
            'repeatable' => [
                'card'  => 'border-slate-600/20 bg-gradient-to-br from-slate-800/40 via-slate-900/70 to-slate-950',
                'title' => 'text-slate-200',
                'label' => 'text-slate-500',
                'chip'  => 'bg-slate-600/15 text-slate-400 border-slate-600/15',
                'hover' => 'hover:border-slate-500/25 hover:shadow-slate-600/8',
            ],
        ],
        'royal_flush' => [
            'match_special' => [
                'card'  => 'border-amber-500/30 bg-gradient-to-br from-amber-950/40 via-stone-900/80 to-stone-900',
                'title' => 'text-amber-200',
                'label' => 'text-amber-400/80',
                'chip'  => 'bg-amber-500/15 text-amber-300 border-amber-500/20',
                'hover' => 'hover:border-amber-400/40 hover:shadow-amber-500/10',
            ],
            'lifetime' => [
                'card'  => 'border-amber-600/20 bg-gradient-to-br from-amber-950/25 via-stone-900/80 to-stone-950',
                'title' => 'text-amber-200',
                'label' => 'text-amber-500/80',
                'chip'  => 'bg-amber-600/15 text-amber-400 border-amber-600/15',
                'hover' => 'hover:border-amber-500/25 hover:shadow-amber-600/8',
            ],
            'repeatable' => [
                'card'  => 'border-amber-700/15 bg-gradient-to-br from-amber-950/15 via-stone-900/70 to-stone-950',
                'title' => 'text-amber-200',
                'label' => 'text-amber-600',
                'chip'  => 'bg-amber-700/12 text-amber-500 border-amber-700/12',
                'hover' => 'hover:border-amber-600/20 hover:shadow-amber-700/8',
            ],
        ],
    ];

    $s = $cardStyles[$family][$tier] ?? $cardStyles['prs']['repeatable'];
    $isSpecial = $tier === 'match_special';
@endphp

<div class="group relative overflow-hidden rounded-2xl border p-5 sm:p-6 transition-all duration-200
    hover:-translate-y-0.5 hover:shadow-lg {{ $s['card'] }} {{ $s['hover'] }}">

    @if($isSpecial)
        {{-- Top-edge accent glow line --}}
        <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r
            {{ $family === 'royal_flush'
                ? 'from-transparent via-amber-400/50 to-transparent'
                : 'from-transparent via-blue-400/50 to-transparent' }}"></div>
    @endif

    <div class="flex items-start gap-4 sm:gap-5">
        <x-badge-crest :icon="$icon" :tier="$tier" :family="$family" />

        <div class="min-w-0 flex-1 space-y-2">
            {{-- Tier overline --}}
            <span class="text-[10px] font-bold uppercase tracking-widest {{ $s['label'] }}">
                {{ $tierLabels[$tier] ?? 'Badge' }}
            </span>

            {{-- Title --}}
            <h3 class="text-lg font-black leading-tight sm:text-xl {{ $s['title'] }}
                transition-colors duration-200 group-hover:brightness-110">
                {{ $badge->label }}
            </h3>

            {{-- Description --}}
            <p class="text-sm leading-relaxed text-zinc-400">
                {{ $badge->description }}
            </p>

            @if($earnChip)
                <div class="pt-1">
                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $s['chip'] }}">
                        {{ $earnChip }}
                    </span>
                </div>
            @endif
        </div>
    </div>
</div>
