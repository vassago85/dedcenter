@php
    $hasSafetyContent = filled($matchBook->program) || filled($matchBook->procedures) || filled($matchBook->safety) || filled($matchBook->custom_notes) || (is_array($matchBook->timetable) && count($matchBook->timetable) > 0);
@endphp
@if($hasSafetyContent)
<div class="page">
    <div class="page-header">Program, Procedures & Safety</div>

    @if(filled($matchBook->program))
        <div class="section-stripe--safety">
            <h2 style="font-size:11pt;margin:0 0 8px;color:#991b1b;text-transform:uppercase;">Program</h2>
            <div style="font-size:9.5pt;">{!! nl2br(e($matchBook->program)) !!}</div>
        </div>
    @endif

    @if(filled($matchBook->procedures))
        <div class="section-stripe--safety">
            <h2 style="font-size:11pt;margin:0 0 8px;color:#991b1b;text-transform:uppercase;">Procedures</h2>
            <div style="font-size:9.5pt;">{!! nl2br(e($matchBook->procedures)) !!}</div>
        </div>
    @endif

    @if(filled($matchBook->safety))
        <div class="section-stripe--safety">
            <h2 style="font-size:11pt;margin:0 0 8px;color:#991b1b;text-transform:uppercase;">Safety Rules</h2>
            <div style="font-size:9.5pt;">{!! nl2br(e($matchBook->safety)) !!}</div>
        </div>
    @endif

    @if(filled($matchBook->custom_notes))
        <div class="section-stripe--safety">
            <h2 style="font-size:11pt;margin:0 0 8px;color:#991b1b;text-transform:uppercase;">Additional Notes</h2>
            <div style="font-size:9.5pt;">{!! nl2br(e($matchBook->custom_notes)) !!}</div>
        </div>
    @endif

    @if(is_array($matchBook->timetable) && count($matchBook->timetable) > 0)
        <div class="section-stripe--safety">
            <h2 style="font-size:11pt;margin:0 0 8px;color:#991b1b;text-transform:uppercase;">Timetable</h2>
            <table style="width:100%;border-collapse:collapse;font-size:9pt;margin-top:6px;">
                <thead>
                    <tr style="background:#991b1b;color:#fff;">
                        <th style="padding:5px 8px;text-align:left;width:22%;">Time</th>
                        <th style="padding:5px 8px;text-align:left;">Item</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($matchBook->timetable as $row)
                        @php
                            if (is_string($row)) { $time = '—'; $item = $row; }
                            elseif (is_array($row)) { $time = $row['time'] ?? $row['start'] ?? '—'; $item = $row['title'] ?? $row['activity'] ?? $row['description'] ?? $row['item'] ?? ''; }
                            else { $time = '—'; $item = (string) $row; }
                        @endphp
                        <tr>
                            <td style="padding:5px 8px;border-bottom:1px solid #fecaca;">{{ $time }}</td>
                            <td style="padding:5px 8px;border-bottom:1px solid #fecaca;">{!! nl2br(e($item)) !!}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endif
