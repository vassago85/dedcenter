@php
    /**
     * Post-match report — executive summary.
     *
     * Designed to look correct in BOTH Gotenberg (Chrome) and dompdf (PHP).
     * Constraints we honor for dompdf compatibility:
     *   - DejaVu Sans font (built-in, full Unicode support for ✓ ✗ — • ★)
     *   - Layout uses <table> not flexbox
     *   - No CSS variables, no transforms, no filters
     *   - All colors as hex, no rgb()/rgba() — safer across PDF engines
     *   - Avoid border-collapse tricks; rely on simple borders + background
     */

    $fmt = function ($v, $decimals = 2) {
        $s = number_format((float) $v, $decimals);
        return rtrim(rtrim($s, '0'), '.');
    };

    $mult = function ($v) use ($fmt) {
        return $fmt($v, 2) . 'x';
    };

    // Winner (rank 1, non-DQ) for hero row
    $winner = $standings->firstWhere(fn ($r) => $r->status !== 'dq' && (int) ($r->rank ?? 0) === 1);

    // Stats for header
    $totalShooters = $standings->count();
    $totalDistances = count($distanceTables);
    $totalGongs = collect($distanceTables)->sum(fn ($d) => count($d['gongs']));
    $totalHits = $standings->sum('hits');
    $totalShots = $standings->sum(fn ($r) => $r->hits + $r->misses);
    $hitRate = $totalShots > 0 ? round($totalHits / $totalShots * 100) : 0;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $match->name }} — Post-Match Report</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 12mm; }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.5pt;
            color: #1a202c;
            line-height: 1.35;
        }

        /* ─── Cover strip ─── */
        .cover {
            background: #0f172a;
            color: #ffffff;
            padding: 10px 14px;
            margin: 0 0 12px 0;
            border-left: 4px solid #f59e0b;
        }
        .cover .eyebrow {
            font-size: 7pt;
            letter-spacing: 0.2em;
            color: #f59e0b;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .cover h1 {
            font-size: 16pt;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 4px;
            line-height: 1.1;
        }
        .cover .meta {
            font-size: 8pt;
            color: #cbd5e1;
        }
        .cover .meta .dot { color: #475569; padding: 0 4px; }

        /* ─── Stat chips row ─── */
        .stats {
            width: 100%;
            margin-bottom: 10px;
        }
        .stats td {
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 6px 8px;
            text-align: center;
            width: 20%;
        }
        .stats .val {
            font-size: 13pt;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.1;
        }
        .stats .lbl {
            font-size: 6.5pt;
            letter-spacing: 0.1em;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 1px;
        }
        .stats .winner .val { color: #d97706; font-size: 10pt; }

        /* ─── Section headers ─── */
        h2 {
            font-size: 9.5pt;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 4px 0 3px;
            border-bottom: 2px solid #0f172a;
            margin: 12px 0 5px;
        }
        h2 .badge {
            float: right;
            background: #0f172a;
            color: #ffffff;
            font-size: 7pt;
            font-weight: 600;
            padding: 2px 7px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        /* ─── Per-distance tables ─── */
        table.dist { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
        table.dist th {
            background: #0f172a;
            color: #ffffff;
            text-align: center;
            padding: 4px 4px;
            font-weight: 600;
            font-size: 7pt;
            letter-spacing: 0.04em;
            border-right: 1px solid #1e293b;
        }
        table.dist th.name { text-align: left; padding-left: 8px; }
        table.dist th.gong-head .gnum { font-size: 8pt; color: #ffffff; }
        table.dist th.gong-head .gmult { display: block; font-size: 6.5pt; color: #fbbf24; font-weight: 700; margin-top: 1px; }
        table.dist th.score-head { background: #7c2d12; }
        table.dist td {
            border-bottom: 1px solid #e2e8f0;
            padding: 3px 4px;
            text-align: center;
            vertical-align: middle;
        }
        table.dist td.rank { font-weight: 700; color: #64748b; width: 22px; }
        table.dist td.name {
            text-align: left;
            padding-left: 8px;
            white-space: nowrap;
        }
        table.dist td.name .n { font-weight: 600; color: #0f172a; }
        table.dist td.name .cal { color: #64748b; font-size: 7pt; }
        table.dist td.score { font-weight: 700; color: #7c2d12; width: 38px; }
        table.dist tr.winner td { background: #fef3c7; }
        table.dist tr.winner td.rank { color: #d97706; }
        table.dist tr.alt td { background: #f8fafc; }
        table.dist tr.dq td { color: #94a3b8; font-style: italic; background: #fff5f5; }

        /* Hit / Miss / None glyphs — DejaVu Sans renders these correctly */
        .mark {
            display: inline-block;
            font-size: 10pt;
            font-weight: 700;
            line-height: 1;
        }
        .mark-hit { color: #15803d; }
        .mark-miss { color: #b91c1c; }
        .mark-none { color: #cbd5e1; font-weight: 400; }
        .pts {
            display: block;
            font-size: 6pt;
            color: #15803d;
            font-weight: 600;
            margin-top: 1px;
            letter-spacing: 0.02em;
        }

        .dist-caption {
            background: #f1f5f9;
            border-left: 3px solid #0f172a;
            padding: 4px 8px;
            margin: 10px 0 0;
            font-size: 8pt;
        }
        .dist-caption .label {
            font-weight: 700;
            color: #0f172a;
            font-size: 9pt;
        }
        .dist-caption .sub { color: #64748b; font-size: 7pt; }

        /* ─── Final totals ─── */
        table.totals { width: 100%; border-collapse: collapse; font-size: 8pt; }
        table.totals th {
            background: #0f172a;
            color: #ffffff;
            text-align: left;
            padding: 5px 8px;
            font-weight: 600;
            font-size: 7.5pt;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        table.totals th.right { text-align: right; }
        table.totals td { border-bottom: 1px solid #e2e8f0; padding: 4px 8px; }
        table.totals td.right { text-align: right; font-variant-numeric: tabular-nums; }
        table.totals tr.alt td { background: #f8fafc; }
        table.totals tr.winner td { background: #fef3c7; font-weight: 600; }
        table.totals tr.podium-2 td { background: #f1f5f9; }
        table.totals tr.podium-3 td { background: #fef6e4; }
        table.totals tr.dq td { color: #94a3b8; font-style: italic; background: #fff5f5; }
        table.totals .rank { width: 28px; text-align: center; font-weight: 700; color: #64748b; }
        table.totals tr.winner .rank { color: #d97706; font-size: 10pt; }
        table.totals .name .n { font-weight: 600; }
        table.totals .name .cal { color: #64748b; font-size: 7pt; display: block; margin-top: 1px; }
        table.totals .total { color: #7c2d12; font-weight: 700; font-size: 9.5pt; }

        /* ─── RF and Side Bet sections ─── */
        .side-section { margin-top: 12px; }
        .side-section .lead {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            padding: 5px 8px;
            font-size: 7.5pt;
            color: #78350f;
            margin-bottom: 4px;
            line-height: 1.4;
        }
        .pill {
            display: inline-block;
            background: #dbeafe;
            color: #1e3a8a;
            padding: 1px 6px;
            font-size: 7pt;
            font-weight: 600;
            margin-right: 2px;
        }

        /* ─── Footer ─── */
        .foot {
            margin-top: 14px;
            padding-top: 6px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 6.5pt;
            color: #94a3b8;
            letter-spacing: 0.04em;
        }
        .foot strong { color: #0f172a; font-weight: 700; letter-spacing: 0.08em; }

        /* Keep distance tables together when possible */
        .dist-block { page-break-inside: avoid; }
    </style>
</head>
<body>

    {{-- ─── Cover ─── --}}
    <div class="cover">
        <div class="eyebrow">Post-Match Report</div>
        <h1>{{ $match->name }}</h1>
        <div class="meta">
            {{ $match->date?->format('l, d F Y') }}
            @if($match->location)<span class="dot">•</span>{{ $match->location }}@endif
            @if($match->organization)<span class="dot">•</span>{{ $match->organization->name }}@endif
        </div>
    </div>

    {{-- ─── Stat chips ─── --}}
    <table class="stats">
        <tr>
            <td class="winner">
                <div class="val">{{ $winner?->name ?? '—' }}</div>
                <div class="lbl">Match Winner</div>
            </td>
            <td>
                <div class="val">{{ $totalShooters }}</div>
                <div class="lbl">Shooters</div>
            </td>
            <td>
                <div class="val">{{ $totalDistances }}</div>
                <div class="lbl">Distances</div>
            </td>
            <td>
                <div class="val">{{ $totalHits }}<span style="font-size:8pt;color:#94a3b8;">/{{ $totalShots }}</span></div>
                <div class="lbl">Hit Rate {{ $hitRate }}%</div>
            </td>
            <td>
                <div class="val">{{ $winner ? $fmt($winner->total_score) : '—' }}</div>
                <div class="lbl">Winning Score</div>
            </td>
        </tr>
    </table>

    {{-- ─── Per-Distance Summary ─── --}}
    @foreach($distanceTables as $dt)
        <div class="dist-block">
            <div class="dist-caption">
                <span class="label">{{ $dt['label'] ?? ($dt['distance_meters'].'m') }}</span>
                <span class="sub">
                    • distance multiplier {{ $mult($dt['distance_multiplier']) }}
                    • {{ count($dt['gongs']) }} gong{{ count($dt['gongs']) === 1 ? '' : 's' }}
                    • max per shooter {{ $fmt($dt['distance_multiplier'] * collect($dt['gongs'])->sum('multiplier')) }}
                </span>
            </div>
            <table class="dist">
                <thead>
                    <tr>
                        <th style="width:22px;">#</th>
                        <th class="name">Shooter</th>
                        @foreach($dt['gongs'] as $g)
                            <th class="gong-head" style="width:42px;">
                                <span class="gnum">G{{ $g['number'] }}</span>
                                <span class="gmult">{{ $mult($g['multiplier']) }}</span>
                            </th>
                        @endforeach
                        <th style="width:28px;">Hits</th>
                        <th class="score-head" style="width:40px;">Score</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dt['rows'] as $i => $row)
                        @php
                            $rowClass = '';
                            if ($row['status'] === 'dq') $rowClass = 'dq';
                            elseif ($row['rank'] === 1) $rowClass = 'winner';
                            elseif ($i % 2 === 1) $rowClass = 'alt';
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td class="rank">{{ $row['status'] === 'dq' ? 'DQ' : ($row['rank'] ?? '—') }}</td>
                            <td class="name">
                                <span class="n">{{ \Illuminate\Support\Str::before($row['name'], ' — ') ?: $row['name'] }}</span>
                                @if($row['caliber'])
                                    <span class="cal">&nbsp;&middot;&nbsp;{{ $row['caliber'] }}</span>
                                @endif
                            </td>
                            @foreach($row['cells'] as $cell)
                                <td>
                                    @if($cell['state'] === 'hit')
                                        <span class="mark mark-hit">&#10003;</span>
                                        <span class="pts">+{{ $fmt($cell['points']) }}</span>
                                    @elseif($cell['state'] === 'miss')
                                        <span class="mark mark-miss">&#10007;</span>
                                    @else
                                        <span class="mark mark-none">&mdash;</span>
                                    @endif
                                </td>
                            @endforeach
                            <td><strong>{{ $row['hits'] }}</strong></td>
                            <td class="score">{{ $fmt($row['subtotal']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    {{-- ─── Final Standings ─── --}}
    <h2>Final Standings <span class="badge">{{ $totalShooters }} shooters</span></h2>
    <table class="totals">
        <thead>
            <tr>
                <th class="rank">#</th>
                <th>Shooter</th>
                <th>Squad</th>
                <th class="right">Hits</th>
                <th class="right">Misses</th>
                <th class="right">Score</th>
            </tr>
        </thead>
        <tbody>
            @foreach($standings as $i => $row)
                @php
                    $cal = null;
                    foreach ([' — ', ' – ', ' - '] as $s) {
                        if (str_contains($row->name, $s)) { $cal = trim(explode($s, $row->name, 2)[1] ?? ''); break; }
                    }
                    $displayName = \Illuminate\Support\Str::before($row->name, ' — ') ?: $row->name;
                    $rowClass = '';
                    if ($row->status === 'dq') $rowClass = 'dq';
                    elseif ($row->rank === 1) $rowClass = 'winner';
                    elseif ($row->rank === 2) $rowClass = 'podium-2';
                    elseif ($row->rank === 3) $rowClass = 'podium-3';
                    elseif ($i % 2 === 1) $rowClass = 'alt';
                @endphp
                <tr class="{{ $rowClass }}">
                    <td class="rank">{{ $row->status === 'dq' ? 'DQ' : ($row->rank ?? '—') }}</td>
                    <td class="name">
                        <span class="n">{{ $displayName }}</span>
                        @if($cal)<span class="cal">{{ $cal }}</span>@endif
                    </td>
                    <td>{{ $row->squad }}</td>
                    <td class="right">{{ $row->hits }}</td>
                    <td class="right">{{ $row->misses }}</td>
                    <td class="right total">{{ $fmt($row->total_score) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ─── Royal Flush Leaderboard ─── --}}
    @if($match->royal_flush_enabled && $rfLeaderboard->isNotEmpty())
        <div class="side-section">
            <h2>Royal Flush <span class="badge">{{ $rfLeaderboard->sum('flush_count') }} flushes</span></h2>
            <table class="totals">
                <thead>
                    <tr>
                        <th class="rank">#</th>
                        <th>Shooter</th>
                        <th>Squad</th>
                        <th class="right">Flushes</th>
                        <th>Distances</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rfLeaderboard as $i => $entry)
                        @php
                            $rowClass = $i === 0 ? 'winner' : ($i === 1 ? 'podium-2' : ($i === 2 ? 'podium-3' : ($i % 2 === 1 ? 'alt' : '')));
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td class="rank">{{ $i + 1 }}</td>
                            <td class="name"><span class="n">{{ $entry->name }}</span></td>
                            <td>{{ $entry->squad }}</td>
                            <td class="right"><strong>{{ $entry->flush_count }}</strong></td>
                            <td>
                                @foreach($entry->flush_distances as $d)
                                    <span class="pill">{{ $d }}m</span>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ─── Side Bet ─── --}}
    @if($match->side_bet_enabled && $sideBetCascade && $sideBetCascade['participants']->isNotEmpty())
        <div class="side-section">
            <h2>Side Bet <span class="badge">Cascade Ranking</span></h2>
            <div class="lead">
                Ranked by smallest-gong (highest-value) hits. Ties break on furthest distance at that gong,
                then cascade down through every gong size.
            </div>
            <table class="totals">
                <thead>
                    <tr>
                        <th class="rank">#</th>
                        <th>Shooter</th>
                        <th>Squad</th>
                        @foreach($sideBetCascade['cascade_columns'] as $gongNumber)
                            <th class="right">G{{ $gongNumber }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($sideBetCascade['participants'] as $i => $entry)
                        @php
                            $rowClass = $i === 0 ? 'winner' : ($i === 1 ? 'podium-2' : ($i === 2 ? 'podium-3' : ($i % 2 === 1 ? 'alt' : '')));
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td class="rank">{{ $i + 1 }}</td>
                            <td class="name"><span class="n">{{ $entry->name }}</span></td>
                            <td>{{ $entry->squad }}</td>
                            @foreach($entry->cascade as $c)
                                <td class="right">{{ $c }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="foot">
        <strong>DeadCenter</strong> &nbsp;&nbsp;•&nbsp;&nbsp;
        {{ $match->name }}
        &nbsp;&nbsp;•&nbsp;&nbsp;
        Generated {{ $generatedAt->format('d M Y H:i') }}
    </div>

</body>
</html>
