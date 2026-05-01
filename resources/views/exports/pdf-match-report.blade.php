@php
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
    $sponsor    = $report['sponsor'] ?? null;

    $scoringType = strtolower($match['scoring_type'] ?? 'standard');
    $isPrs       = $scoringType === 'prs';
    $isElr       = $scoringType === 'elr';

    // Keep the full report aligned with the brand colour — the section
    // headings, brand wordmark and placement callouts used to be orange on
    // PRS reports, which clashed with the rest of the platform. The PRS
    // type-chip retains its own orange so the discipline is still readable
    // at a glance.
    $accent        = '#e10600';
    $accentBg      = '#7F1D1D';
    $typeChipColor = $isPrs ? '#F59E0B' : ($isElr ? '#38BDF8' : '#ff2b2b');
    $scoreLabel    = $isPrs ? 'Hits' : ($isElr ? 'Points' : 'Score');
    $typeLabel     = strtoupper($scoringType);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        /* Print the page edge-to-edge in dark navy so there is no white
           paper band around the content. Inner padding restores the
           visual "margin" inside that dark canvas.

           Use `210mm auto` so Chromium (Gotenberg) renders the report on
           a single continuous page rather than paginating into multiple
           short A4 pages — the shooter report is a narrative document,
           not a form, and reads better as one flowing page. DomPDF
           falls back to its default A4 if it can't parse the height. */
        @page { size: 210mm auto; margin: 0; background: #071327; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { background: #071327 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #e2e8f0;
            background: #071327;
            padding: 10mm 12mm 10mm 12mm;
            line-height: 1.35;
        }

        .page-header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 6px; border-bottom: 2px solid {{ $accent }}; margin-bottom: 8px; }
        .brand { font-size: 16pt; font-weight: 800; letter-spacing: 2px; color: {{ $accent }}; }
        .brand span { color: #ffffff; }
        .subtitle { font-size: 7pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; text-align: right; }

        .match-name { font-size: 13pt; font-weight: 700; color: #ffffff; margin-bottom: 2px; }
        .match-meta { font-size: 7.5pt; color: #94a3b8; margin-bottom: 6px; }
        .badge-tag { display: inline-block; font-size: 7pt; font-weight: 700; padding: 1px 7px; border-radius: 3px; letter-spacing: 0.5px; }
        .badge-accent { background: {{ $accent }}; color: #fff; }
        .badge-type { background: {{ $typeChipColor }}; color: #fff; }
        .badge-muted { background: #1e293b; color: #cbd5e1; }

        .shooter-name { font-size: 11pt; color: #e2e8f0; margin: 8px 0 2px; }
        .shooter-name .bib { color: #64748b; font-size: 8.5pt; }

        .section-title { font-size: 7.5pt; font-weight: 700; color: {{ $accent }}; text-transform: uppercase; letter-spacing: 1.5px; margin: 9px 0 4px; }

        /* The Summary panel wraps the 4 stat tiles plus the score /
           placement / total-time text so the whole block reads as one card
           — same width, same rounded corners, same bg as each .stage-card
           below. Previously the tiles floated on the dark page background
           and read as a lighter strip than the per-stage cards. */
        .summary-panel { background: #0c1a33; border-radius: 6px; padding: 8px 10px 6px; margin-bottom: 6px; page-break-inside: avoid; }
        .stat-cards { display: flex; gap: 6px; margin-bottom: 6px; }
        .stat-card { flex: 1; background: #1d2d4a; border-radius: 5px; padding: 7px 4px; text-align: center; }
        .stat-value { font-size: 15pt; font-weight: 800; color: #ffffff; line-height: 1; }
        .stat-value.green { color: #22c55e; }
        .stat-value.red { color: #ef4444; }
        .stat-value.accent { color: {{ $accent }}; }
        .stat-label { font-size: 6.5pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 2px; }

        .summary-line { font-size: 8.5pt; color: #94a3b8; margin: 1px 0; }
        .summary-line strong { color: #ffffff; }
        .summary-line .hl { color: {{ $accent }}; font-weight: 700; }

        .stage-card { background: #0c1a33; border-radius: 5px; padding: 5px 10px; margin-bottom: 4px; page-break-inside: avoid; }
        .stage-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px; }
        .stage-name { font-size: 9pt; font-weight: 700; color: #ffffff; }
        .stage-name .dist { font-weight: 400; color: #64748b; font-size: 8pt; }
        .stage-time { font-size: 8pt; color: {{ $accent }}; }

        .gong-row { display: flex; gap: 2px; margin-bottom: 3px; flex-wrap: wrap; }
        .gong-dot { width: 13px; height: 13px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 6.5pt; font-weight: 700; color: #fff; line-height: 1; }
        .gong-hit { background: #22c55e; }
        .gong-miss { background: #ef4444; }
        .gong-ns { background: #374151; color: #6b7280; }

        .stage-stats { font-size: 7.5pt; color: #94a3b8; display: flex; justify-content: space-between; }
        .stage-stats .pts { font-size: 9pt; font-weight: 700; color: #ffffff; }

        .bw-card { background: #1d2d4a; border-radius: 5px; padding: 5px 10px; margin-bottom: 3px; display: flex; justify-content: space-between; align-items: center; }
        .bw-card.best { border-left: 3px solid #22c55e; }
        .bw-card.worst { border-left: 3px solid #ef4444; }
        .bw-label { font-size: 8.5pt; color: #e2e8f0; }
        .bw-label strong { font-weight: 700; }
        .bw-label .best-tag { color: #22c55e; }
        .bw-label .worst-tag { color: #ef4444; }
        .bw-pts { font-size: 9pt; font-weight: 700; }

        /* 2-column grid for the compact "How You Compared" block. */
        .field-grid { display: flex; flex-wrap: wrap; gap: 3px 4px; }
        .field-row { flex: 1 1 calc(50% - 2px); background: #0c1a33; border-radius: 4px; padding: 4px 9px; display: flex; justify-content: space-between; align-items: center; min-width: 0; }
        .field-row .label { font-size: 7.5pt; color: #94a3b8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .field-row .value { font-size: 9pt; font-weight: 700; color: #ffffff; flex-shrink: 0; margin-left: 6px; }

        .fact-item { background: #1d2d4a; border-radius: 4px; padding: 4px 9px; margin-bottom: 3px; font-size: 7.5pt; color: #cbd5e1; line-height: 1.35; display: flex; align-items: flex-start; gap: 5px; }
        .fact-bullet { color: {{ $accent }}; font-size: 8pt; flex-shrink: 0; margin-top: 1px; }

        /* Badge row — icon crest on the left, text on the right. Each
           category gets a tinted gradient crest + subtle glow so the PDF
           matches the platform's shooter-badges component instead of
           falling back to plain text rows. */
        .badge-card {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #0c1a33;
            border-radius: 8px;
            padding: 8px 12px;
            margin-bottom: 6px;
            page-break-inside: avoid;
            box-shadow: 0 1px 0 rgba(255, 255, 255, 0.04), 0 4px 12px rgba(0, 0, 0, 0.35);
        }
        .badge-crest {
            flex-shrink: 0;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.04);
        }
        .badge-crest svg { width: 18px; height: 18px; }
        .badge-body { flex: 1; min-width: 0; }
        .badge-name { font-size: 9pt; font-weight: 700; }
        .badge-desc { font-size: 7.5pt; color: #94a3b8; margin-top: 2px; line-height: 1.35; }
        .badge-cat {
            font-size: 6.5pt;
            color: #64748b;
            margin-left: 6px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 600;
        }

        .badge-special { border-left: 3px solid #F59E0B; }
        .badge-special .badge-crest {
            background: linear-gradient(180deg, rgba(245, 158, 11, 0.32) 0%, rgba(245, 158, 11, 0.08) 100%);
            border-color: rgba(245, 158, 11, 0.55);
            color: #FCD34D;
            box-shadow: 0 2px 6px rgba(245, 158, 11, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.12);
        }
        .badge-special .badge-name { color: #F59E0B; }

        .badge-lifetime { border-left: 3px solid #A855F7; }
        .badge-lifetime .badge-crest {
            background: linear-gradient(180deg, rgba(168, 85, 247, 0.32) 0%, rgba(168, 85, 247, 0.08) 100%);
            border-color: rgba(168, 85, 247, 0.55);
            color: #D8B4FE;
            box-shadow: 0 2px 6px rgba(168, 85, 247, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.12);
        }
        .badge-lifetime .badge-name { color: #A855F7; }

        .badge-repeatable { border-left: 3px solid #38BDF8; }
        .badge-repeatable .badge-crest {
            background: linear-gradient(180deg, rgba(56, 189, 248, 0.32) 0%, rgba(56, 189, 248, 0.08) 100%);
            border-color: rgba(56, 189, 248, 0.55);
            color: #7DD3FC;
            box-shadow: 0 2px 6px rgba(56, 189, 248, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.12);
        }
        .badge-repeatable .badge-name { color: #38BDF8; }

        .footer { margin-top: 8px; padding-top: 5px; border-top: 1px solid #1e293b; text-align: center; }
        .footer .brand-sm { font-size: 10pt; font-weight: 700; letter-spacing: 1.5px; color: {{ $accent }}; }
        .footer .brand-sm span { color: #ffffff; }
        .footer .url { font-size: 7pt; color: #475569; margin-top: 1px; }
        .footer .gen { font-size: 6.5pt; color: #334155; margin-top: 3px; }

        .powered-by { display: flex; align-items: center; justify-content: center; gap: 5px; margin-top: 4px; padding: 2px 0; }
        .powered-by img { height: 14px; max-width: 70px; object-fit: contain; }
        .powered-by .text { font-size: 7pt; color: #64748b; }
        .powered-by .name { font-weight: 600; color: #94a3b8; }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="page-header">
        <div class="brand">DEAD<span>CENTER</span></div>
        <div class="subtitle">Match Report</div>
    </div>

    {{-- MATCH INFO --}}
    <div class="match-name">{{ $match['name'] ?? 'Match' }}</div>
    <div class="match-meta">
        {{ $match['date'] ?? '' }}
        @if(!empty($match['location'])) &bull; {{ $match['location'] }} @endif
    </div>
    <div style="margin-bottom: 4px;">
        <span class="badge-tag badge-type">{{ $typeLabel }}</span>
        @if(!empty($shooter['division']))
            <span class="badge-tag badge-muted">{{ $shooter['division'] }}</span>
        @endif
        @if(!empty($shooter['squad']))
            <span class="badge-tag badge-muted">Squad {{ $shooter['squad'] }}</span>
        @endif
    </div>

    {{-- SHOOTER --}}
    <div class="shooter-name">
        {{ $shooter['name'] ?? 'Shooter' }}
        @if(!empty($shooter['bib_number']))
            <span class="bib">#{{ $shooter['bib_number'] }}</span>
        @endif
    </div>

    {{-- SUMMARY CARDS — wrapped in a single .summary-panel so the card
         matches the width & chrome of each per-stage card below. --}}
    <div class="section-title">Match Summary</div>
    <div class="summary-panel">
        <div class="stat-cards">
            <div class="stat-card">
                <div class="stat-value">#{{ $placement['rank'] ?? '—' }}</div>
                <div class="stat-label">Rank</div>
            </div>
            <div class="stat-card">
                <div class="stat-value green">{{ number_format($summary['hit_rate'] ?? 0, 0) }}%</div>
                <div class="stat-label">Hit Rate</div>
            </div>
            <div class="stat-card">
                <div class="stat-value green">{{ $summary['hits'] ?? 0 }}</div>
                <div class="stat-label">Hits</div>
            </div>
            <div class="stat-card">
                <div class="stat-value red">{{ $summary['misses'] ?? 0 }}</div>
                <div class="stat-label">Misses</div>
            </div>
        </div>

        <div class="summary-line">
            <span style="color:#64748b;">{{ $scoreLabel }}:</span>
            <strong>{{ number_format($summary['total_score'] ?? 0, 1) }}</strong>
            <span style="color:#475569;">/ {{ number_format($summary['max_possible'] ?? 0, 1) }}</span>
        </div>
        @if(!empty($placement['rank']) && !empty($placement['total']))
            <div class="summary-line">
                Placement: {{ $placement['rank'] }}{{ match((int)$placement['rank']) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' } }} of {{ $placement['total'] }}
                @if(!empty($placement['percentile']))
                    <span class="hl">(top {{ number_format($placement['percentile'], 0) }}%)</span>
                @endif
            </div>
        @endif
        @if($isPrs && !empty($summary['total_time']))
            <div class="summary-line">
                <span style="color:#64748b;">Total Time:</span>
                <strong style="color:{{ $accent }};">{{ number_format($summary['total_time'], 1) }}s</strong>
            </div>
        @endif
    </div>

    {{-- STAGE BREAKDOWN --}}
    @if(count($stages) > 0)
        <div class="section-title">Per-Stage Breakdown</div>
        @foreach($stages as $stage)
            <div class="stage-card">
                <div class="stage-header">
                    <div class="stage-name">
                        {{ $stage['label'] ?? 'Stage' }}
                        @if(!empty($stage['distance_meters']))
                            <span class="dist">({{ $stage['distance_meters'] }}m)</span>
                        @endif
                    </div>
                    <div>
                        @if(!$isPrs && !empty($stage['distance_multiplier']) && $stage['distance_multiplier'] != 1)
                            <span style="color:{{ $accent }}; font-size:8.5pt;">&times;{{ number_format($stage['distance_multiplier'], 1) }}</span>
                        @endif
                        @if($isPrs && !empty($stage['time']))
                            <span class="stage-time">{{ number_format($stage['time'], 1) }}s</span>
                        @endif
                    </div>
                </div>
                @if(!empty($stage['gongs']))
                    <div class="gong-row">
                        @foreach($stage['gongs'] as $gong)
                            @if(($gong['result'] ?? '') === 'hit')
                                <span class="gong-dot gong-hit">&#10003;</span>
                            @elseif(($gong['result'] ?? '') === 'miss')
                                <span class="gong-dot gong-miss">&#10007;</span>
                            @else
                                <span class="gong-dot gong-ns">-</span>
                            @endif
                        @endforeach
                    </div>
                @endif
                <div class="stage-stats">
                    <div>
                        <span style="color:#22c55e;">{{ $stage['hits'] ?? 0 }} hits</span>
                        &nbsp;/&nbsp;
                        <span style="color:#ef4444;">{{ $stage['misses'] ?? 0 }} miss</span>
                        @if(($stage['no_shots'] ?? 0) > 0)
                            &nbsp;/&nbsp;
                            <span style="color:#64748b;">{{ $stage['no_shots'] }} NS</span>
                        @endif
                    </div>
                    <div class="pts">{{ number_format($stage['score'] ?? 0, 1) }} pts</div>
                </div>
            </div>
        @endforeach
    @endif

    {{-- BEST & WORST --}}
    @if($bestStage || $worstStage)
        <div class="section-title">Best &amp; Worst Stage <span style="font-weight:normal;opacity:0.7;">(by Points)</span></div>
        @if($bestStage)
            <div class="bw-card best">
                <div class="bw-label">
                    <strong class="best-tag">BEST STAGE:</strong>
                    {{ $bestStage['label'] ?? '' }}
                    &mdash; {{ $bestStage['hits'] ?? 0 }}/{{ $bestStage['targets'] ?? 0 }} impacts
                </div>
                <div class="bw-pts" style="color:#22c55e;">{{ number_format($bestStage['score'] ?? 0, 1) }} pts</div>
            </div>
        @endif
        @if($worstStage)
            <div class="bw-card worst">
                <div class="bw-label">
                    <strong class="worst-tag">WORST STAGE:</strong>
                    {{ $worstStage['label'] ?? '' }}
                    &mdash; {{ $worstStage['hits'] ?? 0 }}/{{ $worstStage['targets'] ?? 0 }} impacts
                </div>
                <div class="bw-pts" style="color:#ef4444;">{{ number_format($worstStage['score'] ?? 0, 1) }} pts</div>
            </div>
        @endif
    @endif

    {{-- FIELD COMPARISON --}}
    @if(!empty($fieldStats))
        <div class="section-title">How You Compared</div>
        <div class="field-grid">
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
                    <span class="label">Winner: {{ $fieldStats['winner_name'] }}</span>
                    <span class="value" style="color:{{ $accent }};">{{ number_format($fieldStats['winner_score'] ?? 0, 1) }}</span>
                </div>
            @endif
            @if(!empty($fieldStats['hardest_gong']))
                <div class="field-row">
                    <span class="label">Hardest: <span style="color:#ef4444;">{{ $fieldStats['hardest_gong']['label'] ?? '' }}</span></span>
                    <span class="value" style="color:#ef4444;">{{ number_format($fieldStats['hardest_gong']['hit_rate'] ?? 0, 0) }}%</span>
                </div>
            @endif
            @if(!empty($fieldStats['easiest_gong']))
                <div class="field-row">
                    <span class="label">Easiest: <span style="color:#22c55e;">{{ $fieldStats['easiest_gong']['label'] ?? '' }}</span></span>
                    <span class="value" style="color:#22c55e;">{{ number_format($fieldStats['easiest_gong']['hit_rate'] ?? 0, 0) }}%</span>
                </div>
            @endif
        </div>
    @endif

    {{-- FUN FACTS --}}
    @if(count($funFacts) > 0)
        <div class="section-title">Did You Know?</div>
        @foreach($funFacts as $fact)
            <div class="fact-item">
                <span class="fact-bullet">&#9679;</span>
                <span>{{ $fact }}</span>
            </div>
        @endforeach
    @endif

    {{-- BADGES --}}
    @if(count($badges) > 0)
        @php
            $badgesByCategory = collect($badges)->groupBy('category');
            $categoryOrder = ['match_special', 'lifetime', 'repeatable'];
            $categoryLabels = ['match_special' => 'Match Special', 'lifetime' => 'Lifetime Milestone', 'repeatable' => 'Achievement'];
        @endphp
        <div class="section-title">Badges Awarded</div>
        @foreach($categoryOrder as $cat)
            @if($badgesByCategory->has($cat))
                @foreach($badgesByCategory->get($cat) as $badge)
                    <div class="badge-card badge-{{ $cat }}">
                        <div class="badge-crest">
                            @include('exports.partials.badge-icon-inline', ['name' => $badge['icon'] ?? 'target'])
                        </div>
                        <div class="badge-body">
                            <div class="badge-name">{{ $badge['label'] }}<span class="badge-cat">{{ $categoryLabels[$cat] ?? '' }}</span></div>
                            <div class="badge-desc">
                                {{ $badge['description'] }}
                                @if(!empty($badge['stage'])) &mdash; {{ $badge['stage'] }} @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        @endforeach
    @endif

    {{-- FOOTER --}}
    <div class="footer">
        @if($sponsor)
            <div class="powered-by">
                @if(!empty($sponsor['logo_path']))
                    <img src="{{ public_path('storage/' . $sponsor['logo_path']) }}" alt="">
                @endif
                <span class="text">Results powered by <span class="name">{{ $sponsor['name'] }}</span></span>
            </div>
        @endif
        <div class="brand-sm">DEAD<span>CENTER</span></div>
        <div class="url">deadcenter.co.za</div>
        <div class="gen">This report was generated automatically after match scoring was finalized. &copy; {{ date('Y') }} DeadCenter</div>
    </div>

</body>
</html>
