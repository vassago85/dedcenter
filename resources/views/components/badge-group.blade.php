@props([
    'label',
    'description' => '',
    'category',
    'badges',
    'badgeConfig' => [],
    'family' => 'prs',
    'headerIcon' => null,
])

@php
    $isPrs = $family === 'prs';

    $headerIcons = [
        'match_special' => 'sparkles',
        'lifetime'      => 'award',
        'repeatable'    => 'layers',
    ];

    $headerStyles = [
        'prs' => [
            'match_special' => ['text' => 'text-sky-300/80',   'line' => 'from-transparent via-sky-400/30 to-transparent', 'icon' => 'text-sky-400/70'],
            'lifetime'      => ['text' => 'text-sky-300/60',   'line' => 'from-transparent via-white/10 to-transparent',   'icon' => 'text-sky-400/50'],
            'repeatable'    => ['text' => 'text-white/40',     'line' => 'from-transparent via-white/8 to-transparent',    'icon' => 'text-white/30'],
        ],
        'royal_flush' => [
            'match_special' => ['text' => 'text-amber-300/80', 'line' => 'from-transparent via-amber-400/30 to-transparent', 'icon' => 'text-amber-400/70'],
            'lifetime'      => ['text' => 'text-amber-400/60', 'line' => 'from-transparent via-white/10 to-transparent',     'icon' => 'text-amber-400/50'],
            'repeatable'    => ['text' => 'text-white/40',     'line' => 'from-transparent via-white/8 to-transparent',      'icon' => 'text-white/30'],
        ],
    ];

    $hs = $headerStyles[$family][$category] ?? $headerStyles['prs']['repeatable'];
    $hIcon = $headerIcon ?? ($headerIcons[$category] ?? 'layers');

    $hasFeatured = $badges->contains(fn ($b) => ($badgeConfig[$b->slug]['tier'] ?? 'earned') === 'featured');
@endphp

<div class="mb-12">
    {{-- Group header --}}
    <div class="mb-6">
        <div class="flex items-center gap-2.5">
            <x-badge-icon :name="$hIcon" class="h-4 w-4 {{ $hs['icon'] }}" />
            <h3 class="text-[13px] font-semibold uppercase tracking-[0.14em] {{ $hs['text'] }}">{{ $label }}</h3>
        </div>
        @if($description)
            <p class="mt-1.5 pl-6.5 text-xs text-zinc-500">{{ $description }}</p>
        @endif
        <div class="mt-3 h-px bg-gradient-to-r {{ $hs['line'] }}"></div>
    </div>

    {{-- Badge grid --}}
    <div class="grid gap-5 grid-cols-1 md:grid-cols-2 xl:grid-cols-3">
        @foreach($badges as $badge)
            @php
                $cfg = $badgeConfig[$badge->slug] ?? [];
                $icon = $cfg['icon'] ?? 'target';
                $badgeTier = $cfg['tier'] ?? 'earned';
                $earnChip = $cfg['earnChip'] ?? null;
                $isFeaturedCard = $badgeTier === 'featured';
            @endphp
            <div class="{{ $isFeaturedCard ? 'md:col-span-2 xl:col-span-2' : '' }}">
                <x-badge-card
                    :badge="$badge"
                    :icon="$icon"
                    :tier="$badgeTier"
                    :family="$family"
                    :earnChip="$earnChip"
                />
            </div>
        @endforeach
    </div>
</div>
