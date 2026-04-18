<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $match->name }} — Post-Match Report</title>
    <style>
        @page { size: A4 portrait; margin: 14mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 9.5pt; color: #1e293b; line-height: 1.35; }
        h1 { font-size: 16pt; font-weight: 800; }
        h2 { font-size: 11pt; font-weight: 700; margin: 12px 0 6px; padding-bottom: 4px; border-bottom: 1.5px solid #1e3a5f; color: #1e3a5f; }
        .cover { padding-bottom: 6px; margin-bottom: 10px; border-bottom: 2px solid #1e3a5f; display: flex; justify-content: space-between; align-items: center; }
        .cover .meta { font-size: 9pt; color: #64748b; text-align: right; }
        .cover .tag { font-size: 7.5pt; letter-spacing: 0.08em; color: #1e3a5f; text-transform: uppercase; font-weight: 700; margin-bottom: 3px; }

        table { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
        th { background: #1e3a5f; color: white; text-align: left; padding: 4px 6px; font-weight: 600; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.03em; }
        th.right, td.right { text-align: right; }
        td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) { background: #f8fafc; }
        .rank-1 { font-weight: 700; background: #fef3c7 !important; }
        .rank-1 .rank-cell { color: #d97706; }
        .rank-2 .rank-cell { color: #64748b; font-weight: 700; }
        .rank-3 .rank-cell { color: #92400e; font-weight: 700; }

        .pill { display: inline-block; background: #e0f2fe; color: #0c4a6e; border-radius: 10px; padding: 1px 7px; font-size: 7.5pt; font-weight: 600; margin-right: 3px; }
        .muted { color: #64748b; font-size: 8pt; }

        .two-col { display: table; width: 100%; }
        .two-col > div { display: table-cell; width: 50%; vertical-align: top; padding-right: 6px; }
        .two-col > div:last-child { padding-right: 0; padding-left: 6px; }

        .footer { margin-top: 18px; text-align: center; font-size: 7pt; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 6px; }

        .section-break { page-break-before: always; }
    </style>
</head>
<body>
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

    {{-- ─── Final Standings (Weighted) ─── --}}
    <h2>Final Standings</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 30px;">Rank</th>
                <th>Name</th>
                <th>Squad</th>
                <th class="right">Hits</th>
                <th class="right">Misses</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($standings as $row)
                <tr class="{{ $row->rank !== null && $row->rank <= 3 ? 'rank-'.$row->rank : '' }}">
                    <td class="rank-cell right">{{ $row->rank !== null ? $row->rank : 'DQ' }}</td>
                    <td>{{ $row->name }}</td>
                    <td>{{ $row->squad }}</td>
                    <td class="right">{{ $row->hits }}</td>
                    <td class="right">{{ $row->misses }}</td>
                    <td class="right" style="font-weight: 700;">{{ number_format($row->total_score, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="muted" style="margin-top: 4px;">
        Total = &sum; (distance_multiplier × gong_multiplier) for every hit.
    </p>

    {{-- ─── Per-Distance Breakdown ─── --}}
    @if($targetSets->count() > 0 && $standings->count() > 0)
        <h2>Per-Distance Breakdown (Top 10)</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 22px;">#</th>
                    <th>Name</th>
                    @foreach($targetSets as $ts)
                        <th class="right">{{ $ts->label ?? ($ts->distance_meters.'m') }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($standings->filter(fn ($r) => $r->rank !== null && $r->rank <= 10) as $row)
                    <tr>
                        <td class="right">{{ $row->rank }}</td>
                        <td>{{ $row->name }}</td>
                        @foreach($perShooterBreakdown[$row->shooter_id] ?? [] as $b)
                            <td class="right">{{ $b['hits'] }}/{{ $b['hits'] + $b['misses'] }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

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
            Ranked by biggest-gong hits first, cascading down through each gong size. Highest value gongs (biggest) are rank 1, smallest is the final tiebreaker.
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
