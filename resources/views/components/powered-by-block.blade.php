@props([
    'feature' => 'results',
    'matchId' => null,
    'variant' => 'inline',
])

@php
    if (! (bool) \App\Models\Setting::get('advertising_enabled', false)) {
        return;
    }

    $placementKeyMap = [
        'leaderboard' => \App\Enums\PlacementKey::MatchLeaderboard,
        'results' => \App\Enums\PlacementKey::MatchResults,
        'scoring' => \App\Enums\PlacementKey::MatchScoring,
    ];

    $key = $placementKeyMap[$feature] ?? null;
    $assignment = null;
    $sponsor = null;

    if ($key) {
        $resolver = app(\App\Services\SponsorPlacementResolver::class);
        $assignment = $resolver->resolve($key, $matchId);
        $sponsor = $assignment?->sponsor;
    }

    if (! $sponsor) {
        return;
    }

    $poweredBy = $key?->publicPoweredByPrefix() ?? (ucfirst($feature).' powered by');
    $logoUrl = $sponsor->logo_path ? asset('storage/' . $sponsor->logo_path) : null;
    $showPlatformNote = $assignment->scope_type === \App\Enums\SponsorScope::Platform;
@endphp

@if($variant === 'block')
    <div class="flex flex-col items-stretch gap-1 sm:items-end">
        @if($showPlatformNote)
            <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-500 sm:text-right">DeadCenter platform</span>
        @endif
        <div class="flex items-center gap-3 rounded-lg border border-zinc-700/50 bg-zinc-800/40 px-4 py-2.5">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $sponsor->name }}" class="h-7 max-w-[120px] object-contain">
            @endif
            <span class="text-xs font-medium text-zinc-400">
                {{ $poweredBy }}
                <span class="font-semibold text-zinc-300">{{ $sponsor->name }}</span>
            </span>
        </div>
    </div>
@elseif($variant === 'footer')
    <div class="flex flex-col items-center gap-1">
        @if($showPlatformNote)
            <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-500">DeadCenter platform</span>
        @endif
        <div class="flex items-center justify-center gap-2 py-1.5">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $sponsor->name }}" class="h-5 max-w-[100px] object-contain opacity-70">
            @endif
            <span class="text-[10px] font-medium text-zinc-500">
                {{ $poweredBy }} {{ $sponsor->name }}
            </span>
        </div>
    </div>
@else
    {{-- Default inline --}}
    <span class="inline-flex max-w-full flex-col items-end gap-0.5 text-xs text-zinc-400 sm:items-end">
        @if($showPlatformNote)
            <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-500">DeadCenter platform</span>
        @endif
        <span class="inline-flex items-center gap-1.5">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $sponsor->name }}" class="h-4 max-w-[80px] object-contain">
            @endif
            <span class="font-medium">
                {{ $poweredBy }}
                <span class="text-zinc-300">{{ $sponsor->name }}</span>
            </span>
        </span>
    </span>
@endif
