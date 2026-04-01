@php
    $diffBadgeStyle = function (?string $color): string {
        return match ($color) {
            'green' => 'background:#dcfce7;color:#166534;border:1px solid #86efac;',
            'amber' => 'background:#fef3c7;color:#b45309;border:1px solid #fcd34d;',
            'red' => 'background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;',
            default => 'background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;',
        };
    };
@endphp

@foreach($matchBook->stages as $stage)
    @php $diff = $matchDifficulty['stageScores'][$stage->id] ?? null; @endphp
    <div class="page">
        <div class="section-stripe--stage" style="{{ !$loop->last ? 'page-break-after:always;' : '' }}">
            <table class="mb-table">
                <tr>
                    <td>
                        <h1 style="font-size:14pt;margin:0 0 4px;color:#0f172a;">
                            STAGE {{ $stage->stage_number }}@if($stage->name): {{ $stage->name }}@endif
                            @if($stage->timed) <span style="display:inline-block;font-size:7pt;font-weight:700;text-transform:uppercase;padding:2px 6px;border-radius:3px;margin-left:8px;background:#fef3c7;color:#b45309;border:1px solid #fcd34d;">Timed</span> @endif
                        </h1>
                    </td>
                    <td style="text-align:right;vertical-align:top;white-space:nowrap;">
                        @if($diff && ($diff['hasTargets'] ?? false))
                            <span style="display:inline-block;font-size:9pt;font-weight:700;padding:4px 10px;border-radius:4px;{{ $diffBadgeStyle($diff['overallColor'] ?? 'slate') }}">
                                {{ (int) ($diff['score100'] ?? 0) }}/100 — {{ $diff['overallLabel'] ?? '—' }}
                            </span>
                        @endif
                    </td>
                </tr>
            </table>

            {{-- Meta --}}
            <table class="mb-table" style="font-size:8pt;color:#475569;margin-bottom:10px;">
                <tr><td style="padding:3px 8px 3px 0;border-bottom:1px solid #e2e8f0;font-weight:700;color:#64748b;width:120px;">Rounds</td><td style="padding:3px 8px 3px 0;border-bottom:1px solid #e2e8f0;">{{ $stage->round_count ?? '—' }}</td></tr>
                <tr><td style="padding:3px 8px 3px 0;border-bottom:1px solid #e2e8f0;font-weight:700;color:#64748b;">Positions</td><td style="padding:3px 8px 3px 0;border-bottom:1px solid #e2e8f0;">{{ $stage->positions_count ?? $stage->uniquePositionCount() }}</td></tr>
                <tr><td style="padding:3px 8px 3px 0;border-bottom:1px solid #e2e8f0;font-weight:700;color:#64748b;">Movement</td><td style="padding:3px 8px 3px 0;border-bottom:1px solid #e2e8f0;">@if(($stage->movement_meters ?? 0) > 0){{ $stage->movement_meters }} m @else — @endif</td></tr>
                <tr><td style="padding:3px 8px 3px 0;border-bottom:1px solid #e2e8f0;font-weight:700;color:#64748b;">Time Limit</td><td style="padding:3px 8px 3px 0;border-bottom:1px solid #e2e8f0;">@if($stage->timed && $stage->time_limit) {{ $stage->time_limit }}s @else — @endif</td></tr>
            </table>

            {{-- Stage image --}}
            @if($stage->prop_image_path)
                <img src="{{ str_starts_with($stage->prop_image_path, 'http') ? $stage->prop_image_path : public_path('storage/' . $stage->prop_image_path) }}" alt="" style="max-width:100%;max-height:220px;object-fit:contain;display:block;margin:8px 0 12px;border:1px solid #e2e8f0;">
            @endif

            {{-- Course of fire --}}
            @if(filled($stage->brief))
                <h3 style="font-size:9pt;font-weight:700;text-transform:uppercase;color:#15803d;margin:12px 0 6px;">Course of Fire</h3>
                <div style="font-size:9pt;margin-bottom:8px;">{!! nl2br(e($stage->brief)) !!}</div>
            @endif

            {{-- Shooting sequence --}}
            <h3 style="font-size:9pt;font-weight:700;text-transform:uppercase;color:#15803d;margin:12px 0 6px;">Shooting Sequence</h3>
            @if($stage->compulsory_sequence)
                @if($stage->usesBlockDisplay())
                    @foreach($stage->shotsByPosition() as $group)
                        @php $first = $group->first(); @endphp
                        <div style="border:1px solid #bbf7d0;padding:8px;margin-bottom:8px;background:#fff;">
                            <h4 style="margin:0 0 6px;font-size:9pt;color:#166534;">Position {{ $first->position ?? '—' }}</h4>
                            <table class="data-table">
                                <thead><tr><th style="width:36px;">#</th><th>Gong</th><th style="width:52px;">Dist (m)</th><th style="width:52px;">Size</th><th style="width:44px;">MIL</th></tr></thead>
                                <tbody>
                                    @foreach($group as $shot)
                                        <tr><td>{{ $shot->shot_number }}</td><td>{{ $shot->fullLabel() }}</td><td>{{ $shot->distance_m }}</td><td>@if($shot->size_mm){{ $shot->size_mm }} mm @else — @endif</td><td>{{ $shot->mil ?? '—' }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                @else
                    <table class="data-table">
                        <thead><tr><th style="width:36px;">Shot</th><th style="width:56px;">Pos</th><th>Gong</th><th style="width:52px;">Dist (m)</th><th style="width:52px;">Size</th><th style="width:44px;">MIL</th></tr></thead>
                        <tbody>
                            @foreach($stage->shots as $shot)
                                <tr><td>{{ $shot->shot_number }}</td><td>{{ $shot->position }}</td><td>{{ $shot->fullLabel() }}</td><td>{{ $shot->distance_m }}</td><td>@if($shot->size_mm){{ $shot->size_mm }} mm @else — @endif</td><td>{{ $shot->mil ?? '—' }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @else
                @if(filled($stage->engagement_rules))
                    <div style="font-size:9pt;margin-bottom:8px;">{!! nl2br(e($stage->engagement_rules)) !!}</div>
                @else
                    <p style="font-size:9pt;color:#64748b;">No sequence defined.</p>
                @endif
            @endif

            {{-- Targets table --}}
            @if($stage->uniqueGongs()->isNotEmpty())
                <h3 style="font-size:9pt;font-weight:700;text-transform:uppercase;color:#15803d;margin:12px 0 6px;">Targets</h3>
                <table class="data-table">
                    <thead><tr><th>Label</th><th>Name</th><th style="width:52px;">Dist (m)</th><th style="width:52px;">Size</th><th style="width:44px;">MIL</th><th style="width:44px;">MOA</th></tr></thead>
                    <tbody>
                        @foreach($stage->uniqueGongs() as $g)
                            <tr><td>{{ $g->gong_label }}</td><td>{{ $g->gong_name ?: '—' }}</td><td>{{ $g->distance_m }}</td><td>@if($g->size_mm){{ $g->size_mm }} mm @else — @endif</td><td>{{ $g->mil ?? '—' }}</td><td>{{ $g->moa ?? '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Notes --}}
            @if(filled($stage->notes))
                <div style="border:1px dashed #94a3b8;padding:8px 10px;margin-top:12px;background:#fffbeb;font-size:8.5pt;">
                    <div style="font-weight:700;color:#92400e;margin-bottom:4px;">Notes</div>
                    <div>{!! nl2br(e($stage->notes)) !!}</div>
                </div>
            @endif
        </div>
    </div>
@endforeach
