@php
    /**
     * Phone-shaped, share-style shooter match report (PDF variant).
     *
     * This is the printable twin of `pages.match-share.blade.php`. Same data
     * shape, same visual identity (dark navy gradient, hero-rank tile, gong
     * dots, family-coloured badge crests), but rendered self-contained for
     * Gotenberg / DomPDF — no Vite, no Tailwind, no JS. The page is sized
     * `90mm × auto` so it prints as one tall, narrow column that reads like
     * a phone screenshot when shared as an attachment.
     *
     * Why a separate template? The on-screen view is Tailwind-driven and
     * leans on Vite's compiled stylesheet plus a sticky share-bar that has
     * no place in a printable artifact. Inlining only the styles we need
     * keeps the PDF reliable across both renderers and skips the
     * Vite-asset-resolution-from-Gotenberg-container rabbit hole entirely.
     *
     * Keep the markup blocks one-for-one with the share view so the two
     * artifacts always look like obvious siblings. The badge tile palette
     * mirrors the share-view's PRS sky / Royal Flush amber tier styling.
     */

    $match      = $report['match'] ?? [];
    $shooter    = $report['shooter'] ?? [];
    $placement  = $report['placement'] ?? [];
    $summary    = $report['summary'] ?? [];
    $stages     = $report['stages'] ?? [];
    $bestStage  = $report['best_stage'] ?? null;
    $worstStage = $report['worst_stage'] ?? null;
    $fieldStats = $report['field_stats'] ?? [];
    $funFacts   = $report['fun_facts'] ?? [];
    $badges     = $report['badges'] ?? [];

    $scoringType = strtolower($match['scoring_type'] ?? 'standard');
    $isPrs       = $scoringType === 'prs';
    $isElr       = $scoringType === 'elr';

    $scoreLabel = $isPrs ? 'Hits' : ($isElr ? 'Points' : 'Score');
    $typeLabel  = strtoupper($scoringType);
    $typeChip   = $isPrs ? '#F59E0B' : ($isElr ? '#38BDF8' : '#e10600');

    $tierLabels = [
        'featured'  => 'Signature',
        'elite'     => 'Elite',
        'milestone' => 'Lifetime',
        'earned'    => 'Earned',
    ];
    // Mirrors the share-view crest palette so the screenshot and the PDF
    // produce the same coloured badge tiles for PRS (sky) vs RF (amber).
    $crestTones = [
        'prs' => [
            'featured'  => ['border' => '#7DD3FC', 'text' => '#BAE6FD', 'fill' => 'rgba(56,189,248,0.20)'],
            'elite'     => ['border' => '#38BDF8', 'text' => '#7DD3FC', 'fill' => 'rgba(56,189,248,0.16)'],
            'milestone' => ['border' => '#0EA5E9', 'text' => '#7DD3FC', 'fill' => 'rgba(56,189,248,0.12)'],
            'earned'    => ['border' => '#334155', 'text' => '#7DD3FC', 'fill' => 'rgba(56,189,248,0.08)'],
        ],
        'rf' => [
            'featured'  => ['border' => '#FCD34D', 'text' => '#FDE68A', 'fill' => 'rgba(251,191,36,0.20)'],
            'elite'     => ['border' => '#F59E0B', 'text' => '#FCD34D', 'fill' => 'rgba(251,191,36,0.16)'],
            'milestone' => ['border' => '#D97706', 'text' => '#FCD34D', 'fill' => 'rgba(251,191,36,0.12)'],
            'earned'    => ['border' => '#334155', 'text' => '#FCD34D', 'fill' => 'rgba(251,191,36,0.08)'],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $shooter['name'] ?? 'Shooter' }} — {{ $match['name'] ?? 'Match' }}</title>
    <style>
        /* Phone-shaped page. 90mm wide is ~3.5", giving the same narrow,
           portrait-only feel as a phone screen while still being readable
           on desktop preview. The auto height + Gotenberg singlePage flag
           collapse the entire report into one continuous tall page so the
           document reads as a single shareable image. */
        @page { size: 90mm auto; margin: 0; background: #071327; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            background: #071327 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 8pt;
            color: #e2e8f0;
            line-height: 1.4;
            background:
                radial-gradient(circle at 50% -10%, rgba(225, 6, 0, 0.18), transparent 55%),
                linear-gradient(180deg, #071327 0%, #0a1a33 100%);
            padding: 6mm 5mm;
        }

        /* ── Brand strip ─────────────────────────────────────────────── */
        .brand-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5mm; }
        .brand     { font-size: 10pt; font-weight: 800; letter-spacing: 2px; }
        .brand .red { color: #e10600; }
        .brand .white { color: #ffffff; }
        .brand-tag { font-size: 6.5pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.2px; }

        /* ── Match info ──────────────────────────────────────────────── */
        .match-block { margin-bottom: 4mm; }
        .match-name  { font-size: 13pt; font-weight: 700; color: #ffffff; line-height: 1.2; margin-bottom: 1mm; }
        .match-meta  { font-size: 8pt; color: #94a3b8; margin-bottom: 2mm; }
        .match-meta .sep { color: #475569; }
        .chip {
            display: inline-block;
            font-size: 6.5pt; font-weight: 700; letter-spacing: 0.6px;
            padding: 1px 6px;
            border-radius: 3px;
            margin-right: 3px;
        }
        .chip-type { color: #ffffff; background: {{ $typeChip }}; }
        .chip-muted { color: #cbd5e1; background: #1e293b; }

        /* ── Hero rank tile ──────────────────────────────────────────── */
        .hero {
            background: linear-gradient(160deg, rgba(225, 6, 0, 0.18) 0%, rgba(225, 6, 0, 0.04) 60%, transparent 100%);
            border: 1px solid rgba(225, 6, 0, 0.35);
            border-radius: 4mm;
            padding: 5mm 4mm;
            text-align: center;
            margin-bottom: 4mm;
            page-break-inside: avoid;
        }
        .hero-name {
            font-size: 7pt; font-weight: 700;
            text-transform: uppercase; letter-spacing: 2px;
            color: #cbd5e1;
        }
        .hero-name .bib { color: #64748b; }
        .hero-rank-row { margin-top: 2.5mm; }
        .hero-rank {
            font-size: 32pt; font-weight: 900; color: #ffffff; line-height: 1;
        }
        .hero-of { font-size: 9pt; color: #94a3b8; margin-left: 2mm; }
        .hero-summary {
            margin-top: 2mm;
            font-size: 8pt; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1.2px;
            color: #f87171;
        }
        .hero-score { margin-top: 3mm; font-size: 8.5pt; color: #cbd5e1; }
        .hero-score .label { color: #64748b; }
        .hero-score .val   { color: #ffffff; font-weight: 700; }
        .hero-score .max   { color: #475569; }
        .hero-score .time  { color: #fbbf24; font-weight: 700; }
        .hero-score .sep   { color: #475569; }

        /* ── Stat strip (4 tiles) ────────────────────────────────────── */
        .stat-strip { display: table; width: 100%; border-spacing: 1.5mm 0; margin-bottom: 4mm; }
        .stat-tile  { display: table-cell; width: 25%; background: rgba(24, 24, 27, 0.5); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 3mm; padding: 2mm 1mm; text-align: center; vertical-align: middle; }
        .stat-val   { font-size: 14pt; font-weight: 800; line-height: 1; color: #ffffff; }
        .stat-val.green { color: #22c55e; }
        .stat-val.red   { color: #ef4444; }
        .stat-val.amber { color: #fbbf24; }
        .stat-val .unit { font-size: 8pt; font-weight: 500; color: #fcd34d; }
        .stat-label { font-size: 5.5pt; color: #71717a; text-transform: uppercase; letter-spacing: 0.6px; margin-top: 1mm; font-weight: 700; }

        /* ── Section header ──────────────────────────────────────────── */
        .section-title {
            font-size: 6.5pt; font-weight: 700;
            text-transform: uppercase; letter-spacing: 2px;
            color: #f87171;
            margin: 4mm 0 2mm;
        }

        /* ── Per-stage cards ─────────────────────────────────────────── */
        .stage-card {
            background: rgba(24, 24, 27, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 3mm;
            padding: 2.5mm 3mm;
            margin-bottom: 1.5mm;
            page-break-inside: avoid;
        }
        .stage-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 3mm; margin-bottom: 1.5mm; }
        .stage-name { font-size: 8.5pt; font-weight: 700; color: #ffffff; }
        .stage-name .dist { color: #64748b; font-weight: 400; }
        .stage-time-pill { font-size: 7.5pt; color: #fbbf24; }
        .stage-mult-pill { font-size: 7.5pt; color: #f87171; }
        .gong-row { margin-bottom: 1.5mm; }
        .gong-dot {
            display: inline-block;
            width: 4mm; height: 4mm;
            border-radius: 50%;
            font-size: 6.5pt; font-weight: 700;
            line-height: 4mm;
            text-align: center;
            color: #ffffff;
            margin-right: 1mm; margin-bottom: 1mm;
        }
        .gong-hit       { background: #22c55e; }
        .gong-miss      { background: #ef4444; }
        .gong-not-taken { background: #f59e0b; }
        .gong-none      { background: #1e293b; color: #475569; }

        /* Vertical gong stack — coloured result on top, the gong's nominal
           point value (Royal Flush 1.0/1.25/1.5/1.75/2.0 × distance/100)
           underneath. Surfaces the per-gong scaling that the share view
           also exposes, so the printed report tells the same story.  */
        .gong-stack { display: inline-block; vertical-align: top; text-align: center; margin-right: 1mm; margin-bottom: 1mm; }
        .gong-stack .gong-dot { display: block; margin: 0 auto; }
        .gong-val {
            display: block;
            margin-top: 0.5mm;
            font-size: 5.5pt;
            font-weight: 700;
            line-height: 1;
            color: #d4d4d8;
        }
        .gong-val.miss { color: #71717a; }
        .gong-val.none { color: #475569; }
        .stage-foot { display: flex; justify-content: space-between; align-items: center; font-size: 7.5pt; }
        .stage-foot .dim { color: #64748b; }
        .stage-foot .green { color: #22c55e; }
        .stage-foot .red   { color: #ef4444; }
        .stage-foot .amber { color: #fbbf24; }
        .stage-foot .pts   { font-size: 8pt; font-weight: 700; color: #ffffff; }

        /* ── Best & Worst ────────────────────────────────────────────── */
        .bw-card {
            background: rgba(24, 24, 27, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 3mm;
            padding: 2mm 3mm;
            margin-bottom: 1.5mm;
            display: flex; justify-content: space-between; align-items: center;
            font-size: 8pt;
        }
        .bw-best  { border-left: 1mm solid #22c55e; }
        .bw-worst { border-left: 1mm solid #ef4444; }
        .bw-card .tag-best  { color: #22c55e; font-weight: 700; }
        .bw-card .tag-worst { color: #ef4444; font-weight: 700; }
        .bw-card .dim       { color: #64748b; }
        .bw-pts.green { color: #22c55e; font-weight: 700; }
        .bw-pts.red   { color: #ef4444; font-weight: 700; }

        /* ── How you compared ────────────────────────────────────────── */
        .field-list { background: rgba(24, 24, 27, 0.5); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 3mm; }
        .field-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1.6mm 3mm; font-size: 8pt;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }
        .field-row:last-child { border-bottom: none; }
        .field-row .label { color: #94a3b8; }
        .field-row .label .em-red   { color: #f87171; }
        .field-row .label .em-green { color: #22c55e; }
        .field-row .label .em-white { color: #e4e4e7; }
        .field-row .value { color: #ffffff; font-weight: 700; }
        .field-row .value.red   { color: #f87171; }
        .field-row .value.green { color: #22c55e; }

        /* ── Did you know? ───────────────────────────────────────────── */
        .fact {
            display: flex; gap: 2mm;
            background: rgba(24, 24, 27, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 2mm;
            padding: 1.6mm 2.5mm;
            margin-bottom: 1mm;
            font-size: 7.5pt; line-height: 1.4; color: #d4d4d8;
        }
        .fact-bullet { color: #ef4444; font-size: 8pt; flex-shrink: 0; }

        /* ── Badges grid (3-up, mirroring the share view) ───────────── */
        .badges-grid { display: table; width: 100%; border-spacing: 1.5mm 1.5mm; table-layout: fixed; }
        .badges-row  { display: table-row; }
        .badge-tile  {
            display: table-cell; vertical-align: top;
            width: 33.33%;
            background: rgba(24, 24, 27, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 3mm;
            padding: 2.5mm 1.5mm;
            text-align: center;
            page-break-inside: avoid;
        }
        .badge-crest {
            display: inline-block;
            width: 8mm; height: 8mm;
            border-radius: 2mm;
            border: 1px solid;
            text-align: center;
            line-height: 8mm;
        }
        .badge-crest svg { width: 4.5mm; height: 4.5mm; vertical-align: middle; }
        .badge-tier  { font-size: 5.5pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 1.5mm; }
        .badge-name  { font-size: 7.5pt; font-weight: 700; color: #ffffff; margin-top: 0.6mm; line-height: 1.15; }
        .badge-desc  { font-size: 5.5pt; color: #71717a; margin-top: 1mm; line-height: 1.3; }

        /* ── Footer ──────────────────────────────────────────────────── */
        .footer {
            margin-top: 5mm;
            text-align: center;
            font-size: 6.5pt;
            color: #64748b;
        }
        .footer .url { color: #ef4444; font-weight: 700; }
    </style>
</head>
<body>

    {{-- Brand strip --}}
    <div class="brand-row">
        <div class="brand"><span class="red">DEAD</span><span class="white">CENTER</span></div>
        <div class="brand-tag">Match Report</div>
    </div>

    {{-- Match info --}}
    <div class="match-block">
        <div class="match-name">{{ $match['name'] ?? 'Match' }}</div>
        <div class="match-meta">
            {{ $match['date'] ?? '' }}
            @if(!empty($match['location']))
                <span class="sep"> · </span>{{ $match['location'] }}
            @endif
        </div>
        <div>
            <span class="chip chip-type">{{ $typeLabel }}</span>
            @if(!empty($shooter['division']))
                <span class="chip chip-muted">{{ $shooter['division'] }}</span>
            @endif
            @if(!empty($shooter['squad']))
                <span class="chip chip-muted">Squad {{ $shooter['squad'] }}</span>
            @endif
        </div>
    </div>

    {{-- Hero rank tile --}}
    <div class="hero">
        <div class="hero-name">
            {{ $shooter['name'] ?? 'Shooter' }}
            @if(!empty($shooter['bib_number']))
                <span class="bib">· #{{ $shooter['bib_number'] }}</span>
            @endif
        </div>
        <div class="hero-rank-row">
            <span class="hero-rank">{{ $placement['rank_ordinal'] ?? ('#' . ($placement['rank'] ?? '—')) }}</span>
            <span class="hero-of">of {{ $placement['total'] ?? '—' }}</span>
        </div>
        @if(!empty($placement['summary']))
            <div class="hero-summary">{{ $placement['summary'] }}</div>
        @endif
        <div class="hero-score">
            <span class="label">{{ $scoreLabel }}:</span>
            <span class="val">{{ number_format($summary['total_score'] ?? 0, 1) }}</span>
            <span class="max">/ {{ number_format($summary['max_possible'] ?? 0, 1) }}</span>
            @if($isPrs && !empty($summary['total_time']))
                <span class="sep"> · </span>
                <span class="time">{{ number_format($summary['total_time'], 1) }}s</span>
            @endif
        </div>
    </div>

    {{-- Stat strip --}}
    <div class="stat-strip">
        <div class="stat-tile">
            <div class="stat-val green">{{ $summary['hits'] ?? 0 }}</div>
            <div class="stat-label">Hits</div>
        </div>
        <div class="stat-tile">
            <div class="stat-val red">{{ $summary['misses'] ?? 0 }}</div>
            <div class="stat-label">Miss</div>
        </div>
        <div class="stat-tile">
            <div class="stat-val">{{ number_format($summary['hit_rate'] ?? 0, 0) }}%</div>
            <div class="stat-label">Rate</div>
        </div>
        <div class="stat-tile">
            @if($isPrs && !empty($summary['total_time']))
                <div class="stat-val amber">{{ number_format($summary['total_time'], 0) }}<span class="unit">s</span></div>
                <div class="stat-label">Time</div>
            @else
                <div class="stat-val">{{ $summary['no_shots'] ?? 0 }}</div>
                <div class="stat-label">N/T</div>
            @endif
        </div>
    </div>

    {{-- Per-stage breakdown --}}
    @if(count($stages) > 0)
        <div class="section-title">Per-Stage Breakdown</div>
        @foreach($stages as $idx => $stage)
            <div class="stage-card">
                <div class="stage-head">
                    <div class="stage-name">
                        {{ $stage['label'] ?? 'Stage ' . ($idx + 1) }}
                        @if(!empty($stage['distance_meters']))
                            <span class="dist">({{ $stage['distance_meters'] }}m)</span>
                        @endif
                    </div>
                    <div>
                        @if(!$isPrs && !empty($stage['distance_multiplier']) && (float) $stage['distance_multiplier'] !== 1.0)
                            <span class="stage-mult-pill">×{{ number_format($stage['distance_multiplier'], 1) }}</span>
                        @endif
                        @if($isPrs && !empty($stage['time']))
                            <span class="stage-time-pill">{{ number_format($stage['time'], 1) }}s</span>
                        @endif
                    </div>
                </div>

                @if(!empty($stage['gongs']))
                    @php
                        $showValues = ! $isPrs && collect($stage['gongs'])
                            ->pluck('value')
                            ->filter(fn ($v) => $v !== null)
                            ->unique()
                            ->count() > 1;
                    @endphp
                    <div class="gong-row">
                        @foreach($stage['gongs'] as $gong)
                            @php
                                $r = $gong['result'] ?? '';
                                $cls = match ($r) {
                                    'hit'        => 'gong-dot gong-hit',
                                    'miss'       => 'gong-dot gong-miss',
                                    'not_taken'  => 'gong-dot gong-not-taken',
                                    default      => 'gong-dot gong-none',
                                };
                                $glyph = match ($r) {
                                    'hit'       => '✓',
                                    'miss'      => '✗',
                                    'not_taken' => '–',
                                    default     => '·',
                                };
                                $valCls = match ($r) {
                                    'hit'   => 'gong-val',
                                    'miss'  => 'gong-val miss',
                                    default => 'gong-val none',
                                };
                                $valFmt = isset($gong['value'])
                                    ? rtrim(rtrim(number_format((float) $gong['value'], 2), '0'), '.')
                                    : null;
                            @endphp
                            @if($showValues && $valFmt !== null)
                                <span class="gong-stack">
                                    <span class="{{ $cls }}">{{ $glyph }}</span>
                                    <span class="{{ $valCls }}">{{ $valFmt }}</span>
                                </span>
                            @else
                                <span class="{{ $cls }}">{{ $glyph }}</span>
                            @endif
                        @endforeach
                    </div>
                @endif

                <div class="stage-foot">
                    <div class="dim">
                        <span class="green">{{ $stage['hits'] ?? 0 }} hits</span> ·
                        <span class="red">{{ $stage['misses'] ?? 0 }} miss</span>
                        @if(($stage['no_shots'] ?? 0) > 0)
                            · <span class="amber">{{ $stage['no_shots'] }} N/T</span>
                        @endif
                    </div>
                    <div class="pts">{{ number_format($stage['score'] ?? 0, 1) }} pts</div>
                </div>
            </div>
        @endforeach
    @endif

    {{-- Best & Worst --}}
    @if($bestStage || $worstStage)
        <div class="section-title">Best &amp; Worst Stage</div>
        @if($bestStage)
            <div class="bw-card bw-best">
                <div>
                    <span class="tag-best">BEST:</span>
                    {{ $bestStage['label'] ?? '' }}
                    <span class="dim">— {{ $bestStage['hits'] ?? 0 }}/{{ $bestStage['targets'] ?? 0 }} impacts</span>
                </div>
                <div class="bw-pts green">{{ number_format($bestStage['score'] ?? 0, 1) }} pts</div>
            </div>
        @endif
        @if($worstStage)
            <div class="bw-card bw-worst">
                <div>
                    <span class="tag-worst">WORST:</span>
                    {{ $worstStage['label'] ?? '' }}
                    <span class="dim">— {{ $worstStage['hits'] ?? 0 }}/{{ $worstStage['targets'] ?? 0 }} impacts</span>
                </div>
                <div class="bw-pts red">{{ number_format($worstStage['score'] ?? 0, 1) }} pts</div>
            </div>
        @endif
    @endif

    {{-- How you compared --}}
    @if(!empty($fieldStats))
        <div class="section-title">How You Compared</div>
        <div class="field-list">
            @if(isset($fieldStats['avg_score']))
                <div class="field-row">
                    <span class="label">Field Avg {{ $scoreLabel }}</span>
                    <span class="value">{{ number_format($fieldStats['avg_score'], 1) }}</span>
                </div>
            @endif
            @if(isset($fieldStats['avg_hit_rate']))
                <div class="field-row">
                    <span class="label">Field Avg Hit Rate</span>
                    <span class="value">{{ number_format($fieldStats['avg_hit_rate'], 1) }}%</span>
                </div>
            @endif
            @if(!empty($fieldStats['winner_name']))
                <div class="field-row">
                    <span class="label">Winner: <span class="em-white">{{ $fieldStats['winner_name'] }}</span></span>
                    <span class="value red">{{ number_format($fieldStats['winner_score'] ?? 0, 1) }}</span>
                </div>
            @endif
            @if(!empty($fieldStats['hardest_gong']))
                <div class="field-row">
                    <span class="label">Hardest: <span class="em-red">{{ $fieldStats['hardest_gong']['label'] ?? '' }}</span></span>
                    <span class="value red">{{ number_format($fieldStats['hardest_gong']['hit_rate'] ?? 0, 0) }}%</span>
                </div>
            @endif
            @if(!empty($fieldStats['easiest_gong']))
                <div class="field-row">
                    <span class="label">Easiest: <span class="em-green">{{ $fieldStats['easiest_gong']['label'] ?? '' }}</span></span>
                    <span class="value green">{{ number_format($fieldStats['easiest_gong']['hit_rate'] ?? 0, 0) }}%</span>
                </div>
            @endif
        </div>
    @endif

    {{-- Fun facts --}}
    @if(count($funFacts) > 0)
        <div class="section-title">Did You Know?</div>
        @foreach($funFacts as $fact)
            <div class="fact">
                <span class="fact-bullet">●</span>
                <span>{{ $fact }}</span>
            </div>
        @endforeach
    @endif

    {{-- Badges (3-up grid mirroring the share view) --}}
    @if(count($badges) > 0)
        <div class="section-title">Badges Awarded</div>
        @php
            // Chunk into rows of 3 so display:table-cell renders a real grid
            // (DomPDF doesn't support CSS grid, but it does support tables —
            // and Gotenberg/Chromium handles both). Tables also give us
            // page-break-inside:avoid per row, which keeps badge tiles
            // intact across page boundaries when one ever runs long.
            $rows = collect($badges)->chunk(3);
        @endphp
        <div class="badges-grid">
            @foreach($rows as $row)
                <div class="badges-row">
                    @foreach($row as $badge)
                        @php
                            $family   = $badge['family'] ?? 'prs';
                            $tier     = $badge['tier'] ?? 'earned';
                            $tone     = $crestTones[$family][$tier] ?? $crestTones['prs']['earned'];
                            $name     = $badge['label'] ?? 'Badge';
                            $tierTxt  = $tierLabels[$tier] ?? ucfirst($tier);
                            $icon     = $badge['icon'] ?? 'target';
                            $subtitle = $badge['description'] ?? $badge['earn_chip'] ?? null;
                        @endphp
                        <div class="badge-tile">
                            <div class="badge-crest"
                                 style="border-color: {{ $tone['border'] }}; background: {{ $tone['fill'] }}; color: {{ $tone['text'] }};">
                                <x-badge-icon :name="$icon" style="color: {{ $tone['text'] }};" />
                            </div>
                            <div class="badge-tier" style="color: {{ $tone['text'] }};">{{ $tierTxt }}</div>
                            <div class="badge-name">{{ $name }}</div>
                            @if($subtitle)
                                <div class="badge-desc">{{ \Illuminate\Support\Str::limit($subtitle, 70) }}</div>
                            @endif
                        </div>
                    @endforeach
                    {{-- Pad short rows so the table still tiles 3-up cleanly. --}}
                    @for($i = $row->count(); $i < 3; $i++)
                        <div class="badge-tile" style="background: transparent; border-color: transparent;"></div>
                    @endfor
                </div>
            @endforeach
        </div>
    @endif

    <div class="footer">
        <span class="url">deadcenter.co.za</span> — score it, share it, own it.
    </div>

</body>
</html>
