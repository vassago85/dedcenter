@php
    /**
     * Executive Summary PDF (A4 Landscape, single page).
     *
     * @var \App\Models\ShootingMatch $match
     * @var array $heatmap                Rows of shooter + cells
     * @var array $heatmapColumns         Column meta (distance, gong number, multipliers)
     * @var array $distanceStats          Per-distance aggregate hit rate
     * @var array $podium                 first, second, third standings
     * @var array $statCards              totals for header chips
     * @var \Illuminate\Support\Collection $rfLeaderboard
     * @var \Carbon\Carbon $generatedAt
     */
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $mult = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') . '×';

    // Group columns by distance for the header row (colspan-like behaviour via sub-headers).
    $columnsByDist = [];
    foreach ($heatmapColumns as $col) {
        $columnsByDist[$col['distance_meters']]['label'] = $col['distance_label'];
        $columnsByDist[$col['distance_meters']]['multiplier'] = $col['distance_multiplier'];
        $columnsByDist[$col['distance_meters']]['cols'][] = $col;
    }

    // Royal Flush scorecards use poker-card-face labels (10 J Q K A) for the
    // five gongs per distance, so the technical breakdown reads like a
    // scorecard instead of a G1/G2/G3 spreadsheet. We only switch to this
    // vocabulary when the match is actually a Royal Flush AND every distance
    // exposes exactly 5 gongs — otherwise we keep the neutral G{n} labels so
    // non-RF matches still render correctly.
    $allFiveUp = ! empty($columnsByDist)
        && collect($columnsByDist)->every(fn ($g) => count($g['cols'] ?? []) === 5);
    $useCardFaces = (bool) ($match->royal_flush_enabled ?? false) && $allFiveUp;
    $cardFaces = ['10', 'J', 'Q', 'K', 'A'];
    $gongLabel = function (int $gongNumber, int $positionInDistance) use ($useCardFaces, $cardFaces) {
        if ($useCardFaces && isset($cardFaces[$positionInDistance])) {
            return $cardFaces[$positionInDistance];
        }
        return 'G' . $gongNumber;
    };
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $match->name }} — Executive Summary</title>
    @include('exports.partials.pdf-styles')
    <style>
        /* Landscape page size */
        @page { size: A4 landscape; margin: 0; }
        body { width: 297mm; }

        /* Generous top/bottom gutters so the match-report table and the
           footer breathe — the previous 12–14px gap made the first and last
           shooter rows fuse against the page edges on print. The pdf-footer
           brings its own 16px top margin, so the bottom gutter can stay
           modest while still feeling intentional. */
        .wrap { padding: 20px 16px 16px; }

        /* ─── Top row: podium + stat cards ─── */
        .top-row { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .top-row > tbody > tr > td { vertical-align: top; padding: 0; }
        .top-row .podium-col { width: 58%; padding-right: 10px; }
        .top-row .stats-col  { width: 42%; }

        /* Stat cards — DeadCenter refined tiles */
        .stats-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            table-layout: fixed;
        }
        .stats-grid td {
            border: 1px solid #e8edf4;
            border-radius: 5px;
            padding: 12px 14px;
            vertical-align: top;
            width: 25%;
            background: #ffffff;
        }
        .stats-grid .lbl {
            font-size: 6pt;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.2em;
        }
        .stats-grid .val {
            font-size: 18pt;
            font-weight: 800;
            color: #0b1220;
            font-variant-numeric: tabular-nums;
            line-height: 1;
            margin-top: 6px;
            letter-spacing: -0.02em;
        }
        .stats-grid .val .sub {
            font-size: 9pt;
            color: #94a3b8;
            font-weight: 600;
            letter-spacing: 0;
            margin-left: 1px;
        }
        .stats-grid .bar {
            margin-top: 8px;
            height: 3px;
            background: #f5f7fa;
            border-radius: 2px;
            overflow: hidden;
        }
        .stats-grid .bar > span {
            display: block;
            height: 3px;
            background: #e10600;
            border-radius: 2px;
        }

        /* ─── Distance chips (sub-header strip) ─── */
        .dist-strip {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin-top: 12px;
        }
        .dist-strip td {
            border: 1px solid #e8edf4;
            border-radius: 5px;
            background: #fbfcfd;
            padding: 9px 10px 8px;
            text-align: center;
        }
        .dist-strip .d-label {
            font-size: 10pt;
            font-weight: 800;
            color: #0b1220;
            letter-spacing: -0.01em;
        }
        .dist-strip .d-meta {
            font-size: 6.5pt;
            color: #94a3b8;
            margin-top: 3px;
            letter-spacing: 0.04em;
        }
        .dist-strip .d-rate {
            font-size: 7pt;
            color: #334155;
            font-weight: 700;
            margin-top: 3px;
            letter-spacing: 0.06em;
        }

        /* Match Report title — extra top gutter so the dense 25-row table
           doesn't sit flush against the distance chips strip. */
        .section-title-report { margin-top: 16px; margin-bottom: 8px; }

        /* ─── Heatmap grid ─── */
        .grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            table-layout: fixed;
            border: 1px solid #e8edf4;
            border-radius: 5px;
            overflow: hidden;
        }
        .grid thead th {
            background: #0b1220;
            color: #f8fafc;
            font-weight: 700;
            padding: 5px 2px;
            text-align: center;
            font-size: 6.5pt;
            border-right: 1px solid #121a2b;
            letter-spacing: 0.04em;
        }
        .grid thead th.dist-head {
            background: #121a2b;
            color: #f8fafc;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            font-size: 7pt;
            font-weight: 800;
        }
        .grid thead th.pos-head  { width: 22px; }
        .grid thead th.name-head { width: 150px; text-align: left; padding-left: 8px; letter-spacing: 0.14em; text-transform: uppercase; font-size: 6pt; }
        .grid thead th.cal-head  { width: 110px; text-align: left; padding-left: 6px; letter-spacing: 0.14em; text-transform: uppercase; font-size: 6pt; }
        .grid thead th.score-head,
        .grid thead th.rate-head {
            background: #1e293b;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            font-size: 6pt;
            width: 38px;
        }
        .grid thead th.score-head { color: #f8fafc; width: 44px; }
        .grid thead th.rate-head  { color: #94a3b8; }

        /* Shot cells — narrow fixed width. 20 of these sit side-by-side; every
           pixel saved here is given back to the Shooter / Caliber columns so
           full names and long calibers (e.g. "6.5 Creedmoor", "30 Sherman Max")
           are no longer truncated. An 11px SVG glyph + a hairline of padding
           fits comfortably in 24px. */
        .grid thead th.shot-head,
        .grid td.hm-hit,
        .grid td.hm-miss,
        .grid td.hm-none { width: 24px; }

        .grid thead .gong-num  { font-size: 6.5pt; color: #f8fafc; font-weight: 700; }
        .grid thead .gong-mult { display: block; font-size: 5.5pt; color: #94a3b8; font-weight: 600; margin-top: 2px; letter-spacing: 0.04em; }

        /* Royal Flush card-face header — 10 / J / Q / K / A. Typeset crisp
           but compact so it reads like a scorecard, not a spreadsheet. */
        .grid thead .card-face {
            font-size: 8pt;
            color: #f8fafc;
            font-weight: 800;
            letter-spacing: 0.02em;
            line-height: 1;
        }
        .grid thead .card-face.card-face-special {
            color: #fecaca;
        }
        .grid thead .card-mult {
            display: block;
            font-size: 5pt;
            color: #94a3b8;
            font-weight: 600;
            margin-top: 3px;
            letter-spacing: 0.04em;
        }

        .grid tbody td {
            padding: 0;
            font-size: 6.5pt;
            border-bottom: 1px solid #eef2f7;
            border-right: 1px solid #f5f7fa;
            vertical-align: middle;
            text-align: center;
            line-height: 1;
            height: 15px;
        }
        /* First and last row get a hair of extra padding so the block
           doesn't fuse against the table's own border edges. Only 2px
           each to preserve the single-page-landscape vertical budget. */
        .grid tbody tr:first-child td { padding-top: 2px; }
        .grid tbody tr:last-child  td { padding-bottom: 2px; }
        .grid tbody tr:nth-child(even) td { background: #fbfcfd; }
        .grid tbody tr.top1 td { background: #fef7e0 !important; }
        .grid tbody tr.top2 td { background: #f1f4f9 !important; }
        .grid tbody tr.top3 td { background: #fef2e7 !important; }
        .grid tbody tr.dq   td { background: #fce8e8 !important; color: #94a3b8; font-style: italic; }
        /* No-show: shooter did not attend — dim the whole row and lighten the
           shot cells so the executive eye skips past it instead of mistaking
           the zeros for poor performance. */
        .grid tbody tr.ns   td { background: #f4f4f5 !important; color: #a1a1aa; font-style: italic; }
        .grid tbody tr.ns td.cal,
        .grid tbody tr.ns td.name { color: #71717a; }
        .grid tbody tr.ns .hm-miss { background: #e4e4e7 !important; }
        .grid tbody tr.ns .hm-miss .shot,
        .grid tbody tr.ns .hm-hit .shot { opacity: 0.55; }

        .grid td.pos {
            font-weight: 800;
            color: #94a3b8;
            font-size: 7pt;
            padding: 1px 0;
            letter-spacing: 0.02em;
        }
        .grid tr.top1 td.pos { color: #b45309; }
        .grid tr.top2 td.pos { color: #475569; }
        .grid tr.top3 td.pos { color: #9a3412; }

        .grid td.name {
            text-align: left;
            padding: 2px 4px 2px 8px;
            font-weight: 700;
            color: #0b1220;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 7pt;
            letter-spacing: -0.005em;
        }
        .grid td.cal {
            text-align: left;
            padding: 2px 8px 2px 6px;
            color: #94a3b8;
            font-size: 6.5pt;
            white-space: nowrap;
            /* Soft clip for extreme-length calibers, but most common strings
             * (e.g. "30 Sherman Max", "6.5 Creedmoor", "6.5 PRCW") now fit. */
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 110px;
            letter-spacing: 0.02em;
        }
        .grid td.score {
            font-weight: 800;
            color: #0b1220;
            font-variant-numeric: tabular-nums;
            font-size: 8pt;
            letter-spacing: -0.01em;
        }
        .grid tr.top1 td.score { color: #b45309; }
        .grid td.rate {
            color: #475569;
            font-size: 7pt;
            font-variant-numeric: tabular-nums;
        }

        /* ─── Shot cells — tick / cross scorecard ───
           Every cell renders as a compact icon tile. Gotenberg/Chromium handles
           SVG reliably, so we use inline SVG glyphs for both hit (✓) and miss (✗)
           to guarantee print fidelity regardless of fallback fonts. */
        .grid td.hm-hit,
        .grid td.hm-miss,
        .grid td.hm-none {
            padding: 1px 0;
            background: #ffffff !important;
        }
        .grid td.hm-hit  { background: #f0fdf4 !important; }
        .grid td.hm-miss { background: #fef2f2 !important; }
        .grid td.hm-none { background: #fbfcfd !important; }

        /* Keep podium-row tint present but softer so the icons stay readable. */
        .grid tr.top1 td.hm-hit  { background: #f0fdf4 !important; }
        .grid tr.top1 td.hm-miss { background: #fef6e7 !important; }
        .grid tr.top1 td.hm-none { background: #fef7e0 !important; }
        .grid tr.top2 td.hm-hit  { background: #f0fdf4 !important; }
        .grid tr.top2 td.hm-miss { background: #f1f4f9 !important; }
        .grid tr.top2 td.hm-none { background: #f1f4f9 !important; }
        .grid tr.top3 td.hm-hit  { background: #f0fdf4 !important; }
        .grid tr.top3 td.hm-miss { background: #fef2e7 !important; }
        .grid tr.top3 td.hm-none { background: #fef2e7 !important; }

        .shot {
            display: inline-block;
            width: 11px;
            height: 11px;
            line-height: 0;
            vertical-align: middle;
        }
        .shot svg { display: block; width: 11px; height: 11px; }

        /* Distance boundary — softer vertical separator */
        .grid td.dist-end,
        .grid th.dist-end { border-right: 1.5px solid #cbd5e1; }

        /* ─── Legend ─── */
        .legend {
            margin-top: 14px;
            padding: 10px 2px 6px;
            border-top: 1px solid #e8edf4;
            font-size: 6.5pt;
            color: #94a3b8;
            letter-spacing: 0.06em;
        }
        .legend .icon {
            display: inline-block;
            width: 11px;
            height: 11px;
            vertical-align: middle;
            margin-right: 4px;
        }
        .legend .icon svg { display: block; width: 11px; height: 11px; }
        .legend .sep { margin: 0 12px; color: #cbd5e1; }
    </style>
</head>
<body>
    @include('exports.partials.pdf-header', ['subtitle' => 'Executive Summary'])

    <div class="wrap">
        {{-- ─── Top: Podium + Stat cards ─── --}}
        <table class="top-row">
            <tr>
                <td class="podium-col">
                    <table class="podium">
                        <tr>
                            @if($podium['first'])
                                <td class="p1">
                                    <div class="rk">1st · Winner</div>
                                    <div class="nm">{{ \Illuminate\Support\Str::before($podium['first']->name, ' — ') ?: $podium['first']->name }}</div>
                                    @php $cal1 = \Illuminate\Support\Str::contains($podium['first']->name, ' — ') ? trim(\Illuminate\Support\Str::after($podium['first']->name, ' — ')) : null; @endphp
                                    @if($cal1)<div class="cal">{{ $cal1 }}</div>@endif
                                    <div class="sc">{{ $fmt($podium['first']->total_score) }}</div>
                                    <div class="sub">{{ $podium['first']->hits }} hits · {{ $podium['first']->squad }}</div>
                                </td>
                            @endif
                            @if($podium['second'])
                                <td class="p2">
                                    <div class="rk">2nd</div>
                                    <div class="nm">{{ \Illuminate\Support\Str::before($podium['second']->name, ' — ') ?: $podium['second']->name }}</div>
                                    @php $cal2 = \Illuminate\Support\Str::contains($podium['second']->name, ' — ') ? trim(\Illuminate\Support\Str::after($podium['second']->name, ' — ')) : null; @endphp
                                    @if($cal2)<div class="cal">{{ $cal2 }}</div>@endif
                                    <div class="sc">{{ $fmt($podium['second']->total_score) }}</div>
                                    <div class="sub">{{ $podium['second']->hits }} hits</div>
                                </td>
                            @endif
                            @if($podium['third'])
                                <td class="p3">
                                    <div class="rk">3rd</div>
                                    <div class="nm">{{ \Illuminate\Support\Str::before($podium['third']->name, ' — ') ?: $podium['third']->name }}</div>
                                    @php $cal3 = \Illuminate\Support\Str::contains($podium['third']->name, ' — ') ? trim(\Illuminate\Support\Str::after($podium['third']->name, ' — ')) : null; @endphp
                                    @if($cal3)<div class="cal">{{ $cal3 }}</div>@endif
                                    <div class="sc">{{ $fmt($podium['third']->total_score) }}</div>
                                    <div class="sub">{{ $podium['third']->hits }} hits</div>
                                </td>
                            @endif
                        </tr>
                    </table>
                </td>
                <td class="stats-col">
                    <table class="stats-grid">
                        <tr>
                            <td>
                                <div class="lbl">Shooters</div>
                                <div class="val">{{ $statCards['totalShooters'] }}</div>
                            </td>
                            <td>
                                <div class="lbl">Total Shots</div>
                                <div class="val">{{ number_format($statCards['totalShots']) }}</div>
                                <div class="bar"><span style="width: 100%;"></span></div>
                            </td>
                            <td>
                                <div class="lbl">Hit Rate</div>
                                <div class="val">{{ $statCards['hitRate'] }}<span class="sub">%</span></div>
                                <div class="bar"><span style="width: {{ $statCards['hitRate'] }}%;"></span></div>
                            </td>
                            <td>
                                <div class="lbl">Avg Score</div>
                                <div class="val">{{ $fmt($statCards['avgScore']) }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- ─── Distance strip ─── --}}
        <table class="dist-strip">
            <tr>
                @foreach($distanceStats as $ds)
                    <td>
                        <div class="d-label">{{ $ds['label'] }}</div>
                        <div class="d-meta">multiplier {{ $mult($ds['multiplier']) }} · {{ $ds['gong_count'] }} gongs</div>
                        <div class="d-rate">{{ $ds['hits'] }}/{{ $ds['shots'] }} · {{ $ds['hit_rate'] }}% hit rate</div>
                    </td>
                @endforeach
            </tr>
        </table>

        {{-- ─── Main Heatmap Grid ─── --}}
        <div class="section-title section-title-report">
            <span class="accent">■</span>
            MATCH REPORT
            <span class="muted">
                @if($useCardFaces)
                    Each row: 20 shots, 5 per distance · green tick = hit · red cross = miss
                @else
                    Green tick = hit · Red cross = miss · Dash = no shot recorded
                @endif
            </span>
        </div>

        <table class="grid">
            <thead>
                {{-- Distance header row --}}
                <tr>
                    <th class="pos-head" rowspan="2">#</th>
                    <th class="name-head" rowspan="2">Shooter</th>
                    <th class="cal-head" rowspan="2">Caliber</th>
                    @foreach($columnsByDist as $distM => $distGroup)
                        <th class="dist-head dist-end" colspan="{{ count($distGroup['cols']) }}">
                            {{ $distGroup['label'] }} · {{ $mult($distGroup['multiplier']) }}
                        </th>
                    @endforeach
                    <th class="score-head" rowspan="2">Score</th>
                    <th class="rate-head" rowspan="2">Hit%</th>
                </tr>
                {{-- Gong / card-face row --}}
                <tr>
                    @foreach($columnsByDist as $distM => $distGroup)
                        @foreach($distGroup['cols'] as $ci => $col)
                            @php
                                $label = $gongLabel($col['gong_number'], $ci);
                                $isSpecial = $useCardFaces && in_array($label, ['A', 'K'], true);
                            @endphp
                            <th class="shot-head{{ $ci === count($distGroup['cols']) - 1 ? ' dist-end' : '' }}">
                                @if($useCardFaces)
                                    <span class="card-face {{ $isSpecial ? 'card-face-special' : '' }}">{{ $label }}</span>
                                    <span class="card-mult">{{ $mult($col['gong_multiplier']) }}</span>
                                @else
                                    <span class="gong-num">{{ $label }}</span>
                                    <span class="gong-mult">{{ $mult($col['gong_multiplier']) }}</span>
                                @endif
                            </th>
                        @endforeach
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($heatmap as $i => $row)
                    @php
                        $rowClass = '';
                        $isNoShow = ($row['status'] ?? null) === 'no_show';
                        $isDq = ($row['status'] ?? null) === 'dq';
                        if ($isDq) $rowClass = 'dq';
                        elseif ($isNoShow) $rowClass = 'ns';
                        elseif ($row['rank'] === 1) $rowClass = 'top1';
                        elseif ($row['rank'] === 2) $rowClass = 'top2';
                        elseif ($row['rank'] === 3) $rowClass = 'top3';

                        $posLabel = match (true) {
                            $isDq => 'DQ',
                            $isNoShow => 'N/S',
                            default => $row['rank'],
                        };
                    @endphp
                    <tr class="{{ $rowClass }}">
                        <td class="pos">{{ $posLabel }}</td>
                        <td class="name">{{ $row['display_name'] }}</td>
                        <td class="cal">{{ $row['caliber'] ?? '' }}</td>
                        @php
                            $colIdx = 0;
                            $distColCounts = array_map(fn ($g) => count($g['cols']), $columnsByDist);
                            $distBoundaries = [];
                            $acc = 0;
                            foreach ($distColCounts as $n) { $acc += $n; $distBoundaries[$acc - 1] = true; }
                        @endphp
                        @foreach($row['cells'] as $idx => $cell)
                            @php
                                $cls = match ($cell['state']) {
                                    'hit' => 'hm-hit',
                                    'miss' => 'hm-miss',
                                    default => 'hm-none',
                                };
                                $endCls = isset($distBoundaries[$idx]) ? ' dist-end' : '';
                            @endphp
                            <td class="{{ $cls }}{{ $endCls }}">
                                @if($cell['state'] === 'hit')
                                    <span class="shot" title="Hit">
                                        <svg viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M2.4 6.4 L5 9 L9.8 3.5" fill="none" stroke="#15803d" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                @elseif($cell['state'] === 'miss')
                                    <span class="shot" title="Miss">
                                        <svg viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M3 3 L9 9 M9 3 L3 9" fill="none" stroke="#b91c1c" stroke-width="1.6" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                @else
                                    <span class="shot" title="No shot recorded">
                                        <svg viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M3 6 L9 6" fill="none" stroke="#cbd5e1" stroke-width="1.4" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                @endif
                            </td>
                        @endforeach
                        <td class="score">{{ $fmt($row['total_score']) }}</td>
                        <td class="rate">{{ $row['hit_rate'] }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="legend">
            <span class="icon">
                <svg viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg"><path d="M2.4 6.4 L5 9 L9.8 3.5" fill="none" stroke="#15803d" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>Hit
            <span class="sep">·</span>
            <span class="icon">
                <svg viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg"><path d="M3 3 L9 9 M9 3 L3 9" fill="none" stroke="#b91c1c" stroke-width="1.6" stroke-linecap="round"/></svg>
            </span>Miss
            <span class="sep">·</span>
            <span class="icon">
                <svg viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg"><path d="M3 6 L9 6" fill="none" stroke="#cbd5e1" stroke-width="1.4" stroke-linecap="round"/></svg>
            </span>No shot recorded
            <span class="sep">·</span>
            @if($useCardFaces)
                Royal Flush scorecard · 10 / J / Q / K / A across each distance
            @else
                Rows sorted by match rank · Podium tinted gold / silver / bronze
            @endif
        </div>

        @include('exports.partials.pdf-footer')
    </div>
</body>
</html>
