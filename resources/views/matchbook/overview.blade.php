<div class="page">
    <div class="page-header">Match Information</div>

    @if($matchBook->venue || $matchBook->gps_coordinates || $matchBook->directions)
        <div class="section-stripe--info">
            <h2 style="font-size:11pt;margin:0 0 8px;color:#1e40af;text-transform:uppercase;">Venue</h2>
            <table class="mb-table">
                @if($matchBook->venue)
                    <tr><td style="padding-bottom:8px;"><div class="mb-label">Name / Details</div><p>{!! nl2br(e($matchBook->venue)) !!}</p></td></tr>
                @endif
                @if($matchBook->gps_coordinates)
                    <tr><td style="padding-bottom:8px;"><div class="mb-label">GPS</div><p>{{ $matchBook->gps_coordinates }}</p></td></tr>
                @endif
                @if($matchBook->venue_maps_link || $matchBook->range_maps_link)
                    <tr><td style="padding-bottom:8px;"><div class="mb-label">Maps</div><p>
                        @if($matchBook->venue_maps_link) <a href="{{ $matchBook->venue_maps_link }}" style="color:#2563eb;">Venue map</a> @endif
                        @if($matchBook->venue_maps_link && $matchBook->range_maps_link) · @endif
                        @if($matchBook->range_maps_link) <a href="{{ $matchBook->range_maps_link }}" style="color:#2563eb;">Range map</a> @endif
                    </p></td></tr>
                @endif
                @if($matchBook->directions)
                    <tr><td><div class="mb-label">Directions</div><p>{!! nl2br(e($matchBook->directions)) !!}</p></td></tr>
                @endif
            </table>
        </div>
    @endif

    @if($matchBook->match_director_name || $matchBook->match_director_phone || $matchBook->match_director_email)
        <div class="section-stripe--info">
            <h2 style="font-size:11pt;margin:0 0 8px;color:#1e40af;text-transform:uppercase;">Match Director</h2>
            <table class="mb-table">
                @if($matchBook->match_director_name) <tr><td style="padding-bottom:6px;"><div class="mb-label">Name</div><p>{{ $matchBook->match_director_name }}</p></td></tr> @endif
                @if($matchBook->match_director_phone) <tr><td style="padding-bottom:6px;"><div class="mb-label">Phone</div><p>{{ $matchBook->match_director_phone }}</p></td></tr> @endif
                @if($matchBook->match_director_email) <tr><td><div class="mb-label">Email</div><p><a href="mailto:{{ $matchBook->match_director_email }}" style="color:#2563eb;">{{ $matchBook->match_director_email }}</a></p></td></tr> @endif
            </table>
        </div>
    @endif

    @if($matchBook->emergency_hospital_name || $matchBook->emergency_phone)
        <div class="section-stripe--info">
            <h2 style="font-size:11pt;margin:0 0 8px;color:#1e40af;text-transform:uppercase;">Emergency</h2>
            <table class="mb-table">
                @if($matchBook->emergency_hospital_name) <tr><td style="padding-bottom:6px;"><div class="mb-label">Hospital</div><p>{{ $matchBook->emergency_hospital_name }}</p></td></tr> @endif
                @if($matchBook->emergency_hospital_address) <tr><td style="padding-bottom:6px;"><div class="mb-label">Address</div><p>{!! nl2br(e($matchBook->emergency_hospital_address)) !!}</p></td></tr> @endif
                @if($matchBook->emergency_phone) <tr><td style="padding-bottom:6px;"><div class="mb-label">Phone</div><p>{{ $matchBook->emergency_phone }}</p></td></tr> @endif
                @if($matchBook->hospital_maps_link) <tr><td><div class="mb-label">Hospital Map</div><p><a href="{{ $matchBook->hospital_maps_link }}" style="color:#2563eb;">Open in maps</a></p></td></tr> @endif
            </table>
        </div>
    @endif

    @if($matchBook->locations->isNotEmpty())
        <div class="section-stripe--info">
            <h2 style="font-size:11pt;margin:0 0 8px;color:#1e40af;text-transform:uppercase;">Locations</h2>
            <p style="font-size:9pt;color:#64748b;margin:0 0 10px;">Scan QR codes for directions in your maps app.</p>
            <table class="mb-table">
                @foreach($matchBook->locations->chunk(2) as $pair)
                    <tr>
                        @foreach($pair as $loc)
                            <td style="border:1px solid #e2e8f0;padding:10px;width:50%;vertical-align:top;">
                                <h3 style="font-size:10pt;margin:0 0 8px;">{{ $loc->name }}</h3>
                                @if($loc->gps_coordinates) <div class="mb-label">GPS</div><p style="margin:0 0 6px;">{{ $loc->gps_coordinates }}</p> @endif
                                @if($loc->maps_link)
                                    <div class="mb-label">Maps</div>
                                    <p style="margin:0 0 6px;word-break:break-all;font-size:8pt;"><a href="{{ $loc->maps_link }}" style="color:#2563eb;">{{ $loc->maps_link }}</a></p>
                                    @php $qr = $loc->getQrCodeUrl(120); @endphp
                                    @if($qr) <img src="{{ $qr }}" width="120" height="120" alt="QR" style="display:block;margin-top:6px;"> @endif
                                @endif
                            </td>
                        @endforeach
                        @if($pair->count() === 1) <td style="border:1px solid #e2e8f0;padding:10px;">&nbsp;</td> @endif
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
</div>
