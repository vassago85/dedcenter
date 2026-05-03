@props([
    'match',
    // When true, the stepper is rendered for read-only contexts (admins
    // viewing without transition rights, or stage-locked surfaces) — no
    // wire:click bindings, just visual progress. Defaults to false.
    'readonly' => false,
])

@php
    use App\Enums\MatchStatus;

    $current = $match->status;
    $steps = MatchStatus::cases();

    /*
    |--------------------------------------------------------------------------
    | Match Progress — the lifecycle stepper.
    |--------------------------------------------------------------------------
    | Sits at the top of every Match Control Center page so the MD never
    | has to navigate to Setup to find the lifecycle controls. Renders as
    | a horizontal connected stepper on desktop (all 9 stages visible at
    | once with a connecting rail behind them) and as a horizontally-
    | scrollable strip on mobile (no awkward vertical stack — keeps the
    | "where am I in the match" answer above the fold on phones).
    |
    | Each step button:
    |   - PAST     → muted with a check, clickable backwards if the
    |                graph allows (rare — the warning copy makes it
    |                obvious that backward jumps are an "undo" action).
    |   - CURRENT  → bright accent ring + filled dot, no click (you're
    |                already here).
    |   - NEXT-ALLOWED → solid border, hover-lift, click fires the
    |                transition with `wire:confirm` carrying the warning
    |                copy from `MatchStatus::transitionWarning`.
    |   - DISTANT  → dimmed, disabled. The graph forbids the leap and
    |                the visual matches the rule.
    |
    | The component requires the parent Volt page to use the trait
    | `App\Concerns\HandlesMatchLifecycleTransitions` so the wire:click
    | calls land on a `transitionStatus(string $target)` method. If the
    | parent doesn't have it, the buttons are inert (the trait isn't
    | enforced at the component level — that would couple a Blade
    | component to a PHP class — but every Match Control Center page
    | uses it).
    */
@endphp

<section
    {{ $attributes->merge([
        'class' => 'rounded-2xl border border-border bg-surface px-4 py-4 sm:px-5 sm:py-5',
        'aria-label' => 'Match progress',
    ]) }}
>
    <div class="mb-3 flex items-baseline justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-[11px] font-bold uppercase tracking-[0.18em] text-accent">Match Progress</h2>
            <p class="mt-0.5 text-xs text-muted truncate">
                {{ $current->shortDescription() }}
            </p>
        </div>
        <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wider text-muted tabular-nums">
            {{ $current->ordinal() + 1 }} / {{ count($steps) }}
        </span>
    </div>

    {{-- Horizontal scrollable strip — same layout on mobile and desktop --}}
    {{-- so the stepper always reads as one connected timeline. Mobile gets --}}
    {{-- the horizontal scroll for free; desktop fits all 9 steps within --}}
    {{-- the 1100-1200px content width without overflow. --}}
    <div class="relative overflow-x-auto pb-2 -mb-2">
        <ol class="relative flex min-w-max items-center gap-0">
            @foreach($steps as $idx => $step)
                @php
                    $isCurrent = $step === $current;
                    $isPast    = $step->ordinal() < $current->ordinal();
                    $isFuture  = $step->ordinal() > $current->ordinal();
                    $allowed   = $current->canTransitionTo($step) && ! $readonly;
                    $isLast    = $loop->last;

                    // Per-state visual treatment. Kept inline (not a match()
                    // returning a class soup) so the relationship between
                    // each state and its colour is obvious at a glance.
                    if ($isCurrent) {
                        $dotCls = 'border-accent bg-accent text-white shadow-[0_0_18px_rgba(225,6,0,0.35)]';
                        $labelCls = 'text-primary font-semibold';
                    } elseif ($isPast) {
                        $dotCls = 'border-emerald-500/40 bg-emerald-500/15 text-emerald-300';
                        $labelCls = 'text-secondary';
                    } elseif ($allowed) {
                        $dotCls = 'border-border bg-surface text-muted hover:border-accent/60 hover:text-primary';
                        $labelCls = 'text-muted group-hover:text-primary';
                    } else {
                        $dotCls = 'border-border/40 bg-surface text-muted/50';
                        $labelCls = 'text-muted/50';
                    }

                    // Connector colour: solid emerald for completed segments,
                    // muted for upcoming. The rail lives between adjacent
                    // dots and reads as continuous progress.
                    $connectorCls = $step->ordinal() < $current->ordinal()
                        ? 'bg-emerald-500/40'
                        : 'bg-border/40';

                    $warning = $allowed ? $step->transitionWarning($current) : null;
                    // wire:confirm requires single-line text. Strip newlines
                    // from the warning copy and drop the trailing punctuation
                    // so the confirmation reads naturally as a question.
                    $confirm = $warning
                        ? "Transition to {$step->label()}? " . trim(str_replace(["\r\n", "\n"], ' ', $warning))
                        : null;
                @endphp

                <li class="flex items-center">
                    {{-- Step button. <button> when interactive, <span> when --}}
                    {{-- inert — keeps the click target obvious to keyboard --}}
                    {{-- and screenreader users without faking interactivity --}}
                    {{-- on disabled steps. --}}
                    @if($allowed)
                        <button
                            type="button"
                            wire:click="transitionStatus('{{ $step->value }}')"
                            wire:confirm="{{ $confirm }}"
                            class="group flex flex-col items-center gap-1.5 px-2.5 transition-transform duration-150 hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/60 rounded-lg"
                            title="{{ $warning }}"
                        >
                            <span class="grid h-8 w-8 place-items-center rounded-full border-2 transition-colors {{ $dotCls }}">
                                @if($isPast)
                                    <x-icon name="check" class="h-4 w-4" />
                                @else
                                    <span class="text-[11px] font-bold tabular-nums">{{ $idx + 1 }}</span>
                                @endif
                            </span>
                            <span class="whitespace-nowrap text-[10px] font-semibold uppercase tracking-wider {{ $labelCls }}">
                                {{ $step->label() }}
                            </span>
                        </button>
                    @else
                        <span
                            class="flex flex-col items-center gap-1.5 px-2.5 {{ $isCurrent ? '' : 'cursor-not-allowed' }}"
                            @if($isCurrent) aria-current="step" @endif
                            title="{{ $isCurrent ? $current->shortDescription() : ($isPast ? 'Already completed' : 'Not yet reachable from the current state') }}"
                        >
                            <span class="grid h-8 w-8 place-items-center rounded-full border-2 {{ $dotCls }}">
                                @if($isPast)
                                    <x-icon name="check" class="h-4 w-4" />
                                @else
                                    <span class="text-[11px] font-bold tabular-nums">{{ $idx + 1 }}</span>
                                @endif
                            </span>
                            <span class="whitespace-nowrap text-[10px] font-semibold uppercase tracking-wider {{ $labelCls }}">
                                {{ $step->label() }}
                            </span>
                        </span>
                    @endif

                    @if(! $isLast)
                        {{-- Connector rail between adjacent dots. Sits at --}}
                        {{-- dot-centre height (h-8 w-8 → centre at top: --}}
                        {{-- 1rem). The mt offset keeps it visually aligned --}}
                        {{-- with the centre of the dot row regardless of --}}
                        {{-- whether the labels below wrap. --}}
                        <span class="h-0.5 w-6 sm:w-8 self-start mt-[1.05rem] {{ $connectorCls }}"></span>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>
</section>
