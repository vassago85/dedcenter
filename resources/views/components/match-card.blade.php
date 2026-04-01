@props(['match', 'context' => 'default'])

@php
    $statusEnum = $match->status;
    $statusValue = $statusEnum instanceof \BackedEnum ? $statusEnum->value : $statusEnum;
    $statusLabel = method_exists($statusEnum, 'label') ? $statusEnum->label() : ucfirst(str_replace('_', ' ', $statusValue));
    $statusColor = method_exists($statusEnum, 'color') ? $statusEnum->color() : 'zinc';
    $isLive = $statusValue === 'active';
    $shooterCount = $match->shooters_count ?? $match->shooters()->count();
@endphp

<div class="relative rounded-2xl p-5 transition-all duration-200 hover:scale-[1.01]" style="background: var(--lp-surface); border: 1px solid var(--lp-border);">
    @if($isLive)
        <div class="absolute top-3 right-3 flex items-center gap-1.5">
            <span class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-600"></span>
            </span>
            <span class="text-xs font-bold uppercase tracking-wider text-red-500">Live</span>
        </div>
    @endif

    <div class="mb-3">
        <h3 class="text-base font-semibold" style="color: var(--lp-text);">{{ $match->name }}</h3>
        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs" style="color: var(--lp-text-muted);">
            @if($match->date)
                <span>{{ $match->date->format('d M Y') }}</span>
            @endif
            @if($match->location)
                <span>{{ $match->location }}</span>
            @endif
        </div>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium" style="background: rgba(225, 6, 0, 0.1); color: var(--lp-red);">
            {{ strtoupper($match->scoring_type ?? 'standard') }}
        </span>
        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium" style="background: var(--lp-surface-2); color: var(--lp-text-muted);">
            {{ $shooterCount }} {{ Str::plural('shooter', $shooterCount) }}
        </span>
        <flux:badge :color="$statusColor" size="sm">{{ $statusLabel }}</flux:badge>
    </div>

    @if($context !== 'marketplace')
        <div class="mt-auto">
            @if(in_array($statusValue, ['pre_registration', 'registration_open']))
                <a href="{{ app_url('/matches/' . $match->id) }}"
                   class="inline-flex w-full items-center justify-center rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors"
                   style="background: var(--lp-red);">
                    Register &rarr;
                </a>
            @elseif($statusValue === 'squadding_open')
                <a href="{{ app_url('/matches/' . $match->id . '/squadding') }}"
                   class="inline-flex w-full items-center justify-center rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors"
                   style="background: var(--lp-red);">
                    Choose Squad &rarr;
                </a>
            @elseif($isLive)
                <a href="{{ route('scoreboard', $match) }}"
                   class="inline-flex w-full items-center justify-center rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors"
                   style="background: var(--lp-red);">
                    View Live Scores &rarr;
                </a>
            @elseif($statusValue === 'completed')
                <a href="{{ route('scoreboard', $match) }}"
                   class="inline-flex w-full items-center justify-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                   style="background: var(--lp-surface-2); color: var(--lp-text-soft); border: 1px solid var(--lp-border);">
                    View Results &rarr;
                </a>
            @else
                <a href="{{ route('scoreboard', $match) }}"
                   class="inline-flex w-full items-center justify-center rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                   style="background: var(--lp-surface-2); color: var(--lp-text-soft); border: 1px solid var(--lp-border);">
                    View Details &rarr;
                </a>
            @endif
        </div>
    @endif
</div>
