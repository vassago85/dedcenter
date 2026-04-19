{{--
    x-status-badge — token-mapped status pill.

    DeadCenter UX Standard §5 (status-driven UX) + §9 (typography). Use
    this anywhere a status needs to be surfaced (match status, registration
    status, claim status, org status). Takes a tone name that matches the
    `color()` output on our enums (MatchStatus, ShooterClaimStatus, etc.),
    so you can feed it directly:

        <x-status-badge :tone="$match->status->color()" :label="$match->status->label()" />

    Props
    ─────
    label     string   Required. Text shown.
    tone      string   Color tone. Accepts: slate | zinc | accent | red |
                       rose | green | emerald | amber | yellow | orange |
                       blue | sky | violet | indigo | purple | cyan | teal.
    size      string   'sm' | 'md'. Default 'md'.
    dot       bool     Show a leading colored dot. Default true.
    pulse     bool     Pulse the dot (for "live"/"active" states). Default false.
    icon      ?string  Optional Lucide icon name (shown before the label).
--}}
@props([
    'label',
    'tone' => 'slate',
    'size' => 'md',
    'dot' => true,
    'pulse' => false,
    'icon' => null,
])

@php
    $toneMap = [
        'slate'   => ['bg' => 'bg-slate-500/10',   'text' => 'text-slate-200',   'ring' => 'ring-slate-500/20',   'dot' => 'bg-slate-400'],
        'zinc'    => ['bg' => 'bg-zinc-500/10',    'text' => 'text-zinc-200',    'ring' => 'ring-zinc-500/20',    'dot' => 'bg-zinc-400'],
        'accent'  => ['bg' => 'bg-accent/15',      'text' => 'text-accent',      'ring' => 'ring-accent/30',      'dot' => 'bg-accent'],
        'red'     => ['bg' => 'bg-rose-500/10',    'text' => 'text-rose-200',    'ring' => 'ring-rose-500/25',    'dot' => 'bg-rose-400'],
        'rose'    => ['bg' => 'bg-rose-500/10',    'text' => 'text-rose-200',    'ring' => 'ring-rose-500/25',    'dot' => 'bg-rose-400'],
        'green'   => ['bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-200', 'ring' => 'ring-emerald-500/25', 'dot' => 'bg-emerald-400'],
        'emerald' => ['bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-200', 'ring' => 'ring-emerald-500/25', 'dot' => 'bg-emerald-400'],
        'amber'   => ['bg' => 'bg-amber-500/10',   'text' => 'text-amber-200',   'ring' => 'ring-amber-500/25',   'dot' => 'bg-amber-400'],
        'yellow'  => ['bg' => 'bg-amber-500/10',   'text' => 'text-amber-200',   'ring' => 'ring-amber-500/25',   'dot' => 'bg-amber-400'],
        'orange'  => ['bg' => 'bg-orange-500/10',  'text' => 'text-orange-200',  'ring' => 'ring-orange-500/25',  'dot' => 'bg-orange-400'],
        'blue'    => ['bg' => 'bg-sky-500/10',     'text' => 'text-sky-200',     'ring' => 'ring-sky-500/25',     'dot' => 'bg-sky-400'],
        'sky'     => ['bg' => 'bg-sky-500/10',     'text' => 'text-sky-200',     'ring' => 'ring-sky-500/25',     'dot' => 'bg-sky-400'],
        'violet'  => ['bg' => 'bg-violet-500/10',  'text' => 'text-violet-200',  'ring' => 'ring-violet-500/25',  'dot' => 'bg-violet-400'],
        'indigo'  => ['bg' => 'bg-indigo-500/10',  'text' => 'text-indigo-200',  'ring' => 'ring-indigo-500/25',  'dot' => 'bg-indigo-400'],
        'purple'  => ['bg' => 'bg-purple-500/10',  'text' => 'text-purple-200',  'ring' => 'ring-purple-500/25',  'dot' => 'bg-purple-400'],
        'cyan'    => ['bg' => 'bg-cyan-500/10',    'text' => 'text-cyan-200',    'ring' => 'ring-cyan-500/25',    'dot' => 'bg-cyan-400'],
        'teal'    => ['bg' => 'bg-teal-500/10',    'text' => 'text-teal-200',    'ring' => 'ring-teal-500/25',    'dot' => 'bg-teal-400'],
    ];

    $t = $toneMap[$tone] ?? $toneMap['slate'];

    [$pad, $text, $dotSize, $iconSize] = match ($size) {
        'sm' => ['px-2 py-0.5',   'text-[10px]', 'h-1 w-1',     'h-3 w-3'],
        default => ['px-2.5 py-1', 'text-label',  'h-1.5 w-1.5', 'h-3.5 w-3.5'],
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full font-semibold ring-1 ring-inset {$pad} {$text} {$t['bg']} {$t['text']} {$t['ring']}"]) }}>
    @if($dot)
        <span class="shrink-0 rounded-full {{ $dotSize }} {{ $t['dot'] }} {{ $pulse ? 'node-pulse' : '' }}"></span>
    @endif
    @if($icon)
        <x-icon :name="$icon" class="{{ $iconSize }} shrink-0" />
    @endif
    <span class="whitespace-nowrap">{{ $label }}</span>
</span>
