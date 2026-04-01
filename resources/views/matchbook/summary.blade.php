@php
    $stageScores = $matchDifficulty['stageScores'] ?? [];
    $diffColor = match ($matchDifficulty['avgColor'] ?? 'slate') { 'green' => '#15803d', 'amber' => '#b45309', 'red' => '#b91c1c', default => '#475569' };
    $stageColor = function (string $c): string { return match ($c) { 'green' => '#15803d', 'amber' => '#b45309', 'red' => '#b91c1c', default => '#475569' }; };
    $minDist = (float) ($matchStats['min_distance'] ?? 0);
    $maxDist = (float) ($matchStats['max_distance'] ?? 0);
    $minSize = (int) ($matchStats['min_size'] ?? 0);
    $maxSize = (int) ($matchStats['max_size'] ?? 0);
    $distRange = $minDist > 0 || $maxDist > 0 ? ($minDist == $maxDist ? number_format($minDist, 0).' m' : number_format($minDist, 0).'–'.number_format($maxDist, 0).' m') : '—';
    $sizeRange = $minSize > 0 && $maxSize > 0 ? ($minSize === $maxSize ? $minSize.' mm' : $minSize.'–'.$maxSize.' mm') : '—';
@endphp

<div class="page">
    <div class="page-header" style="color:#5b21b6;border-bottom-color:#7c3aed;">Match Summary</div>

    <div class="section-stripe--summary">
        <table style="width:100%;border-collapse:collapse;font-size:10pt;margin-bottom:16px;">
            <tr style="background:#ede9fe;"><th align="left" style="padding:8px;border:1px solid #ddd6fe;">Metric</th><th align="left" style="padding:8px;border:1px solid #ddd6fe;">Value</th></tr>
            <tr><td style="padding:8px;border:1px solid #e9d5ff;">Total Stages</td><td style="padding:8px;border:1px solid #e9d5ff;">{{ $matchStats['total_stages'] ?? 0 }}</td></tr>
            <tr><td style="padding:8px;border:1px solid #e9d5ff;">Total Shots</td><td style="padding:8px;border:1px solid #e9d5ff;">{{ $matchStats['total_shots'] ?? 0 }}</td></tr>
            <tr><td style="padding:8px;border:1px solid #e9d5ff;">Total Rounds</td><td style="padding:8px;border:1px solid #e9d5ff;">{{ $matchStats['total_rounds'] ?? 0 }}</td></tr>
            <tr><td style="padding:8px;border:1px solid #e9d5ff;">Distance Range</td><td style="padding:8px;border:1px solid #e9d5ff;">{{ $distRange }}</td></tr>
            <tr><td style="padding:8px;border:1px solid #e9d5ff;">Target Size Range</td><td style="padding:8px;border:1px solid #e9d5ff;">{{ $sizeRange }}</td></tr>
            <tr><td style="padding:8px;border:1px solid #e9d5ff;">Average Difficulty</td><td style="padding:8px;border:1px solid #e9d5ff;"><span style="font-weight:700;color:{{ $diffColor }};">{{ $matchDifficulty['avgScore100'] ?? 0 }} — {{ $matchDifficulty['avgLabel'] ?? '—' }}</span></td></tr>
        </table>

        <h2 style="font-size:12pt;font-weight:700;color:#4c1d95;margin:0 0 10px;">Stage Breakdown</h2>
        <table style="width:100%;border-collapse:collapse;font-size:9pt;">
            <tr style="background:#ede9fe;"><th align="center" style="padding:6px;border:1px solid #c4b5fd;width:36px;">#</th><th align="left" style="padding:6px;border:1px solid #c4b5fd;">Name</th><th align="center" style="padding:6px;border:1px solid #c4b5fd;width:48px;">Shots</th><th align="center" style="padding:6px;border:1px solid #c4b5fd;width:52px;">Rounds</th><th align="center" style="padding:6px;border:1px solid #c4b5fd;width:56px;">Pos.</th><th align="left" style="padding:6px;border:1px solid #c4b5fd;">Difficulty</th></tr>
            @foreach($matchBook->stages as $stage)
                @php $d = $stageScores[$stage->id] ?? null; $dc = $d ? $stageColor($d['overallColor'] ?? 'slate') : '#6b7280'; @endphp
                <tr>
                    <td align="center" style="padding:6px;border:1px solid #e9d5ff;">{{ $stage->stage_number }}</td>
                    <td style="padding:6px;border:1px solid #e9d5ff;">{{ $stage->name }}</td>
                    <td align="center" style="padding:6px;border:1px solid #e9d5ff;">{{ $stage->shots->count() }}</td>
                    <td align="center" style="padding:6px;border:1px solid #e9d5ff;">{{ $stage->round_count ?? '—' }}</td>
                    <td align="center" style="padding:6px;border:1px solid #e9d5ff;">{{ $stage->uniquePositionCount() }}</td>
                    <td style="padding:6px;border:1px solid #e9d5ff;">@if($d && ($d['hasTargets'] ?? false))<span style="font-weight:700;color:{{ $dc }};">{{ $d['score100'] }}</span> <span style="color:{{ $dc }};">{{ $d['overallLabel'] }}</span>@else <span style="color:#9ca3af;">{{ $d['overallLabel'] ?? '—' }}</span>@endif</td>
                </tr>
            @endforeach
        </table>
    </div>
</div>
