@php
    /**
     * Individual Shooter Report (A4 Portrait, 3 pages).
     *
     * Page 1: Hero + podium strip + stat cards + compact stage summary table
     * Page 2: Detailed per-stage cards + match insights
     * Page 3: Badges & achievements grid (+ RF awards for royal flush matches)
     *
     * @var \App\Models\ShootingMatch $match
     * @var \App\Models\Shooter $shooter
     * @var object $myStanding
     * @var array  $myDistances
     * @var int|null $myRank
     * @var float $myScore
     * @var int   $fieldSize
     * @var float $fieldAvg
     * @var array $insights
     * @var array $badges
     * @var object|null $myRf
     * @var array  $podium
     * @var \Illuminate\Support\Collection $standings
     * @var \Carbon\Carbon $generatedAt
     */
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $mult = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') . '×';

    $shooterDisplay = \Illuminate\Support\Str::before($myStanding?->name ?? $shooter->name, ' — ');
    if ($shooterDisplay === '') { $shooterDisplay = $myStanding?->name ?? $shooter->name; }
    $shooterCaliber = null;
    $origName = $myStanding?->name ?? $shooter->name;
    foreach ([' — ', ' – ', ' - '] as $sep) {
        if (str_contains($origName, $sep)) {
            $shooterCaliber = trim(explode($sep, $origName, 2)[1] ?? '');
            break;
        }
    }

    $totalHits = $myStanding?->hits ?? 0;
    $totalMisses = $myStanding?->misses ?? 0;
    $totalShots = $totalHits + $totalMisses;
    $hitRate = $totalShots > 0 ? round(($totalHits / $totalShots) * 100) : 0;

    $rankLabel = match (true) {
        $myStanding?->status === 'dq' => 'DQ',
        $myRank === 1 => '1ST',
        $myRank === 2 => '2ND',
        $myRank === 3 => '3RD',
        $myRank !== null => $myRank . 'TH',
        default => '—',
    };
    $rankOfField = $myRank !== null ? ($myRank . ' / ' . $fieldSize) : '—';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $match->name }} — {{ $shooterDisplay }}</title>
    @include('exports.partials.pdf-styles')
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { width: 210mm; }
        .wrap { padding: 14px 16px 12px; }

        /* ─── HERO ─── */
        .hero {
            border: 1px solid #e8edf4;
            border-top: 3px solid #e10600;
            border-radius: 5px;
            padding: 16px 20px;
            margin-top: 8px;
            background: #ffffff;
        }
        .hero-grid { width: 100%; border-collapse: collapse; }
        .hero-grid td { vertical-align: middle; }
        .hero-left  { width: 60%; padding-right: 14px; }
        .hero-right { width: 40%; text-align: right; }

        .hero .eyebrow {
            font-size: 6.5pt;
            font-weight: 800;
            color: #e10600;
            letter-spacing: 0.28em;
            text-transform: uppercase;
        }
        .hero .name {
            font-size: 24pt;
            font-weight: 800;
            color: #0b1220;
            line-height: 1.02;
            margin-top: 6px;
            letter-spacing: -0.02em;
        }
        .hero .caliber {
            font-size: 9.5pt;
            color: #475569;
            margin-top: 8px;
            letter-spacing: 0.02em;
        }
        .hero .squad {
            font-size: 8.5pt;
            color: #94a3b8;
            margin-top: 3px;
            letter-spacing: 0.03em;
        }

        .hero-rank {
            display: inline-block;
            background: #0b1220;
            color: #f8fafc;
            padding: 12px 20px;
            line-height: 1;
            border-radius: 5px;
            border-top: 2px solid #e10600;
        }
        .hero-rank .rk-val {
            font-size: 32pt;
            font-weight: 900;
            letter-spacing: -0.04em;
            color: #f8fafc;
            line-height: 0.9;
        }
        .hero-rank .rk-lbl {
            font-size: 6.5pt;
            font-weight: 800;
            letter-spacing: 0.28em;
            color: #94a3b8;
            text-transform: uppercase;
            margin-top: 6px;
        }
        .hero-rank .rk-of {
            font-size: 8pt;
            color: #cbd5e1;
            margin-top: 4px;
            letter-spacing: 0.06em;
        }

        /* ─── Stat cards ─── */
        .stat-row {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin-top: 10px;
            table-layout: fixed;
        }
        .stat-row td {
            border: 1px solid #e8edf4;
            border-radius: 5px;
            padding: 12px 14px;
            vertical-align: top;
            width: 25%;
            background: #ffffff;
        }
        .stat-row .lbl {
            font-size: 6pt;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.22em;
        }
        .stat-row .val {
            font-size: 22pt;
            font-weight: 800;
            color: #0b1220;
            font-variant-numeric: tabular-nums;
            line-height: 1;
            margin-top: 8px;
            letter-spacing: -0.03em;
        }
        .stat-row .val .sub {
            font-size: 10pt;
            color: #94a3b8;
            font-weight: 600;
            letter-spacing: 0;
            margin-left: 1px;
        }
        .stat-row .bar {
            margin-top: 8px;
            height: 3px;
            background: #f5f7fa;
            border-radius: 2px;
            overflow: hidden;
        }
        .stat-row .bar > span {
            display: block;
            height: 3px;
            background: #e10600;
            border-radius: 2px;
        }

        /* ─── Field context strip ─── */
        .field-ctx {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin-top: 10px;
        }
        .field-ctx td {
            border: 1px solid #e8edf4;
            border-radius: 5px;
            padding: 9px 12px;
            vertical-align: top;
            width: 33.33%;
            background: #fbfcfd;
        }
        .field-ctx .lbl {
            font-size: 6pt;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.22em;
        }
        .field-ctx .val {
            font-size: 14pt;
            font-weight: 800;
            color: #0b1220;
            margin-top: 4px;
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.02em;
        }
        .field-ctx .sub {
            font-size: 7pt;
            color: #94a3b8;
            margin-top: 2px;
            letter-spacing: 0.04em;
        }

        /* ─── Stage summary (page 1) ─── */
        .stage-summary {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            border: 1px solid #e8edf4;
            border-radius: 5px;
            overflow: hidden;
        }
        .stage-summary th {
            background: #0b1220;
            color: #f8fafc;
            padding: 7px 10px;
            text-align: left;
            font-size: 6.5pt;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }
        .stage-summary th.num { text-align: right; }
        .stage-summary td {
            border-bottom: 1px solid #eef2f7;
            padding: 8px 10px;
            font-size: 8.5pt;
            vertical-align: middle;
        }
        .stage-summary tr:last-child td { border-bottom: none; }
        .stage-summary tr:nth-child(even) td { background: #fbfcfd; }
        .stage-summary td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .stage-summary .dist-name {
            font-weight: 800;
            color: #0b1220;
            letter-spacing: -0.01em;
        }
        .stage-summary .dist-sub {
            font-size: 6.5pt;
            color: #94a3b8;
            display: block;
            margin-top: 2px;
            letter-spacing: 0.04em;
        }
        .stage-summary .rate-bar {
            height: 4px;
            width: 64px;
            background: #f5f7fa;
            display: inline-block;
            vertical-align: middle;
            margin-right: 8px;
            border-radius: 2px;
            overflow: hidden;
        }
        .stage-summary .rate-bar span {
            display: block;
            height: 4px;
            background: #15803d;
            border-radius: 2px;
        }
        .stage-summary tr.clean td { background: #f1faf4 !important; }
        .stage-summary tr.clean .dist-name { color: #15803d; }

        /* ─── PAGE 2 — Stage cards ─── */
        .stage-card {
            margin-top: 12px;
            border: 1px solid #e8edf4;
            border-left: 3px solid #0b1220;
            border-radius: 4px;
            padding: 12px 14px;
            page-break-inside: avoid;
            background: #ffffff;
        }
        .stage-card.clean {
            border-left-color: #15803d;
            background: #f6fcf8;
            border-color: #d7ead9;
        }
        .stage-card .hdr {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .stage-card .hdr td { vertical-align: middle; }
        .stage-card .hdr .ttl-wrap  { width: 65%; }
        .stage-card .hdr .stat-wrap { width: 35%; text-align: right; }
        .stage-card .ttl {
            font-size: 14pt;
            font-weight: 800;
            color: #0b1220;
            letter-spacing: -0.02em;
        }
        .stage-card .meta {
            font-size: 7.5pt;
            color: #94a3b8;
            margin-top: 3px;
            letter-spacing: 0.04em;
        }
        .stage-card .subtotal-val {
            font-size: 22pt;
            font-weight: 800;
            color: #0b1220;
            font-variant-numeric: tabular-nums;
            line-height: 1;
            letter-spacing: -0.03em;
        }
        .stage-card.clean .subtotal-val { color: #15803d; }
        .stage-card .subtotal-lbl {
            font-size: 6pt;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.22em;
            margin-top: 4px;
        }

        .gong-strip {
            width: 100%;
            border-collapse: separate;
            border-spacing: 3px 0;
            margin-top: 6px;
            table-layout: fixed;
        }
        .gong-strip td {
            padding: 7px 4px;
            text-align: center;
            border: 1px solid #e8edf4;
            border-radius: 3px;
            vertical-align: middle;
            font-size: 8pt;
            background: #ffffff;
        }
        .gong-strip td.gong-hit {
            background: #15803d;
            border-color: #15803d;
            color: #ffffff;
            font-weight: 800;
        }
        .gong-strip td.gong-miss {
            background: #fce8e8;
            border-color: #f3c4c4;
            color: #b91c1c;
            font-weight: 600;
        }
        .gong-strip td.gong-none {
            background: #f5f7fa;
            border-color: #e8edf4;
            color: #94a3b8;
        }
        .gong-strip .gong-top {
            font-size: 6pt;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            opacity: 0.75;
        }
        .gong-strip .gong-val {
            font-size: 10pt;
            font-weight: 800;
            margin-top: 3px;
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.01em;
        }
        .gong-strip .gong-sub {
            font-size: 6pt;
            margin-top: 2px;
            letter-spacing: 0.04em;
        }

        /* Insights */
        .insights {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin-top: 12px;
            table-layout: fixed;
        }
        .insights td {
            border: 1px solid #e8edf4;
            border-radius: 5px;
            padding: 12px 14px;
            vertical-align: top;
            width: 25%;
            background: #ffffff;
        }
        .insights .lbl {
            font-size: 6pt;
            font-weight: 800;
            color: #e10600;
            text-transform: uppercase;
            letter-spacing: 0.24em;
        }
        .insights .val {
            font-size: 11pt;
            font-weight: 800;
            color: #0b1220;
            margin-top: 6px;
            line-height: 1.25;
            letter-spacing: -0.01em;
        }
        .insights .sub {
            font-size: 7pt;
            color: #94a3b8;
            margin-top: 3px;
            letter-spacing: 0.03em;
        }

        /* ─── PAGE 3 — Badges grid ─── */
        .badge-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin-top: 10px;
        }
        .badge-cell {
            border: 1px solid #e8edf4;
            border-radius: 5px;
            padding: 12px;
            vertical-align: top;
            width: 33.33%;
            background: #ffffff;
        }
        .badge-cell.rf {
            border-color: #f0c674;
            background: linear-gradient(180deg, #fef7e0 0%, #ffffff 70%);
            border-top: 2px solid #b45309;
        }
        .badge-cell.prs {
            border-color: #bae6fd;
            background: linear-gradient(180deg, #f0f9ff 0%, #ffffff 70%);
            border-top: 2px solid #0284c7;
        }
        .badge-cell.featured { border-width: 1px; }
        .badge-cell .b-icon {
            width: 32px;
            height: 32px;
            background: #0b1220;
            color: #f8fafc;
            text-align: center;
            line-height: 32px;
            font-size: 14pt;
            font-weight: 800;
            display: inline-block;
            border-radius: 4px;
        }
        .badge-cell.rf  .b-icon { background: #b45309; color: #fef7e0; }
        .badge-cell.prs .b-icon { background: #0284c7; color: #f0f9ff; }
        .badge-cell .b-body { margin-top: 8px; }
        .badge-cell .b-label {
            font-size: 10pt;
            font-weight: 800;
            color: #0b1220;
            line-height: 1.2;
            letter-spacing: -0.01em;
        }
        .badge-cell .b-desc {
            font-size: 7.5pt;
            color: #475569;
            margin-top: 4px;
            line-height: 1.4;
        }
        .badge-cell .b-tier {
            font-size: 6pt;
            font-weight: 700;
            color: #94a3b8;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            margin-top: 6px;
        }

        .no-badges {
            padding: 28px;
            text-align: center;
            border: 1px dashed #cbd5e1;
            border-radius: 5px;
            color: #94a3b8;
            font-size: 9pt;
            margin-top: 10px;
            letter-spacing: 0.02em;
        }

        /* RF awards strip */
        .rf-awards {
            margin-top: 12px;
            background: #0b1220;
            color: #f8fafc;
            padding: 14px 18px;
            border-left: 3px solid #e10600;
            border-radius: 5px;
        }
        .rf-awards .ttl {
            font-size: 7pt;
            font-weight: 800;
            letter-spacing: 0.28em;
            color: #94a3b8;
            text-transform: uppercase;
        }
        .rf-awards .flushes {
            font-size: 20pt;
            font-weight: 800;
            color: #f8fafc;
            margin-top: 6px;
            line-height: 1;
            letter-spacing: -0.02em;
        }
        .rf-awards .flush-list {
            font-size: 8.5pt;
            color: #cbd5e1;
            margin-top: 5px;
            letter-spacing: 0.03em;
        }
    </style>
</head>
<body>

{{-- ═══════════════════════════════════════════════════════════
     PAGE 1 — Hero + Stat cards + Stage summary
     ═══════════════════════════════════════════════════════════ --}}
@include('exports.partials.pdf-header', ['subtitle' => 'Shooter Report'])

<div class="wrap">
    <div class="hero">
        <table class="hero-grid">
            <tr>
                <td class="hero-left">
                    <div class="eyebrow">Competitor</div>
                    <div class="name">{{ $shooterDisplay }}</div>
                    @if($shooterCaliber)<div class="caliber">{{ $shooterCaliber }}</div>@endif
                    @if($myStanding?->squad)<div class="squad">Squad · {{ $myStanding->squad }}</div>@endif
                </td>
                <td class="hero-right">
                    <div class="hero-rank">
                        <div class="rk-val">{{ $rankLabel }}</div>
                        <div class="rk-lbl">Final Placing</div>
                        <div class="rk-of">of {{ $fieldSize }} shooters</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="stat-row">
        <tr>
            <td>
                <div class="lbl">Total Score</div>
                <div class="val">{{ $fmt($myScore) }}</div>
            </td>
            <td>
                <div class="lbl">Hit Rate</div>
                <div class="val">{{ $hitRate }}<span class="sub">%</span></div>
                <div class="bar"><span style="width: {{ $hitRate }}%;"></span></div>
            </td>
            <td>
                <div class="lbl">Hits</div>
                <div class="val">{{ $totalHits }}<span class="sub"> / {{ $totalShots }}</span></div>
            </td>
            <td>
                <div class="lbl">Misses</div>
                <div class="val">{{ $totalMisses }}</div>
            </td>
        </tr>
    </table>

    {{-- Field context strip --}}
    <table class="field-ctx">
        <tr>
            <td>
                <div class="lbl">Match Winner</div>
                <div class="val">{{ $fmt($podium['first']->total_score ?? 0) }}</div>
                <div class="sub">{{ $podium['first'] ? \Illuminate\Support\Str::before($podium['first']->name, ' — ') ?: $podium['first']->name : '—' }}</div>
            </td>
            <td>
                <div class="lbl">Field Average</div>
                <div class="val">{{ $fmt($fieldAvg) }}</div>
                <div class="sub">across {{ $fieldSize }} shooters</div>
            </td>
            <td>
                <div class="lbl">vs Field Avg</div>
                <div class="val">{{ ($myScore - $fieldAvg) >= 0 ? '+' : '' }}{{ $fmt($myScore - $fieldAvg) }}</div>
                <div class="sub">{{ $myScore > $fieldAvg ? 'above' : 'below' }} average</div>
            </td>
        </tr>
    </table>

    {{-- Stage summary table --}}
    <div class="section-title"><span class="accent">■</span>STAGE SUMMARY <span class="muted">Your performance at each distance</span></div>
    <table class="stage-summary">
        <thead>
            <tr>
                <th>Distance</th>
                <th class="num">Hits</th>
                <th class="num">Misses</th>
                <th class="num">Hit Rate</th>
                <th class="num">Points</th>
                <th class="num">Max Possible</th>
            </tr>
        </thead>
        <tbody>
            @foreach($myDistances as $dist)
                <tr class="{{ $dist['is_clean_sweep'] ? 'clean' : '' }}">
                    <td>
                        <span class="dist-name">{{ $dist['label'] }}</span>
                        <span class="dist-sub">{{ $mult($dist['distance_multiplier']) }} multiplier · {{ count($dist['gongs']) }} gongs @if($dist['is_clean_sweep']) · CLEAN SWEEP @endif</span>
                    </td>
                    <td class="num">{{ $dist['hits'] }}</td>
                    <td class="num">{{ $dist['misses'] }}</td>
                    <td class="num">
                        <span class="rate-bar"><span style="width: {{ $dist['hit_rate'] }}%;"></span></span>{{ $dist['hit_rate'] }}%
                    </td>
                    <td class="num"><strong>{{ $fmt($dist['subtotal']) }}</strong></td>
                    <td class="num">{{ $fmt($dist['max_points']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @include('exports.partials.pdf-footer')
</div>

<div class="page-break"></div>

{{-- ═══════════════════════════════════════════════════════════
     PAGE 2 — Detailed stage cards + insights
     ═══════════════════════════════════════════════════════════ --}}
@include('exports.partials.pdf-header', ['subtitle' => 'Stage Breakdown'])

<div class="wrap">
    <div class="section-title"><span class="accent">■</span>SHOT-BY-SHOT <span class="muted">Every gong at every distance — green = hit, red = miss</span></div>

    @foreach($myDistances as $dist)
        <div class="stage-card {{ $dist['is_clean_sweep'] ? 'clean' : '' }}">
            <table class="hdr">
                <tr>
                    <td class="ttl-wrap">
                        <div class="ttl">{{ $dist['label'] }}</div>
                        <div class="meta">
                            Distance multiplier {{ $mult($dist['distance_multiplier']) }}
                            · {{ count($dist['gongs']) }} gongs
                            · {{ $dist['hits'] }}/{{ $dist['hits'] + $dist['misses'] }} hits ({{ $dist['hit_rate'] }}%)
                            @if($dist['is_clean_sweep']) · CLEAN SWEEP @endif
                        </div>
                    </td>
                    <td class="stat-wrap">
                        <div class="subtotal-val">{{ $fmt($dist['subtotal']) }}</div>
                        <div class="subtotal-lbl">of {{ $fmt($dist['max_points']) }} possible</div>
                    </td>
                </tr>
            </table>
            <table class="gong-strip">
                <tr>
                    @foreach($dist['cells'] as $idx => $cell)
                        @php
                            $g = $dist['gongs'][$idx] ?? null;
                            $cls = match ($cell['state']) {
                                'hit' => 'gong-hit',
                                'miss' => 'gong-miss',
                                default => 'gong-none',
                            };
                        @endphp
                        <td class="{{ $cls }}">
                            <div class="gong-top">G{{ $g['number'] ?? '?' }}</div>
                            @if($cell['state'] === 'hit')
                                <div class="gong-val">+{{ $fmt($cell['points']) }}</div>
                                <div class="gong-sub">{{ $mult($g['multiplier'] ?? 0) }}</div>
                            @elseif($cell['state'] === 'miss')
                                <div class="gong-val">MISS</div>
                                <div class="gong-sub">{{ $mult($g['multiplier'] ?? 0) }}</div>
                            @else
                                <div class="gong-val">—</div>
                                <div class="gong-sub">no shot</div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>
    @endforeach

    <div class="section-title"><span class="accent">■</span>MATCH INSIGHTS <span class="muted">Auto-computed from your scores</span></div>
    <table class="insights">
        <tr>
            @foreach($insights as $ins)
                <td>
                    <div class="lbl">{{ $ins['label'] }}</div>
                    <div class="val">{{ $ins['value'] }}</div>
                    @if(!empty($ins['sub']))<div class="sub">{{ $ins['sub'] }}</div>@endif
                </td>
            @endforeach
        </tr>
    </table>

    @include('exports.partials.pdf-footer')
</div>

<div class="page-break"></div>

{{-- ═══════════════════════════════════════════════════════════
     PAGE 3 — Badges + RF awards
     ═══════════════════════════════════════════════════════════ --}}
@include('exports.partials.pdf-header', ['subtitle' => 'Achievements'])

<div class="wrap">
    @if($match->royal_flush_enabled && $myRf)
        <div class="rf-awards">
            <div class="ttl">♦ ROYAL FLUSH · DISTANCES CLEARED</div>
            <div class="flushes">
                {{ $myRf->flush_count }} flush{{ $myRf->flush_count === 1 ? '' : 'es' }}
            </div>
            <div class="flush-list">
                {{ implode('m · ', $myRf->flush_distances) }}m
            </div>
        </div>
    @endif

    <div class="section-title"><span class="accent">■</span>BADGES EARNED THIS MATCH <span class="muted">{{ count($badges) }} total</span></div>

    @if(empty($badges))
        <div class="no-badges">
            No badges were awarded for this match.<br>
            <small style="color: #94a3b8; font-size: 7pt;">Earn badges by finishing on the podium, clearing a full distance, or hitting your first Royal Flush.</small>
        </div>
    @else
        @php
            $rows = array_chunk($badges, 3);
        @endphp
        <table class="badge-grid">
            @foreach($rows as $row)
                <tr>
                    @foreach($row as $badge)
                        @php
                            $a = $badge->achievement;
                            $family = $a->competition_type ?? 'prs';
                            $cfg = \App\Http\Controllers\BadgeGalleryController::BADGE_CONFIG[$a->slug] ?? [];
                            $tier = $cfg['tier'] ?? 'earned';
                            $icon = $cfg['icon'] ?? 'target';
                            $letter = strtoupper(substr($a->label ?? $a->slug, 0, 1));
                        @endphp
                        <td class="badge-cell {{ $family }} {{ $tier === 'featured' || $tier === 'elite' ? 'featured' : '' }}">
                            <div class="b-icon">{{ $letter }}</div>
                            <div class="b-body">
                                <div class="b-label">{{ $a->label }}</div>
                                <div class="b-desc">{{ $a->description }}</div>
                                <div class="b-tier">{{ $family === 'royal_flush' ? 'ROYAL FLUSH' : 'PRS' }} · {{ strtoupper($tier) }}</div>
                            </div>
                        </td>
                    @endforeach
                    @for($pad = count($row); $pad < 3; $pad++)
                        <td class="badge-cell" style="visibility: hidden;">&nbsp;</td>
                    @endfor
                </tr>
            @endforeach
        </table>
    @endif

    @include('exports.partials.pdf-footer')
</div>

</body>
</html>
