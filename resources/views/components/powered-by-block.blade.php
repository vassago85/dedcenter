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

    $featureLabel = $key?->poweredByLabel() ?? ucfirst($feature);
    $logoUrl = $sponsor->logo_path ? asset('storage/' . $sponsor->logo_path) : null;
    $poweredBy = $featureLabel . ' powered by';
@endphp

@if($variant === 'block')
    <div class="flex items-center gap-3 rounded-lg border border-zinc-700/50 bg-zinc-800/40 px-4 py-2.5">
        @if($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $sponsor->name }}" class="h-7 max-w-[120px] object-contain">
        @endif
        <span class="text-xs font-medium text-zinc-400">
            {{ $poweredBy }}
            <span class="font-semibold text-zinc-300">{{ $sponsor->name }}</span>
        </span>
    </div>
@elseif($variant === 'footer')
    <div class="flex items-center justify-center gap-2 py-1.5">
        @if($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $sponsor->name }}" class="h-5 max-w-[100px] object-contain opacity-70">
        @endif
        <span class="text-[10px] font-medium text-zinc-500">
            {{ $poweredBy }} {{ $sponsor->name }}
        </span>
    </div>
@else
    {{-- Default inline --}}
    <span class="inline-flex items-center gap-1.5 text-xs text-zinc-400">
        @if($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $sponsor->name }}" class="h-4 max-w-[80px] object-contain">
        @endif
        <span class="font-medium">
            {{ $poweredBy }}
            <span class="text-zinc-300">{{ $sponsor->name }}</span>
        </span>
    </span>
@endif
