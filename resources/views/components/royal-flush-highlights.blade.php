@props([
    // Required: the match whose highlights we're showing.
    'match',
    // Optional: the organization owning the match. When provided, action
    // buttons route through the /org/{organization}/... group; otherwise
    // we fall back to the /admin/matches/... group. This keeps the panel
    // reusable on both the org hub and the admin hub without caller
    // duplication.
    'organization' => null,
])

{{-- Royal Flush Highlights panel.
     Shown on the Match Hub (org + admin) once a Royal Flush match is
     completed, so MDs can celebrate the sweep at a glance instead of
     fishing for it in the Full Match Report PDF. Automatically hides
     itself for non-RF matches, for matches that aren't completed yet,
     or when the match had zero flushes AND zero perfect hands — in that
     case the match tabs / Full Match Report button already tell the
     story and a "no highlights" card would just be noise. --}}

@php
    use App\Enums\MatchStatus;

    $isRoyalFlush = (bool) ($match->royal_flush_enabled ?? false);
    $isCompleted = $match->status === MatchStatus::Completed;

    if (! $isRoyalFlush || ! $isCompleted) {
        $__showHighlights = false;
    } else {
        $highlights = app(\App\Services\RoyalFlushHighlightsService::class)->build($match);
        $__showHighlights = $highlights['has_any'];
    }

    if ($__showHighlights) {
        $flushes = $highlights['flushes_by_distance'];
        $shootersByDist = $highlights['shooters_by_distance'];
        $distanceLabels = $highlights['distance_labels'];
        $perfectHands = $highlights['perfect_hand_shooters'];
        $totalFlushes = $highlights['total_flushes'];

        // Keep distances in sort_order (service already guarantees this —
        // we just re-key on distance_meters for the loop).
        $distancesInOrder = array_keys($distanceLabels);

        if ($organization) {
            $fullReportHref = route('org.matches.export.pdf-executive-summary', [$organization, $match]);
            $sideBetHref = $match->side_bet_enabled
                ? route('org.matches.side-bet-report', [$organization, $match])
                : null;
        } else {
            $fullReportHref = route('admin.matches.export.pdf-executive-summary', $match);
            $sideBetHref = $match->side_bet_enabled
                ? route('admin.matches.side-bet-report', $match)
                : null;
        }
        $scoreboardHref = route('scoreboard', $match);
    }
@endphp

@if($__showHighlights)
    <div class="rounded-xl border border-amber-600/40 bg-gradient-to-br from-amber-900/15 via-surface to-surface overflow-hidden">
        {{-- Header with headline stat + quick actions ---------------- --}}
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-amber-600/30 bg-amber-900/10 px-5 py-4">
            <div class="min-w-0">
                <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-amber-400">
                    <x-icon name="trophy" class="h-3.5 w-3.5" />
                    Royal Flush Highlights
                </div>
                <h2 class="mt-1 text-lg font-bold text-primary">
                    {{ $totalFlushes }} {{ $totalFlushes === 1 ? 'Royal Flush' : 'Royal Flushes' }}
                    @if(count($perfectHands) > 0)
                        <span class="text-amber-400">·</span>
                        <span class="text-amber-300">{{ count($perfectHands) }} Perfect Hand{{ count($perfectHands) === 1 ? '' : 's' }}</span>
                    @endif
                </h2>
                <p class="mt-0.5 text-xs text-muted">
                    A Royal Flush = every gong hit at one distance.
                    @if(count($perfectHands) > 0)
                        A Perfect Hand = every gong at <em>every</em> distance.
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <a href="{{ $fullReportHref }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-600/40 bg-emerald-900/20 px-3 py-1.5 text-xs font-semibold text-emerald-200 hover:border-emerald-500 hover:bg-emerald-900/30 transition-colors">
                    <x-icon name="document-check" class="h-3.5 w-3.5" />
                    Full Match Report
                </a>
                @if($sideBetHref)
                    <a href="{{ $sideBetHref }}"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-amber-600/40 bg-amber-900/20 px-3 py-1.5 text-xs font-semibold text-amber-200 hover:border-amber-500 hover:bg-amber-900/30 transition-colors">
                        <x-icon name="trophy" class="h-3.5 w-3.5" />
                        Side Bet Report
                    </a>
                @endif
                <a href="{{ $scoreboardHref }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-2/40 px-3 py-1.5 text-xs font-semibold text-secondary hover:border-accent hover:text-primary transition-colors">
                    <x-icon name="chart-bar" class="h-3.5 w-3.5" />
                    Scoreboard
                </a>
            </div>
        </div>

        {{-- Perfect Hand banner (rare — pop it above the per-distance
             grid so the bigger achievement gets the bigger visual). --}}
        @if(count($perfectHands) > 0)
            <div class="border-b border-amber-600/20 bg-gradient-to-r from-amber-900/25 to-transparent px-5 py-3">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 text-amber-300">
                        <x-icon name="crown" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-amber-400">
                            Perfect Hand{{ count($perfectHands) === 1 ? '' : 's' }}
                        </div>
                        <div class="mt-0.5 text-sm font-bold text-primary">
                            @foreach($perfectHands as $i => $name)
                                <span>{{ $name }}</span>@if($i < count($perfectHands) - 1)<span class="text-amber-400"> · </span>@endif
                            @endforeach
                        </div>
                        <div class="text-xs text-amber-200/70">
                            Flushed every distance &mdash; zero misses on the day.
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Per-distance flush grid.
             Zero-count distances are rendered dimmed rather than hidden
             so MDs get a complete picture of where the steel held and
             where it didn't. --}}
        <div class="p-4">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach($distancesInOrder as $distM)
                    @php
                        $count = (int) ($flushes[$distM] ?? 0);
                        $names = $shootersByDist[$distM] ?? [];
                        $label = $distanceLabels[$distM];
                        $isZero = $count === 0;
                    @endphp
                    <div class="rounded-lg border px-3 py-3
                        {{ $isZero
                            ? 'border-border bg-surface-2/20'
                            : 'border-amber-600/30 bg-surface-2/50' }}">
                        <div class="flex items-baseline justify-between gap-2">
                            <div class="text-sm font-bold text-primary">{{ $label }}</div>
                            <div class="text-[10px] font-semibold uppercase tracking-wider
                                {{ $isZero ? 'text-muted' : 'text-amber-400' }}">
                                {{ $count === 1 ? 'Flush' : 'Flushes' }}
                            </div>
                        </div>
                        <div class="mt-1 text-3xl font-extrabold tabular-nums leading-none
                            {{ $isZero ? 'text-muted/60' : 'text-amber-400' }}">
                            {{ $count }}
                        </div>
                        @if(count($names) > 0)
                            <div class="mt-2.5 border-t border-border/40 pt-2 text-xs leading-snug text-secondary">
                                @foreach($names as $i => $n)
                                    <span class="font-semibold text-primary">{{ $n }}</span>@if($i < count($names) - 1)<span class="text-muted">, </span>@endif
                                @endforeach
                            </div>
                        @else
                            <div class="mt-2.5 border-t border-border/40 pt-2 text-xs italic text-muted/70">
                                No sweeps &mdash; the steel held.
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
