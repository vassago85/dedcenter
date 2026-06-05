<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $match->name }} — Rankings</title>
    @include('exports.partials.pdf-styles-dark')
    <style>
        @page { size: 210mm auto; margin: 0; background: #071327; }
        body { width: 210mm; background: #071327; }
        .wrap { padding: 16px 14px; background: #071327; }

        .sponsor {
            margin: 8px 0 2px; padding: 6px 10px; border: 1px solid #31486d;
            background: #0c1a33; border-radius: 4px; display: table; width: 100%;
            border-collapse: collapse; font-size: 7.5pt; color: #94a3b8; letter-spacing: 0.02em;
            page-break-inside: avoid;
        }
        .sponsor td { vertical-align: middle; padding: 0; }
        .sponsor img { height: 20px; max-width: 80px; object-fit: contain; margin-right: 8px; }

        .section-title {
            margin: 18px 0 6px; color: #f8fafc; font-size: 11pt; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.12em;
        }
        .section-sub { color: #94a3b8; font-size: 8pt; font-weight: 700; margin: 12px 0 4px; }

        .standings {
            width: 100%; border-collapse: collapse; margin-top: 6px; border: 1px solid #31486d;
            border-radius: 5px; background: #0c1a33; page-break-inside: auto;
        }
        .standings tr { page-break-inside: avoid; page-break-after: auto; }
        .standings thead { display: table-header-group; }
        .standings thead th {
            background: #243757; color: #f8fafc; text-align: left; padding: 6px 9px;
            font-weight: 700; font-size: 6.5pt; text-transform: uppercase; letter-spacing: 0.12em;
            border-bottom: 1px solid #31486d;
        }
        .standings thead th.right { text-align: right; }
        .standings tbody td {
            padding: 5px 9px; border-bottom: 1px solid #1e293b; color: #cbd5e1; font-size: 8.5pt;
        }
        .standings tbody td.right { text-align: right; font-variant-numeric: tabular-nums; }
        .standings tbody tr:nth-child(even) td { background: #0c1a33; }
        .standings tbody tr:nth-child(odd) td { background: #111f3c; }
        .standings tr.rank-1 td { background: rgba(251,191,36,0.10) !important; font-weight: 700; }
        .standings tr.rank-2 td { background: rgba(203,213,225,0.08) !important; }
        .standings tr.rank-3 td { background: rgba(251,146,60,0.10) !important; }
        .standings .total { font-weight: 800; color: #f8fafc; }
        .muted { color: #64748b; }
        .empty { color: #94a3b8; font-size: 8.5pt; padding: 8px 0; }
    </style>
</head>
<body>
    @include('exports.partials.pdf-header', ['subtitle' => 'Rankings'])

    @php
        $r = $rankings;
        $stages = $r['stages'] ?? [];
        $rankLabel = fn ($row) => ($row['joint'] ?? false) ? '=' . $row['rank'] : $row['rank'];
        $cell = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
    @endphp

    <div class="wrap">
        @if(isset($sponsorAssignment) && $sponsorAssignment && $sponsorAssignment->sponsor)
            <table class="sponsor">
                <tr>
                    <td style="width: 1%;">
                        @if($sponsorAssignment->sponsor->logo_path)
                            <img src="{{ public_path('storage/' . $sponsorAssignment->sponsor->logo_path) }}" alt="">
                        @endif
                    </td>
                    <td>Results powered by {{ $sponsorAssignment->sponsor->name }}</td>
                </tr>
            </table>
        @endif

        @if(empty($stages))
            <p class="empty">No stages have been completed yet. Rankings appear once teams finish stages.</p>
        @else
            {{-- Overall individual --}}
            <div class="section-title">Overall — Individual</div>
            <table class="standings">
                <thead>
                    <tr>
                        <th class="right" style="width: 42px;">Rank</th>
                        <th>Name</th>
                        <th>Division</th>
                        <th>Team</th>
                        @foreach($stages as $s)<th class="right">{{ $s['label'] }}</th>@endforeach
                        <th class="right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($r['overall'] as $row)
                        <tr class="{{ $row['rank'] <= 3 ? 'rank-' . $row['rank'] : '' }}">
                            <td class="right">{{ $rankLabel($row) }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ $row['division'] ?? '—' }}</td>
                            <td>{{ $row['team'] ?? '—' }}</td>
                            @foreach($stages as $s)<td class="right">{{ $cell($row['stage_scores'][$s['stage_id']] ?? null) }}</td>@endforeach
                            <td class="right total">{{ number_format((float) $row['total_score'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Teams --}}
            <div class="section-title">Teams</div>
            <table class="standings">
                <thead>
                    <tr>
                        <th class="right" style="width: 42px;">Rank</th>
                        <th>Team</th>
                        <th>Divisions</th>
                        @foreach($stages as $s)<th class="right">{{ $s['label'] }}</th>@endforeach
                        <th class="right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($r['teams'] as $row)
                        <tr class="{{ $row['rank'] <= 3 ? 'rank-' . $row['rank'] : '' }}">
                            <td class="right">{{ $rankLabel($row) }}</td>
                            <td>{{ $row['team'] }}</td>
                            <td>{{ $row['division_composition'] }}</td>
                            @foreach($stages as $s)<td class="right">{{ $cell($row['stage_scores'][$s['stage_id']] ?? null) }}</td>@endforeach
                            <td class="right total">{{ number_format((float) $row['total_score'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Divisions --}}
            <div class="section-title">Divisions</div>
            @foreach($r['divisions'] as $div)
                <div class="section-sub">{{ $div['division'] }}</div>
                <table class="standings">
                    <thead>
                        <tr>
                            <th class="right" style="width: 42px;">Rank</th>
                            <th>Name</th>
                            <th>Team</th>
                            @foreach($stages as $s)<th class="right">{{ $s['label'] }}</th>@endforeach
                            <th class="right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($div['rows'] as $row)
                            <tr class="{{ $row['rank'] <= 3 ? 'rank-' . $row['rank'] : '' }}">
                                <td class="right">{{ $rankLabel($row) }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['team'] ?? '—' }}</td>
                                @foreach($stages as $s)<td class="right">{{ $cell($row['stage_scores'][$s['stage_id']] ?? null) }}</td>@endforeach
                                <td class="right total">{{ number_format((float) $row['total_score'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        @endif

        @include('exports.partials.pdf-footer')
    </div>
</body>
</html>
