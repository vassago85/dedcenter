@php
    $ackText = trim($matchBook->sponsor_acknowledgement ?? '');
    $sponsorAssignments = app(\App\Services\SponsorPlacementResolver::class)->resolveAll('matchbook_inside_cover', $match->id, $matchBook->id);
@endphp
@if($ackText !== '' || $sponsorAssignments->isNotEmpty())
<div class="page">
    <div class="page-header" style="color:#92400e;border-bottom-color:#f59e0b;">Brand Partners</div>

    @if($ackText !== '')
        <div class="section-stripe--sponsor">
            <div style="font-size:10pt;line-height:1.55;color:#44403c;">{!! nl2br(e($ackText)) !!}</div>
        </div>
    @endif

    @foreach($sponsorAssignments as $assignment)
        @php $sponsor = $assignment->sponsor; @endphp
        @continue(!$sponsor)
        <div class="section-stripe--sponsor" style="margin-bottom:14px;">
            <table class="mb-table">
                <tr>
                    @if($sponsor->hasLogo())
                        <td style="width:100px;padding-right:14px;vertical-align:middle;text-align:center;">
                            <img src="{{ asset('storage/' . $sponsor->logo_path) }}" alt="" style="max-width:88px;max-height:72px;object-fit:contain;">
                        </td>
                    @endif
                    <td style="vertical-align:top;">
                        <div style="font-size:14pt;font-weight:700;color:#78350f;margin-bottom:6px;">{{ $sponsor->name }}</div>
                        @if(filled($sponsor->short_description))
                            <div style="font-size:9pt;line-height:1.5;color:#57534e;margin-bottom:8px;">{{ $sponsor->short_description }}</div>
                        @endif
                        @if(filled($sponsor->website_url))
                            <div style="font-size:9pt;"><a href="{{ $sponsor->website_url }}" style="color:#b45309;">{{ $sponsor->website_url }}</a></div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
</div>
@endif
