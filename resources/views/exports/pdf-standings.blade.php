<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $match->name }} — Standings</title>
    @include('exports.partials.pdf-styles-dark')
    <style>
        @page { size: A4 landscape; margin: 0; }
        body { width: 297mm; background: #071327; }
        .wrap { padding: 16px 18px; background: #071327; }

        /* Sponsor strip — tuned to dark */
        .sponsor {
            margin: 10px 0 4px;
            padding: 6px 10px;
            border: 1px solid #31486d;
            background: #0c1a33;
            border-radius: 4px;
            display: table;
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
            color: #94a3b8;
            letter-spacing: 0.02em;
        }
        .sponsor td { vertical-align: middle; padding: 0; }
        .sponsor img { height: 20px; max-width: 80px; object-fit: contain; margin-right: 8px; }

        /* Standings table — overrides .tbl defaults for wider landscape fit */
        .standings {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            border: 1px solid #31486d;
            border-radius: 5px;
            overflow: hidden;
            background: #0c1a33;
        }
        .standings thead th {
            background: #243757;
            color: #f8fafc;
            text-align: left;
            padding: 7px 10px;
            font-weight: 700;
            font-size: 7pt;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            border-bottom: 1px solid #31486d;
        }
        .standings thead th.right { text-align: right; }
        .standings tbody td {
            padding: 6px 10px;
            border-bottom: 1px solid #1e293b;
            color: #cbd5e1;
            font-size: 9pt;
        }
        .standings tbody td.right { text-align: right; font-variant-numeric: tabular-nums; }
        .standings tbody tr:nth-child(even) td { background: #0c1a33; }
        .standings tbody tr:nth-child(odd) td { background: #111f3c; }

        /* Podium rows on dark — tinted background + coloured rank cell. */
        .standings tr.rank-1 td { background: rgba(251,191,36,0.10) !important; font-weight: 700; }
        .standings tr.rank-2 td { background: rgba(203,213,225,0.08) !important; }
        .standings tr.rank-3 td { background: rgba(251,146,60,0.10) !important; }
        .standings tr.rank-1 .rank-cell { color: #fbbf24; font-weight: 800; }
        .standings tr.rank-2 .rank-cell { color: #cbd5e1; font-weight: 800; }
        .standings tr.rank-3 .rank-cell { color: #fb923c; font-weight: 800; }

        .standings .total { font-weight: 800; color: #f8fafc; }
    </style>
</head>
<body>
    @include('exports.partials.pdf-header', ['subtitle' => 'Standings'])

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

        <table class="standings">
            <thead>
                <tr>
                    <th style="width: 48px;" class="right">Rank</th>
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
                        <td class="right total">{{ number_format((float) $s->agg_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @include('exports.partials.pdf-footer')
    </div>
</body>
</html>
