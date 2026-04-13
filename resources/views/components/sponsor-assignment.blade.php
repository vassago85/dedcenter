@props([
    'assignment',
    'variant' => 'inline',
])

@php
    $sponsor = $assignment->sponsor;
    $label = $assignment->displayLabel();
    $logoUrl = $sponsor->logo_path ? asset('storage/' . $sponsor->logo_path) : null;
    $placementKey = $assignment->placement_key;
    $portalOrLanding = $placementKey->isPortalPlacement() || $placementKey->isLandingPlacement();
    $eyebrow = null;
    if ($portalOrLanding) {
        $eyebrow = $assignment->scope_type === \App\Enums\SponsorScope::Platform
            ? 'DeadCenter platform'
            : 'Club portal partner';
    }
@endphp

@if($variant === 'cover')
    {{-- Large cover placement (matchbook cover, header banners) --}}
    <div class="flex flex-col items-center justify-center gap-2 py-3 sm:gap-2.5">
        @if($eyebrow)
            <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ $eyebrow }}</span>
        @endif
        <div class="flex flex-wrap items-center justify-center gap-3">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $sponsor->name }}" class="h-10 max-w-[160px] object-contain">
            @endif
            <p class="max-w-xl text-center text-sm leading-snug text-zinc-500 dark:text-zinc-400 sm:text-left">
                <span class="font-medium">{{ $label }}</span>
                <span class="font-semibold text-zinc-700 dark:text-zinc-200"> {{ $sponsor->name }}</span>
            </p>
        </div>
    </div>
@elseif($variant === 'block')
    {{-- Block placement (results headers, leaderboard headers) --}}
    <div class="flex flex-col gap-1.5">
        @if($eyebrow)
            <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ $eyebrow }}</span>
        @endif
        <div class="flex items-center gap-2.5 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $sponsor->name }}" class="h-7 max-w-[120px] object-contain">
            @endif
            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">
                <span class="font-medium">{{ $label }}</span>
                <span class="font-semibold text-zinc-600 dark:text-zinc-300"> {{ $sponsor->name }}</span>
            </span>
        </div>
    </div>
@elseif($variant === 'footer')
    {{-- Footer placement (page footers, subtle branding) --}}
    <div class="flex flex-col items-center gap-1">
        @if($eyebrow)
            <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ $eyebrow }}</span>
        @endif
        <div class="flex items-center justify-center gap-2 py-1.5">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $sponsor->name }}" class="h-5 max-w-[100px] object-contain opacity-70">
            @endif
            <span class="text-[10px] font-medium text-zinc-400 dark:text-zinc-500">
                {{ $label }}
                <span class="font-semibold text-zinc-500 dark:text-zinc-400"> {{ $sponsor->name }}</span>
            </span>
        </div>
    </div>
@else
    {{-- Default inline placement (subtle, compact) --}}
    <span class="inline-flex max-w-full flex-col gap-0.5 text-xs text-zinc-500 dark:text-zinc-400">
        @if($eyebrow)
            <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ $eyebrow }}</span>
        @endif
        <span class="inline-flex flex-wrap items-center gap-1.5">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $sponsor->name }}" class="h-4 max-w-[80px] object-contain">
            @endif
            <span class="font-medium">
                {{ $label }}
                <span class="font-semibold text-zinc-600 dark:text-zinc-200"> {{ $sponsor->name }}</span>
            </span>
        </span>
    </span>
@endif
