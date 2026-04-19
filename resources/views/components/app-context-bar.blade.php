{{--
    x-app-context-bar — mode/context banner under the top bar.

    DeadCenter UX Standard §2 (mode-based experience). Used to make the
    current mode or context unambiguous without the user having to think
    in routes. The colored left accent signals the mode at a glance.

    Props
    ─────
    mode           string   'shooter' | 'org' | 'platform' | 'scoring'.
                            Controls the accent color.
    modeLabel      string   e.g. 'Shooter Mode', 'Acme Range Admin Mode'.
    contextLabel   ?string  One-line supporting context (what the user is doing).
    exitUrl        ?string  Optional "escape hatch" link target.
    exitLabel      ?string  Label for the escape hatch button.
--}}
@props([
    'mode' => 'shooter',
    'modeLabel' => 'Shooter Mode',
    'contextLabel' => null,
    'exitUrl' => null,
    'exitLabel' => null,
])

@php
    $styles = match ($mode) {
        'org'      => 'border-l-4 border-l-accent bg-accent/5',
        'platform' => 'border-l-4 border-l-amber-500/70 bg-amber-500/5',
        'scoring'  => 'border-l-4 border-l-red-500/70 bg-red-500/10',
        default    => 'border-l-4 border-l-sky-500/70 bg-sky-500/5',
    };
@endphp

<div class="app-context-bar border-b border-border">
    <div class="flex items-center justify-between gap-3 px-4 py-2.5 lg:px-8 {{ $styles }}">
        <div class="min-w-0">
            <p class="text-label uppercase text-muted">{{ $modeLabel }}</p>
            @if($contextLabel)
                <p class="truncate text-body text-secondary">{{ $contextLabel }}</p>
            @endif
        </div>
        @if($exitUrl && $exitLabel)
            <a href="{{ $exitUrl }}"
               class="inline-flex min-h-[36px] shrink-0 items-center rounded-lg border border-border px-3 text-label font-semibold text-secondary transition-colors hover:bg-surface-2 hover:text-primary focus:outline-none focus:ring-2 focus:ring-accent">
                {{ $exitLabel }}
            </a>
        @endif
    </div>
</div>
