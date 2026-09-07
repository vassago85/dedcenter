@php
    /**
     * ALRHA Full Match Report — prize-book document.
     *
     * Not a gong heatmap. This is the printed-programme layout:
     *   per-class podium → prize tables in programme order
     *   (Hunter Team → Hunter Individual → categories → CBC).
     *
     * Same payload the scoreboard Prize Book tab uses
     * (`AlrhaScoringService::buildPrizeBookSections`).
     */
    $viewMode = $viewMode ?? 'pdf';
    $isHtmlView = $viewMode === 'html';
    $prizeSections = $prizeSections ?? [];
    $classPodiums = $classPodiums ?? [];
    $statCards = $statCards ?? [];
    $isDualClass = (bool) ($isDualClass ?? false);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @if($isHtmlView)
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @endif
    <title>{{ $match->name }} — ALRHA Prize Book</title>
    @include('exports.partials.pdf-styles-dark')
    <style>
        @page { size: 210mm auto; margin: 0; background: #071327; }
        body { width: 210mm; background: #071327; }
        .wrap { padding: 16px 14px; background: #071327; }

        .type-chip {
            display: inline-block;
            margin: 0 0 10px;
            padding: 3px 9px;
            border-radius: 999px;
            background: #ff2b2b;
            color: #fff;
            font-size: 7.5pt;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }
        .lede {
            margin: 0 0 14px;
            color: #94a3b8;
            font-size: 8pt;
            line-height: 1.45;
        }

        .stats-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            table-layout: fixed;
            margin-bottom: 14px;
        }
        .stats-grid td {
            border: 1px solid #31486d;
            border-radius: 5px;
            padding: 8px 10px;
            background: #0c1a33;
            width: 25%;
        }
        .stats-grid .lbl {
            font-size: 6pt;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.2em;
        }
        .stats-grid .val {
            font-size: 15pt;
            font-weight: 800;
            color: #f8fafc;
            font-variant-numeric: tabular-nums;
            line-height: 1;
            margin-top: 5px;
        }

        .class-podium { margin: 0 0 16px; page-break-inside: avoid; }
        .class-podium h2 {
            margin: 0 0 8px;
            color: #f8fafc;
            font-size: 11pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }
        .podium { width: 100%; border-collapse: separate; border-spacing: 6px 0; }
        .podium td {
            width: 33.33%;
            border: 1px solid #31486d;
            border-radius: 5px;
            padding: 10px 12px;
            background: #0c1a33;
            vertical-align: top;
        }
        .podium td.p1 { border-color: #d97706; background: rgba(251,191,36,0.08); }
        .podium td.p2 { border-color: #64748b; background: rgba(203,213,225,0.06); }
        .podium td.p3 { border-color: #c2410c; background: rgba(251,146,60,0.08); }
        .podium .rk { font-size: 7pt; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; color: #94a3b8; }
        .podium td.p1 .rk { color: #fbbf24; }
        .podium td.p2 .rk { color: #cbd5e1; }
        .podium td.p3 .rk { color: #fb923c; }
        .podium .nm { margin-top: 4px; font-size: 11pt; font-weight: 800; color: #f8fafc; }
        .podium .sc { margin-top: 6px; font-size: 16pt; font-weight: 800; color: #6ee7b7; font-variant-numeric: tabular-nums; }
        .podium .sub { margin-top: 2px; font-size: 8pt; color: #94a3b8; }

        .section { margin: 18px 0 8px; page-break-inside: avoid; }
        .section h2 {
            margin: 0 0 2px;
            color: #f8fafc;
            font-size: 11pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }
        .section .sub {
            margin: 0 0 8px;
            color: #64748b;
            font-size: 7.5pt;
        }

        .prize {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #31486d;
            border-radius: 5px;
            overflow: hidden;
            background: #0c1a33;
        }
        .prize thead th {
            background: #243757;
            color: #f8fafc;
            padding: 5px 6px;
            font-weight: 700;
            font-size: 6.5pt;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            border-bottom: 1px solid #31486d;
            text-align: left;
        }
        .prize thead th.right { text-align: right; }
        .prize tbody td {
            padding: 4px 6px;
            font-size: 8pt;
            color: #cbd5e1;
            border-bottom: 1px solid #1e293b;
        }
        .prize tbody tr:nth-child(even) td { background: #0c1a33; }
        .prize tbody tr:nth-child(odd) td { background: #111f3c; }
        .prize tbody tr.rank-1 td { background: rgba(251,191,36,0.08) !important; }
        .prize tbody tr.rank-2 td { background: rgba(203,213,225,0.06) !important; }
        .prize tbody tr.rank-3 td { background: rgba(251,146,60,0.08) !important; }
        .prize .right { text-align: right; font-variant-numeric: tabular-nums; }
        .prize .pts { font-weight: 800; color: #6ee7b7; }
        .prize .hits { color: #22c55e; font-weight: 700; }
        .prize .muted { color: #64748b; font-size: 7pt; }
        .prize .coached { color: #fbbf24; font-size: 6.5pt; text-transform: uppercase; letter-spacing: 0.08em; }

        .note {
            margin-top: 14px;
            color: #64748b;
            font-size: 7pt;
            line-height: 1.4;
        }

        @if($isHtmlView)
            html, body { min-height: 100%; }
            body { width: auto; max-width: 100%; margin: 0 auto; overflow-x: hidden; }
            .wrap { max-width: 960px; margin: 0 auto; padding: 16px clamp(12px, 3vw, 28px); }
            .report-actions {
                position: sticky; top: 0; z-index: 10;
                display: flex; align-items: center; justify-content: flex-end; gap: 10px;
                padding: 10px 16px; background: #071327; border-bottom: 1px solid #31486d;
            }
            .report-actions .title { margin-right: auto; color: #f8fafc; font-weight: 700; font-size: 11pt; }
            .report-actions .btn {
                display: inline-block; padding: 6px 12px; border-radius: 6px;
                background: #ff2b2b; color: #fff; font-size: 10pt; font-weight: 700; text-decoration: none;
            }
            .report-actions .btn.ghost { background: transparent; border: 1px solid #31486d; color: #cbd5e1; }
            @media (max-width: 640px) {
                .podium, .podium tbody, .podium tr, .stats-grid, .stats-grid tbody, .stats-grid tr { display: block; width: 100%; }
                .podium td, .stats-grid td { display: block; width: auto; margin-bottom: 6px; }
            }
        @endif
    </style>
</head>
<body>
    @if($isHtmlView)
        <div class="report-actions">
            <span class="title">{{ $match->name }} — ALRHA Prize Book</span>
            @if(!empty($downloadUrl ?? null))
                <a class="btn" href="{{ $downloadUrl }}">Download PDF</a>
            @endif
            @if(!empty($backUrl ?? null))
                <a class="btn ghost" href="{{ $backUrl }}">Back</a>
            @endif
        </div>
    @endif

    @include('exports.partials.pdf-header', ['subtitle' => 'ALRHA Prize Book'])

    <div class="wrap">
        <div class="type-chip">ALRHA</div>
        <p class="lede">
            Prize tables by class. Points use the 5-4-3-2-1 shot index.
            Cold Bore Challenge is a separate prize and is not included in class totals.
        </p>

        <table class="stats-grid">
            <tr>
                <td>
                    <div class="lbl">Shooters</div>
                    <div class="val">{{ (int) ($statCards['shooters'] ?? 0) }}</div>
                </td>
                @if($isDualClass)
                    <td>
                        <div class="lbl">Hunters</div>
                        <div class="val">{{ (int) ($statCards['hunters'] ?? 0) }}</div>
                    </td>
                    <td>
                        <div class="lbl">Varmint</div>
                        <div class="val">{{ (int) ($statCards['varmint'] ?? 0) }}</div>
                    </td>
                @elseif(($statCards['teams'] ?? 0) > 0)
                    <td>
                        <div class="lbl">Teams</div>
                        <div class="val">{{ (int) $statCards['teams'] }}</div>
                    </td>
                @endif
                <td>
                    <div class="lbl">CBC hits</div>
                    <div class="val">{{ (int) ($statCards['cbcHits'] ?? 0) }}</div>
                </td>
            </tr>
        </table>

        @foreach($classPodiums as $podium)
            @if($podium['first'])
                <div class="class-podium">
                    <h2>{{ $podium['label'] }} podium</h2>
                    <table class="podium">
                        <tr>
                            @if($podium['first'])
                                <td class="p1">
                                    <div class="rk">1st · Winner</div>
                                    <div class="nm">{{ $podium['first']['name'] ?? '' }}</div>
                                    <div class="sc">{{ number_format((float) ($podium['first']['total_points'] ?? 0), 1) }}</div>
                                    <div class="sub">{{ (int) ($podium['first']['total_hits'] ?? 0) }} hits · {{ $podium['first']['squad_name'] ?? '' }}</div>
                                </td>
                            @endif
                            @if($podium['second'])
                                <td class="p2">
                                    <div class="rk">2nd</div>
                                    <div class="nm">{{ $podium['second']['name'] ?? '' }}</div>
                                    <div class="sc">{{ number_format((float) ($podium['second']['total_points'] ?? 0), 1) }}</div>
                                    <div class="sub">{{ (int) ($podium['second']['total_hits'] ?? 0) }} hits</div>
                                </td>
                            @endif
                            @if($podium['third'])
                                <td class="p3">
                                    <div class="rk">3rd</div>
                                    <div class="nm">{{ $podium['third']['name'] ?? '' }}</div>
                                    <div class="sc">{{ number_format((float) ($podium['third']['total_points'] ?? 0), 1) }}</div>
                                    <div class="sub">{{ (int) ($podium['third']['total_hits'] ?? 0) }} hits</div>
                                </td>
                            @endif
                        </tr>
                    </table>
                </div>
            @endif
        @endforeach

        @foreach($prizeSections as $section)
            <section class="section">
                <h2>{{ $section['title'] }}</h2>
                @if(! empty($section['subtitle']))
                    <p class="sub">{{ $section['subtitle'] }}</p>
                @endif

                @if(($section['kind'] ?? '') === 'teams')
                    <table class="prize">
                        <thead>
                            <tr>
                                <th style="width:36px;">#</th>
                                <th>Team</th>
                                <th class="right">Team pts</th>
                                <th class="right">Hits</th>
                                <th class="right">1st rd</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($section['rows'] as $i => $team)
                                @php
                                    $teamId = (int) ($team['team_id'] ?? 0);
                                    $teamName = trim((string) ($team['team'] ?? ''));
                                    if ($teamName === '' && $teamId > 0) {
                                        $teamName = 'Team '.$teamId;
                                    }
                                    $pair = null;
                                    if (! empty($team['shooter_1_name'])) {
                                        $pair = $team['shooter_1_name'];
                                        if (! empty($team['shooter_2_name'])) {
                                            $pair .= ' & '.$team['shooter_2_name'];
                                        }
                                    }
                                    $rank = (int) ($team['rank'] ?? ($i + 1));
                                @endphp
                                <tr class="{{ $rank <= 3 ? 'rank-'.$rank : '' }}">
                                    <td class="right">{{ $rank }}</td>
                                    <td>
                                        {{ $teamName !== '' ? $teamName : 'Unassigned team' }}
                                        @if($pair)
                                            <div class="muted">{{ $pair }}</div>
                                        @endif
                                    </td>
                                    <td class="right pts">{{ number_format((float) ($team['team_total_points'] ?? 0), 2) }}</td>
                                    <td class="right hits">{{ (int) ($team['team_total_hits'] ?? 0) }}</td>
                                    <td class="right">{{ (int) ($team['first_round_hits'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @elseif(($section['kind'] ?? '') === 'cbc')
                    <table class="prize">
                        <thead>
                            <tr>
                                <th style="width:36px;">#</th>
                                <th>Shooter</th>
                                <th>Squad</th>
                                <th class="right">Hits</th>
                                <th class="right">CBC pts</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($section['rows'] as $i => $row)
                                @php $rank = (int) ($row['rank'] ?? ($i + 1)); @endphp
                                <tr class="{{ $rank <= 3 ? 'rank-'.$rank : '' }}">
                                    <td class="right">{{ $rank }}</td>
                                    <td>{{ $row['name'] ?? '' }}</td>
                                    <td>{{ $row['squad_name'] ?? '—' }}</td>
                                    <td class="right hits">{{ (int) ($row['cbc_hits'] ?? 0) }}</td>
                                    <td class="right pts">{{ number_format((float) ($row['cbc_points'] ?? 0), 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <table class="prize">
                        <thead>
                            <tr>
                                <th style="width:36px;">#</th>
                                <th>Shooter</th>
                                <th>Squad</th>
                                <th class="right">Points</th>
                                <th class="right">Hits</th>
                                <th class="right">1st rd</th>
                                <th class="right">Furthest</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($section['rows'] as $i => $row)
                                @php
                                    $rank = (int) ($row['rank'] ?? ($i + 1));
                                    $furthest = (int) ($row['furthest_hit_m'] ?? 0);
                                @endphp
                                <tr class="{{ $rank <= 3 ? 'rank-'.$rank : '' }}">
                                    <td class="right">{{ $rank }}</td>
                                    <td>
                                        {{ $row['name'] ?? '' }}
                                        @if(! empty($row['is_coached']))
                                            <span class="coached">coached</span>
                                        @endif
                                    </td>
                                    <td>{{ $row['squad_name'] ?? '—' }}</td>
                                    <td class="right pts">{{ number_format((float) ($row['total_points'] ?? 0), 2) }}</td>
                                    <td class="right hits">{{ (int) ($row['total_hits'] ?? 0) }}</td>
                                    <td class="right">{{ (int) ($row['first_round_hits'] ?? 0) }}</td>
                                    <td class="right">{{ $furthest > 0 ? $furthest.'m' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        @endforeach

        <p class="note">
            CBC hits do not count toward class score. Coached shooters appear on the overall table but are not prize-eligible.
            Generated {{ ($generatedAt ?? now())->format('d M Y H:i') }}.
        </p>

        @include('exports.partials.pdf-footer')
    </div>
</body>
</html>
