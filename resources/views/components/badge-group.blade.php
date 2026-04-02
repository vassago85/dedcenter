@props([
    'label',
    'description' => '',
    'tier',
    'badges',
    'badgeConfig' => [],
    'family' => 'prs',
    'headerIcon' => null,
])

@php
    $headerIcons = [
        'match_special' => 'sparkles',
        'lifetime'      => 'award',
        'repeatable'    => 'layers',
    ];

    $headerStyles = [
        'prs' => [
            'match_special' => ['text' => 'text-blue-300',  'line' => 'from-transparent via-blue-500/40 to-transparent', 'icon' => 'text-blue-400'],
            'lifetime'      => ['text' => 'text-slate-300', 'line' => 'from-transparent via-slate-500/30 to-transparent', 'icon' => 'text-slate-400'],
            'repeatable'    => ['text' => 'text-slate-400', 'line' => 'from-transparent via-slate-600/25 to-transparent', 'icon' => 'text-slate-500'],
        ],
        'royal_flush' => [
            'match_special' => ['text' => 'text-amber-300',  'line' => 'from-transparent via-amber-500/40 to-transparent', 'icon' => 'text-amber-400'],
            'lifetime'      => ['text' => 'text-amber-400',  'line' => 'from-transparent via-amber-600/25 to-transparent', 'icon' => 'text-amber-500'],
            'repeatable'    => ['text' => 'text-amber-500',  'line' => 'from-transparent via-amber-700/20 to-transparent', 'icon' => 'text-amber-600'],
        ],
    ];

    $hs = $headerStyles[$family][$tier] ?? $headerStyles['prs']['repeatable'];
    $hIcon = $headerIcon ?? ($headerIcons[$tier] ?? 'layers');
    $isSingle = $badges->count() === 1 && $tier === 'match_special';
@endphp

<div class="mb-10">
    {{-- Group header --}}
    <div class="mb-5">
        <div class="flex items-center gap-2.5">
            <x-badge-icon :name="$hIcon" class="h-4 w-4 {{ $hs['icon'] }}" />
            <h3 class="text-sm font-bold uppercase tracking-wider {{ $hs['text'] }}">{{ $label }}</h3>
        </div>
        @if($description)
            <p class="mt-1 pl-6.5 text-xs text-zinc-500">{{ $description }}</p>
        @endif
        <div class="mt-3 h-px bg-gradient-to-r {{ $hs['line'] }}"></div>
    </div>

    {{-- Badge grid --}}
    <div class="grid gap-4 {{ $isSingle ? 'grid-cols-1 max-w-2xl' : 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3' }}">
        @foreach($badges as $badge)
            @php
                $cfg = $badgeConfig[$badge->slug] ?? [];
                $icon = $cfg['icon'] ?? 'target';
                $earnChip = $cfg['earnChip'] ?? null;
            @endphp
            <x-badge-card
                :badge="$badge"
                :icon="$icon"
                :tier="$tier"
                :family="$family"
                :earnChip="$earnChip"
            />
        @endforeach
    </div>
</div>
