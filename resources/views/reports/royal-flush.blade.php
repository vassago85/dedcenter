@php
    /**
     * Royal Flush — Match Results Report.
     *
     * Uses the DeadCenter landing-page design system (deep navy, glass surfaces,
     * restrained red accents). Every shot is shown as hit / miss, grouped into
     * per-distance sub-sections styled after the scoring-app end-of-stage summary.
     *
     * Data comes from MatchExportController::buildExecutiveSummaryData() which
     * merges in $base so these keys are all present:
     *
     * @var \App\Models\ShootingMatch $match
     * @var array $distanceTables   per-distance rows with cells
     * @var \Illuminate\Support\Collection $standings
     * @var array $distanceStats
     * @var array $statCards
     */

    $orgName    = $match->organization?->name;
    $matchDate  = $match->date?->format('j F Y');
    $location   = $match->location;

    $fmt        = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $mult       = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') . '×';

    $caliberFromName = function ($name) {
        foreach ([' — ', ' – ', ' - '] as $sep) {
            if (str_contains($name, $sep)) {
                return trim(explode($sep, $name, 2)[1] ?? '') ?: null;
            }
        }
        return null;
    };

    $displayName = function ($name) {
        foreach ([' — ', ' – ', ' - '] as $sep) {
            if (str_contains($name, $sep)) {
                return trim(explode($sep, $name, 2)[0]);
            }
        }
        return $name;
    };

    // Final-results rollup: every shooter, sum of distance subtotals per them.
    // Built from $standings (which is already rank-ordered by total_score).
    $finalResults = [];
    foreach ($standings as $s) {
        $perDistance = [];
        foreach ($distanceTables as $dt) {
            $row = collect($dt['rows'])->firstWhere('name', $s->name);
            $perDistance[] = [
                'label'    => $dt['label'] ?? ($dt['distance_meters'] . 'm'),
                'subtotal' => $row['subtotal'] ?? 0,
                'hits'     => $row['hits']     ?? 0,
                'misses'   => $row['misses']   ?? 0,
            ];
        }
        $finalResults[] = [
            'rank'         => $s->rank,
            'name'         => $displayName($s->name),
            'caliber'      => $caliberFromName($s->name),
            'status'       => $s->status,
            'total'        => $s->total_score,
            'hits'         => $s->hits,
            'misses'       => $s->misses,
            'per_distance' => $perDistance,
        ];
    }

    $seasonId = $match->season_id ?? null;
@endphp
<!DOCTYPE html>
<html lang="en" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $match->name }} — Royal Flush Results</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,900" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* ── Report-local overrides (stays fully inside the lp-* palette) ── */
        .rf-shell {
            background:
                radial-gradient(ellipse 70% 50% at 50% 0%, rgba(225, 6, 0, 0.05), transparent 60%),
                linear-gradient(180deg, var(--lp-bg) 0%, var(--lp-bg-2) 100%);
            min-height: 100vh;
            color: var(--lp-text);
        }
        .glass {
            background: var(--lp-surface);
            border: 1px solid var(--lp-border);
            backdrop-filter: blur(6px);
        }
        .glass-2 {
            background: var(--lp-surface-2);
            border: 1px solid var(--lp-border);
        }
        .eyebrow {
            color: var(--lp-red);
            font-size: 11px;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            font-weight: 700;
        }
        .tick {
            display: inline-block;
            width: 6px;
            height: 10px;
            border-right: 2px solid currentColor;
            border-bottom: 2px solid currentColor;
            transform: rotate(45deg);
            margin-top: -2px;
        }
        .cross {
            display: inline-block;
            width: 10px;
            height: 10px;
            background:
                linear-gradient(45deg, transparent 42%, currentColor 42%, currentColor 58%, transparent 58%),
                linear-gradient(-45deg, transparent 42%, currentColor 42%, currentColor 58%, transparent 58%);
        }
        .dash {
            display: inline-block;
            width: 8px;
            height: 2px;
            background: currentColor;
            opacity: 0.4;
        }

        /* Gong cell — compact, premium */
        .gong-cell {
            height: 28px;
            min-width: 26px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            font-size: 10px;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            line-height: 1;
        }
        .gong-hit {
            background: rgba(34, 197, 94, 0.18);
            color: #86efac;
            border: 1px solid rgba(34, 197, 94, 0.32);
        }
        .gong-miss {
            background: rgba(225, 6, 0, 0.16);
            color: #fca5a5;
            border: 1px solid rgba(225, 6, 0, 0.35);
        }
        .gong-none {
            background: rgba(255, 255, 255, 0.03);
            color: var(--lp-text-muted);
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        /* Rank medal (top 3) */
        .medal {
            font-weight: 900;
            letter-spacing: -0.02em;
        }
        .medal-1 { color: #fcd34d; }
        .medal-2 { color: #cbd5e1; }
        .medal-3 { color: #fdba74; }

        /* Table zebra feel for per-distance tables */
        .rf-table-row:nth-child(even) { background: rgba(255, 255, 255, 0.015); }
        .rf-table-row:hover { background: rgba(255, 255, 255, 0.03); }

        /* Number styling */
        .num { font-variant-numeric: tabular-nums; letter-spacing: -0.01em; }

        /* Action bar — hidden on print */
        @media print {
            .no-print { display: none !important; }
            .rf-shell {
                background: #ffffff !important;
                color: #0b1220 !important;
            }
            .glass, .glass-2 {
                background: #ffffff !important;
                border-color: #e8edf4 !important;
                backdrop-filter: none !important;
            }
            .gong-hit  { background: #15803d !important; color: #fff !important; border-color: #15803d !important; }
            .gong-miss { background: #fce8e8 !important; color: #b91c1c !important; border-color: #f3c4c4 !important; }
            .gong-none { background: #f5f7fa !important; color: #94a3b8 !important; border-color: #e8edf4 !important; }
            .medal-1 { color: #b45309 !important; }
            .medal-2 { color: #475569 !important; }
            .medal-3 { color: #9a3412 !important; }
            body  { background: #fff !important; }
            .text-white\/90 { color: #0b1220 !important; }
            a { color: #0b1220 !important; text-decoration: none !important; }
            @page { size: A4 portrait; margin: 10mm; }
        }
    </style>
</head>
<body class="rf-shell antialiased">

{{-- ════════════ Action bar (screen only) ════════════ --}}
<div class="no-print sticky top-0 z-50 border-b" style="background: rgba(7, 19, 39, 0.85); border-color: var(--lp-border); backdrop-filter: blur(20px) saturate(1.4);">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-3">
        <a href="{{ route('scoreboard', $match) }}" class="inline-flex items-center gap-2 text-sm font-medium" style="color: var(--lp-text-muted);">
            <span>←</span> Back to scoreboard
        </a>
        <div class="flex items-center gap-2">
            @if($seasonId)
                <a href="{{ route('leaderboard', $match->organization?->slug ?? '') }}"
                   class="lp-btn-secondary inline-flex items-center gap-2 rounded-lg px-4 py-2 text-[13px] font-semibold">
                    View Season Standings
                </a>
            @endif
            <button type="button" onclick="window.print()"
                    class="lp-btn-primary inline-flex items-center gap-2 rounded-lg px-4 py-2 text-[13px] font-semibold"
                    style="box-shadow: 0 1px 2px rgba(0,0,0,0.3), 0 8px 24px rgba(225, 6, 0, 0.25);">
                Save as PDF
            </button>
        </div>
    </div>
</div>

{{-- ════════════ Hero / letterhead ════════════ --}}
<section class="relative isolate overflow-hidden">
    <div class="pointer-events-none absolute inset-0">
        <div class="absolute inset-0" style="background: radial-gradient(ellipse 60% 40% at 50% 0%, rgba(225, 6, 0, 0.08), transparent 70%);"></div>
    </div>

    <div class="relative mx-auto max-w-6xl px-6 pt-12 pb-10">
        <div class="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-center">
            {{-- DeadCenter mark --}}
            <div class="flex items-center gap-3">
                <svg class="h-9 w-9 shrink-0" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="2" fill="var(--lp-red)"/>
                    <line x1="12" y1="4"  x2="12" y2="8"  stroke="var(--lp-red)" stroke-width="2" stroke-linecap="round"/>
                    <line x1="12" y1="16" x2="12" y2="20" stroke="var(--lp-red)" stroke-width="2" stroke-linecap="round"/>
                    <line x1="4"  y1="12" x2="8"  y2="12" stroke="var(--lp-red)" stroke-width="2" stroke-linecap="round"/>
                    <line x1="16" y1="12" x2="20" y2="12" stroke="var(--lp-red)" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <div>
                    <div class="text-lg font-black tracking-tight"><span class="text-white">DEAD</span><span style="color: var(--lp-red);">CENTER</span></div>
                    <div class="mt-0.5 text-[10px] font-semibold uppercase tracking-[0.28em]" style="color: var(--lp-text-muted);">Precision Gong</div>
                </div>
            </div>

            {{-- Royal Flush mark --}}
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <div class="text-[10px] font-semibold uppercase tracking-[0.28em]" style="color: var(--lp-text-muted);">Royal Flush</div>
                    <div class="mt-1 text-lg font-bold tracking-wide text-white">Gong Shoot</div>
                </div>
                <svg class="h-10 w-10 shrink-0" viewBox="0 0 32 32" fill="none">
                    <circle cx="16" cy="16" r="15" stroke="#e2e8f0" stroke-width="1" fill="none" opacity="0.6"/>
                    <circle cx="16" cy="16" r="11" stroke="#e2e8f0" stroke-width="0.8" fill="none" opacity="0.5"/>
                    <circle cx="16" cy="16" r="7"  stroke="#e2e8f0" stroke-width="0.8" fill="none" opacity="0.4"/>
                    <path d="M16 8.5 C 13 12, 10 13.5, 10 16.3 C 10 18.4, 11.7 19.8, 13.5 19.8 C 14.5 19.8, 15.4 19.4, 16 18.6 C 16.6 19.4, 17.5 19.8, 18.5 19.8 C 20.3 19.8, 22 18.4, 22 16.3 C 22 13.5, 19 12, 16 8.5 Z M 15.2 19.2 L 14.5 22.5 L 17.5 22.5 L 16.8 19.2 Z"
                          fill="var(--lp-red)"/>
                </svg>
            </div>
        </div>

        <div class="mt-10 text-center">
            <div class="eyebrow inline-flex items-center gap-2">
                <span class="h-1.5 w-1.5 rounded-full" style="background: var(--lp-red);"></span>
                Match Results
            </div>
            <h1 class="mt-4 text-4xl font-black leading-[1.05] tracking-tight text-white sm:text-5xl lg:text-6xl">
                {{ $match->name }}
            </h1>
            <div class="mt-4 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-sm" style="color: var(--lp-text-muted);">
                @if($matchDate)<span>{{ $matchDate }}</span>@endif
                @if($matchDate && ($location || $orgName))<span class="opacity-50">·</span>@endif
                @if($location)<span>{{ $location }}</span>@endif
                @if($location && $orgName)<span class="opacity-50">·</span>@endif
                @if($orgName)<span>{{ $orgName }}</span>@endif
            </div>
        </div>

        {{-- Summary ribbon --}}
        <div class="mx-auto mt-10 grid max-w-4xl grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="glass rounded-xl px-5 py-4">
                <div class="text-[10px] font-semibold uppercase tracking-[0.24em]" style="color: var(--lp-text-muted);">Shooters</div>
                <div class="num mt-2 text-3xl font-black text-white">{{ number_format($statCards['totalShooters']) }}</div>
            </div>
            <div class="glass rounded-xl px-5 py-4">
                <div class="text-[10px] font-semibold uppercase tracking-[0.24em]" style="color: var(--lp-text-muted);">Shots Fired</div>
                <div class="num mt-2 text-3xl font-black text-white">{{ number_format($statCards['totalShots']) }}</div>
            </div>
            <div class="glass rounded-xl px-5 py-4">
                <div class="text-[10px] font-semibold uppercase tracking-[0.24em]" style="color: var(--lp-text-muted);">Hits</div>
                <div class="num mt-2 text-3xl font-black" style="color: #86efac;">{{ number_format($statCards['totalHits']) }}<span class="ml-1 text-base font-semibold" style="color: var(--lp-text-muted);">/ {{ $statCards['hitRate'] }}%</span></div>
            </div>
            <div class="glass rounded-xl px-5 py-4">
                <div class="text-[10px] font-semibold uppercase tracking-[0.24em]" style="color: var(--lp-text-muted);">Top Result</div>
                <div class="num mt-2 text-3xl font-black" style="color: var(--lp-red);">{{ $fmt($statCards['winnerScore']) }}</div>
            </div>
        </div>
    </div>
</section>

{{-- ════════════ Per-distance results (every hit, every miss) ════════════ --}}
<section class="relative border-t" style="border-color: var(--lp-border);">
    <div class="mx-auto max-w-6xl px-6 py-12">
        <div class="mb-8 flex items-end justify-between gap-4">
            <div>
                <div class="eyebrow">Every Shot, Every Shooter</div>
                <h2 class="mt-3 text-2xl font-bold tracking-tight text-white sm:text-3xl">Distance-by-Distance Breakdown</h2>
                <p class="mt-2 text-sm" style="color: var(--lp-text-muted);">Each distance below shows every gong engaged by every shooter. Subtotal is the shooter's points at that distance (hits × distance-multiplier × gong-multiplier).</p>
            </div>
        </div>

        <div class="space-y-10">
            @foreach($distanceTables as $dt)
                @php
                    $distLabel = $dt['label'] ?? ($dt['distance_meters'] . 'm');
                    $distMult  = (float) ($dt['distance_multiplier'] ?? 1);
                    $gongs     = $dt['gongs'] ?? [];
                    $gongCount = count($gongs);
                    $distStat  = collect($distanceStats ?? [])->firstWhere('distance_meters', $dt['distance_meters']);
                @endphp

                <div class="glass rounded-2xl overflow-hidden">
                    {{-- Distance header --}}
                    <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-6" style="border-bottom: 1px solid var(--lp-border); background: rgba(225, 6, 0, 0.04);">
                        <div class="flex items-baseline gap-4">
                            <h3 class="text-2xl font-black tracking-tight text-white sm:text-3xl">{{ $distLabel }}</h3>
                            <span class="rounded-md px-2.5 py-1 text-xs font-bold tracking-wide" style="background: rgba(225, 6, 0, 0.15); color: var(--lp-red); border: 1px solid rgba(225, 6, 0, 0.3);">{{ $mult($distMult) }} MULTIPLIER</span>
                            <span class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--lp-text-muted);">{{ $gongCount }} gongs</span>
                        </div>
                        @if($distStat)
                            <div class="text-right">
                                <div class="text-[10px] font-semibold uppercase tracking-[0.24em]" style="color: var(--lp-text-muted);">Field Hit Rate</div>
                                <div class="num mt-1 text-xl font-black text-white">{{ $distStat['hit_rate'] }}%</div>
                                <div class="num mt-0.5 text-[10px]" style="color: var(--lp-text-muted);">{{ $distStat['hits'] }} / {{ $distStat['shots'] }} impacts</div>
                            </div>
                        @endif
                    </div>

                    {{-- Distance results table --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-[11px]" style="border-collapse: collapse;">
                            <thead>
                                <tr style="background: rgba(255, 255, 255, 0.02); border-bottom: 1px solid var(--lp-border);">
                                    <th class="px-4 py-2 text-left font-semibold uppercase tracking-[0.16em]" style="color: var(--lp-text-muted); font-size: 9px;">#</th>
                                    <th class="px-3 py-2 text-left font-semibold uppercase tracking-[0.16em]" style="color: var(--lp-text-muted); font-size: 9px;">Shooter</th>
                                    <th class="px-3 py-2 text-left font-semibold uppercase tracking-[0.16em]" style="color: var(--lp-text-muted); font-size: 9px;">Caliber</th>
                                    @foreach($gongs as $g)
                                        <th class="px-1.5 py-2 text-center font-semibold" style="color: var(--lp-text-muted);">
                                            <div class="text-[9px] tracking-[0.12em] uppercase">G{{ $g['number'] }}</div>
                                            <div class="mt-0.5 text-[9px] font-bold" style="color: var(--lp-red);">{{ $mult($g['multiplier']) }}</div>
                                        </th>
                                    @endforeach
                                    <th class="px-2 py-2 text-center font-semibold uppercase tracking-[0.14em]" style="color: #86efac; font-size: 9px;">Hits</th>
                                    <th class="px-2 py-2 text-center font-semibold uppercase tracking-[0.14em]" style="color: #fca5a5; font-size: 9px;">Miss</th>
                                    <th class="px-3 py-2 text-right font-semibold uppercase tracking-[0.14em]" style="color: var(--lp-red); font-size: 9px;">Points</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dt['rows'] as $row)
                                    @php
                                        $rowName    = $displayName($row['name']);
                                        $rowCaliber = $caliberFromName($row['name']);
                                        $isDq       = ($row['status'] ?? null) === 'dq';
                                    @endphp
                                    <tr class="rf-table-row {{ $isDq ? 'opacity-50' : '' }}" style="border-bottom: 1px solid rgba(255, 255, 255, 0.04);">
                                        <td class="num px-4 py-2 text-left text-[11px] font-bold" style="color: var(--lp-text-muted);">{{ $isDq ? 'DQ' : $row['rank'] }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <span class="text-[12px] font-semibold text-white">{{ $rowName }}</span>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-[10.5px]" style="color: var(--lp-text-muted);">{{ $rowCaliber ?? '—' }}</td>
                                        @foreach($row['cells'] as $cell)
                                            <td class="px-1 py-1.5 text-center">
                                                @if($cell['state'] === 'hit')
                                                    <span class="gong-cell gong-hit" title="Hit · +{{ $fmt($cell['points']) }}">
                                                        <span class="tick"></span>
                                                    </span>
                                                @elseif($cell['state'] === 'miss')
                                                    <span class="gong-cell gong-miss" title="Miss">
                                                        <span class="cross"></span>
                                                    </span>
                                                @else
                                                    <span class="gong-cell gong-none" title="Not engaged">
                                                        <span class="dash"></span>
                                                    </span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="num px-2 py-2 text-center text-[12px] font-bold" style="color: #86efac;">{{ $row['hits'] }}</td>
                                        <td class="num px-2 py-2 text-center text-[12px] font-bold" style="color: #fca5a5;">{{ $row['misses'] }}</td>
                                        <td class="num px-3 py-2 text-right text-[13px] font-black text-white">{{ $fmt($row['subtotal']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ════════════ Final Results (every shooter, total = Σ per-distance subtotals) ════════════ --}}
<section class="relative border-t" style="border-color: var(--lp-border);">
    <div class="mx-auto max-w-6xl px-6 py-12">
        <div class="mb-8">
            <div class="eyebrow">Final Results</div>
            <h2 class="mt-3 text-2xl font-bold tracking-tight text-white sm:text-3xl">Match Total by Shooter</h2>
            <p class="mt-2 text-sm" style="color: var(--lp-text-muted);">
                Final result = sum of every distance subtotal. Royal Flush is an individual event — this is a per-match result, not a season standing.
                @if($seasonId)<a href="{{ route('leaderboard', $match->organization?->slug ?? '') }}" class="ml-1 font-semibold underline underline-offset-2 hover:!text-white" style="color: var(--lp-text);">View season standings →</a>@endif
            </p>
        </div>

        <div class="glass rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-[12px]" style="border-collapse: collapse;">
                    <thead>
                        <tr style="background: rgba(255, 255, 255, 0.02); border-bottom: 1px solid var(--lp-border);">
                            <th class="px-4 py-3 text-left font-semibold uppercase tracking-[0.16em]" style="color: var(--lp-text-muted); font-size: 10px;">#</th>
                            <th class="px-3 py-3 text-left font-semibold uppercase tracking-[0.16em]" style="color: var(--lp-text-muted); font-size: 10px;">Shooter</th>
                            <th class="px-3 py-3 text-left font-semibold uppercase tracking-[0.16em]" style="color: var(--lp-text-muted); font-size: 10px;">Caliber</th>
                            @foreach($distanceTables as $dt)
                                <th class="px-2 py-3 text-center font-semibold uppercase tracking-[0.14em]" style="color: var(--lp-text-muted); font-size: 10px;">
                                    {{ $dt['label'] ?? ($dt['distance_meters'] . 'm') }}
                                </th>
                            @endforeach
                            <th class="px-2 py-3 text-center font-semibold uppercase tracking-[0.14em]" style="color: #86efac; font-size: 10px;">Hits</th>
                            <th class="px-2 py-3 text-center font-semibold uppercase tracking-[0.14em]" style="color: #fca5a5; font-size: 10px;">Miss</th>
                            <th class="px-4 py-3 text-right font-semibold uppercase tracking-[0.14em]" style="color: var(--lp-red); font-size: 10px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($finalResults as $r)
                            @php $isDq = $r['status'] === 'dq'; @endphp
                            <tr class="rf-table-row {{ $isDq ? 'opacity-50' : '' }}" style="border-bottom: 1px solid rgba(255, 255, 255, 0.04);">
                                <td class="num px-4 py-2.5 text-left">
                                    @if($isDq)
                                        <span class="text-[11px] font-bold tracking-wide" style="color: var(--lp-text-muted);">DQ</span>
                                    @elseif($r['rank'] === 1)
                                        <span class="medal medal-1 text-lg">1</span>
                                    @elseif($r['rank'] === 2)
                                        <span class="medal medal-2 text-lg">2</span>
                                    @elseif($r['rank'] === 3)
                                        <span class="medal medal-3 text-lg">3</span>
                                    @else
                                        <span class="text-[12px] font-bold" style="color: var(--lp-text-muted);">{{ $r['rank'] }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    <span class="text-[13px] font-semibold text-white">{{ $r['name'] }}</span>
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-[11px]" style="color: var(--lp-text-muted);">{{ $r['caliber'] ?? '—' }}</td>
                                @foreach($r['per_distance'] as $pd)
                                    <td class="num px-2 py-2.5 text-center text-[12px] font-semibold text-white/90">{{ $fmt($pd['subtotal']) }}</td>
                                @endforeach
                                <td class="num px-2 py-2.5 text-center text-[12px] font-bold" style="color: #86efac;">{{ $r['hits'] }}</td>
                                <td class="num px-2 py-2.5 text-center text-[12px] font-bold" style="color: #fca5a5;">{{ $r['misses'] }}</td>
                                <td class="num px-4 py-2.5 text-right text-[15px] font-black" style="color: var(--lp-red);">{{ $fmt($r['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

{{-- ════════════ Footer ════════════ --}}
<footer class="border-t" style="border-color: var(--lp-border); background: var(--lp-bg);">
    <div class="mx-auto max-w-6xl px-6 py-8">
        <div class="flex flex-col items-center justify-between gap-3 sm:flex-row">
            <div class="flex items-center gap-2 opacity-60">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="2" fill="var(--lp-red)"/>
                    <line x1="12" y1="4"  x2="12" y2="8"  stroke="var(--lp-red)" stroke-width="2" stroke-linecap="round"/>
                    <line x1="12" y1="16" x2="12" y2="20" stroke="var(--lp-red)" stroke-width="2" stroke-linecap="round"/>
                    <line x1="4"  y1="12" x2="8"  y2="12" stroke="var(--lp-red)" stroke-width="2" stroke-linecap="round"/>
                    <line x1="16" y1="12" x2="20" y2="12" stroke="var(--lp-red)" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <span class="text-xs font-semibold tracking-widest" style="color: var(--lp-text-muted);">DEADCENTER · ROYAL FLUSH</span>
            </div>
            <div class="text-[11px]" style="color: var(--lp-text-muted);">
                Generated {{ now()->format('j M Y · H:i') }} · deadcenter.co.za
            </div>
        </div>
    </div>
</footer>

</body>
</html>
