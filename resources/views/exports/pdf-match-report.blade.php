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

    $accent      = $isPrs ? '#F59E0B' : '#E10600';
    $accentBg    = $isPrs ? '#78350F' : '#7F1D1D';
    $scoreLabel  = $isPrs ? 'Hits' : ($isElr ? 'Points' : 'Score');
    $typeLabel   = strtoupper($scoringType);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 12mm 14mm 16mm 14mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 9pt; color: #e2e8f0; background: #040C1A; }

        .page-header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 10px; border-bottom: 3px solid {{ $accent }}; margin-bottom: 14px; }
        .brand { font-size: 20pt; font-weight: 800; letter-spacing: 3px; color: {{ $accent }}; }
        .brand span { color: #ffffff; }
        .subtitle { font-size: 8pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px; text-align: right; }

        .match-name { font-size: 16pt; font-weight: 700; color: #ffffff; margin-bottom: 4px; }
        .match-meta { font-size: 8.5pt; color: #94a3b8; margin-bottom: 10px; }
        .badge-tag { display: inline-block; font-size: 7.5pt; font-weight: 700; padding: 2px 8px; border-radius: 3px; letter-spacing: 0.5px; }
        .badge-accent { background: {{ $accent }}; color: #fff; }
        .badge-muted { background: #1e293b; color: #cbd5e1; }

        .shooter-name { font-size: 12pt; color: #e2e8f0; margin: 12px 0 4px; }
        .shooter-name .bib { color: #64748b; font-size: 9pt; }

        .section-title { font-size: 8pt; font-weight: 700; color: {{ $accent }}; text-transform: uppercase; letter-spacing: 2px; margin: 16px 0 8px; }

        .stat-cards { display: flex; gap: 8px; margin-bottom: 10px; }
        .stat-card { flex: 1; background: #111D35; border-radius: 6px; padding: 12px 6px; text-align: center; }
        .stat-value { font-size: 20pt; font-weight: 800; color: #ffffff; }
        .stat-value.green { color: #22c55e; }
        .stat-value.red { color: #ef4444; }
        .stat-value.accent { color: {{ $accent }}; }
        .stat-label { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-top: 2px; }

        .summary-line { font-size: 9pt; color: #94a3b8; margin: 3px 0; }
        .summary-line strong { color: #ffffff; }
        .summary-line .hl { color: {{ $accent }}; font-weight: 700; }

        .stage-card { background: #0D1B33; border-radius: 6px; padding: 10px 14px; margin-bottom: 8px; page-break-inside: avoid; }
        .stage-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .stage-name { font-size: 10pt; font-weight: 700; color: #ffffff; }
        .stage-name .dist { font-weight: 400; color: #64748b; font-size: 9pt; }
        .stage-time { font-size: 9pt; color: {{ $accent }}; }

        .gong-row { display: flex; gap: 3px; margin-bottom: 6px; flex-wrap: wrap; }
        .gong-dot { width: 16px; height: 16px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 7pt; font-weight: 700; color: #fff; }
        .gong-hit { background: #22c55e; }
        .gong-miss { background: #ef4444; }
        .gong-ns { background: #374151; color: #6b7280; }

        .stage-stats { font-size: 8.5pt; color: #94a3b8; display: flex; justify-content: space-between; }
        .stage-stats .pts { font-size: 10pt; font-weight: 700; color: #ffffff; }

        .bw-card { background: #111D35; border-radius: 6px; padding: 10px 14px; margin-bottom: 6px; display: flex; justify-content: space-between; align-items: center; }
        .bw-card.best { border-left: 3px solid #22c55e; }
        .bw-card.worst { border-left: 3px solid #ef4444; }
        .bw-label { font-size: 9pt; color: #e2e8f0; }
        .bw-label strong { font-weight: 700; }
        .bw-label .best-tag { color: #22c55e; }
        .bw-label .worst-tag { color: #ef4444; }
        .bw-pts { font-size: 10pt; font-weight: 700; }

        .field-row { background: #0D1B33; border-radius: 5px; padding: 8px 14px; margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center; }
        .field-row .label { font-size: 8.5pt; color: #94a3b8; }
        .field-row .value { font-size: 10pt; font-weight: 700; color: #ffffff; }

        .fact-item { background: #111D35; border-radius: 5px; padding: 8px 14px; margin-bottom: 4px; font-size: 8.5pt; color: #cbd5e1; line-height: 1.5; display: flex; align-items: flex-start; gap: 6px; }
        .fact-bullet { color: {{ $accent }}; font-size: 9pt; flex-shrink: 0; margin-top: 1px; }

        .badge-card { background: #0D1B33; border-radius: 6px; padding: 8px 14px; margin-bottom: 4px; page-break-inside: avoid; }
        .badge-name { font-size: 9pt; font-weight: 700; }
        .badge-desc { font-size: 8pt; color: #94a3b8; margin-top: 2px; }
        .badge-cat { font-size: 7pt; color: #64748b; margin-left: 6px; }
        .badge-special { color: #F59E0B; border-left: 3px solid #F59E0B; }
        .badge-lifetime { color: #A855F7; border-left: 3px solid #A855F7; }
        .badge-repeatable { color: #38BDF8; border-left: 3px solid #38BDF8; }

        .footer { margin-top: 16px; padding-top: 10px; border-top: 1px solid #1e293b; text-align: center; }
        .footer .brand-sm { font-size: 12pt; font-weight: 700; letter-spacing: 2px; color: {{ $accent }}; }
        .footer .brand-sm span { color: #ffffff; }
        .footer .url { font-size: 7.5pt; color: #475569; margin-top: 2px; }
        .footer .gen { font-size: 7pt; color: #334155; margin-top: 6px; }

        .powered-by { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 8px; padding: 6px 0; }
        .powered-by img { height: 18px; max-width: 80px; object-fit: contain; }
        .powered-by .text { font-size: 7.5pt; color: #64748b; }
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
    <div style="margin-bottom: 10px;">
        <span class="badge-tag badge-accent">{{ $typeLabel }}</span>
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

    {{-- SUMMARY CARDS --}}
    <div class="section-title">Match Summary</div>
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
        <div class="section-title">Best &amp; Worst</div>
        @if($bestStage)
            <div class="bw-card best">
                <div class="bw-label"><strong class="best-tag">BEST:</strong> {{ $bestStage['label'] ?? '' }} &mdash; {{ number_format($bestStage['hit_rate'] ?? 0, 0) }}%</div>
                <div class="bw-pts" style="color:#22c55e;">{{ number_format($bestStage['score'] ?? 0, 1) }} pts</div>
            </div>
        @endif
        @if($worstStage)
            <div class="bw-card worst">
                <div class="bw-label"><strong class="worst-tag">WORST:</strong> {{ $worstStage['label'] ?? '' }} &mdash; {{ number_format($worstStage['hit_rate'] ?? 0, 0) }}%</div>
                <div class="bw-pts" style="color:#ef4444;">{{ number_format($worstStage['score'] ?? 0, 1) }} pts</div>
            </div>
        @endif
    @endif

    {{-- FIELD COMPARISON --}}
    @if(!empty($fieldStats))
        <div class="section-title">How You Compared</div>
        @if(isset($fieldStats['avg_score']))
            <div class="field-row">
                <span class="label">Field Average {{ $scoreLabel }}</span>
                <span class="value">{{ number_format($fieldStats['avg_score'], 1) }}</span>
            </div>
        @endif
        @if(isset($fieldStats['avg_hit_rate']))
            <div class="field-row">
                <span class="label">Field Average Hit Rate</span>
                <span class="value">{{ number_format($fieldStats['avg_hit_rate'], 1) }}%</span>
            </div>
        @endif
        @if(!empty($fieldStats['winner_name']))
            <div class="field-row">
                <span class="label">Winner: {{ $fieldStats['winner_name'] }}</span>
                <span class="value" style="color:{{ $accent }};">{{ number_format($fieldStats['winner_score'] ?? 0, 1) }} pts</span>
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
                        <div class="badge-name">{{ $badge['label'] }}<span class="badge-cat">{{ $categoryLabels[$cat] ?? '' }}</span></div>
                        <div class="badge-desc">
                            {{ $badge['description'] }}
                            @if(!empty($badge['stage'])) &mdash; {{ $badge['stage'] }} @endif
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
