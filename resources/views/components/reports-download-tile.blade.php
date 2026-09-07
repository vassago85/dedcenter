@props([
    'href',
    'title',
    'subtitle' => null,
    'icon' => 'file-text',
    'variant' => 'default', // 'default' | 'accent' | 'amber' | 'emerald' | 'zinc'
    'external' => true,
])

@php
    /*
    |--------------------------------------------------------------------------
    | <x-reports-download-tile> — shared primitive for the Reports panels.
    |--------------------------------------------------------------------------
    | Consistent visual for every CSV / PDF / HTML report link on the
    | Reports tab, so each per-type panel (standard, royal-flush, prs,
    | elr, alrha) reads as one continuous catalogue rather than a mash-up
    | of one-off styling. Variants map to accent colours used elsewhere
    | in the app (accent-red for the "primary" download, amber for the
    | scoreboard/prize-family, emerald for shooter-report family, zinc
    | for utility CSVs).
    */

    $variantClasses = match ($variant) {
        'accent'   => 'border-accent/30 bg-accent/10 text-accent',
        'amber'    => 'border-amber-400/25 bg-amber-400/10 text-amber-300',
        'emerald'  => 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300',
        'zinc'     => 'border-zinc-500/25 bg-zinc-500/10 text-zinc-300',
        default    => 'border-border bg-surface-2/40 text-muted',
    };

    $hoverBorder = match ($variant) {
        'accent' => 'hover:border-accent/60',
        'amber'  => 'hover:border-amber-400/60',
        'emerald'=> 'hover:border-emerald-500/60',
        'zinc'   => 'hover:border-zinc-500/60',
        default  => 'hover:border-accent/50',
    };
@endphp

<a
    href="{{ $href }}"
    @if($external) target="_blank" rel="noopener" @endif
    class="group flex items-start gap-3 rounded-xl border border-border bg-surface-2/40 px-3.5 py-3 transition-colors {{ $hoverBorder }} hover:bg-surface-2"
>
    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border {{ $variantClasses }}">
        <x-icon :name="$icon" class="h-4 w-4" />
    </span>
    <span class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-primary group-hover:text-primary">{{ $title }}</p>
        @if($subtitle)
            <p class="text-[11px] text-muted">{{ $subtitle }}</p>
        @endif
    </span>
</a>
