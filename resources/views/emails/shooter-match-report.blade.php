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

    $scoringType = strtolower($match['scoring_type'] ?? 'standard');
    $isPrs       = $scoringType === 'prs';
    $isElr       = $scoringType === 'elr';

    // Single DeadCenter brand accent across every match type. The old PRS-only
    // orange was inconsistent with the rest of the platform (the red "LIVE"
    // pill, the scoreboard header, the button accent). Keep the type-chip
    // (PRS / ELR / STANDARD) in its own colour so the discipline is still
    // instantly identifiable.
    $accentColor   = '#e10600';
    $accentDark    = '#b00400';
    $typeChipColor = $isPrs ? '#F59E0B' : ($isElr ? '#38BDF8' : '#ff2b2b');
    $scoreLabel    = $isPrs ? 'Hits' : ($isElr ? 'Points' : 'Score');
    $typeLabel     = strtoupper($scoringType);
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Match Report</title>
    {{-- Responsive overrides. Gmail & Apple Mail honour these; Outlook
         desktop ignores them and falls back to the fixed 600-wide layout.
         Browser preview honours them fully. We deliberately keep the 4-tile
         stat row intact on mobile (just shrunken) instead of stacking —
         stacking via display:block on <td> breaks in too many email clients.
    --}}
    <style>
        @media only screen and (max-width: 520px) {
            .dc-outer { padding:12px 8px !important; }
            .dc-section-pad { padding:20px 16px !important; }
            .dc-summary-card { padding:12px 10px !important; }
            .dc-stage-card { padding:14px !important; }
            .dc-stage-head { font-size:14px !important; }
            .dc-stat-value { font-size:20px !important; }
            .dc-stat-label { font-size:9px !important; letter-spacing:0.5px !important; }
            .dc-stage-gong { width:18px !important; height:18px !important; line-height:18px !important; font-size:10px !important; }
            .dc-page-title { font-size:20px !important; }
            .dc-brand { font-size:22px !important; letter-spacing:2px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#071327;font-family:Arial,Helvetica,sans-serif;">

{{-- Wrapper --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#071327;">

{{-- ============================================================
     ACTION BAR (browser preview only — hidden in emails)

     $showActions is only set on the in-browser shooter-report
     preview route. The email pipeline never sets it, so the block
     is silently omitted and the message looks identical to before.
     Download PDF is the only live action; rendered as a single-cell
     table so it works on the same mobile/email clients without any
     JS or Flexbox.
============================================================= --}}
@if(!empty($showActions ?? false))
<tr><td align="center" style="padding:14px 10px 0;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;">
        <tr>
            <td style="padding:0 6px 14px;" align="right">
                @if(!empty($downloadUrl ?? null))
                    <a href="{{ $downloadUrl }}"
                       style="display:inline-block;padding:10px 18px;background-color:{{ $accentColor }};color:#ffffff;text-decoration:none;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;border-radius:8px;font-family:Arial,Helvetica,sans-serif;">
                        Download PDF
                    </a>
                @endif
            </td>
        </tr>
    </table>
</td></tr>
@endif

<tr><td align="center" class="dc-outer" style="padding:20px 10px;">

{{-- Main container --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;border-radius:12px;overflow:hidden;">

    {{-- ============================================================
         HEADER
    ============================================================= --}}
    <tr>
        <td bgcolor="#071327" class="dc-section-pad" style="padding:32px 30px 24px;border-bottom:3px solid {{ $accentColor }};">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td class="dc-brand" style="font-size:28px;font-weight:bold;letter-spacing:4px;color:{{ $accentColor }};font-family:Arial,Helvetica,sans-serif;">
                        DEAD<span style="color:#ffffff;">CENTER</span>
                    </td>
                    <td align="right" style="font-size:13px;color:#94a3b8;font-family:Arial,Helvetica,sans-serif;text-transform:uppercase;letter-spacing:2px;">
                        Match Report
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- ============================================================
         MATCH INFO
    ============================================================= --}}
    <tr>
        <td bgcolor="#0c1a33" class="dc-section-pad" style="padding:28px 30px 20px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td class="dc-page-title" style="font-size:22px;font-weight:bold;color:#ffffff;font-family:Arial,Helvetica,sans-serif;padding-bottom:8px;">
                        {{ $match['name'] ?? 'Match' }}
                    </td>
                </tr>
                <tr>
                    <td style="font-size:13px;color:#94a3b8;font-family:Arial,Helvetica,sans-serif;padding-bottom:14px;">
                        {{ $match['date'] ?? '' }}
                        @if(!empty($match['location']))
                            &nbsp;&bull;&nbsp; {{ $match['location'] }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="background-color:{{ $typeChipColor }};color:#ffffff;font-size:11px;font-weight:bold;padding:4px 12px;border-radius:4px;letter-spacing:1px;font-family:Arial,Helvetica,sans-serif;">
                                    {{ $typeLabel }}
                                </td>
                                @if(!empty($shooter['division']))
                                <td style="padding-left:8px;">
                                    <span style="background-color:#1e293b;color:#cbd5e1;font-size:11px;padding:4px 12px;border-radius:4px;font-family:Arial,Helvetica,sans-serif;">
                                        {{ $shooter['division'] }}
                                    </span>
                                </td>
                                @endif
                                @if(!empty($shooter['squad']))
                                <td style="padding-left:8px;">
                                    <span style="background-color:#1e293b;color:#cbd5e1;font-size:11px;padding:4px 12px;border-radius:4px;font-family:Arial,Helvetica,sans-serif;">
                                        Squad {{ $shooter['squad'] }}
                                    </span>
                                </td>
                                @endif
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- ============================================================
         SHOOTER NAME + MATCH SUMMARY
         (Summary rewrapped as a single rounded card — same width, same
         padding, same bg as the per-stage breakdown and "How You Compared"
         cards below. Stat tiles sit *inside* the card so the whole section
         reads as one visual unit instead of a flat strip.)
    ============================================================= --}}
    <tr>
        <td bgcolor="#071327" class="dc-section-pad" style="padding:24px 30px 20px;">
            {{-- Shooter identity --}}
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="padding-bottom:14px;">
                <tr>
                    <td style="font-size:16px;color:#e2e8f0;font-family:Arial,Helvetica,sans-serif;">
                        {{ $shooter['name'] ?? 'Shooter' }}
                        @if(!empty($shooter['bib_number']))
                            <span style="color:#64748b;font-size:13px;">&nbsp;#{{ $shooter['bib_number'] }}</span>
                        @endif
                    </td>
                </tr>
            </table>

            {{-- Section heading --}}
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td style="font-size:11px;font-weight:bold;color:{{ $accentColor }};text-transform:uppercase;letter-spacing:2px;padding-bottom:12px;font-family:Arial,Helvetica,sans-serif;">
                        Match Summary
                    </td>
                </tr>
            </table>

            {{-- Summary card (matches .stage-card / .compared-card) --}}
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td bgcolor="#0c1a33" class="dc-summary-card" style="border-radius:8px;padding:18px;">
                        {{-- 4 stat tiles. `td width="25%"` with a nested
                             rounded cell each gives us the equal-width grid
                             most email clients render correctly. The .dc-stat
                             class lets the mobile stylesheet stack them on
                             narrow screens. --}}
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td width="25%" align="center" class="dc-stat" style="padding:0 3px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-radius:8px;overflow:hidden;">
                                        <tr>
                                            <td bgcolor="#1d2d4a" align="center" style="padding:14px 6px 10px;">
                                                <div class="dc-stat-value" style="font-size:26px;font-weight:bold;color:#ffffff;font-family:Arial,Helvetica,sans-serif;">
                                                    #{{ $placement['rank'] ?? '—' }}
                                                </div>
                                                <div class="dc-stat-label" style="font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:1px;padding-top:4px;font-family:Arial,Helvetica,sans-serif;">
                                                    Rank
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                                <td width="25%" align="center" class="dc-stat" style="padding:0 3px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-radius:8px;overflow:hidden;">
                                        <tr>
                                            <td bgcolor="#1d2d4a" align="center" style="padding:14px 6px 10px;">
                                                <div class="dc-stat-value" style="font-size:26px;font-weight:bold;color:#22c55e;font-family:Arial,Helvetica,sans-serif;">
                                                    {{ number_format($summary['hit_rate'] ?? 0, 0) }}%
                                                </div>
                                                <div class="dc-stat-label" style="font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:1px;padding-top:4px;font-family:Arial,Helvetica,sans-serif;">
                                                    Hit Rate
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                                <td width="25%" align="center" class="dc-stat" style="padding:0 3px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-radius:8px;overflow:hidden;">
                                        <tr>
                                            <td bgcolor="#1d2d4a" align="center" style="padding:14px 6px 10px;">
                                                <div class="dc-stat-value" style="font-size:26px;font-weight:bold;color:#22c55e;font-family:Arial,Helvetica,sans-serif;">
                                                    {{ $summary['hits'] ?? 0 }}
                                                </div>
                                                <div class="dc-stat-label" style="font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:1px;padding-top:4px;font-family:Arial,Helvetica,sans-serif;">
                                                    Hits
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                                <td width="25%" align="center" class="dc-stat" style="padding:0 3px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-radius:8px;overflow:hidden;">
                                        <tr>
                                            <td bgcolor="#1d2d4a" align="center" style="padding:14px 6px 10px;">
                                                <div class="dc-stat-value" style="font-size:26px;font-weight:bold;color:#ef4444;font-family:Arial,Helvetica,sans-serif;">
                                                    {{ $summary['misses'] ?? 0 }}
                                                </div>
                                                <div class="dc-stat-label" style="font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:1px;padding-top:4px;font-family:Arial,Helvetica,sans-serif;">
                                                    Misses
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        {{-- Score + Placement text --}}
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="padding-top:14px;">
                            <tr>
                                <td style="font-size:15px;color:#e2e8f0;font-family:Arial,Helvetica,sans-serif;padding-bottom:4px;">
                                    <span style="color:#64748b;">{{ $scoreLabel }}:</span>
                                    <strong style="color:#ffffff;">{{ number_format($summary['total_score'] ?? 0, 1) }}</strong>
                                    <span style="color:#475569;">/ {{ number_format($summary['max_possible'] ?? 0, 1) }}</span>
                                </td>
                            </tr>
                            @if(!empty($placement['rank']) && !empty($placement['total']))
                            <tr>
                                <td style="font-size:14px;color:#94a3b8;font-family:Arial,Helvetica,sans-serif;">
                                    Placement: {{ $placement['rank'] }}{{ $placement['rank'] == 1 ? 'st' : ($placement['rank'] == 2 ? 'nd' : ($placement['rank'] == 3 ? 'rd' : 'th')) }} of {{ $placement['total'] }}
                                    @if(!empty($placement['percentile']))
                                        <span style="color:{{ $accentColor }};font-weight:bold;">(top {{ number_format($placement['percentile'], 0) }}%)</span>
                                    @endif
                                </td>
                            </tr>
                            @endif
                            @if($isPrs && !empty($summary['total_time']))
                            <tr>
                                <td style="font-size:14px;color:#94a3b8;font-family:Arial,Helvetica,sans-serif;padding-top:4px;">
                                    <span style="color:#64748b;">Total Time:</span>
                                    <strong style="color:#F59E0B;">{{ number_format($summary['total_time'], 1) }}s</strong>
                                </td>
                            </tr>
                            @endif
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- ============================================================
         PER-STAGE BREAKDOWN
    ============================================================= --}}
    @if(count($stages) > 0)
    <tr>
        <td bgcolor="#071327" class="dc-section-pad" style="padding:28px 30px;">
            {{-- Section heading --}}
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td style="font-size:11px;font-weight:bold;color:{{ $accentColor }};text-transform:uppercase;letter-spacing:2px;padding-bottom:18px;font-family:Arial,Helvetica,sans-serif;">
                        Per-Stage Breakdown
                    </td>
                </tr>
            </table>

            @foreach($stages as $idx => $stage)
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:{{ $idx < count($stages) - 1 ? '12px' : '0' }};">
                {{-- Stage card — same rounded container, same width as
                     the Match Summary card above and the How-You-Compared
                     rows below. --}}
                <tr>
                    <td bgcolor="#0c1a33" class="dc-stage-card" style="border-radius:8px;padding:16px 18px;">
                        {{-- Stage title row --}}
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="padding-bottom:12px;">
                            <tr>
                                <td class="dc-stage-head" style="font-size:15px;font-weight:bold;color:#ffffff;font-family:Arial,Helvetica,sans-serif;">
                                    {{ $stage['label'] ?? 'Stage ' . ($idx + 1) }}
                                    @if(!empty($stage['distance_meters']))
                                        <span style="color:#64748b;font-weight:normal;font-size:13px;">
                                            ({{ $stage['distance_meters'] }}m)
                                        </span>
                                    @endif
                                </td>
                                <td align="right" style="font-size:13px;color:#94a3b8;font-family:Arial,Helvetica,sans-serif;">
                                    @if(!$isPrs && !empty($stage['distance_multiplier']))
                                        <span style="color:{{ $accentColor }};">&times;{{ number_format($stage['distance_multiplier'], 1) }}</span>
                                    @endif
                                    @if($isPrs && !empty($stage['time']))
                                        <span style="color:#F59E0B;">{{ number_format($stage['time'], 1) }}s</span>
                                    @endif
                                </td>
                            </tr>
                        </table>

                        {{-- Gong indicators --}}
                        @if(!empty($stage['gongs']))
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="padding-bottom:12px;">
                            <tr>
                                @foreach($stage['gongs'] as $gong)
                                <td align="center" style="padding:0 2px;">
                                    @if(($gong['result'] ?? '') === 'hit')
                                        <span class="dc-stage-gong" style="display:inline-block;width:22px;height:22px;border-radius:50%;background:#22c55e;color:#ffffff;text-align:center;line-height:22px;font-size:11px;font-weight:bold;">&#10003;</span>
                                    @elseif(($gong['result'] ?? '') === 'miss')
                                        <span class="dc-stage-gong" style="display:inline-block;width:22px;height:22px;border-radius:50%;background:#ef4444;color:#ffffff;text-align:center;line-height:22px;font-size:11px;font-weight:bold;">&#10007;</span>
                                    @else
                                        <span class="dc-stage-gong" style="display:inline-block;width:22px;height:22px;border-radius:50%;background:#374151;color:#6b7280;text-align:center;line-height:22px;font-size:11px;">-</span>
                                    @endif
                                </td>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach($stage['gongs'] as $gong)
                                <td align="center" style="padding:2px 3px 0;">
                                    <span style="font-size:9px;color:#64748b;font-family:Arial,Helvetica,sans-serif;white-space:nowrap;">
                                        {{ $gong['label'] ?? '' }}
                                    </span>
                                </td>
                                @endforeach
                            </tr>
                        </table>
                        @endif

                        {{-- Stage stats line --}}
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td style="font-size:13px;color:#94a3b8;font-family:Arial,Helvetica,sans-serif;">
                                    <span style="color:#22c55e;">{{ $stage['hits'] ?? 0 }} hits</span>
                                    &nbsp;/&nbsp;
                                    <span style="color:#ef4444;">{{ $stage['misses'] ?? 0 }} miss</span>
                                    @if(($stage['no_shots'] ?? 0) > 0)
                                        &nbsp;/&nbsp;
                                        <span style="color:#64748b;">{{ $stage['no_shots'] }} NS</span>
                                    @endif
                                </td>
                                <td align="right" style="font-size:15px;font-weight:bold;color:#ffffff;font-family:Arial,Helvetica,sans-serif;">
                                    {{ number_format($stage['score'] ?? 0, 1) }} pts
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            @endforeach
        </td>
    </tr>
    @endif

    {{-- ============================================================
         BEST & WORST
    ============================================================= --}}
    @if($bestStage || $worstStage)
    <tr>
        <td bgcolor="#0c1a33" class="dc-section-pad" style="padding:24px 30px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td style="font-size:11px;font-weight:bold;color:{{ $accentColor }};text-transform:uppercase;letter-spacing:2px;padding-bottom:14px;font-family:Arial,Helvetica,sans-serif;">
                        Best &amp; Worst Stage <span style="color:#94a3b8;font-weight:normal;">(by Points)</span>
                    </td>
                </tr>
            </table>
            @if($bestStage)
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:10px;">
                <tr>
                    <td bgcolor="#1d2d4a" style="border-radius:8px;padding:14px 18px;border-left:4px solid #22c55e;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td style="font-size:14px;color:#e2e8f0;font-family:Arial,Helvetica,sans-serif;">
                                    <strong style="color:#22c55e;">BEST STAGE:</strong>
                                    {{ $bestStage['label'] ?? '' }}
                                    &mdash;
                                    {{ $bestStage['hits'] ?? 0 }}/{{ $bestStage['targets'] ?? 0 }} impacts
                                </td>
                                <td align="right" style="font-size:14px;font-weight:bold;color:#22c55e;font-family:Arial,Helvetica,sans-serif;">
                                    {{ number_format($bestStage['score'] ?? 0, 1) }} pts
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            @endif
            @if($worstStage)
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td bgcolor="#1d2d4a" style="border-radius:8px;padding:14px 18px;border-left:4px solid #ef4444;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td style="font-size:14px;color:#e2e8f0;font-family:Arial,Helvetica,sans-serif;">
                                    <strong style="color:#ef4444;">WORST STAGE:</strong>
                                    {{ $worstStage['label'] ?? '' }}
                                    &mdash;
                                    {{ $worstStage['hits'] ?? 0 }}/{{ $worstStage['targets'] ?? 0 }} impacts
                                </td>
                                <td align="right" style="font-size:14px;font-weight:bold;color:#ef4444;font-family:Arial,Helvetica,sans-serif;">
                                    {{ number_format($worstStage['score'] ?? 0, 1) }} pts
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            @endif
        </td>
    </tr>
    @endif

    {{-- ============================================================
         HOW YOU COMPARED
    ============================================================= --}}
    @if(!empty($fieldStats))
    <tr>
        <td bgcolor="#071327" class="dc-section-pad" style="padding:24px 30px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td style="font-size:11px;font-weight:bold;color:{{ $accentColor }};text-transform:uppercase;letter-spacing:2px;padding-bottom:16px;font-family:Arial,Helvetica,sans-serif;">
                        How You Compared
                    </td>
                </tr>
            </table>

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                {{-- Field Avg Score --}}
                @if(isset($fieldStats['avg_score']))
                <tr>
                    <td bgcolor="#0c1a33" style="border-radius:6px;padding:12px 18px;{{ isset($fieldStats['avg_hit_rate']) || isset($fieldStats['winner_name']) || isset($fieldStats['hardest_gong']) || isset($fieldStats['easiest_gong']) ? 'margin-bottom:6px;' : '' }}">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td style="font-size:13px;color:#94a3b8;font-family:Arial,Helvetica,sans-serif;">
                                    Field Average {{ $scoreLabel }}
                                </td>
                                <td align="right" style="font-size:15px;font-weight:bold;color:#ffffff;font-family:Arial,Helvetica,sans-serif;">
                                    {{ number_format($fieldStats['avg_score'], 1) }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr><td style="height:6px;font-size:0;line-height:0;">&nbsp;</td></tr>
                @endif

                {{-- Field Avg Hit Rate --}}
                @if(isset($fieldStats['avg_hit_rate']))
                <tr>
                    <td bgcolor="#0c1a33" style="border-radius:6px;padding:12px 18px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td style="font-size:13px;color:#94a3b8;font-family:Arial,Helvetica,sans-serif;">
                                    Field Average Hit Rate
                                </td>
                                <td align="right" style="font-size:15px;font-weight:bold;color:#ffffff;font-family:Arial,Helvetica,sans-serif;">
                                    {{ number_format($fieldStats['avg_hit_rate'], 1) }}%
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr><td style="height:6px;font-size:0;line-height:0;">&nbsp;</td></tr>
                @endif

                {{-- Winner --}}
                @if(!empty($fieldStats['winner_name']))
                <tr>
                    <td bgcolor="#0c1a33" style="border-radius:6px;padding:12px 18px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td style="font-size:13px;color:#94a3b8;font-family:Arial,Helvetica,sans-serif;">
                                    Winner: <span style="color:#e2e8f0;">{{ $fieldStats['winner_name'] }}</span>
                                </td>
                                <td align="right" style="font-size:15px;font-weight:bold;color:{{ $accentColor }};font-family:Arial,Helvetica,sans-serif;">
                                    {{ number_format($fieldStats['winner_score'] ?? 0, 1) }} pts
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr><td style="height:6px;font-size:0;line-height:0;">&nbsp;</td></tr>
                @endif

                {{-- Hardest Gong --}}
                @if(!empty($fieldStats['hardest_gong']))
                <tr>
                    <td bgcolor="#0c1a33" style="border-radius:6px;padding:12px 18px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td style="font-size:13px;color:#94a3b8;font-family:Arial,Helvetica,sans-serif;">
                                    Hardest Gong:
                                    <span style="color:#ef4444;">{{ $fieldStats['hardest_gong']['label'] ?? '' }}</span>
                                </td>
                                <td align="right" style="font-size:14px;font-weight:bold;color:#ef4444;font-family:Arial,Helvetica,sans-serif;">
                                    {{ number_format($fieldStats['hardest_gong']['hit_rate'] ?? 0, 0) }}% hit
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr><td style="height:6px;font-size:0;line-height:0;">&nbsp;</td></tr>
                @endif

                {{-- Easiest Gong --}}
                @if(!empty($fieldStats['easiest_gong']))
                <tr>
                    <td bgcolor="#0c1a33" style="border-radius:6px;padding:12px 18px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td style="font-size:13px;color:#94a3b8;font-family:Arial,Helvetica,sans-serif;">
                                    Easiest Gong:
                                    <span style="color:#22c55e;">{{ $fieldStats['easiest_gong']['label'] ?? '' }}</span>
                                </td>
                                <td align="right" style="font-size:14px;font-weight:bold;color:#22c55e;font-family:Arial,Helvetica,sans-serif;">
                                    {{ number_format($fieldStats['easiest_gong']['hit_rate'] ?? 0, 0) }}% hit
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                @endif
            </table>
        </td>
    </tr>
    @endif

    {{-- ============================================================
         DID YOU KNOW?
    ============================================================= --}}
    @if(count($funFacts) > 0)
    <tr>
        <td bgcolor="#0c1a33" class="dc-section-pad" style="padding:24px 30px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td style="font-size:11px;font-weight:bold;color:{{ $accentColor }};text-transform:uppercase;letter-spacing:2px;padding-bottom:14px;font-family:Arial,Helvetica,sans-serif;">
                        Did You Know?
                    </td>
                </tr>
            </table>

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                @foreach($funFacts as $fact)
                <tr>
                    <td bgcolor="#1d2d4a" style="border-radius:6px;padding:12px 18px;{{ !$loop->last ? 'margin-bottom:6px;' : '' }}">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td width="24" valign="top" style="font-size:14px;color:{{ $accentColor }};font-family:Arial,Helvetica,sans-serif;padding-right:8px;">
                                    &#9679;
                                </td>
                                <td style="font-size:13px;color:#cbd5e1;line-height:1.5;font-family:Arial,Helvetica,sans-serif;">
                                    {{ $fact }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                @if(!$loop->last)
                <tr><td style="height:6px;font-size:0;line-height:0;">&nbsp;</td></tr>
                @endif
                @endforeach
            </table>
        </td>
    </tr>
    @endif

    {{-- ============================================================
         BADGES AWARDED
         Match-report badge cards styled to match the marketing badge
         gallery (resources/views/components/badge-card.blade.php):
         dark rounded panel, family-coloured crest on the left, tier
         overline, title, description and earn-chip. Rendered with
         nested tables + inline styles so it survives Gmail/Outlook
         while still reading as "premium" in the browser preview.
    ============================================================= --}}
    @if(count($badges) > 0)
    @php
        $tierLabels = [
            'featured'  => 'Signature Badge',
            'elite'     => 'Elite Achievement',
            'milestone' => 'Lifetime Milestone',
            'earned'    => 'Repeatable',
        ];
        $tierOrder = ['featured' => 0, 'elite' => 1, 'milestone' => 2, 'earned' => 3];

        $distOverlineLabels = [
            'dist-700' => 'Extreme Distance',
            'dist-600' => 'Long Distance',
            'dist-500' => 'Mid Distance',
            'dist-400' => 'Standard Distance',
        ];

        // Family + tier palettes mirror components/badge-card.blade.php. Email-
        // safe hex values (no rgba() — not every client resolves it cleanly
        // against dark backgrounds).
        $palettes = [
            'prs' => [
                'featured'  => ['border' => '#1b3a52', 'accent' => '#38BDF8', 'overline' => '#7DD3FC', 'crest_bg' => '#0b1f2c', 'crest_border' => '#1e4a6b', 'chip_bg' => '#08202e', 'chip_border' => '#1e5172', 'chip_text' => '#7DD3FC'],
                'elite'     => ['border' => '#15314a', 'accent' => '#38BDF8', 'overline' => '#7DD3FC', 'crest_bg' => '#0a1b26', 'crest_border' => '#184461', 'chip_bg' => '#07182a', 'chip_border' => '#184968', 'chip_text' => '#7DD3FC'],
                'milestone' => ['border' => '#22262e', 'accent' => null,      'overline' => '#7DD3FC', 'crest_bg' => '#111319', 'crest_border' => '#2a2e37', 'chip_bg' => '#14161c', 'chip_border' => '#2a2e37', 'chip_text' => '#a1a1aa'],
                'earned'    => ['border' => '#1e2129', 'accent' => null,      'overline' => '#a1a1aa', 'crest_bg' => '#101218', 'crest_border' => '#252932', 'chip_bg' => '#14161c', 'chip_border' => '#252932', 'chip_text' => '#a1a1aa'],
            ],
            'rf' => [
                'featured'  => ['border' => '#4a3410', 'accent' => '#FBBF24', 'overline' => '#FCD34D', 'crest_bg' => '#291b08', 'crest_border' => '#5b3f14', 'chip_bg' => '#231606', 'chip_border' => '#5b3f14', 'chip_text' => '#FCD34D'],
                'elite'     => ['border' => '#3d2a0c', 'accent' => '#FBBF24', 'overline' => '#FCD34D', 'crest_bg' => '#22160a', 'crest_border' => '#4a3410', 'chip_bg' => '#1d1207', 'chip_border' => '#4a3410', 'chip_text' => '#FCD34D'],
                'milestone' => ['border' => '#22262e', 'accent' => null,      'overline' => '#FCD34D', 'crest_bg' => '#111319', 'crest_border' => '#2a2e37', 'chip_bg' => '#14161c', 'chip_border' => '#2a2e37', 'chip_text' => '#a1a1aa'],
                'earned'    => ['border' => '#1e2129', 'accent' => null,      'overline' => '#a1a1aa', 'crest_bg' => '#101218', 'crest_border' => '#252932', 'chip_bg' => '#14161c', 'chip_border' => '#252932', 'chip_text' => '#a1a1aa'],
            ],
        ];

        // Distance flush badges (RF only) get a per-distance colour treatment.
        $distPalettes = [
            'dist-700' => ['border' => '#4a1a1a', 'accent' => '#F87171', 'overline' => '#FCA5A5', 'crest_bg' => '#2a0e0e', 'crest_border' => '#5b2323', 'chip_bg' => '#24090a', 'chip_border' => '#5b2323', 'chip_text' => '#FCA5A5'],
            'dist-600' => ['border' => '#42260f', 'accent' => '#FB923C', 'overline' => '#FDBA74', 'crest_bg' => '#26150a', 'crest_border' => '#512f13', 'chip_bg' => '#1f1108', 'chip_border' => '#512f13', 'chip_text' => '#FDBA74'],
            'dist-500' => ['border' => '#3d3413', 'accent' => '#FACC15', 'overline' => '#FDE68A', 'crest_bg' => '#1e1a0a', 'crest_border' => '#493e18', 'chip_bg' => '#181509', 'chip_border' => '#493e18', 'chip_text' => '#FDE68A'],
            'dist-400' => ['border' => '#143029', 'accent' => '#34D399', 'overline' => '#86EFAC', 'crest_bg' => '#0a1c17', 'crest_border' => '#1a3e33', 'chip_bg' => '#08171324', 'chip_border' => '#1a3e33', 'chip_text' => '#86EFAC'],
        ];

        // Order badges: featured → elite → milestone → earned, keeping stable insertion order inside each tier.
        $orderedBadges = collect($badges)
            ->sortBy(fn ($b) => $tierOrder[$b['tier'] ?? 'earned'] ?? 9)
            ->values();
    @endphp
    <tr>
        <td bgcolor="#071327" class="dc-section-pad" style="padding:24px 30px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td style="font-size:11px;font-weight:bold;color:{{ $accentColor }};text-transform:uppercase;letter-spacing:2px;padding-bottom:16px;font-family:Arial,Helvetica,sans-serif;">
                        Badges Awarded
                    </td>
                </tr>
            </table>

            @foreach($orderedBadges as $badge)
                @php
                    $family = $badge['family'] ?? 'prs';
                    $tier   = $badge['tier'] ?? 'earned';
                    $icon   = $badge['icon'] ?? 'target';
                    $isDist = str_starts_with($icon, 'dist-');
                    $s = ($isDist && isset($distPalettes[$icon]))
                        ? $distPalettes[$icon]
                        : ($palettes[$family][$tier] ?? $palettes['prs']['earned']);
                    $overlineText = $isDist
                        ? ($distOverlineLabels[$icon] ?? $tierLabels[$tier])
                        : ($tierLabels[$tier] ?? 'Badge');
                    $chipText = $badge['earn_chip'] ?? null;
                @endphp
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:12px;">
                    <tr>
                        <td bgcolor="#0b0b10" style="border:1px solid {{ $s['border'] }};border-radius:18px;padding:0;">
                            @if($s['accent'])
                                {{-- Top accent strip for featured / elite tiers.
                                     Flat colour (not a gradient) so Outlook and Gmail render it; the
                                     marketing card's gradient glow degrades gracefully to a solid bar. --}}
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                    <tr>
                                        <td bgcolor="{{ $s['accent'] }}" height="2" style="height:2px;line-height:2px;font-size:0;border-top-left-radius:18px;border-top-right-radius:18px;">&nbsp;</td>
                                    </tr>
                                </table>
                            @endif
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td valign="top" width="76" style="padding:18px 0 18px 18px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td width="56" height="56" align="center" valign="middle" bgcolor="{{ $s['crest_bg'] }}" style="width:56px;height:56px;border:1px solid {{ $s['crest_border'] }};border-radius:14px;color:{{ $s['accent'] ?? $s['overline'] }};font-family:Arial,Helvetica,sans-serif;">
                                                    @if($isDist)
                                                        @php $meters = substr($icon, 5); @endphp
                                                        <span style="font-size:15px;font-weight:900;letter-spacing:-0.5px;color:{{ $s['accent'] ?? $s['overline'] }};">{{ $meters }}<span style="font-size:10px;">m</span></span>
                                                    @else
                                                        <x-badge-icon :name="$icon" class="h-6 w-6" style="width:24px;height:24px;" />
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td valign="top" style="padding:18px 18px 18px 14px;font-family:Arial,Helvetica,sans-serif;">
                                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:{{ $s['overline'] }};">
                                            {{ $overlineText }}
                                        </div>
                                        <div style="font-size:17px;font-weight:700;color:#ffffff;padding-top:6px;line-height:1.25;">
                                            {{ $badge['label'] }}
                                        </div>
                                        <div style="font-size:13px;color:#a1a1aa;padding-top:6px;line-height:1.55;">
                                            {{ $badge['description'] }}
                                        </div>
                                        @if(!empty($badge['stage']) || $chipText)
                                            <div style="padding-top:10px;">
                                                @if($chipText)
                                                    <span style="display:inline-block;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:1px;padding:4px 10px;border-radius:999px;border:1px solid {{ $s['chip_border'] }};background-color:{{ $s['chip_bg'] }};color:{{ $s['chip_text'] }};margin-right:6px;">
                                                        {{ $chipText }}
                                                    </span>
                                                @endif
                                                @if(!empty($badge['stage']))
                                                    <span style="display:inline-block;font-size:11px;color:#64748b;">
                                                        {{ $badge['stage'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            @endforeach
        </td>
    </tr>
    @endif

    {{-- ============================================================
         FOOTER
    ============================================================= --}}
    <tr>
        <td bgcolor="#071327" class="dc-section-pad" style="padding:28px 30px;border-top:2px solid #1e293b;">
            @php $sponsor = $report['sponsor'] ?? null; @endphp
            @if($sponsor)
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="padding-bottom:16px;">
                    <tr>
                        <td align="center">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    @if(!empty($sponsor['logo_path']))
                                        <td style="padding-right:8px;" valign="middle">
                                            <img src="{{ asset('storage/' . $sponsor['logo_path']) }}" alt="{{ $sponsor['name'] }}" style="height:20px;max-width:80px;object-fit:contain;">
                                        </td>
                                    @endif
                                    <td valign="middle" style="font-size:11px;color:#64748b;font-family:Arial,Helvetica,sans-serif;">
                                        Results powered by <span style="font-weight:600;color:#94a3b8;">{{ $sponsor['name'] }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            @endif
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td align="center" class="dc-brand" style="font-size:18px;font-weight:bold;letter-spacing:3px;color:{{ $accentColor }};font-family:Arial,Helvetica,sans-serif;padding-bottom:8px;">
                        DEAD<span style="color:#ffffff;">CENTER</span>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="font-size:12px;color:#475569;font-family:Arial,Helvetica,sans-serif;">
                        deadcenter.co.za
                    </td>
                </tr>
                <tr>
                    <td align="center" style="font-size:11px;color:#334155;font-family:Arial,Helvetica,sans-serif;padding-top:12px;">
                        This report was generated automatically after match scoring was finalized. A PDF copy is attached.
                    </td>
                </tr>
            </table>
        </td>
    </tr>

</table>
{{-- End main container --}}

</td></tr>
</table>
{{-- End wrapper --}}

</body>
</html>
