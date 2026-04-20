@php
    /**
     * Full Match Report PDF (digital-first, one tall continuous navy page).
     *
     * The surface is sized as 210mm wide (matches A4 portrait width so the
     * typographic scale is consistent with the shooter report) with height
     * `auto`, so Chromium/Gotenberg grow the page to fit all rows. No
     * horizontal page breaks, no orphan rows, no need to repeat a thead.
     * The dark navy background is unsuitable for print anyway, so we stop
     * pretending this is a printable doc and just let it scroll in the
     * viewer. Called "Executive Summary" historically — kept the filename
     * for route-stability but renamed everywhere user-facing.
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
    // $fmt renders a score as a whole number — every visible total / average
    // on the Full Match Report is rounded to the closest integer so the
    // reader doesn't have to parse decimal trailings. Multipliers keep their
    // decimal precision via $mult since a 1.5× or 2.25× factor is meaningful
    // at the sub-integer level and becomes useless when truncated.
    $fmt = fn ($v) => (string) (int) round((float) $v);
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
@php
    /*
     * $viewMode decides whether this template is being rendered for PDF
     * generation (default — Gotenberg/DomPDF) or as a live HTML page in the
     * browser. The HTML mode re-uses the exact same markup so we don't
     * diverge content between the download and the on-screen version; it
     * just layers a handful of responsive overrides and shows an action
     * bar at the top with a Download PDF button.
     */
    $viewMode = $viewMode ?? 'pdf';
    $isHtmlView = $viewMode === 'html';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @if($isHtmlView)
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @endif
    <title>{{ $match->name }} — Full Match Report</title>
    @include('exports.partials.pdf-styles-dark')
    <style>
        /* Digital-first: 210mm wide (keeps the A4-width typographic scale
           shared with the shooter report) and auto height so the whole
           report lays out on a single tall page. No page breaks, no thead
           repeats, no orphan-row juggling. Edge-to-edge navy. */
        @page { size: 210mm auto; margin: 0; background: #071327; }
        body { width: 210mm; background: #071327; }

        /* Page gutter — generous since there's no per-page footer fighting
           for vertical budget any more. */
        .wrap { padding: 16px 14px; background: #071327; }

        /* ─── Top block: podium above stat cards (stacked for portrait).
             Each block is page-break-inside:avoid so page 1 always ships a
             complete header without a split podium or split stat strip. ─── */
        .podium { page-break-inside: avoid; }

        /* Stat cards — dark tiles. Full-width 4-up strip under the podium. */
        .stats-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            table-layout: fixed;
            margin-top: 8px;
            page-break-inside: avoid;
        }
        .stats-grid td {
            border: 1px solid #31486d;
            border-radius: 5px;
            padding: 8px 10px;
            vertical-align: top;
            width: 25%;
            background: #0c1a33;
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
            background: #1e293b;
            border-radius: 2px;
            overflow: hidden;
        }
        .stats-grid .bar > span {
            display: block;
            height: 3px;
            background: #ff2b2b;
            border-radius: 2px;
        }

        /* ─── Distance chips (sub-header strip). Kept on page 1 via the
             parent .wrap orphans/widows, and page-break-inside:avoid so it
             never splits across pages. ─── */
        .dist-strip {
            width: 100%;
            border-collapse: separate;
            border-spacing: 5px 0;
            margin-top: 10px;
            page-break-inside: avoid;
            page-break-after: avoid;
        }
        .dist-strip td {
            border: 1px solid #31486d;
            border-radius: 5px;
            background: #1d2d4a;
            padding: 6px 8px 5px;
            text-align: center;
        }
        .dist-strip .d-label {
            font-size: 10pt;
            font-weight: 800;
            color: #f8fafc;
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
            color: #cbd5e1;
            font-weight: 700;
            margin-top: 3px;
            letter-spacing: 0.06em;
        }

        /* Match Report title. page-break-after:avoid keeps the heading
           glued to the table below so you never get a floating title at
           the bottom of a page. */
        .section-title-report { margin-top: 12px; margin-bottom: 6px; page-break-after: avoid; }

        /* ─── Heatmap grid ───
           Chromium/Gotenberg repeat <thead> on every page automatically for
           overflowing tables (display:table-header-group is the default). We
           let the table itself break via page-break-inside:auto, and every
           <tr> gets page-break-inside:avoid so a shooter's row is never
           split across a page boundary. ─── */
        .grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            table-layout: fixed;
            border: 1px solid #31486d;
            border-radius: 5px;
            background: #0c1a33;
            page-break-inside: auto;
        }
        .grid tr { page-break-inside: avoid; page-break-after: auto; }
        .grid thead { display: table-header-group; }
        .grid tfoot { display: table-footer-group; }
        .grid thead th {
            background: #243757;
            color: #f8fafc;
            font-weight: 700;
            padding: 4px 1px;
            text-align: center;
            font-size: 6pt;
            border-right: 1px solid #31486d;
            letter-spacing: 0.04em;
        }
        .grid thead th.dist-head {
            background: #1d2d4a;
            color: #f8fafc;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-size: 6.5pt;
            font-weight: 800;
        }
        /* Portrait widths: usable ~186mm. Fixed-width framing columns leave
           the rest to divvy up among ~20 shot cells. */
        .grid thead th.pos-head  { width: 20px; }
        .grid thead th.name-head { width: 110px; text-align: left; padding-left: 6px; letter-spacing: 0.12em; text-transform: uppercase; font-size: 5.5pt; }
        .grid thead th.cal-head  { width: 78px; text-align: left; padding-left: 4px; letter-spacing: 0.12em; text-transform: uppercase; font-size: 5.5pt; }
        .grid thead th.score-head,
        .grid thead th.rel-head,
        .grid thead th.rate-head {
            background: #1d2d4a;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-size: 5.5pt;
            width: 32px;
        }
        .grid thead th.score-head { color: #f8fafc; width: 38px; }
        .grid thead th.rel-head   { color: #fde68a; width: 32px; }
        .grid thead th.rate-head  { color: #94a3b8; }

        /* Shot cells — narrower for portrait. Glyph still sits at ~9px so
           tick/cross stay legible. */
        .grid thead th.shot-head,
        .grid td.hm-hit,
        .grid td.hm-miss,
        .grid td.hm-none { width: 14px; }

        .grid thead .gong-num  { font-size: 5.5pt; color: #f8fafc; font-weight: 700; }
        .grid thead .gong-mult { display: block; font-size: 4.5pt; color: #94a3b8; font-weight: 600; margin-top: 1px; letter-spacing: 0.04em; }

        /* Royal Flush card-face header — 10 / J / Q / K / A. Typeset crisp
           but compact so it reads like a scorecard, not a spreadsheet. */
        .grid thead .card-face {
            font-size: 6.5pt;
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
            font-size: 4.5pt;
            color: #94a3b8;
            font-weight: 600;
            margin-top: 2px;
            letter-spacing: 0.04em;
        }

        .grid tbody td {
            padding: 0;
            font-size: 6pt;
            border-bottom: 1px solid #1e293b;
            border-right: 1px solid #1e293b;
            vertical-align: middle;
            text-align: center;
            line-height: 1;
            height: 14px;
            color: #cbd5e1;
        }
        /* First and last row get a hair of extra padding so the block
           doesn't fuse against the table's own border edges. Only 2px
           each to preserve the single-page-landscape vertical budget. */
        .grid tbody tr:first-child td { padding-top: 2px; }
        .grid tbody tr:last-child  td { padding-bottom: 2px; }
        .grid tbody tr:nth-child(even) td { background: #0c1a33; }
        /* Podium row tints — flat rgba on dark so they print true. A thin
           2px left rail on the position cell carries the family signal even
           in mono print. */
        .grid tbody tr.top1 td { background: rgba(251,191,36,0.08) !important; }
        .grid tbody tr.top2 td { background: rgba(203,213,225,0.06) !important; }
        .grid tbody tr.top3 td { background: rgba(251,146,60,0.08) !important; }
        .grid tbody tr.top1 td.pos { border-left: 2px solid #d97706; }
        .grid tbody tr.top2 td.pos { border-left: 2px solid #64748b; }
        .grid tbody tr.top3 td.pos { border-left: 2px solid #c2410c; }
        .grid tbody tr.dq   td { background: rgba(239,68,68,0.08) !important; color: #64748b; font-style: italic; }
        /* No-show: shooter did not attend — dim the whole row and lighten the
           shot cells so the executive eye skips past it instead of mistaking
           the zeros for poor performance. */
        .grid tbody tr.ns   td { background: #0c1a33 !important; color: #64748b; font-style: italic; }
        .grid tbody tr.ns td.cal,
        .grid tbody tr.ns td.name { color: #64748b; }
        .grid tbody tr.ns .hm-miss { background: #0c1a33 !important; }
        .grid tbody tr.ns .hm-miss .shot,
        .grid tbody tr.ns .hm-hit .shot { opacity: 0.55; }

        .grid td.pos {
            font-weight: 800;
            color: #94a3b8;
            font-size: 7pt;
            padding: 1px 0;
            letter-spacing: 0.02em;
        }
        .grid tr.top1 td.pos { color: #fbbf24; }
        .grid tr.top2 td.pos { color: #cbd5e1; }
        .grid tr.top3 td.pos { color: #fb923c; }

        .grid td.name {
            text-align: left;
            padding: 2px 4px 2px 6px;
            font-weight: 700;
            color: #f8fafc;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 6.5pt;
            max-width: 110px;
            letter-spacing: -0.005em;
        }
        .grid td.cal {
            text-align: left;
            padding: 2px 6px 2px 4px;
            color: #94a3b8;
            font-size: 6pt;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 78px;
            letter-spacing: 0.02em;
        }
        .grid td.score {
            font-weight: 800;
            color: #f8fafc;
            font-variant-numeric: tabular-nums;
            font-size: 7pt;
            letter-spacing: -0.01em;
        }
        .grid tr.top1 td.score { color: #fbbf24; }
        .grid td.rel {
            font-weight: 700;
            color: #fde68a;
            font-variant-numeric: tabular-nums;
            font-size: 6.8pt;
        }
        .grid tr.top1 td.rel,
        .grid tr.top2 td.rel,
        .grid tr.top3 td.rel { color: #fbbf24; }
        .grid td.rate {
            color: #cbd5e1;
            font-size: 6.5pt;
            font-variant-numeric: tabular-nums;
        }

        /* ─── Shot cells — filled circle "gong dots" ───
           Unified with the shooter report (pdf-match-report.blade.php) so
           every DeadCenter PDF uses the same visual vocabulary for a shot.
           The cell background is kept transparent now — the coloured circle
           alone carries the hit/miss signal, which gives a cleaner, denser
           grid than tinted tiles behind tiny icons. */
        .grid td.hm-hit,
        .grid td.hm-miss,
        .grid td.hm-none {
            padding: 1px 0;
            background: #0c1a33 !important;
        }

        /* No more tinted cells — podium-row backgrounds alone mark the
           top-3 rows; the circles themselves carry hit/miss. */
        .grid tr.top1 td.hm-hit,
        .grid tr.top1 td.hm-miss,
        .grid tr.top1 td.hm-none { background: rgba(251,191,36,0.08) !important; }
        .grid tr.top2 td.hm-hit,
        .grid tr.top2 td.hm-miss,
        .grid tr.top2 td.hm-none { background: rgba(203,213,225,0.06) !important; }
        .grid tr.top3 td.hm-hit,
        .grid tr.top3 td.hm-miss,
        .grid tr.top3 td.hm-none { background: rgba(251,146,60,0.08) !important; }

        /* Gong dot — filled circle, identical treatment to the shooter
           report (13px there; 9px here because we have ~20 shots per row
           in portrait and need them to stay compact). */
        .shot {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            vertical-align: middle;
            line-height: 1;
        }
        .shot-hit  { background: #22c55e; }
        .shot-miss { background: #ef4444; }
        .shot-none { background: #374151; }

        /* Distance boundary — slightly firmer vertical separator on dark */
        .grid td.dist-end,
        .grid th.dist-end { border-right: 1.5px solid #31486d; }

        /* ─── Legend ─── */
        .legend {
            margin-top: 14px;
            padding: 10px 2px 6px;
            border-top: 1px solid #1e293b;
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
        .legend .legend-dot { border-radius: 50%; }
        .legend .legend-hit  { background: #22c55e; }
        .legend .legend-miss { background: #ef4444; }
        .legend .legend-none { background: #374151; }
        .legend .sep { margin: 0 12px; color: #475569; }

        /* ─── Royal Flushes by Distance ─── */
        .rf-section { margin-top: 14px; page-break-inside: avoid; }
        .rf-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 5px 0;
            table-layout: fixed;
            margin-top: 5px;
        }
        .rf-grid td {
            border: 1px solid #31486d;
            border-radius: 5px;
            background: #0c1a33;
            padding: 7px 8px;
            vertical-align: top;
        }
        .rf-grid .rf-dist {
            font-size: 8pt;
            font-weight: 800;
            color: #f8fafc;
            letter-spacing: 0.04em;
        }
        .rf-grid .rf-count {
            font-size: 18pt;
            font-weight: 800;
            color: #fbbf24;
            line-height: 1;
            margin-top: 4px;
            font-variant-numeric: tabular-nums;
        }
        .rf-grid .rf-count.zero { color: #475569; }
        .rf-grid .rf-label {
            font-size: 5.5pt;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-top: 2px;
        }
        .rf-grid .rf-names {
            margin-top: 5px;
            padding-top: 5px;
            border-top: 1px solid #1e293b;
            font-size: 6.5pt;
            color: #cbd5e1;
            line-height: 1.3;
        }
        .rf-grid .rf-names strong {
            color: #f8fafc;
            font-weight: 700;
        }

        /* ─── Match Facts ─── */
        .facts-section { margin-top: 14px; page-break-inside: avoid; }
        .fact {
            background: #0c1a33;
            border-left: 2px solid #ff2b2b;
            border-radius: 3px;
            padding: 5px 9px;
            margin-bottom: 3px;
            font-size: 7.5pt;
            color: #cbd5e1;
            line-height: 1.35;
            page-break-inside: avoid;
        }
        .fact .tag {
            display: inline-block;
            font-size: 5.5pt;
            font-weight: 800;
            color: #ff2b2b;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-right: 6px;
            vertical-align: baseline;
        }

        @if($isHtmlView)
            /* ═══════ BROWSER-ONLY OVERRIDES ═══════════════════════════════
               These collapse the fixed A4-width PDF surface into something
               that renders correctly across phones, tablets, and desktop.
               The heatmap is allowed to overflow horizontally so the
               typographic scale stays faithful to the PDF (shrinking it to
               fit a phone screen just produces unreadable text). ─── */
            html, body { min-height: 100%; }
            body {
                width: auto;
                max-width: 100%;
                margin: 0 auto;
                font-size: 11pt;
                overflow-x: hidden;
            }
            .wrap {
                max-width: 1100px;
                margin: 0 auto;
                padding: 16px clamp(12px, 3vw, 28px);
            }
            /* Sticky Download PDF bar. Placed inside <body> so all CSS
               tokens resolve against the same cascade as the report. */
            .report-actions {
                position: sticky;
                top: 0;
                z-index: 10;
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 10px;
                padding: 12px clamp(12px, 3vw, 28px);
                background: rgba(7, 19, 39, 0.92);
                backdrop-filter: blur(6px);
                border-bottom: 1px solid rgba(49, 72, 109, 0.6);
            }
            .report-actions .title {
                margin-right: auto;
                font-size: 11pt;
                font-weight: 700;
                color: #f8fafc;
                letter-spacing: 0.01em;
            }
            .report-actions a.btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 14px;
                border-radius: 8px;
                background: #ff2b2b;
                color: #fff;
                font-size: 11pt;
                font-weight: 700;
                text-decoration: none;
                letter-spacing: 0.02em;
            }
            .report-actions a.btn:hover { background: #e10600; }
            .report-actions a.btn.ghost {
                background: transparent;
                color: #cbd5e1;
                border: 1px solid #31486d;
            }
            .report-actions a.btn.ghost:hover { border-color: #94a3b8; color: #fff; }

            /* Wrap the wide heatmap so it scrolls horizontally on narrow
               screens while the rest of the report stays flush with the
               viewport. The grid's `table-layout: fixed` keeps columns
               predictable; we just need to let it exceed the container. */
            .heatmap-scroll {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin: 0 -4px;
                padding: 0 4px;
            }
            .heatmap-scroll .grid {
                min-width: 760px;
            }

            /* Stat cards collapse to 2-up on phones, 4-up on tablets+. */
            @media (max-width: 640px) {
                .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; border-spacing: 0; }
                .stats-grid tbody, .stats-grid tr { display: contents; }
                .stats-grid td { display: block; width: auto; }
            }

            /* Podium wraps on very narrow viewports. */
            @media (max-width: 520px) {
                .podium { display: grid; grid-template-columns: 1fr; gap: 8px; border-spacing: 0; }
                .podium tbody, .podium tr { display: contents; }
                .podium td { display: block; }
            }

            /* Header strip stacks vertically on phones so the org logo,
               match title, and "Published on DeadCenter" credit don't get
               crushed into ~80px columns. */
            @media (max-width: 640px) {
                .pdf-header-table, .pdf-header-table tbody, .pdf-header-table tr { display: block; width: 100%; }
                .pdf-header-table td { display: block; width: 100%; text-align: center !important; padding: 4px 0; }
                .brand-hero-logo { margin: 0 auto; max-height: 72px; max-width: 70vw; }
                .published-by { margin: 0 auto; }
                .brand-center .match-name { font-size: 16pt; }
            }
        @endif
    </style>
</head>
<body>
    @if($isHtmlView)
        <div class="report-actions">
            <span class="title">{{ $match->name }} &mdash; Full Match Report</span>
            @if(!empty($downloadUrl ?? null))
                <a class="btn" href="{{ $downloadUrl }}">Download PDF</a>
            @endif
            @if(!empty($backUrl ?? null))
                <a class="btn ghost" href="{{ $backUrl }}">Back</a>
            @endif
        </div>
    @endif
    @include('exports.partials.pdf-header', ['subtitle' => 'Full Match Report'])

    <div class="wrap">
        {{-- ─── Top: Podium (stacked for portrait) ─── --}}
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

        {{-- ─── Stat cards (full-width strip under the podium) ─── --}}
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
                    Each row: 20 shots, 5 per distance · green = hit · red = miss
                @else
                    Green = hit · Red = miss · Grey = no shot recorded
                @endif
            </span>
        </div>

        @if($isHtmlView)<div class="heatmap-scroll">@endif
        <table class="grid">
            <thead>
                {{-- Distance header row --}}
                <tr>
                    <th class="pos-head" rowspan="2">#</th>
                    <th class="name-head" rowspan="2">Shooter</th>
                    <th class="cal-head" rowspan="2">Cartridge</th>
                    @foreach($columnsByDist as $distM => $distGroup)
                        <th class="dist-head dist-end" colspan="{{ count($distGroup['cols']) }}">
                            {{ $distGroup['label'] }} · {{ $mult($distGroup['multiplier']) }}
                        </th>
                    @endforeach
                    <th class="score-head" rowspan="2">Score</th>
                    <th class="rel-head" rowspan="2" title="Score as a proportion of the winner (winner = 100)">Rel.</th>
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
                                    <span class="shot shot-hit" title="Hit"></span>
                                @elseif($cell['state'] === 'miss')
                                    <span class="shot shot-miss" title="Miss"></span>
                                @else
                                    <span class="shot shot-none" title="No shot recorded"></span>
                                @endif
                            </td>
                        @endforeach
                        <td class="score">{{ $fmt($row['total_score']) }}</td>
                        <td class="rel">{{ $row['relative_score'] !== null ? $row['relative_score'] : '—' }}</td>
                        <td class="rate">{{ $row['hit_rate'] }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if($isHtmlView)</div>@endif

        <div class="legend">
            <span class="icon legend-dot legend-hit"></span>Hit
            <span class="sep">·</span>
            <span class="icon legend-dot legend-miss"></span>Miss
            <span class="sep">·</span>
            <span class="icon legend-dot legend-none"></span>No shot recorded
            <span class="sep">·</span>
            @if($useCardFaces)
                Royal Flush scorecard · 10 / J / Q / K / A across each distance
            @else
                Rows sorted by match rank · Podium tinted gold / silver / bronze
            @endif
        </div>

        {{-- ─── Royal Flushes by Distance ───
             Only rendered for Royal Flush matches. Shows a per-distance
             card with the count of full sweeps + the shooters who pulled
             each one off. Zero-counts render as dimmed cards so the grid
             stays visually aligned even when no flushes happened. --}}
        @if(($match->royal_flush_enabled ?? false) && !empty($royalFlushesByDistance ?? []))
            <div class="rf-section">
                <div class="section-title section-title-report">
                    <span class="accent">■</span>
                    ROYAL FLUSHES BY DISTANCE
                    <span class="muted">Full-sweep = every gong at the distance · {{ array_sum($royalFlushesByDistance) }} total this match</span>
                </div>
                <table class="rf-grid">
                    <tr>
                        @foreach($distanceStats as $ds)
                            @php
                                $distM = (int) $ds['distance_meters'];
                                $count = (int) ($royalFlushesByDistance[$distM] ?? 0);
                                $names = $royalFlushShootersByDistance[$distM] ?? [];
                            @endphp
                            <td>
                                <div class="rf-dist">{{ $ds['label'] }}</div>
                                <div class="rf-count {{ $count === 0 ? 'zero' : '' }}">{{ $count }}</div>
                                <div class="rf-label">{{ $count === 1 ? 'Flush' : 'Flushes' }}</div>
                                @if(count($names) > 0)
                                    <div class="rf-names">
                                        @foreach($names as $i => $n)
                                            <strong>{{ $n }}</strong>@if($i < count($names) - 1), @endif
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </table>
            </div>
        @endif

        {{-- ─── Match Facts ─── --}}
        @if(!empty($matchFacts ?? []))
            <div class="facts-section">
                <div class="section-title section-title-report">
                    <span class="accent">■</span>
                    MATCH FACTS
                    <span class="muted">Highlights &amp; quirks from the day</span>
                </div>
                @foreach($matchFacts as $fact)
                    <div class="fact">
                        <span class="tag">{{ $fact['tag'] }}</span>{{ $fact['text'] }}
                    </div>
                @endforeach
            </div>
        @endif

        @include('exports.partials.pdf-footer')
    </div>
</body>
</html>
