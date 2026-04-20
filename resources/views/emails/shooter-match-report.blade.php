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

    $accentColor   = $isPrs ? '#F59E0B' : '#ff2b2b';
    $accentDark    = $isPrs ? '#D97706' : '#e10600';
    $scoreLabel    = $isPrs ? 'Hits' : ($isElr ? 'Points' : 'Score');
    $typeLabel     = strtoupper($scoringType);
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Match Report</title>
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

<tr><td align="center" style="padding:20px 10px;">

{{-- Main container --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;border-radius:12px;overflow:hidden;">

    {{-- ============================================================
         HEADER
    ============================================================= --}}
    <tr>
        <td bgcolor="#071327" style="padding:32px 30px 24px;border-bottom:3px solid {{ $accentColor }};">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td style="font-size:28px;font-weight:bold;letter-spacing:4px;color:{{ $accentColor }};font-family:Arial,Helvetica,sans-serif;">
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
        <td bgcolor="#0c1a33" style="padding:28px 30px 20px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td style="font-size:22px;font-weight:bold;color:#ffffff;font-family:Arial,Helvetica,sans-serif;padding-bottom:8px;">
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
                                <td style="background-color:{{ $accentColor }};color:#ffffff;font-size:11px;font-weight:bold;padding:4px 12px;border-radius:4px;letter-spacing:1px;font-family:Arial,Helvetica,sans-serif;">
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

    {{-- Divider --}}
    <tr><td bgcolor="#071327" style="height:2px;font-size:0;line-height:0;">&nbsp;</td></tr>

    {{-- ============================================================
         SHOOTER NAME
    ============================================================= --}}
    <tr>
        <td bgcolor="#0c1a33" style="padding:20px 30px 6px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td style="font-size:16px;color:#e2e8f0;font-family:Arial,Helvetica,sans-serif;">
                        {{ $shooter['name'] ?? 'Shooter' }}
                        @if(!empty($shooter['bib_number']))
                            <span style="color:#64748b;font-size:13px;">&nbsp;#{{ $shooter['bib_number'] }}</span>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- ============================================================
         MATCH SUMMARY — stat cards
    ============================================================= --}}
    <tr>
        <td bgcolor="#0c1a33" style="padding:14px 30px 24px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                {{-- Section heading --}}
                <tr>
                    <td colspan="4" style="font-size:11px;font-weight:bold;color:{{ $accentColor }};text-transform:uppercase;letter-spacing:2px;padding-bottom:14px;font-family:Arial,Helvetica,sans-serif;">
                        Match Summary
                    </td>
                </tr>
                {{-- Cards row --}}
                <tr>
                    {{-- Rank --}}
                    <td width="25%" align="center" style="padding:0 4px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-radius:8px;overflow:hidden;">
                            <tr>
                                <td bgcolor="#1d2d4a" align="center" style="padding:16px 6px 10px;">
                                    <div style="font-size:28px;font-weight:bold;color:#ffffff;font-family:Arial,Helvetica,sans-serif;">
                                        #{{ $placement['rank'] ?? '—' }}
                                    </div>
                                    <div style="font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:1px;padding-top:4px;font-family:Arial,Helvetica,sans-serif;">
                                        Rank
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                    {{-- Hit Rate --}}
                    <td width="25%" align="center" style="padding:0 4px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-radius:8px;overflow:hidden;">
                            <tr>
                                <td bgcolor="#1d2d4a" align="center" style="padding:16px 6px 10px;">
                                    <div style="font-size:28px;font-weight:bold;color:#22c55e;font-family:Arial,Helvetica,sans-serif;">
                                        {{ number_format($summary['hit_rate'] ?? 0, 0) }}%
                                    </div>
                                    <div style="font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:1px;padding-top:4px;font-family:Arial,Helvetica,sans-serif;">
                                        Hit Rate
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                    {{-- Hits --}}
                    <td width="25%" align="center" style="padding:0 4px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-radius:8px;overflow:hidden;">
                            <tr>
                                <td bgcolor="#1d2d4a" align="center" style="padding:16px 6px 10px;">
                                    <div style="font-size:28px;font-weight:bold;color:#22c55e;font-family:Arial,Helvetica,sans-serif;">
                                        {{ $summary['hits'] ?? 0 }}
                                    </div>
                                    <div style="font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:1px;padding-top:4px;font-family:Arial,Helvetica,sans-serif;">
                                        Hits
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                    {{-- Misses --}}
                    <td width="25%" align="center" style="padding:0 4px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-radius:8px;overflow:hidden;">
                            <tr>
                                <td bgcolor="#1d2d4a" align="center" style="padding:16px 6px 10px;">
                                    <div style="font-size:28px;font-weight:bold;color:#ef4444;font-family:Arial,Helvetica,sans-serif;">
                                        {{ $summary['misses'] ?? 0 }}
                                    </div>
                                    <div style="font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:1px;padding-top:4px;font-family:Arial,Helvetica,sans-serif;">
                                        Misses
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            {{-- Score + Placement text --}}
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="padding-top:16px;">
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

    {{-- ============================================================
         PER-STAGE BREAKDOWN
    ============================================================= --}}
    @if(count($stages) > 0)
    <tr>
        <td bgcolor="#071327" style="padding:28px 30px;">
            {{-- Section heading --}}
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td style="font-size:11px;font-weight:bold;color:{{ $accentColor }};text-transform:uppercase;letter-spacing:2px;padding-bottom:18px;font-family:Arial,Helvetica,sans-serif;">
                        Per-Stage Breakdown
                    </td>
                </tr>
            </table>

            @foreach($stages as $idx => $stage)
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:{{ $idx < count($stages) - 1 ? '16px' : '0' }};">
                {{-- Stage card --}}
                <tr>
                    <td bgcolor="#0c1a33" style="border-radius:8px;padding:16px 18px;">
                        {{-- Stage title row --}}
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="padding-bottom:12px;">
                            <tr>
                                <td style="font-size:15px;font-weight:bold;color:#ffffff;font-family:Arial,Helvetica,sans-serif;">
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
                                <td align="center" style="padding:0 3px;">
                                    @if(($gong['result'] ?? '') === 'hit')
                                        <span style="display:inline-block;width:22px;height:22px;border-radius:50%;background:#22c55e;color:#ffffff;text-align:center;line-height:22px;font-size:11px;font-weight:bold;">&#10003;</span>
                                    @elseif(($gong['result'] ?? '') === 'miss')
                                        <span style="display:inline-block;width:22px;height:22px;border-radius:50%;background:#ef4444;color:#ffffff;text-align:center;line-height:22px;font-size:11px;font-weight:bold;">&#10007;</span>
                                    @else
                                        <span style="display:inline-block;width:22px;height:22px;border-radius:50%;background:#374151;color:#6b7280;text-align:center;line-height:22px;font-size:11px;">-</span>
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
        <td bgcolor="#0c1a33" style="padding:24px 30px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td style="font-size:11px;font-weight:bold;color:{{ $accentColor }};text-transform:uppercase;letter-spacing:2px;padding-bottom:14px;font-family:Arial,Helvetica,sans-serif;">
                        Best &amp; Worst Stage
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
        <td bgcolor="#071327" style="padding:24px 30px;">
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
        <td bgcolor="#0c1a33" style="padding:24px 30px;">
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
    ============================================================= --}}
    @if(count($badges) > 0)
    @php
        $badgesByCategory = collect($badges)->groupBy('category');
        $categoryOrder = ['match_special', 'lifetime', 'repeatable'];
        $categoryLabels = ['match_special' => 'Match Special', 'lifetime' => 'Lifetime Milestone', 'repeatable' => 'Achievement'];
        $categoryColors = ['match_special' => '#F59E0B', 'lifetime' => '#A855F7', 'repeatable' => '#38BDF8'];
        $badgeIcons = [
            'match_special' => '&#9733;',
            'lifetime' => '&#9734;',
            'repeatable' => '&#9679;',
        ];
    @endphp
    <tr>
        <td bgcolor="#071327" style="padding:24px 30px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td style="font-size:11px;font-weight:bold;color:{{ $accentColor }};text-transform:uppercase;letter-spacing:2px;padding-bottom:16px;font-family:Arial,Helvetica,sans-serif;">
                        Badges Awarded
                    </td>
                </tr>
            </table>

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                @foreach($categoryOrder as $cat)
                    @if($badgesByCategory->has($cat))
                        @foreach($badgesByCategory->get($cat) as $badge)
                        <tr>
                            <td bgcolor="#0c1a33" style="border-radius:8px;padding:14px 18px;border-left:4px solid {{ $categoryColors[$cat] ?? '#38BDF8' }};{{ !$loop->last || !$loop->parent->last ? 'margin-bottom:6px;' : '' }}">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                    <tr>
                                        <td style="font-size:14px;color:#e2e8f0;font-family:Arial,Helvetica,sans-serif;">
                                            <strong style="color:{{ $categoryColors[$cat] ?? '#38BDF8' }};">{{ $badge['label'] }}</strong>
                                            <span style="font-size:11px;color:#64748b;padding-left:6px;">{{ $categoryLabels[$cat] ?? '' }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="font-size:12px;color:#94a3b8;font-family:Arial,Helvetica,sans-serif;padding-top:4px;">
                                            {{ $badge['description'] }}
                                            @if(!empty($badge['stage']))
                                                &mdash; {{ $badge['stage'] }}
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr><td style="height:6px;font-size:0;line-height:0;">&nbsp;</td></tr>
                        @endforeach
                    @endif
                @endforeach
            </table>
        </td>
    </tr>
    @endif

    {{-- ============================================================
         FOOTER
    ============================================================= --}}
    <tr>
        <td bgcolor="#071327" style="padding:28px 30px;border-top:2px solid #1e293b;">
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
                    <td align="center" style="font-size:18px;font-weight:bold;letter-spacing:3px;color:{{ $accentColor }};font-family:Arial,Helvetica,sans-serif;padding-bottom:8px;">
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
