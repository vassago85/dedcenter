<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $match->name }} — Post-Match Report</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 9pt; color: #1e293b; line-height: 1.3; }
        h1 { font-size: 16pt; font-weight: 800; }
        h2 { font-size: 11pt; font-weight: 700; margin: 14px 0 6px; padding-bottom: 4px; border-bottom: 1.5px solid #1e3a5f; color: #1e3a5f; }
        h3 { font-size: 10pt; font-weight: 700; margin: 10px 0 4px; color: #0f172a; }

        .cover { padding-bottom: 6px; margin-bottom: 10px; border-bottom: 2px solid #1e3a5f; display: flex; justify-content: space-between; align-items: center; }
        .cover .meta { font-size: 8.5pt; color: #64748b; text-align: right; }
        .cover .tag { font-size: 7.5pt; letter-spacing: 0.08em; color: #1e3a5f; text-transform: uppercase; font-weight: 700; margin-bottom: 3px; }

        table { width: 100%; border-collapse: collapse; font-size: 8pt; }
        th { background: #1e3a5f; color: white; text-align: left; padding: 4px 6px; font-weight: 600; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.03em; }
        th.center, td.center { text-align: center; }
        th.right, td.right { text-align: right; }
        td { padding: 3px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        tr:nth-child(even) { background: #f8fafc; }
        tr.rank-1 { background: #fef3c7 !important; }
        tr.rank-1 .rank-cell { color: #d97706; font-weight: 700; }
        tr.rank-2 .rank-cell { color: #475569; font-weight: 700; }
        tr.rank-3 .rank-cell { color: #92400e; font-weight: 700; }
        tr.dq { color: #64748b; font-style: italic; background: #fef2f2 !important; }

        /* Distance summary table — like the tablet post-relay screen */
        .dist-table th.gong { text-align: center; width: 46px; }
        .dist-table th.gong .mult { display: block; font-size: 6.5pt; color: #fbbf24; font-weight: 600; margin-top: 1px; }
        .dist-table td.gong { text-align: center; padding: 2px 4px; }
        .dist-table td.caliber { color: #64748b; font-size: 7.5pt; white-space: nowrap; }
        .dist-table td.name { white-space: nowrap; }

        .mark { display: inline-block; width: 16px; height: 16px; line-height: 16px; border-radius: 50%; text-align: center; font-size: 9pt; font-weight: 700; }
        .hit { background: #dcfce7; color: #15803d; }
        .miss { background: #fee2e2; color: #b91c1c; }
        .none { color: #cbd5e1; }
        .pts { display: block; font-size: 6.5pt; color: #16a34a; margin-top: 1px; font-weight: 600; }

        .dist-header { background: #f1f5f9; padding: 4px 8px; border-left: 3px solid #1e3a5f; margin-top: 10px; margin-bottom: 4px; }
        .dist-header .title { font-weight: 700; font-size: 10pt; color: #0f172a; }
        .dist-header .sub { font-size: 7.5pt; color: #64748b; }

        .pill { display: inline-block; background: #e0f2fe; color: #0c4a6e; border-radius: 10px; padding: 1px 7px; font-size: 7.5pt; font-weight: 600; margin-right: 3px; }
        .muted { color: #64748b; font-size: 7.5pt; }

        .final-total { font-weight: 700; color: #b45309; }
        .subtotal { font-weight: 600; color: #b45309; }

        .footer { margin-top: 14px; text-align: center; font-size: 7pt; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; }

        .section-break { page-break-before: always; }
        /* Keep each per-distance table together so rows don't split awkwardly across pages when possible. */
        .dist-block { page-break-inside: avoid; }
    </style>
</head>
<body>

    {{-- ─── Cover ─── --}}
    <div class="cover">
        <div>
            <div class="tag">Post-Match Report</div>
            <h1>{{ $match->name }}</h1>
            <div class="muted">
                {{ $match->date?->format('l, d M Y') }}
                @if($match->location) &bull; {{ $match->location }} @endif
                @if($match->organization) &bull; {{ $match->organization->name }} @endif
            </div>
        </div>
        <div class="meta">
            Generated {{ $generatedAt->format('d M Y H:i') }}<br>
            {{ $standings->count() }} shooters &bull;
            {{ $match->scoring_type ?? 'standard' }}
            @if($match->royal_flush_enabled) &bull; Royal Flush @endif
            @if($match->side_bet_enabled) &bull; Side Bet @endif
        </div>
    </div>

    {{-- ─── Per-Distance Summary (tablet-style) ─── --}}
    @foreach($distanceTables as $dt)
        <div class="dist-block">
            <div class="dist-header">
                <span class="title">{{ $dt['label'] ?? ($dt['distance_meters'].'m') }}</span>
                <span class="sub">
                    &bull; distance multiplier {{ rtrim(rtrim(number_format($dt['distance_multiplier'], 2), '0'), '.') }}x
                    &bull; {{ count($dt['gongs']) }} gong{{ count($dt['gongs']) === 1 ? '' : 's' }}
                </span>
            </div>
            <table class="dist-table">
                <thead>
                    <tr>
                        <th style="width: 22px;">#</th>
                        <th>Name</th>
                        <th>Caliber</th>
                        @foreach($dt['gongs'] as $g)
                            <th class="gong">
                                G{{ $g['number'] }}
                                <span class="mult">{{ rtrim(rtrim(number_format($g['multiplier'], 2), '0'), '.') }}x</span>
                            </th>
                        @endforeach
                        <th class="center" style="width: 30px;">Hits</th>
                        <th class="center" style="width: 30px;">Miss</th>
                        <th class="right" style="width: 42px;">Score</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dt['rows'] as $row)
                        <tr class="{{ $row['status'] === 'dq' ? 'dq' : ($row['rank'] !== null && $row['rank'] <= 3 ? 'rank-'.$row['rank'] : '') }}">
                            <td class="rank-cell right">{{ $row['status'] === 'dq' ? 'DQ' : ($row['rank'] ?? '—') }}</td>
                            <td class="name">{{ $row['name'] }}</td>
                            <td class="caliber">{{ $row['caliber'] ?? '—' }}</td>
                            @foreach($row['cells'] as $cell)
                                <td class="gong">
                                    @if($cell['state'] === 'hit')
                                        <span class="mark hit">&#10003;</span>
                                        <span class="pts">+{{ rtrim(rtrim(number_format($cell['points'], 2), '0'), '.') }}</span>
                                    @elseif($cell['state'] === 'miss')
                                        <span class="mark miss">&#10007;</span>
                                    @else
                                        <span class="none">&mdash;</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="center">{{ $row['hits'] }}</td>
                            <td class="center">{{ $row['misses'] }}</td>
                            <td class="right subtotal">{{ number_format($row['subtotal'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    {{-- ─── Final Totals ─── --}}
    <h2>Final Totals</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 30px;">Rank</th>
                <th>Name</th>
                <th>Caliber</th>
                <th>Squad</th>
                <th class="right">Hits</th>
                <th class="right">Misses</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($standings as $row)
                @php $cal = null; $n = $row->name; foreach([' — ',' – ',' - '] as $s){ if(str_contains($n,$s)){ $cal = trim(explode($s,$n,2)[1] ?? ''); break; } } @endphp
                <tr class="{{ $row->status === 'dq' ? 'dq' : ($row->rank !== null && $row->rank <= 3 ? 'rank-'.$row->rank : '') }}">
                    <td class="rank-cell right">{{ $row->status === 'dq' ? 'DQ' : ($row->rank ?? '—') }}</td>
                    <td>{{ $row->name }}</td>
                    <td class="muted">{{ $cal !== '' && $cal !== null ? $cal : '—' }}</td>
                    <td>{{ $row->squad }}</td>
                    <td class="right">{{ $row->hits }}</td>
                    <td class="right">{{ $row->misses }}</td>
                    <td class="right final-total">{{ number_format($row->total_score, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="muted" style="margin-top: 4px;">
        Total = &sum; (distance_multiplier × gong_multiplier) for every hit.
    </p>

    {{-- ─── Royal Flush Leaderboard ─── --}}
    @if($match->royal_flush_enabled && $rfLeaderboard->isNotEmpty())
        <h2>Royal Flush Leaderboard</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 30px;">Rank</th>
                    <th>Name</th>
                    <th>Squad</th>
                    <th class="right">Flushes</th>
                    <th>Distances</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rfLeaderboard as $i => $entry)
                    <tr class="{{ $i < 3 ? 'rank-'.($i + 1) : '' }}">
                        <td class="rank-cell right">{{ $i + 1 }}</td>
                        <td>{{ $entry->name }}</td>
                        <td>{{ $entry->squad }}</td>
                        <td class="right">{{ $entry->flush_count }}</td>
                        <td>
                            @foreach($entry->flush_distances as $d)
                                <span class="pill">{{ $d }}m</span>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ─── Side Bet Cascade ─── --}}
    @if($match->side_bet_enabled && $sideBetCascade && $sideBetCascade['participants']->isNotEmpty())
        <h2>Side Bet — Cascade Ranking</h2>
        <p class="muted" style="margin-bottom: 6px;">
            Ranked by smallest-gong (highest-value) hits first, furthest distance wins ties at that gong,
            then cascading down through every gong size and its distances.
        </p>
        <table>
            <thead>
                <tr>
                    <th style="width: 30px;">Rank</th>
                    <th>Name</th>
                    <th>Squad</th>
                    @foreach($sideBetCascade['cascade_columns'] as $gongNumber)
                        <th class="right">G{{ $gongNumber }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($sideBetCascade['participants'] as $i => $entry)
                    <tr class="{{ $i === 0 ? 'rank-1' : '' }}">
                        <td class="rank-cell right">{{ $i + 1 }}</td>
                        <td>{{ $entry->name }}</td>
                        <td>{{ $entry->squad }}</td>
                        @foreach($entry->cascade as $c)
                            <td class="right">{{ $c }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        DeadCenter &bull; {{ $match->name }} &bull; Generated {{ $generatedAt->format('d M Y H:i') }}
    </div>
</body>
</html>
