@php
    $allShots = $matchBook->stages->flatMap->shots;
    $distances = $allShots->pluck('distance_m')->filter(fn ($d) => (float) $d > 0)->map(fn ($d) => (float) $d)->unique()->sort()->values();
@endphp
@if($matchBook->include_dope_card && $distances->isNotEmpty())
<div class="page">
    <div class="page-header" style="color:#334155;border-bottom-color:#64748b;">Dope Card</div>

    <div class="section-stripe--dope">
        <p style="font-size:9pt;color:#64748b;margin:0 0 12px;">Record your holds — distances from match stages pre-filled.</p>
        <table style="width:100%;border-collapse:collapse;font-size:10pt;">
            <thead>
                <tr style="background:#f1f5f9;">
                    <th align="center" style="padding:10px 8px;border:1px solid #334155;">Distance (m)</th>
                    <th align="center" style="padding:10px 8px;border:1px solid #334155;">MIL</th>
                    <th align="center" style="padding:10px 8px;border:1px solid #334155;">MOA</th>
                    <th align="left" style="padding:10px 8px;border:1px solid #334155;">Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($distances as $distM)
                    @php
                        $shotAtDist = $allShots->first(fn ($s) => round((float) $s->distance_m, 2) === round((float) $distM, 2) && $s->mil);
                        $mil = $shotAtDist?->mil ? number_format((float) $shotAtDist->mil, 2) : '';
                        $moa = $shotAtDist?->moa ? number_format((float) $shotAtDist->moa, 2) : '';
                    @endphp
                    <tr>
                        <td align="center" style="padding:14px 8px;border:1px solid #94a3b8;height:36px;">{{ number_format($distM, 0) }}</td>
                        <td align="center" style="padding:14px 8px;border:1px solid #94a3b8;">{{ $mil }}</td>
                        <td align="center" style="padding:14px 8px;border:1px solid #94a3b8;">{{ $moa }}</td>
                        <td style="padding:14px 8px;border:1px solid #94a3b8;min-height:28px;">&nbsp;</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
