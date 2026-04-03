<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 8pt; color: #1e293b; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 2px solid #1e3a5f; }
        .header h1 { font-size: 16pt; font-weight: 800; }
        .header .meta { font-size: 8pt; color: #64748b; text-align: right; }
        .sponsor { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; font-size: 7pt; color: #64748b; }
        .sponsor img { height: 16px; max-width: 60px; object-fit: contain; }
        table { width: 100%; border-collapse: collapse; font-size: 7pt; }
        th { background: #1e3a5f; color: white; padding: 4px 5px; font-weight: 600; font-size: 6.5pt; text-transform: uppercase; white-space: nowrap; }
        th.right, td.right { text-align: right; }
        th.center, td.center { text-align: center; }
        td { padding: 3px 5px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) { background: #f8fafc; }
        .hit { color: #16a34a; font-weight: 700; }
        .miss { color: #dc2626; }
        .subtotal { font-weight: 700; color: #1e3a5f; }
        .footer { margin-top: 10px; text-align: center; font-size: 6pt; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>{{ $match->name }} — Detailed Results</h1>
            <div class="meta">{{ $match->date?->format('d M Y') }}@if($match->location) — {{ $match->location }}@endif</div>
        </div>
        @if($match->organization?->logo_path)
            <img src="{{ public_path('storage/' . $match->organization->logo_path) }}" style="height: 35px; max-width: 100px; object-fit: contain;" alt="">
        @endif
    </div>

    @if($sponsorAssignment && $sponsorAssignment->sponsor)
        <div class="sponsor">
            @if($sponsorAssignment->sponsor->logo_path)
                <img src="{{ public_path('storage/' . $sponsorAssignment->sponsor->logo_path) }}" alt="">
            @endif
            <span>Results powered by {{ $sponsorAssignment->sponsor->name }}</span>
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Name</th>
                <th>Squad</th>
                <th>Div</th>
                @foreach($targetSets as $ts)
                    @php $label = $ts->label ?: "{$ts->distance_meters}m"; @endphp
                    @foreach($ts->gongs as $g)
                        <th class="center">{{ $label }} G{{ $g->number }}</th>
                    @endforeach
                    <th class="right">{{ $label }}</th>
                @endforeach
                <th class="right">Hits</th>
                <th class="right">Miss</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $row)
                <tr>
                    <td class="right">{{ $i + 1 }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['squad'] }}</td>
                    <td>{{ $row['division'] }}</td>
                    @foreach($row['stages'] as $stage)
                        @foreach($stage['results'] as $result)
                            <td class="center {{ $result === 'H' ? 'hit' : ($result === 'M' ? 'miss' : '') }}">{{ $result }}</td>
                        @endforeach
                        <td class="right subtotal">{{ $stage['subtotal'] }}</td>
                    @endforeach
                    <td class="right">{{ $row['hits'] }}</td>
                    <td class="right">{{ $row['misses'] }}</td>
                    <td class="right" style="font-weight: 700;">{{ number_format($row['total'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        DeadCenter — Detailed results generated {{ now()->format('d M Y H:i') }}
    </div>
</body>
</html>
