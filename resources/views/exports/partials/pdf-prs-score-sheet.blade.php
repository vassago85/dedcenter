{{--
    PRS Score Sheet — the Full Match Report's main grid for PRS matches.

    Mirrors the on-screen Scoreboard "Score Sheet" tab the scorers and
    shooters know by sight: per-stage column groups, per-shot gong dots
    (green = hit, red = miss, amber = declared no-take, grey = not
    recorded), and a Total / Time pair on the right edge of each row.
    Deliberately drops the "multiplier" / "Rel.%" / per-distance chrome
    the standard heatmap shows because PRS scoring is just one point
    per hit — there are no multipliers in the discipline.

    Required vars in scope:
      $prsScoreSheet  array  see MatchExportController::buildPrsScoreSheetData
      $useCardFaces   bool   from the parent template (kept for API parity)
--}}
<style>
    /* PRS-only grid styles. Scoped via .prs-grid so they don't leak into
       the standard heatmap shared by RF / standard matches. */
    .prs-grid {
        width: 100%;
        border-collapse: collapse;
        margin-top: 4px;
        table-layout: fixed;
        border: 1px solid #31486d;
        border-radius: 5px;
        background: #0c1a33;
        page-break-inside: auto;
    }
    .prs-grid tr { page-break-inside: avoid; page-break-after: auto; }
    .prs-grid thead { display: table-header-group; }

    .prs-grid thead th {
        background: #243757;
        color: #f8fafc;
        font-weight: 700;
        padding: 4px 1px;
        text-align: center;
        font-size: 6pt;
        border-right: 1px solid #31486d;
        letter-spacing: 0.04em;
    }
    .prs-grid thead th.stage-head {
        background: #1d2d4a;
        color: #f8fafc;
        letter-spacing: 0.10em;
        text-transform: uppercase;
        font-size: 7pt;
        font-weight: 800;
        padding: 6px 4px;
    }
    .prs-grid thead th.stage-head.tb { color: #fbbf24; }
    .prs-grid thead th.stage-head .stage-tag {
        display: inline-block;
        margin-left: 4px;
        padding: 0 4px;
        font-size: 5.5pt;
        font-weight: 800;
        background: rgba(251, 191, 36, 0.18);
        color: #fbbf24;
        border-radius: 2px;
        letter-spacing: 0.08em;
    }
    .prs-grid thead th.pos-head  { width: 22px; }
    .prs-grid thead th.name-head {
        width: 130px;
        text-align: left;
        padding-left: 6px;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        font-size: 5.5pt;
    }
    .prs-grid thead th.shot-head {
        width: 13px;
        font-size: 5.5pt;
        color: #cbd5e1;
        font-weight: 600;
        background: #1a2942;
    }
    .prs-grid thead th.shot-head.stage-end { border-right: 1.5px solid #31486d; }

    .prs-grid thead th.hits-head,
    .prs-grid thead th.miss-head,
    .prs-grid thead th.nt-head,
    .prs-grid thead th.score-head,
    .prs-grid thead th.time-head {
        background: #1d2d4a;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        font-size: 5.5pt;
        width: 32px;
    }
    .prs-grid thead th.hits-head  { color: #4ade80; }
    .prs-grid thead th.miss-head  { color: #f87171; }
    .prs-grid thead th.nt-head    { color: #fbbf24; }
    .prs-grid thead th.score-head { color: #f8fafc; width: 38px; }
    .prs-grid thead th.time-head  { color: #fde68a; }

    /* Body rows + cells */
    .prs-grid tbody td {
        padding: 0;
        font-size: 6pt;
        border-bottom: 1px solid #1e293b;
        border-right: 1px solid #1e293b;
        vertical-align: middle;
        text-align: center;
        line-height: 1;
        height: 14px;
        color: #cbd5e1;
        background: #0c1a33;
    }
    .prs-grid tbody tr:first-child td { padding-top: 2px; }
    .prs-grid tbody tr:last-child  td { padding-bottom: 2px; }
    .prs-grid tbody tr:nth-child(even) td { background: #102143; }

    /* Podium tinting — flat rgba on dark so it prints true. */
    .prs-grid tbody tr.top1 td { background: rgba(251,191,36,0.10) !important; }
    .prs-grid tbody tr.top2 td { background: rgba(203,213,225,0.07) !important; }
    .prs-grid tbody tr.top3 td { background: rgba(251,146,60,0.10) !important; }
    .prs-grid tbody tr.top1 td.pos { border-left: 2px solid #d97706; }
    .prs-grid tbody tr.top2 td.pos { border-left: 2px solid #64748b; }
    .prs-grid tbody tr.top3 td.pos { border-left: 2px solid #c2410c; }
    .prs-grid tbody tr.dq td {
        background: rgba(239,68,68,0.08) !important;
        color: #94a3b8;
        font-style: italic;
    }
    .prs-grid tbody tr.ns td {
        background: #0c1a33 !important;
        color: #64748b;
        font-style: italic;
    }
    .prs-grid tbody tr.ns .shot { opacity: 0.45; }

    .prs-grid td.pos {
        font-weight: 800;
        color: #94a3b8;
        font-size: 7pt;
        padding: 1px 0;
    }
    .prs-grid tr.top1 td.pos { color: #fbbf24; }
    .prs-grid tr.top2 td.pos { color: #cbd5e1; }
    .prs-grid tr.top3 td.pos { color: #fb923c; }

    .prs-grid td.name {
        text-align: left;
        padding: 2px 4px 2px 6px;
        font-weight: 700;
        color: #f8fafc;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 6.8pt;
        max-width: 130px;
        letter-spacing: -0.005em;
    }
    .prs-grid td.name .squad {
        display: block;
        font-size: 5.5pt;
        font-weight: 500;
        color: #94a3b8;
        margin-top: 1px;
        letter-spacing: 0.04em;
    }
    .prs-grid td.shot-cell {
        padding: 1px 0;
    }
    .prs-grid td.shot-cell.stage-end { border-right: 1.5px solid #31486d; }

    .prs-grid td.hits  { color: #4ade80; font-weight: 800; font-size: 7pt; font-variant-numeric: tabular-nums; }
    .prs-grid td.miss  { color: #f87171; font-weight: 700; font-size: 6.5pt; font-variant-numeric: tabular-nums; }
    .prs-grid td.nt    { color: #fbbf24; font-weight: 600; font-size: 6.5pt; font-variant-numeric: tabular-nums; }
    .prs-grid td.score {
        font-weight: 800;
        color: #f8fafc;
        font-size: 8pt;
        font-variant-numeric: tabular-nums;
        border-left: 1.5px solid #31486d;
        letter-spacing: -0.01em;
    }
    .prs-grid tr.top1 td.score { color: #fbbf24; }
    .prs-grid td.time {
        color: #fde68a;
        font-size: 6.5pt;
        font-variant-numeric: tabular-nums;
        font-weight: 600;
    }

    /* Gong dot — same vocabulary as the on-screen Scoreboard. */
    .prs-grid .shot {
        display: inline-block;
        width: 9px;
        height: 9px;
        border-radius: 50%;
        vertical-align: middle;
        line-height: 1;
    }
    .prs-grid .shot-hit       { background: #22c55e; box-shadow: 0 0 0 1px rgba(34,197,94,0.35); }
    .prs-grid .shot-miss      { background: #ef4444; box-shadow: 0 0 0 1px rgba(239,68,68,0.35); }
    .prs-grid .shot-not_taken { background: #b45309; box-shadow: 0 0 0 1px rgba(180,83,9,0.45); }
    .prs-grid .shot-none      { background: #374151; }

    /* PRS stage-time strip under the title — compact summary of who set
       the fastest stage time and the match's average tempo. */
    .prs-stage-strip {
        width: 100%;
        border-collapse: separate;
        border-spacing: 5px 0;
        margin-top: 10px;
        page-break-inside: avoid;
        page-break-after: avoid;
    }
    .prs-stage-strip td {
        border: 1px solid #31486d;
        border-radius: 5px;
        background: #1d2d4a;
        padding: 6px 8px 5px;
        text-align: center;
        vertical-align: top;
    }
    .prs-stage-strip td.tb { background: linear-gradient(180deg, rgba(251,191,36,0.12), #1d2d4a); border-color: rgba(251,191,36,0.45); }
    .prs-stage-strip .ps-label {
        font-size: 9pt;
        font-weight: 800;
        color: #f8fafc;
        letter-spacing: -0.01em;
    }
    .prs-stage-strip .ps-meta {
        font-size: 6pt;
        color: #94a3b8;
        margin-top: 3px;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }
    .prs-stage-strip .ps-rate {
        font-size: 7pt;
        color: #cbd5e1;
        font-weight: 700;
        margin-top: 3px;
        letter-spacing: 0.04em;
    }
    .prs-stage-strip .ps-tb-tag {
        display: inline-block;
        margin-left: 4px;
        padding: 0 4px;
        font-size: 5.5pt;
        font-weight: 800;
        background: rgba(251, 191, 36, 0.20);
        color: #fbbf24;
        border-radius: 2px;
        letter-spacing: 0.08em;
    }

    /* PRS legend — a notch denser than the standard one because we
       have an extra "no-take" state to call out. */
    .prs-legend {
        margin-top: 14px;
        padding: 10px 2px 6px;
        border-top: 1px solid #1e293b;
        font-size: 6.5pt;
        color: #94a3b8;
        letter-spacing: 0.06em;
    }
    .prs-legend .icon {
        display: inline-block;
        width: 11px;
        height: 11px;
        border-radius: 50%;
        vertical-align: middle;
        margin-right: 4px;
    }
    .prs-legend .lg-hit       { background: #22c55e; }
    .prs-legend .lg-miss      { background: #ef4444; }
    .prs-legend .lg-not_taken { background: #b45309; }
    .prs-legend .lg-none      { background: #374151; }
    .prs-legend .sep { margin: 0 12px; color: #475569; }
</style>

@php
    // Per-stage aggregates for the strip above the grid: hit-rate per
    // stage + the fastest official time so the report leads with the
    // signal scorers actually care about.
    $stageAggregates = [];
    foreach ($prsScoreSheet['stages'] as $stage) {
        $sid = $stage['stage_id'];
        $hits = 0;
        $misses = 0;
        $fastestTime = null;
        $fastestName = null;
        foreach ($prsScoreSheet['rows'] as $row) {
            if (($row['status'] ?? null) === 'no_show' || ($row['status'] ?? null) === 'dq') continue;
            $st = $row['stages'][$sid] ?? null;
            if (!$st) continue;
            $hits += $st['hits'];
            $misses += $st['misses'];
            if ($st['time'] !== null && ($fastestTime === null || $st['time'] < $fastestTime) && $st['hits'] > 0) {
                $fastestTime = $st['time'];
                $fastestName = $row['display_name'];
            }
        }
        $shots = $hits + $misses;
        $stageAggregates[$sid] = [
            'hits' => $hits,
            'shots' => $shots,
            'hit_rate' => $shots > 0 ? round(($hits / $shots) * 100) : 0,
            'fastest_time' => $fastestTime,
            'fastest_name' => $fastestName,
        ];
    }
@endphp

{{-- Per-stage summary strip (replaces the "distance" strip used by RF/standard) --}}
<table class="prs-stage-strip">
    <tr>
        @foreach($prsScoreSheet['stages'] as $stage)
            @php $agg = $stageAggregates[$stage['stage_id']] ?? null; @endphp
            <td class="{{ $stage['is_tiebreaker'] ? 'tb' : '' }}">
                <div class="ps-label">
                    {{ $stage['short_label'] }}
                    @if($stage['is_tiebreaker'])<span class="ps-tb-tag">TB</span>@endif
                </div>
                <div class="ps-meta">{{ $stage['gong_count'] }} gongs</div>
                @if($agg)
                    <div class="ps-rate">
                        {{ $agg['hits'] }}/{{ $agg['shots'] }}
                        <span style="color:#94a3b8;">·</span>
                        {{ $agg['hit_rate'] }}%
                    </div>
                    @if($agg['fastest_name'] !== null)
                        <div class="ps-rate" style="color:#fde68a; font-size:6pt; margin-top:2px;">
                            {{ number_format($agg['fastest_time'], 1) }}s — {{ $agg['fastest_name'] }}
                        </div>
                    @endif
                @endif
            </td>
        @endforeach
    </tr>
</table>

{{-- Section title --}}
<div class="section-title section-title-report">
    <span class="accent">■</span>
    SCORE SHEET
    <span class="muted">
        Each row: every shot of the match · green = hit · red = miss · amber = no-take · grey = no shot recorded
    </span>
</div>

{{-- The grid itself --}}
@if($isHtmlView)<div class="heatmap-scroll">@endif
<table class="prs-grid">
    <thead>
        <tr>
            <th class="pos-head" rowspan="2">#</th>
            <th class="name-head" rowspan="2">Shooter</th>
            @foreach($prsScoreSheet['stages'] as $stage)
                <th class="stage-head{{ $stage['is_tiebreaker'] ? ' tb' : '' }}" colspan="{{ $stage['gong_count'] }}">
                    {{ $stage['short_label'] }}
                    @if($stage['is_tiebreaker'])<span class="stage-tag">TB</span>@endif
                </th>
            @endforeach
            <th class="hits-head"  rowspan="2">Hits</th>
            <th class="miss-head"  rowspan="2">Miss</th>
            <th class="nt-head"    rowspan="2">N/T</th>
            <th class="score-head" rowspan="2">Total</th>
            <th class="time-head"  rowspan="2">Time</th>
        </tr>
        <tr>
            @foreach($prsScoreSheet['stages'] as $stage)
                @for($g = 1; $g <= $stage['gong_count']; $g++)
                    <th class="shot-head{{ $g === $stage['gong_count'] ? ' stage-end' : '' }}">{{ $g }}</th>
                @endfor
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($prsScoreSheet['rows'] as $row)
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
                <td class="name">
                    {{ $row['display_name'] }}
                    @if(!empty($row['squad']))
                        <span class="squad">{{ $row['squad'] }}</span>
                    @endif
                </td>
                @foreach($prsScoreSheet['stages'] as $stage)
                    @php $stageData = $row['stages'][$stage['stage_id']] ?? null; @endphp
                    @for($g = 0; $g < $stage['gong_count']; $g++)
                        @php
                            $cell = $stageData['cells'][$g] ?? 'none';
                            $endCls = ($g === $stage['gong_count'] - 1) ? ' stage-end' : '';
                        @endphp
                        <td class="shot-cell{{ $endCls }}">
                            <span class="shot shot-{{ $cell }}" title="{{ ucfirst(str_replace('_', ' ', $cell)) }}"></span>
                        </td>
                    @endfor
                @endforeach
                <td class="hits">{{ $row['total_hits'] }}</td>
                <td class="miss">{{ $row['total_misses'] }}</td>
                <td class="nt">{{ $row['total_not_taken'] }}</td>
                <td class="score">{{ $row['total_hits'] }}</td>
                <td class="time">{{ $row['total_time'] !== null ? number_format($row['total_time'], 1) . 's' : '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@if($isHtmlView)</div>@endif

<div class="prs-legend">
    <span class="icon lg-hit"></span>Hit
    <span class="sep">·</span>
    <span class="icon lg-miss"></span>Miss
    <span class="sep">·</span>
    <span class="icon lg-not_taken"></span>No-take
    <span class="sep">·</span>
    <span class="icon lg-none"></span>No shot recorded
    <span class="sep">·</span>
    PRS scoring · 1 hit = 1 point, no multipliers · podium tinted gold / silver / bronze
</div>
