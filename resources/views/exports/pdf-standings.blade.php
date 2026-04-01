<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 10pt; color: #1e293b; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #1e3a5f; }
        .header h1 { font-size: 18pt; font-weight: 800; }
        .header .meta { font-size: 9pt; color: #64748b; text-align: right; }
        .sponsor { display: flex; align-items: center; gap: 6px; margin-bottom: 8px; font-size: 8pt; color: #64748b; }
        .sponsor img { height: 20px; max-width: 80px; object-fit: contain; }
        table { width: 100%; border-collapse: collapse; font-size: 9pt; }
        th { background: #1e3a5f; color: white; text-align: left; padding: 6px 8px; font-weight: 600; font-size: 8pt; text-transform: uppercase; }
        th.right, td.right { text-align: right; }
        td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) { background: #f8fafc; }
        .rank-1 { font-weight: 700; }
        .rank-1 .rank-cell { color: #d97706; }
        .rank-2 .rank-cell { color: #94a3b8; font-weight: 600; }
        .rank-3 .rank-cell { color: #92400e; font-weight: 600; }
        .footer { margin-top: 12px; text-align: center; font-size: 7pt; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>{{ $match->name }}</h1>
            <div class="meta">{{ $match->date?->format('d M Y') }}@if($match->location) — {{ $match->location }}@endif</div>
        </div>
        @if($match->organization?->logo_path)
            <img src="{{ public_path('storage/' . $match->organization->logo_path) }}" style="height: 40px; max-width: 120px; object-fit: contain;" alt="">
        @endif
    </div>

    @if($sponsorAssignment && $sponsorAssignment->sponsor)
        <div class="sponsor">
            @if($sponsorAssignment->sponsor->logo_path)
                <img src="{{ public_path('storage/' . $sponsorAssignment->sponsor->logo_path) }}" alt="">
            @endif
            <span>{{ $sponsorAssignment->displayLabel() }} {{ $sponsorAssignment->sponsor->name }}</span>
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width: 40px;">Rank</th>
                <th>Name</th>
                <th>Squad</th>
                <th>Division</th>
                <th class="right">Hits</th>
                <th class="right">Misses</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($shooters as $i => $s)
                <tr class="{{ $i < 3 ? 'rank-' . ($i + 1) : '' }}">
                    <td class="rank-cell right">{{ $i + 1 }}</td>
                    <td>{{ $s->name }}</td>
                    <td>{{ $s->squad }}</td>
                    <td>{{ $s->division }}</td>
                    <td class="right">{{ (int) $s->agg_hits }}</td>
                    <td class="right">{{ (int) $s->agg_misses }}</td>
                    <td class="right" style="font-weight: 700;">{{ number_format((float) $s->agg_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        DeadCenter — Results generated {{ now()->format('d M Y H:i') }}
    </div>
</body>
</html>
