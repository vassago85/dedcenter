<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $match->name }} — Detailed Results</title>
    @include('exports.partials.pdf-styles-dark')
    <style>
        @page { size: A4 landscape; margin: 0; }
        body { width: 297mm; background: #071327; font-size: 8pt; }
        .wrap { padding: 14px 16px; background: #071327; }

        /* Sponsor strip */
        .sponsor {
            margin: 8px 0 4px;
            padding: 5px 8px;
            border: 1px solid #31486d;
            background: #0c1a33;
            border-radius: 4px;
            display: table;
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
            color: #94a3b8;
        }
        .sponsor td { vertical-align: middle; padding: 0; }
        .sponsor img { height: 16px; max-width: 60px; object-fit: contain; margin-right: 6px; }

        /* Detailed results table */
        .detailed {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            border: 1px solid #31486d;
            border-radius: 5px;
            overflow: hidden;
            background: #0c1a33;
        }
        .detailed thead th {
            background: #243757;
            color: #f8fafc;
            padding: 4px 5px;
            font-weight: 700;
            font-size: 6.5pt;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            white-space: nowrap;
            border-bottom: 1px solid #31486d;
            border-right: 1px solid #31486d;
        }
        .detailed thead th.right { text-align: right; }
        .detailed thead th.center { text-align: center; }
        .detailed tbody td {
            padding: 3px 5px;
            font-size: 7pt;
            color: #cbd5e1;
            border-bottom: 1px solid #1e293b;
            border-right: 1px solid #1e293b;
        }
        .detailed tbody td.right { text-align: right; font-variant-numeric: tabular-nums; }
        .detailed tbody td.center { text-align: center; font-variant-numeric: tabular-nums; }
        .detailed tbody tr:nth-child(even) td { background: #0c1a33; }
        .detailed tbody tr:nth-child(odd)  td { background: #111f3c; }

        /* Hit / miss / subtotal */
        .detailed .hit  { color: #22c55e; font-weight: 700; background: rgba(34,197,94,0.10) !important; }
        .detailed .miss { color: #ef4444; font-weight: 600; background: rgba(239,68,68,0.10) !important; }
        .detailed .subtotal {
            font-weight: 700;
            color: #f8fafc;
            background: #1d2d4a !important;
            font-variant-numeric: tabular-nums;
        }
        .detailed .total {
            font-weight: 800;
            color: #f8fafc;
            font-variant-numeric: tabular-nums;
        }

        /* Podium rows — keep subtle so detailed grid stays readable */
        .detailed tbody tr.rank-1 td { background: rgba(251,191,36,0.08) !important; }
        .detailed tbody tr.rank-2 td { background: rgba(203,213,225,0.06) !important; }
        .detailed tbody tr.rank-3 td { background: rgba(251,146,60,0.08) !important; }
    </style>
</head>
<body>
    @include('exports.partials.pdf-header', ['subtitle' => 'Detailed Results'])

    <div class="wrap">
        @if(isset($sponsorAssignment) && $sponsorAssignment && $sponsorAssignment->sponsor)
            <table class="sponsor">
                <tr>
                    <td style="width: 1%;">
                        @if($sponsorAssignment->sponsor->logo_path)
                            <img src="{{ public_path('storage/' . $sponsorAssignment->sponsor->logo_path) }}" alt="">
                        @endif
                    </td>
                    <td>
                        Results powered by {{ $sponsorAssignment->sponsor->name }}
                    </td>
                </tr>
            </table>
        @endif

        <table class="detailed">
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
                    <tr class="{{ $i < 3 ? 'rank-' . ($i + 1) : '' }}">
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
                        <td class="right total">{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @include('exports.partials.pdf-footer')
    </div>
</body>
</html>
