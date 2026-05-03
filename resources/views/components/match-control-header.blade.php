@props([
    'match',
    'organization' => null,
])

@php
    use App\Enums\MatchStatus;

    /*
    |--------------------------------------------------------------------------
    | Match Control Center — page header.
    |--------------------------------------------------------------------------
    | The single source of identity for any match-admin page. Everything
    | the MD needs to orient themselves on a fresh page load:
    |
    |   - Match name (h1)
    |   - Organization · Date · Scoring type chip
    |   - Current lifecycle status badge
    |   - PRIMARY action button — chosen based on lifecycle stage so the
    |     MD always has a "do the next obvious thing" CTA without having
    |     to hunt for the right button buried in a tab.
    |   - SECONDARY quick links — Scoreboard + Open Scoring App. Always
    |     visible, low-emphasis, so deep-linking is one click from any
    |     page in the control center without becoming the visual focus.
    |
    | The primary action mapping mirrors the user's lifecycle spec:
    |
    |   Draft               → Continue Setup
    |   PreRegistration     → Open Registration       (status transition)
    |   RegistrationOpen    → Close Registration      (status transition)
    |   RegistrationClosed  → Open Squadding          (status transition)
    |   SquaddingOpen       → Close Squadding         (status transition)
    |   SquaddingClosed     → Mark Ready              (status transition)
    |   Ready               → Start Match             (status transition)
    |   Active              → Open Scoring App        (links to /score/{id})
    |   Completed           → Open Reports            (tab navigation)
    |
    | Status transitions use the same `wire:click="transitionStatus(...)"
    | + wire:confirm` plumbing the lifecycle stepper does — backed by the
    | `HandlesMatchLifecycleTransitions` trait the parent Volt page mixes
    | in. So "click the big primary button" and "click the next stage on
    | the stepper" go through identical code paths.
    */
    $isAdminContext = $organization === null;

    $scoreboardUrl = route('scoreboard', $match);
    $scoringAppUrl = url('/score/' . $match->id);
    $reportsUrl = $isAdminContext
        ? route('admin.matches.reports', $match)
        : route('org.matches.reports', [$organization, $match]);
    $setupUrl = $isAdminContext
        ? route('admin.matches.edit', $match)
        : route('org.matches.edit', [$organization, $match]);
    $squaddingUrl = $isAdminContext
        ? route('admin.matches.squadding', $match)
        : route('org.matches.squadding', [$organization, $match]);

    // Status badge palette — matches the enum's `color()` token to a
    // concrete Tailwind ring + text + bg trio. Keeps the badge readable
    // on the dark theme and consistent with the lifecycle stepper colours
    // (Completed = zinc/muted, Active = green, Ready = emerald, etc.).
    $badgePalette = [
        'slate'   => 'border-zinc-500/30 bg-zinc-500/10 text-zinc-300',
        'violet'  => 'border-violet-500/30 bg-violet-500/10 text-violet-300',
        'sky'     => 'border-sky-500/30 bg-sky-500/10 text-sky-300',
        'amber'   => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
        'indigo'  => 'border-indigo-500/30 bg-indigo-500/10 text-indigo-300',
        'cyan'    => 'border-cyan-500/30 bg-cyan-500/10 text-cyan-300',
        'emerald' => 'border-emerald-500/40 bg-emerald-500/15 text-emerald-300',
        'green'   => 'border-green-500/45 bg-green-500/18 text-green-200',
        'zinc'    => 'border-white/15 bg-white/5 text-zinc-400',
    ];
    $badgeCls = $badgePalette[$match->status->color()] ?? $badgePalette['slate'];

    // Primary action descriptor. `kind` is either 'transition' (fires
    // `transitionStatus(...)` with confirmation) or 'link' (a plain
    // navigation). `target` is the MatchStatus enum value or a URL.
    $primary = match ($match->status) {
        MatchStatus::Draft               => ['kind' => 'link',       'target' => $setupUrl,                                    'label' => 'Continue Setup',     'icon' => 'settings',     'tone' => 'accent'],
        MatchStatus::PreRegistration     => ['kind' => 'transition', 'target' => MatchStatus::RegistrationOpen,                'label' => 'Open Registration',  'icon' => 'lock-open',    'tone' => 'accent'],
        MatchStatus::RegistrationOpen    => ['kind' => 'transition', 'target' => MatchStatus::RegistrationClosed,              'label' => 'Close Registration', 'icon' => 'lock',         'tone' => 'amber'],
        MatchStatus::RegistrationClosed  => ['kind' => 'transition', 'target' => MatchStatus::SquaddingOpen,                   'label' => 'Open Squadding',     'icon' => 'users',        'tone' => 'accent'],
        MatchStatus::SquaddingOpen       => ['kind' => 'transition', 'target' => MatchStatus::SquaddingClosed,                 'label' => 'Close Squadding',    'icon' => 'users',        'tone' => 'amber'],
        MatchStatus::SquaddingClosed     => ['kind' => 'transition', 'target' => MatchStatus::Ready,                           'label' => 'Mark Ready',         'icon' => 'circle-check', 'tone' => 'emerald'],
        MatchStatus::Ready               => ['kind' => 'transition', 'target' => MatchStatus::Active,                          'label' => 'Start Match',        'icon' => 'play',         'tone' => 'green'],
        MatchStatus::Active              => ['kind' => 'link',       'target' => $scoringAppUrl,                               'label' => 'Open Scoring App',   'icon' => 'target',       'tone' => 'green',  'newTab' => true],
        MatchStatus::Completed           => ['kind' => 'link',       'target' => $reportsUrl,                                  'label' => 'Open Reports',       'icon' => 'file-text',    'tone' => 'accent'],
    };

    // Tone → button palette. The lifecycle uses red (`accent`) for the
    // canonical "next step" CTAs, amber for "be careful, this closes
    // something", emerald/green for go-live moments. Same palette as the
    // status badges so primary CTA and current state read together.
    $primaryToneCls = match ($primary['tone']) {
        'accent'  => 'bg-accent text-white hover:bg-accent-hover focus-visible:ring-accent/60',
        'amber'   => 'bg-amber-500 text-zinc-950 hover:bg-amber-400 focus-visible:ring-amber-400/60',
        'emerald' => 'bg-emerald-500 text-zinc-950 hover:bg-emerald-400 focus-visible:ring-emerald-400/60',
        'green'   => 'bg-green-500 text-zinc-950 hover:bg-green-400 focus-visible:ring-green-400/60',
        default   => 'bg-accent text-white hover:bg-accent-hover focus-visible:ring-accent/60',
    };

    $orgName = $match->organization?->name;
    $matchDate = $match->date?->format('D, j M Y');
    $scoringType = strtoupper($match->scoring_type ?? '');
@endphp

<header
    {{ $attributes->merge(['class' => 'rounded-2xl border border-border bg-surface p-4 sm:p-6']) }}
>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
        {{-- Identity column: name, status, meta strip --}}
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider {{ $badgeCls }}">
                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                    {{ $match->status->label() }}
                </span>
                @if($scoringType)
                    <span class="inline-flex items-center rounded-full border border-border bg-surface-2 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-muted">
                        {{ $scoringType }}
                    </span>
                @endif
            </div>

            <h1 class="mt-2 text-xl font-bold leading-tight text-primary sm:text-2xl">
                {{ $match->name }}
            </h1>

            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                @if($orgName)
                    <span class="inline-flex items-center gap-1.5">
                        <x-icon name="building-2" class="h-3.5 w-3.5" />
                        {{ $orgName }}
                    </span>
                @endif
                @if($matchDate)
                    <span class="inline-flex items-center gap-1.5">
                        <x-icon name="calendar" class="h-3.5 w-3.5" />
                        {{ $matchDate }}
                    </span>
                @endif
                @if($match->location)
                    <span class="inline-flex items-center gap-1.5 truncate">
                        <x-icon name="map-pin" class="h-3.5 w-3.5" />
                        {{ $match->location }}
                    </span>
                @endif
            </p>
        </div>

        {{-- Action column: primary CTA + secondary quick links --}}
        <div class="flex flex-col items-stretch gap-2 sm:items-end">
            @if($primary['kind'] === 'transition')
                @php
                    /** @var MatchStatus $targetStatus */
                    $targetStatus = $primary['target'];
                    $warning = $targetStatus->transitionWarning($match->status);
                    $confirm = $warning
                        ? "{$primary['label']}? " . trim(str_replace(["\r\n", "\n"], ' ', $warning))
                        : "{$primary['label']}?";
                @endphp
                <button
                    type="button"
                    wire:click="transitionStatus('{{ $targetStatus->value }}')"
                    wire:confirm="{{ $confirm }}"
                    class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-lg px-5 text-sm font-bold shadow-sm transition-colors focus:outline-none focus-visible:ring-2 {{ $primaryToneCls }}"
                >
                    <x-icon name="{{ $primary['icon'] }}" class="h-4 w-4" />
                    {{ $primary['label'] }}
                </button>
            @else
                <a
                    href="{{ $primary['target'] }}"
                    @if(! empty($primary['newTab'])) target="_blank" rel="noopener" @endif
                    @if(empty($primary['newTab'])) wire:navigate @endif
                    class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-lg px-5 text-sm font-bold shadow-sm transition-colors focus:outline-none focus-visible:ring-2 {{ $primaryToneCls }}"
                >
                    <x-icon name="{{ $primary['icon'] }}" class="h-4 w-4" />
                    {{ $primary['label'] }}
                    @if(! empty($primary['newTab']))
                        <x-icon name="external-link" class="h-3.5 w-3.5 opacity-70" />
                    @endif
                </a>
            @endif

            {{-- Secondary quick links — small icon buttons, single row, --}}
            {{-- always present so the MD can jump to scoreboard / scoring --}}
            {{-- app from any page without hunting through tabs. The tabs --}}
            {{-- below carry the long-form navigation; this is the "I just --}}
            {{-- need the scoreboard URL" affordance. --}}
            <div class="flex items-center gap-1.5">
                <a
                    href="{{ $scoreboardUrl }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex min-h-[36px] items-center gap-1.5 rounded-lg border border-border bg-surface-2 px-3 text-xs font-semibold text-secondary transition-colors hover:border-accent/50 hover:text-primary"
                    title="Open the public scoreboard"
                >
                    <x-icon name="trophy" class="h-3.5 w-3.5" />
                    Scoreboard
                </a>
                @if($primary['kind'] !== 'link' || $primary['target'] !== $scoringAppUrl)
                    <a
                        href="{{ $scoringAppUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex min-h-[36px] items-center gap-1.5 rounded-lg border border-border bg-surface-2 px-3 text-xs font-semibold text-secondary transition-colors hover:border-accent/50 hover:text-primary"
                        title="Open the scoring PWA in a new tab"
                    >
                        <x-icon name="target" class="h-3.5 w-3.5" />
                        Scoring App
                    </a>
                @endif
            </div>
        </div>
    </div>
</header>
