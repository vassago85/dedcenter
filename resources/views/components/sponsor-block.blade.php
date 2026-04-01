@php
    $sponsor = $assignment->sponsor;
    $label = $assignment->displayLabel();
    $logoUrl = $sponsor->logo_path ? asset('storage/' . $sponsor->logo_path) : null;
@endphp

@if($variant === 'cover')
    {{-- Large cover placement (matchbook cover, header banners) --}}
    <div class="flex items-center justify-center gap-3 py-3">
        @if($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $sponsor->name }}" class="h-10 max-w-[160px] object-contain">
        @endif
        <div class="text-sm text-zinc-500 dark:text-zinc-400">
            <span class="font-medium">{{ $label }}</span>
            @if(!$logoUrl)
                <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $sponsor->name }}</span>
            @endif
        </div>
    </div>
@elseif($variant === 'block')
    {{-- Block placement (results headers, leaderboard headers) --}}
    <div class="flex items-center gap-2.5 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
        @if($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $sponsor->name }}" class="h-7 max-w-[120px] object-contain">
        @endif
        <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">
            {{ $label }}
            @if(!$logoUrl)
                <span class="font-semibold text-zinc-600 dark:text-zinc-300">{{ $sponsor->name }}</span>
            @endif
        </span>
    </div>
@elseif($variant === 'footer')
    {{-- Footer placement (page footers, subtle branding) --}}
    <div class="flex items-center justify-center gap-2 py-1.5">
        @if($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $sponsor->name }}" class="h-5 max-w-[100px] object-contain opacity-70">
        @endif
        <span class="text-[10px] font-medium text-zinc-400 dark:text-zinc-500">
            {{ $label }}
            @if(!$logoUrl)
                {{ $sponsor->name }}
            @endif
        </span>
    </div>
@else
    {{-- Default inline placement (subtle, compact) --}}
    <span class="inline-flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
        @if($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $sponsor->name }}" class="h-4 max-w-[80px] object-contain">
        @endif
        <span class="font-medium">
            {{ $label }}
            @if(!$logoUrl)
                {{ $sponsor->name }}
            @endif
        </span>
    </span>
@endif
