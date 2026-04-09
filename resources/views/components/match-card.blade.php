@props(['match', 'context' => 'default'])

@php
    $statusEnum = $match->status;
    $statusValue = $statusEnum instanceof \BackedEnum ? $statusEnum->value : $statusEnum;
    $statusLabel = method_exists($statusEnum, 'label') ? $statusEnum->label() : ucfirst(str_replace('_', ' ', $statusValue));
    $statusColor = method_exists($statusEnum, 'color') ? $statusEnum->color() : 'zinc';
    $isLive = $statusValue === 'active';
    $shooterCount = $match->shooters_count ?? $match->shooters()->count();
    $cardImage = $match->card_image_url;
    $hasImage = ! empty($cardImage);

    $scoringColors = [
        'prs' => 'from-amber-900/40 to-surface',
        'elr' => 'from-violet-900/40 to-surface',
        'standard' => 'from-red-900/30 to-surface',
    ];
    $fallbackGradient = $scoringColors[$match->scoring_type ?? 'standard'] ?? $scoringColors['standard'];

    $statusBadgeLabel = match ($statusValue) {
        'registration_open', 'pre_registration' => 'Registration Open',
        'registration_closed' => 'Closed',
        'completed' => 'Results Available',
        default => null,
    };
    $statusBadgeClasses = match ($statusValue) {
        'registration_open', 'pre_registration' => 'bg-green-600/90 text-white',
        'registration_closed' => 'bg-zinc-600/90 text-zinc-200',
        'completed' => 'bg-sky-600/90 text-white',
        default => '',
    };
@endphp

<div class="relative flex flex-col overflow-hidden rounded-2xl border border-border bg-surface shadow-md transition-all duration-200 hover:scale-[1.01] hover:shadow-lg hover:border-border/80">
    {{-- Image / Header Section (16:9) --}}
    <div class="relative aspect-video overflow-hidden">
        @if($hasImage)
            <img src="{{ $cardImage }}" alt="{{ $match->name }}" class="absolute inset-0 h-full w-full object-cover" loading="lazy" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-black/10"></div>
        @else
            <div class="absolute inset-0 bg-gradient-to-br {{ $fallbackGradient }}"></div>
        @endif

        {{-- Status Badge (top-right) --}}
        <div class="absolute top-3 right-3 flex items-center gap-1.5">
            @if($isLive)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-red-600/90 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white backdrop-blur-sm">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-white"></span>
                    </span>
                    Live
                </span>
            @elseif($statusBadgeLabel)
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider backdrop-blur-sm {{ $statusBadgeClasses }}">
                    {{ $statusBadgeLabel }}
                </span>
            @endif
        </div>

        {{-- Title Overlay (bottom) --}}
        <div class="absolute inset-x-0 bottom-0 p-4">
            <h3 class="text-base font-bold {{ $hasImage ? 'text-white' : 'text-primary' }} leading-tight">{{ $match->name }}</h3>
            <div class="mt-1 flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-xs {{ $hasImage ? 'text-white/70' : 'text-muted' }}">
                @if($match->date)
                    <span>{{ $match->date->format('d M Y') }}</span>
                @endif
                @if($match->organization)
                    <span>&bull; {{ $match->organization->name }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Bottom Section --}}
    <div class="flex flex-1 flex-col p-4">
        {{-- Meta chips --}}
        <div class="mb-3 flex flex-wrap items-center gap-2">
            @if($match->location)
                <span class="inline-flex items-center gap-1 text-xs text-muted">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                    {{ $match->location }}
                </span>
            @endif
            <span class="inline-flex items-center rounded-full bg-surface-2 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted">
                {{ ($match->scoring_type ?? 'standard') === 'standard' ? 'RELAY' : strtoupper($match->scoring_type) }}
            </span>
            <span class="text-[10px] text-muted">{{ $shooterCount }} {{ Str::plural('shooter', $shooterCount) }}</span>
        </div>

        {{-- CTA --}}
        @if($context !== 'marketplace')
            <div class="mt-auto">
                @if(in_array($statusValue, ['pre_registration', 'registration_open']))
                    <a href="{{ app_url('/matches/' . $match->id) }}"
                       class="inline-flex w-full items-center justify-center rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-accent-hover">
                        Register &rarr;
                    </a>
                @elseif($statusValue === 'squadding_open')
                    <a href="{{ app_url('/matches/' . $match->id . '/squadding') }}"
                       class="inline-flex w-full items-center justify-center rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-accent-hover">
                        Choose Squad &rarr;
                    </a>
                @elseif($isLive)
                    <a href="{{ route('scoreboard', $match) }}"
                       class="inline-flex w-full items-center justify-center rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-accent-hover">
                        View Live Scores &rarr;
                    </a>
                @elseif($statusValue === 'completed')
                    <a href="{{ route('scoreboard', $match) }}"
                       class="inline-flex w-full items-center justify-center rounded-lg border border-border bg-surface-2 px-4 py-2 text-sm font-medium text-secondary transition-colors hover:bg-surface-2/80 hover:text-primary">
                        View Results &rarr;
                    </a>
                @else
                    <a href="{{ route('scoreboard', $match) }}"
                       class="inline-flex w-full items-center justify-center rounded-lg border border-border bg-surface-2 px-4 py-2 text-sm font-medium text-secondary transition-colors hover:bg-surface-2/80 hover:text-primary">
                        View Details &rarr;
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
