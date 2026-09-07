{{--
    ALRHA per-shooter breakdown.

    Renders one card per stage, each stage showing a row per target with
    the shot dots (hit / miss / not-taken) inline. Used from the ALRHA
    Standings and Teams tabs when the viewer expands a shooter row.

    Cold-bore targets get a "CBC" chip so it's visually obvious why those
    points don't roll into the main class total — a common source of "why
    is my Cold Bore not on my score?" questions. Distances are shown per
    target so the same shooter's 300 m and 1400 m targets read distinctly.

    Only reads keys the ELR service actually emits (name, distance_m,
    base_points, is_cold_bore, shots[] with result / points / shot_number).
    Missing keys fall through gracefully — a partially-scored shooter's
    card still renders, just with fewer dots.
--}}

@if(empty($stages))
    <p class="text-sm text-muted">No stage detail available yet for this shooter.</p>
@else
    <div class="space-y-3">
        @foreach($stages as $stage)
            @php
                $stageLabel = $stage['label'] ?? ('Stage ' . ($stage['stage_number'] ?? '?'));
                $stagePoints = (float) ($stage['stage_points'] ?? 0);
                $stageHits = (int) ($stage['stage_hits'] ?? 0);
                $stageTargets = $stage['targets'] ?? [];
            @endphp
            <div class="rounded-xl border border-border bg-app/60">
                <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-border/60 px-3 py-2 sm:px-4">
                    <span class="text-sm font-bold text-primary">{{ $stageLabel }}</span>
                    <span class="text-xs text-muted">
                        <span class="font-semibold text-emerald-300 tabular-nums">{{ number_format($stagePoints, 2) }}</span>
                        <span class="ml-1">pts</span>
                        &bull;
                        <span class="text-green-400 tabular-nums">{{ $stageHits }}</span>
                        <span class="ml-1">hits</span>
                    </span>
                </div>
                <div class="divide-y divide-border/50">
                    @forelse($stageTargets as $target)
                        @php
                            $targetName = $target['name'] ?? '—';
                            $distanceM = (int) ($target['distance_m'] ?? 0);
                            $isCbc = ! empty($target['is_cold_bore']);
                            $shots = $target['shots'] ?? [];
                            $targetHits = 0;
                            $targetPoints = 0.0;
                            foreach ($shots as $shot) {
                                if (($shot['result'] ?? null) === 'hit') {
                                    $targetHits++;
                                    $targetPoints += (float) ($shot['points'] ?? 0);
                                }
                            }
                        @endphp
                        <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-xs sm:px-4 sm:text-sm">
                            <div class="flex items-baseline gap-2">
                                <span class="font-semibold text-secondary">{{ $targetName }}</span>
                                @if($distanceM > 0)
                                    <span class="text-muted">{{ $distanceM }}m</span>
                                @endif
                                @if($isCbc)
                                    <span class="inline-flex items-center rounded-full border border-amber-400/50 bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-300" title="Cold Bore Challenge — scored on its own prize table, not the main total">CBC</span>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5">
                                @forelse($shots as $shot)
                                    @php
                                        $result = $shot['result'] ?? 'not_taken';
                                        $isHit = $result === 'hit';
                                        $isMiss = $result === 'miss';
                                        $shotNum = $shot['shot_number'] ?? '';
                                        $shotPoints = (float) ($shot['points'] ?? 0);
                                        $shotTip = "Shot {$shotNum} — " . ucfirst((string) str_replace('_', ' ', (string) $result));
                                        if ($isHit && $shotPoints > 0) {
                                            $shotTip .= " ({$shotPoints} pts)";
                                        }
                                    @endphp
                                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold leading-none
                                        {{ $isHit ? 'bg-green-500 text-white' : ($isMiss ? 'bg-red-500 text-white' : 'bg-zinc-700 text-zinc-500') }}"
                                        title="{{ $shotTip }}">
                                        {{ $isHit ? '✓' : ($isMiss ? '✗' : '–') }}
                                    </span>
                                @empty
                                    <span class="text-[10px] italic text-muted">No shots taken</span>
                                @endforelse
                                @if(! empty($shots))
                                    <span class="ml-2 whitespace-nowrap text-[10px] tabular-nums text-muted">
                                        {{ $targetHits }}/{{ count($shots) }}
                                        @if($targetPoints > 0)
                                            &middot; <span class="text-emerald-300">{{ number_format($targetPoints, 2) }} pts</span>
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-3 py-2 text-xs italic text-muted sm:px-4">No targets on this stage.</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
@endif
