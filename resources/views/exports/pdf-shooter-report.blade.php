@php
    /**
     * Individual Shooter Report (A4 Portrait, SINGLE PAGE).
     *
     * All the same data that used to span 3 pages, compressed onto one A4 page:
     *   • Hero strip (name, caliber, squad, final placing)
     *   • Score / hit-rate / hits / misses stat row
     *   • Field context (winner, field avg, vs avg)
     *   • Combined stage breakdown — one row per distance with the per-gong
     *     tick/cross strip INLINE (was split across a summary table on page 1
     *     and stage cards on page 2 — now merged into one compact row).
     *   • Match insights
     *   • Badges + Royal Flush awards (side-by-side)
     *
     * No content was removed. Nothing about scoring, ranking or data-shape
     * changed. Only layout / typography tightened to fit A4 portrait.
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

    // Royal Flush scorecard labels — 10 / J / Q / K / A — only when this is an
    // RF match and every distance exposes exactly 5 gongs. Mirrors the gating
    // used by the Executive Summary so the two reports speak the same language.
    $allFiveUp = ! empty($myDistances)
        && collect($myDistances)->every(fn ($d) => count($d['gongs'] ?? []) === 5);
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
    <title>{{ $match->name }} — {{ $shooterDisplay }}</title>
    @include('exports.partials.pdf-styles')
    <style>
        @page { size: A4 portrait; margin: 0; }
        body  { width: 210mm; }
        .wrap { padding: 10px 14px 10px; }

        /* Tighten the shared section-title spacing for a single-page layout. */
        .wrap .section-title {
            margin-top: 10px;
            margin-bottom: 4px;
            font-size: 7pt;
            letter-spacing: 0.18em;
        }

        /* ─── HERO (compact) ─── */
        .hero {
            border: 1px solid #e8edf4;
            border-top: 2px solid #e10600;
            border-radius: 4px;
            padding: 10px 14px;
            margin-top: 4px;
            background: #ffffff;
        }
        .hero-grid { width: 100%; border-collapse: collapse; }
        .hero-grid td { vertical-align: middle; }
        .hero-left  { width: 62%; padding-right: 10px; }
        .hero-right { width: 38%; text-align: right; }

        .hero .eyebrow {
            font-size: 6pt;
            font-weight: 800;
            color: #e10600;
            letter-spacing: 0.26em;
            text-transform: uppercase;
        }
        .hero .name {
            font-size: 17pt;
            font-weight: 800;
            color: #0b1220;
            line-height: 1.02;
            margin-top: 3px;
            letter-spacing: -0.02em;
        }
        .hero .meta-line {
            font-size: 8pt;
            color: #475569;
            margin-top: 4px;
            letter-spacing: 0.02em;
        }
        .hero .meta-line .sep {
            color: #cbd5e1;
            margin: 0 6px;
        }

        .hero-rank {
            display: inline-block;
            background: #0b1220;
            color: #f8fafc;
            padding: 7px 14px;
            line-height: 1;
            border-radius: 4px;
            border-top: 2px solid #e10600;
        }
        .hero-rank .rk-val {
            font-size: 20pt;
            font-weight: 900;
            letter-spacing: -0.04em;
            color: #f8fafc;
            line-height: 0.95;
        }
        .hero-rank .rk-lbl {
            font-size: 5.5pt;
            font-weight: 800;
            letter-spacing: 0.26em;
            color: #94a3b8;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .hero-rank .rk-of {
            font-size: 6.5pt;
            color: #cbd5e1;
            margin-top: 2px;
            letter-spacing: 0.06em;
        }

        /* ─── Compact stat row + field ctx (merged into a single 7-column strip) ─── */
        .kpi-row {
            width: 100%;
            border-collapse: separate;
            border-spacing: 4px 0;
            margin-top: 6px;
            table-layout: fixed;
        }
        .kpi-row td {
            border: 1px solid #e8edf4;
            border-radius: 4px;
            padding: 7px 9px;
            vertical-align: top;
            background: #ffffff;
        }
        .kpi-row td.ctx { background: #fbfcfd; }
        .kpi-row .lbl {
            font-size: 5.5pt;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.22em;
        }
        .kpi-row .val {
            font-size: 13pt;
            font-weight: 800;
            color: #0b1220;
            font-variant-numeric: tabular-nums;
            line-height: 1;
            margin-top: 4px;
            letter-spacing: -0.02em;
        }
        .kpi-row .val .sub {
            font-size: 7pt;
            color: #94a3b8;
            font-weight: 600;
            letter-spacing: 0;
            margin-left: 1px;
        }
        .kpi-row .bar {
            margin-top: 4px;
            height: 2px;
            background: #f5f7fa;
            border-radius: 2px;
            overflow: hidden;
        }
        .kpi-row .bar > span {
            display: block;
            height: 2px;
            background: #e10600;
            border-radius: 2px;
        }
        .kpi-row .caption {
            font-size: 6.5pt;
            color: #94a3b8;
            margin-top: 3px;
            letter-spacing: 0.04em;
        }

        /* ─── Combined stage breakdown (was two sections, now one) ─── */
        .stage-block {
            border: 1px solid #e8edf4;
            border-radius: 4px;
            overflow: hidden;
        }
        .stage-row {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1px solid #eef2f7;
        }
        .stage-block .stage-row:last-child { border-bottom: none; }
        .stage-block .stage-row.clean { background: #f6fcf8; }

        .stage-row > tbody > tr > td { vertical-align: middle; padding: 6px 8px; }

        .stage-row .s-label {
            width: 20%;
        }
        .stage-row .s-label .nm {
            font-size: 10pt;
            font-weight: 800;
            color: #0b1220;
            letter-spacing: -0.01em;
        }
        .stage-row.clean .s-label .nm { color: #15803d; }
        .stage-row .s-label .sub {
            font-size: 6pt;
            color: #94a3b8;
            margin-top: 2px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .stage-row .s-cells {
            width: 52%;
            padding-right: 6px;
        }
        .stage-row .s-summary {
            width: 14%;
            text-align: right;
            border-left: 1px solid #eef2f7;
        }
        .stage-row .s-points {
            width: 14%;
            text-align: right;
            border-left: 1px solid #eef2f7;
        }
        .stage-row .s-summary .big,
        .stage-row .s-points  .big {
            font-size: 11pt;
            font-weight: 800;
            color: #0b1220;
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.01em;
            line-height: 1;
        }
        .stage-row.clean .s-points .big { color: #15803d; }
        .stage-row .s-summary .tiny,
        .stage-row .s-points  .tiny {
            font-size: 6pt;
            color: #94a3b8;
            margin-top: 3px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .stage-row .rate-bar {
            height: 3px;
            width: 100%;
            max-width: 70px;
            background: #f5f7fa;
            display: inline-block;
            margin-top: 4px;
            border-radius: 2px;
            overflow: hidden;
        }
        .stage-row .rate-bar span {
            display: block;
            height: 3px;
            background: #15803d;
            border-radius: 2px;
        }

        /* Shot strip inside a stage row (tick/cross tiles, 5 per distance) */
        .shot-strip {
            width: 100%;
            border-collapse: separate;
            border-spacing: 3px 0;
            table-layout: fixed;
        }
        .shot-strip td {
            padding: 3px 0;
            text-align: center;
            border: 1px solid #e8edf4;
            border-radius: 3px;
            vertical-align: middle;
            background: #ffffff;
            height: 26px;
        }
        .shot-strip td.gong-hit  { background: #f0fdf4; border-color: #bbf7d0; }
        .shot-strip td.gong-miss { background: #fef2f2; border-color: #fecaca; }
        .shot-strip td.gong-none { background: #fbfcfd; border-color: #e8edf4; }

        .shot-strip .lbl-top {
            font-size: 6.5pt;
            font-weight: 800;
            color: #475569;
            letter-spacing: 0.04em;
            line-height: 1;
        }
        .shot-strip .lbl-top.special { color: #b91c1c; }
        .shot-strip .icon {
            display: inline-block;
            width: 10px;
            height: 10px;
            line-height: 0;
            vertical-align: middle;
            margin-top: 2px;
        }
        .shot-strip .icon svg {
            display: block;
            width: 10px;
            height: 10px;
        }
        .shot-strip .pts {
            display: block;
            font-size: 6pt;
            font-weight: 700;
            color: #15803d;
            margin-top: 1px;
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.02em;
            line-height: 1;
        }
        .shot-strip .pts.miss  { color: #94a3b8; font-weight: 600; }
        .shot-strip .pts.empty { color: #cbd5e1; font-weight: 600; }

        /* ─── Bottom row: insights (left) + badges/RF (right) ─── */
        .bottom-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin-top: 8px;
            table-layout: fixed;
        }
        .bottom-grid > tbody > tr > td {
            vertical-align: top;
            padding: 0;
        }
        .bottom-grid .col-insights { width: 52%; }
        .bottom-grid .col-badges   { width: 48%; }

        .insights-tbl {
            width: 100%;
            border-collapse: separate;
            border-spacing: 4px 4px;
            table-layout: fixed;
        }
        .insights-tbl td {
            border: 1px solid #e8edf4;
            border-radius: 4px;
            padding: 7px 9px;
            vertical-align: top;
            background: #ffffff;
            width: 50%;
        }
        .insights-tbl .lbl {
            font-size: 5.5pt;
            font-weight: 800;
            color: #e10600;
            text-transform: uppercase;
            letter-spacing: 0.24em;
        }
        .insights-tbl .val {
            font-size: 9pt;
            font-weight: 800;
            color: #0b1220;
            margin-top: 4px;
            line-height: 1.2;
            letter-spacing: -0.01em;
        }
        .insights-tbl .sub {
            font-size: 6pt;
            color: #94a3b8;
            margin-top: 2px;
            letter-spacing: 0.03em;
        }

        /* RF awards strip (compact) */
        .rf-awards {
            background: #0b1220;
            color: #f8fafc;
            padding: 8px 12px;
            border-left: 3px solid #e10600;
            border-radius: 4px;
            margin-bottom: 6px;
        }
        .rf-awards .ttl {
            font-size: 5.5pt;
            font-weight: 800;
            letter-spacing: 0.26em;
            color: #94a3b8;
            text-transform: uppercase;
        }
        .rf-awards .flushes {
            font-size: 13pt;
            font-weight: 800;
            color: #f8fafc;
            margin-top: 3px;
            line-height: 1;
            letter-spacing: -0.02em;
        }
        .rf-awards .flush-list {
            font-size: 7pt;
            color: #cbd5e1;
            margin-top: 3px;
            letter-spacing: 0.03em;
        }

        /* Badges — compact grid (3 across, very small tiles).
         *
         * Visual language mirrors the platform shooter-badges / badge-flair
         * components: the inline SVG glyph sits inside a tinted crest whose
         * colour comes from (family x tier) or the medal / distance override.
         * Gradients from the web UI are flattened to solid tinted backgrounds
         * so print output stays faithful instead of washing out under Gotenberg. */
        .badge-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 4px 4px;
        }
        .badge-cell {
            border: 1px solid #e8edf4;
            border-radius: 5px;
            padding: 6px 7px;
            vertical-align: top;
            width: 33.33%;
            background: #ffffff;
        }
        .badge-cell .b-row { width: 100%; border-collapse: collapse; }
        .badge-cell .b-row td { vertical-align: middle; padding: 0; }

        .badge-cell .b-crest {
            width: 22px;
            height: 22px;
            border-radius: 5px;
            border: 1px solid transparent;
            text-align: center;
            line-height: 0;
            display: inline-block;
            vertical-align: middle;
        }
        .badge-cell .b-crest svg {
            width: 13px;
            height: 13px;
            vertical-align: middle;
            stroke-width: 2;
            fill: none;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
            margin-top: 4px;
        }
        .badge-cell .b-crest .b-dist {
            display: inline-block;
            width: 20px;
            line-height: 20px;
            font-weight: 900;
            font-size: 6pt;
            letter-spacing: -0.02em;
            vertical-align: middle;
        }
        .badge-cell .b-crest .b-dist .b-dist-unit {
            font-size: 5pt;
            font-weight: 700;
        }

        /* Family + tier crests (flat print-safe equivalents of the platform
         * gradient tokens). PRS = sky, Royal Flush = amber, tiers drop
         * saturation as prestige drops.  */
        .badge-cell.fam-prs.tier-featured  .b-crest { background: #e0f2fe; border-color: #7dd3fc; color: #0369a1; }
        .badge-cell.fam-prs.tier-elite     .b-crest { background: #e0f2fe; border-color: #bae6fd; color: #0369a1; }
        .badge-cell.fam-prs.tier-milestone .b-crest { background: #f0f9ff; border-color: #bae6fd; color: #0284c7; }
        .badge-cell.fam-prs.tier-earned    .b-crest { background: #f8fafc; border-color: #e2e8f0; color: #0369a1; }

        .badge-cell.fam-rf.tier-featured   .b-crest { background: #fef3c7; border-color: #fcd34d; color: #b45309; }
        .badge-cell.fam-rf.tier-elite      .b-crest { background: #fef3c7; border-color: #fde68a; color: #b45309; }
        .badge-cell.fam-rf.tier-milestone  .b-crest { background: #fefce8; border-color: #fde68a; color: #a16207; }
        .badge-cell.fam-rf.tier-earned     .b-crest { background: #fffbeb; border-color: #fef3c7; color: #b45309; }

        /* Medal overrides (podium-gold / silver / bronze). Same three tiers
         * the platform uses: amber, slate, orange. */
        .badge-cell.ico-medal-1 .b-crest { background: #fef3c7; border-color: #fcd34d; color: #b45309; }
        .badge-cell.ico-medal-2 .b-crest { background: #f1f5f9; border-color: #cbd5e1; color: #475569; }
        .badge-cell.ico-medal-3 .b-crest { background: #ffedd5; border-color: #fdba74; color: #9a3412; }

        /* Distance overrides (hot → cool, matching dist-* tokens). */
        .badge-cell.ico-dist-700 .b-crest { background: #fee2e2; border-color: #fca5a5; color: #b91c1c; }
        .badge-cell.ico-dist-600 .b-crest { background: #ffedd5; border-color: #fdba74; color: #c2410c; }
        .badge-cell.ico-dist-500 .b-crest { background: #fef9c3; border-color: #fde68a; color: #a16207; }
        .badge-cell.ico-dist-400 .b-crest { background: #dcfce7; border-color: #86efac; color: #15803d; }

        /* Optional thin accent bar along the top so the competition family
         * reads at a glance even in monochrome print. */
        .badge-cell.fam-prs { border-top: 2px solid #0ea5e9; }
        .badge-cell.fam-rf  { border-top: 2px solid #d97706; }

        .badge-cell .b-body { padding-left: 6px; }
        .badge-cell .b-label {
            font-size: 7.5pt;
            font-weight: 800;
            color: #0b1220;
            line-height: 1.15;
            letter-spacing: -0.01em;
        }
        .badge-cell .b-tier {
            font-size: 5pt;
            font-weight: 700;
            color: #94a3b8;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .no-badges {
            padding: 12px;
            text-align: center;
            border: 1px dashed #cbd5e1;
            border-radius: 4px;
            color: #94a3b8;
            font-size: 7.5pt;
            letter-spacing: 0.02em;
        }
    </style>
</head>
<body>

@include('exports.partials.pdf-header', ['subtitle' => 'Shooter Report'])

<div class="wrap">

    {{-- ═════════ HERO ═════════ --}}
    <div class="hero">
        <table class="hero-grid">
            <tr>
                <td class="hero-left">
                    <div class="eyebrow">Competitor</div>
                    <div class="name">{{ $shooterDisplay }}</div>
                    <div class="meta-line">
                        @if($shooterCaliber){{ $shooterCaliber }}@endif
                        @if($shooterCaliber && $myStanding?->squad)<span class="sep">·</span>@endif
                        @if($myStanding?->squad)Squad {{ $myStanding->squad }}@endif
                    </div>
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

    {{-- ═════════ KPI ROW (score / hit-rate / hits / misses / field context) ═════════ --}}
    <table class="kpi-row">
        <tr>
            <td style="width: 15%;">
                <div class="lbl">Score</div>
                <div class="val">{{ $fmt($myScore) }}</div>
            </td>
            <td style="width: 16%;">
                <div class="lbl">Hit Rate</div>
                <div class="val">{{ $hitRate }}<span class="sub">%</span></div>
                <div class="bar"><span style="width: {{ $hitRate }}%;"></span></div>
            </td>
            <td style="width: 15%;">
                <div class="lbl">Hits</div>
                <div class="val">{{ $totalHits }}<span class="sub"> / {{ $totalShots }}</span></div>
            </td>
            <td style="width: 12%;">
                <div class="lbl">Misses</div>
                <div class="val">{{ $totalMisses }}</div>
            </td>
            <td class="ctx" style="width: 14%;">
                <div class="lbl">Winner</div>
                <div class="val">{{ $fmt($podium['first']->total_score ?? 0) }}</div>
                <div class="caption">{{ $podium['first'] ? \Illuminate\Support\Str::before($podium['first']->name, ' — ') ?: $podium['first']->name : '—' }}</div>
            </td>
            <td class="ctx" style="width: 14%;">
                <div class="lbl">Field Avg</div>
                <div class="val">{{ $fmt($fieldAvg) }}</div>
                <div class="caption">{{ $fieldSize }} shooters</div>
            </td>
            <td class="ctx" style="width: 14%;">
                <div class="lbl">vs Field</div>
                <div class="val">{{ ($myScore - $fieldAvg) >= 0 ? '+' : '' }}{{ $fmt($myScore - $fieldAvg) }}</div>
                <div class="caption">{{ $myScore > $fieldAvg ? 'above' : 'below' }} avg</div>
            </td>
        </tr>
    </table>

    {{-- ═════════ COMBINED STAGE BREAKDOWN ═════════ --}}
    <div class="section-title">
        <span class="accent">■</span>
        {{ $useCardFaces ? 'ROYAL FLUSH SCORECARD' : 'STAGE BREAKDOWN' }}
        <span class="muted">
            @if($useCardFaces)
                Each distance · 10 / J / Q / K / A · green tick = hit · red cross = miss
            @else
                Shot-by-shot results at every distance · subtotals on the right
            @endif
        </span>
    </div>

    <div class="stage-block">
        @foreach($myDistances as $dist)
            <table class="stage-row {{ $dist['is_clean_sweep'] ? 'clean' : '' }}">
                <tr>
                    {{-- Distance label + metadata --}}
                    <td class="s-label">
                        <div class="nm">{{ $dist['label'] }}</div>
                        <div class="sub">
                            {{ $mult($dist['distance_multiplier']) }} · {{ count($dist['gongs']) }} gongs
                            @if($dist['is_clean_sweep']) · Clean sweep @endif
                        </div>
                    </td>

                    {{-- Inline per-gong tick/cross strip --}}
                    <td class="s-cells">
                        <table class="shot-strip">
                            <tr>
                                @foreach($dist['cells'] as $idx => $cell)
                                    @php
                                        $g = $dist['gongs'][$idx] ?? null;
                                        $cls = match ($cell['state']) {
                                            'hit' => 'gong-hit',
                                            'miss' => 'gong-miss',
                                            default => 'gong-none',
                                        };
                                        $label = $gongLabel($g['number'] ?? ($idx + 1), $idx);
                                        $isSpecial = $useCardFaces && in_array($label, ['A', 'K'], true);
                                    @endphp
                                    <td class="{{ $cls }}">
                                        <div class="lbl-top {{ $isSpecial ? 'special' : '' }}">{{ $label }}</div>
                                        @if($cell['state'] === 'hit')
                                            <span class="icon">
                                                <svg viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M2.4 6.4 L5 9 L9.8 3.5" fill="none" stroke="#15803d" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </span>
                                            <span class="pts">+{{ $fmt($cell['points']) }}</span>
                                        @elseif($cell['state'] === 'miss')
                                            <span class="icon">
                                                <svg viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M3 3 L9 9 M9 3 L3 9" fill="none" stroke="#b91c1c" stroke-width="1.6" stroke-linecap="round"/>
                                                </svg>
                                            </span>
                                            <span class="pts miss">miss</span>
                                        @else
                                            <span class="icon">
                                                <svg viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M3 6 L9 6" fill="none" stroke="#cbd5e1" stroke-width="1.4" stroke-linecap="round"/>
                                                </svg>
                                            </span>
                                            <span class="pts empty">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        </table>
                    </td>

                    {{-- Hits / Hit-rate cell --}}
                    <td class="s-summary">
                        <div class="big">{{ $dist['hits'] }}/{{ $dist['hits'] + $dist['misses'] }}</div>
                        <div class="tiny">{{ $dist['hit_rate'] }}% hit rate</div>
                        <span class="rate-bar"><span style="width: {{ $dist['hit_rate'] }}%;"></span></span>
                    </td>

                    {{-- Points subtotal cell --}}
                    <td class="s-points">
                        <div class="big">{{ $fmt($dist['subtotal']) }}</div>
                        <div class="tiny">of {{ $fmt($dist['max_points']) }}</div>
                    </td>
                </tr>
            </table>
        @endforeach
    </div>

    {{-- ═════════ BOTTOM ROW — Insights (left) + Badges / RF (right) ═════════ --}}
    <table class="bottom-grid">
        <tr>
            <td class="col-insights">
                <div class="section-title"><span class="accent">■</span>MATCH INSIGHTS <span class="muted">Auto-computed from your scores</span></div>
                <table class="insights-tbl">
                    @php $insightRows = array_chunk($insights, 2); @endphp
                    @foreach($insightRows as $row)
                        <tr>
                            @foreach($row as $ins)
                                <td>
                                    <div class="lbl">{{ $ins['label'] }}</div>
                                    <div class="val">{{ $ins['value'] }}</div>
                                    @if(!empty($ins['sub']))<div class="sub">{{ $ins['sub'] }}</div>@endif
                                </td>
                            @endforeach
                            @for($pad = count($row); $pad < 2; $pad++)
                                <td style="visibility: hidden;">&nbsp;</td>
                            @endfor
                        </tr>
                    @endforeach
                </table>
            </td>
            <td class="col-badges">
                <div class="section-title"><span class="accent">■</span>{{ count($badges) }} {{ count($badges) === 1 ? 'BADGE' : 'BADGES' }} EARNED <span class="muted">this match</span></div>

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

                @if(empty($badges))
                    <div class="no-badges">
                        No badges earned.<br>
                        <span style="color: #cbd5e1; font-size: 6pt;">Podium finishes, clean sweeps and first Royal Flushes earn badges.</span>
                    </div>
                @else
                    @php $badgeRows = array_chunk($badges, 3); @endphp
                    <table class="badge-grid">
                        @foreach($badgeRows as $row)
                            <tr>
                                @foreach($row as $badge)
                                    @php
                                        $a = $badge->achievement;
                                        $family = ($a->competition_type ?? 'prs') === 'royal_flush' ? 'rf' : 'prs';
                                        $cfg = \App\Http\Controllers\BadgeGalleryController::BADGE_CONFIG[$a->slug] ?? [];
                                        $tier = $cfg['tier'] ?? 'earned';
                                        $icon = $cfg['icon'] ?? 'target';
                                        $tierLabel = $family === 'rf' ? 'RF' : 'PRS';
                                    @endphp
                                    <td class="badge-cell fam-{{ $family }} tier-{{ $tier }} ico-{{ $icon }}">
                                        <table class="b-row">
                                            <tr>
                                                <td style="width: 24px;">
                                                    <span class="b-crest">
                                                        @if(str_starts_with($icon, 'dist-'))
                                                            <span class="b-dist">{{ substr($icon, 5) }}<span class="b-dist-unit">m</span></span>
                                                        @else
                                                            @include('exports.partials.badge-icon-inline', ['name' => $icon])
                                                        @endif
                                                    </span>
                                                </td>
                                                <td class="b-body">
                                                    <div class="b-label">{{ $a->label }}</div>
                                                    <div class="b-tier">{{ $tierLabel }} · {{ strtoupper($tier) }}</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                @endforeach
                                @for($pad = count($row); $pad < 3; $pad++)
                                    <td class="badge-cell" style="visibility: hidden;">&nbsp;</td>
                                @endfor
                            </tr>
                        @endforeach
                    </table>
                @endif
            </td>
        </tr>
    </table>

    @include('exports.partials.pdf-footer')
</div>

</body>
</html>
