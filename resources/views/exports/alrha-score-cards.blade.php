{{--
    ALRHA mark-sense score cards.

    One A4 card per shooter. The top half of the page is for people:
    match, shooter, class, relay, gong, team, coached flag, and a short
    instruction. The bottom half is the scan block — the only region
    the phone camera cares about.

    The scan block is positioned by absolute millimetres so the saved
    layout (see App\Services\Scoring\ScoreSheetLayoutBuilder) lines up
    with what Gotenberg actually renders. All millimetre values in the
    layout are relative to the page's top-left corner.

    $cards : array<int, ['sheet' => ScoreSheet, 'shooter' => Shooter]>
    $match : ShootingMatch
--}}
@php
    use App\Services\Scoring\ScoreSheetLayoutBuilder as L;
    use chillerlan\QRCode\{QRCode, QROptions};
    use chillerlan\QRCode\Output\QRGdImagePNG;

    $mm = fn (float $v) => number_format($v, 2, '.', '').'mm';
    // chillerlan v6 configuration. We emit a base64 PNG (not SVG) so
    // the DomPDF fallback renders the QR correctly in local dev where
    // Gotenberg/Chromium isn't available. Chromium handles the same
    // `<img src="data:image/png;base64,...">` identically on production.
    $qrOptions = new QROptions([
        'outputInterface' => QRGdImagePNG::class,
        'outputBase64' => true,
        'scale' => 6,
        'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
    ]);
    $qr = new QRCode($qrOptions);

    // Inline-base64 the brand logos so Gotenberg (which receives one
    // HTML blob over multipart) and DomPDF (which can't fetch over
    // HTTP) both render them without any file-attachment dance.
    $toDataUri = function (string $path): ?string {
        if (! is_file($path)) return null;
        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    };
    $dcLogo = $toDataUri(public_path('logo.png'));
    $alrhaLogo = $toDataUri(public_path('images/alrha-logo.png'));
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>ALRHA Score Cards — {{ $match->name }}</title>
    <style>
        @page { size: 210mm 297mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #ffffff; color: #0f172a; font-family: 'Helvetica', 'Arial', sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .page { position: relative; width: 210mm; height: 297mm; page-break-after: always; overflow: hidden; }
        .page:last-child { page-break-after: auto; }

        /* Fixed-height header area (35mm) positioned with absolute mm
           coordinates so DomPDF (local-dev fallback) and Chromium (prod)
           render it identically. Flexbox/grid behave differently across
           PDF engines; absolute positioning is deterministic. */
        .header-area { position: absolute; left: 0; top: 0; width: 210mm; height: 35mm; }
        .header-rule { position: absolute; left: 12mm; right: 12mm; top: 33mm; border-top: 1.5px solid #0f172a; }

        .brand { font-size: 10pt; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: #334155; }
        .match-name { font-size: 15pt; font-weight: 800; color: #0f172a; margin-top: 1mm; }
        .match-meta { font-size: 9pt; color: #475569; margin-top: 0.5mm; }

        /* ALRHA PNG is 1024x899 (≈1.14:1). 22mm wide × 19.3mm tall. */
        .brand-mark-alrha { position: absolute; left: 12mm; top: 8mm; width: 22mm; height: 19.3mm; }
        /* Copy block sits immediately to the right of the ALRHA mark. */
        .brand-copy { position: absolute; left: 38mm; top: 10mm; right: 55mm; }

        /* DeadCenter PNG is 1024x682 (≈1.50:1). 32mm × 21.3mm, but the
           readable DEADCENTER text sits in roughly the middle 50% of
           the frame — negative margins crop the whitespace visually. */
        .brand-mark-dc { position: absolute; right: 12mm; top: 6mm; width: 32mm; height: 21.3mm; }
        .card-id-block { position: absolute; right: 12mm; top: 24mm; text-align: right; }
        .card-id-label { font-size: 7pt; letter-spacing: 0.08em; text-transform: uppercase; color: #475569; }
        .card-id { font-family: 'Courier New', monospace; font-size: 10pt; font-weight: 800; color: #0f172a; letter-spacing: 0.08em; }

        /* Body header content (shooter block + instructions) starts
           below the brand strip. */
        .header { padding: 38mm 12mm 0 12mm; }

        .shooter-block { margin-top: 5mm; display: flex; justify-content: space-between; gap: 8mm; }
        .shooter-identity { flex: 1; }
        .shooter-name { font-size: 22pt; font-weight: 900; color: #0f172a; line-height: 1; }
        .shooter-sub { margin-top: 1.5mm; font-size: 10pt; color: #334155; }
        .shooter-chips { margin-top: 3mm; display: flex; flex-wrap: wrap; gap: 2mm; }
        .chip { font-size: 8pt; font-weight: 700; padding: 1mm 2.5mm; border-radius: 2mm; border: 1px solid #94a3b8; color: #0f172a; background: #f1f5f9; letter-spacing: 0.04em; text-transform: uppercase; }
        .chip.strong { background: #0f172a; color: #ffffff; border-color: #0f172a; }
        .chip.cls-hunters { background: #065f46; color: #ffffff; border-color: #065f46; }
        .chip.cls-varmint { background: #7c2d12; color: #ffffff; border-color: #7c2d12; }
        .chip.coached { background: #fef3c7; color: #78350f; border-color: #fde68a; }

        .instructions { margin: 5mm 12mm 0 12mm; padding: 3mm 4mm; border: 1px dashed #94a3b8; border-radius: 2mm; background: #f8fafc; font-size: 9.5pt; color: #0f172a; }
        .instructions strong { font-weight: 800; }

        .scan-wrap { position: absolute; top: 0; left: 0; width: 210mm; height: 297mm; pointer-events: none; }
        .scan-item { position: absolute; }
        .fiducial { background: #0f172a; }
        .fiducial-inner { background: #ffffff; position: absolute; }
        .fiducial-core { background: #0f172a; position: absolute; }

        .block-title { position: absolute; font-size: 8pt; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; color: #475569; }
        .qr img { width: 100%; height: 100%; display: block; }
        .qr-caption { position: absolute; font-size: 6pt; color: #475569; letter-spacing: 0.08em; text-transform: uppercase; }

        .row-label { position: absolute; font-size: 8.5pt; font-weight: 700; color: #0f172a; }
        .row-label small { font-size: 7pt; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; }
        .row-cbc { color: #7c2d12; }

        .bubble { position: absolute; border: 1.2pt solid #0f172a; background: #ffffff; border-radius: 50%; }
        .bubble-label { position: absolute; font-size: 6pt; font-weight: 700; color: #475569; text-align: center; letter-spacing: 0.08em; }
        .shot-number { position: absolute; font-size: 6pt; font-weight: 700; color: #94a3b8; text-align: center; }

        .footer { position: absolute; bottom: 10mm; left: 12mm; right: 12mm; font-size: 8pt; color: #475569; display: flex; justify-content: space-between; gap: 4mm; }
        .sig-row { display: flex; gap: 6mm; margin-top: 2mm; }
        .sig-line { flex: 1; border-top: 1px solid #0f172a; padding-top: 1mm; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.08em; color: #475569; }
    </style>
</head>
<body>
@foreach($cards as $card)
    @php
        $sheet = $card['sheet'];
        $shooter = $card['shooter'] ?? null;
        $spare = $card['spare'] ?? null;
        $layout = $sheet->layout;
        // Spares carry their class on the sheet; assigned cards derive
        // from the shooter (with legacy fallback to the match class).
        $sheetClass = $spare ? $spare['class'] : ($shooter?->alrha_class ?? $match->alrhaClass());
        $classLabel = $sheetClass?->label() ?? '—';
        $classChip = $sheetClass?->value;
        $qrData = $qr->render($layout['qr']['payload'] ?? ('DCS1:'.$sheet->token));
    @endphp
    <div class="page">
        {{-- ─── Brand strip (fixed-height, absolute positioning) ─── --}}
        <div class="header-area">
            @if($alrhaLogo)
                <img src="{{ $alrhaLogo }}" alt="ALRHA" class="brand-mark-alrha">
            @endif
            <div class="brand-copy">
                <div class="brand">
                    ALRHA Score Card
                    @if($spare)
                        · SPARE {{ $spare['index'] }} of {{ $spare['total'] }}
                    @endif
                </div>
                <div class="match-name">{{ $match->name }}</div>
                <div class="match-meta">
                    @if($match->date)
                        {{ \Carbon\Carbon::parse($match->date)->format('D d M Y') }}
                    @endif
                    @if($match->location) · {{ $match->location }} @endif
                </div>
            </div>
            @if($dcLogo)
                <img src="{{ $dcLogo }}" alt="DeadCenter" class="brand-mark-dc">
            @endif
            <div class="card-id-block">
                <div class="card-id-label">Card ID</div>
                <div class="card-id">{{ $sheet->token }}</div>
            </div>
            <div class="header-rule"></div>
        </div>

        <div class="header">

            <div class="shooter-block">
                <div class="shooter-identity">
                    @if($spare)
                        {{-- No pre-filled identity. The shooter writes
                             their name on the line; the MD picks the
                             shooter in the scanner before confirming. --}}
                        <div style="font-size: 10pt; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; color: #475569;">Shooter name</div>
                        <div style="margin-top: 2mm; border-bottom: 1.5px solid #0f172a; height: 11mm;"></div>
                        <div class="shooter-sub">
                            Bib # ________ · Relay ________
                        </div>
                        <div class="shooter-chips">
                            <span class="chip {{ $classChip ? 'cls-'.$classChip : 'strong' }}">{{ $classLabel }}</span>
                            <span class="chip" style="background:#fef3c7; color:#78350f; border-color:#fde68a;">SPARE — MD assigns at scan</span>
                        </div>
                    @else
                        <div class="shooter-name">{{ $shooter->name }}</div>
                        <div class="shooter-sub">
                            @if($shooter->bib_number) Bib #{{ $shooter->bib_number }} · @endif
                            {{ $shooter->squad?->name ?? 'No relay' }}
                            @if($shooter->gong_position) · Gong {{ $shooter->gong_position }}@endif
                        </div>
                        <div class="shooter-chips">
                            <span class="chip {{ $classChip ? 'cls-'.$classChip : 'strong' }}">{{ $classLabel }}</span>
                            @if($shooter->team) <span class="chip">Team {{ $shooter->team->name }}</span> @endif
                            @if($shooter->division) <span class="chip">{{ $shooter->division->name }}</span> @endif
                            @if($shooter->is_coached) <span class="chip coached">Coached · not prize-eligible</span> @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="instructions">
            Fill <strong>exactly one</strong> box per shot — <strong>H</strong> for a hit, <strong>M</strong> for a miss. Fill it solid in pen. Leave the pair blank if the shot wasn't fired yet (the import leaves blank shots alone). Scoring: shot&nbsp;1 = 5&nbsp;pts, shot&nbsp;2 = 4, shot&nbsp;3 = 3, shot&nbsp;4 = 2, shot&nbsp;5 = 1. A miss on shot&nbsp;2 is zero and doesn't shift later shots up. Cold Bore stays off the match total.
        </div>

        {{-- ─── Scan block (positioned by mm so decoder can locate it) ─── --}}
        <div class="scan-wrap">
            {{-- Fiducials: black square with a white hole and a black centre --}}
            @foreach($layout['fiducials'] as $fid)
                <div class="scan-item fiducial" style="left: {{ $mm($fid['x']) }}; top: {{ $mm($fid['y']) }}; width: {{ $mm($layout['fiducial_size']) }}; height: {{ $mm($layout['fiducial_size']) }};">
                    <div class="fiducial-inner" style="left: 25%; top: 25%; width: 50%; height: 50%;">
                        <div class="fiducial-core" style="left: 25%; top: 25%; width: 50%; height: 50%;"></div>
                    </div>
                </div>
            @endforeach

            {{-- QR code (top-right, inside the block) — base64 PNG so
                 both Gotenberg/Chromium (prod) and DomPDF (local
                 fallback) render it identically. --}}
            <div class="scan-item qr" style="left: {{ $mm($layout['qr']['x']) }}; top: {{ $mm($layout['qr']['y']) }}; width: {{ $mm($layout['qr']['size']) }}; height: {{ $mm($layout['qr']['size']) }};">
                <img src="{{ $qrData }}" alt="Card QR {{ $sheet->token }}">
            </div>
            <div class="qr-caption" style="left: {{ $mm($layout['qr']['x']) }}; top: {{ $mm($layout['qr']['y'] + $layout['qr']['size'] + 0.6) }}; width: {{ $mm($layout['qr']['size']) }}; text-align: center;">
                Scan this
            </div>

            {{-- Block title above the first row --}}
            <div class="block-title" style="left: {{ $mm($layout['block']['x']) }}; top: {{ $mm($layout['block']['y'] - 5) }};">
                Scan block — shot grid
            </div>

            {{-- Shot-number header above the bubble columns --}}
            @php
                $maxShots = 0;
                foreach ($layout['rows'] as $r) { $maxShots = max($maxShots, count($r['shots'])); }
                $headerY = $layout['block']['y'] + 3;
                $bubbleStartX = $layout['block']['x'] + L::ROW_LABEL_WIDTH_MM;
            @endphp
            @for($i = 1; $i <= $maxShots; $i++)
                @php
                    $centerX = $bubbleStartX + ($i - 1) * L::SHOT_PAIR_WIDTH_MM + (L::BUBBLE_DIAMETER_MM + L::BUBBLE_GAP_MM / 2);
                    $pts = max(0, 6 - $i);
                @endphp
                <div class="shot-number" style="left: {{ $mm($centerX - 9) }}; top: {{ $mm($headerY) }}; width: 18mm;">
                    Shot {{ $i }} · {{ $pts }}&nbsp;pt{{ $pts === 1 ? '' : 's' }}
                </div>
            @endfor

            {{-- Per-shot row: label on the left, Hit/Miss pairs across --}}
            @foreach($layout['rows'] as $row)
                @php
                    $firstShot = $row['shots'][0] ?? null;
                    $rowY = $firstShot ? ($firstShot['hit']['y'] - L::BUBBLE_DIAMETER_MM / 2) : $layout['block']['y'];
                    $labelClass = $row['is_cold_bore'] ? 'row-cbc' : '';
                @endphp
                <div class="row-label {{ $labelClass }}" style="left: {{ $mm($layout['block']['x'] + 1) }}; top: {{ $mm($rowY + 0.5) }}; width: {{ $mm(L::ROW_LABEL_WIDTH_MM - 2) }};">
                    {{ $row['distance_m'] }}m
                    @if($row['is_cold_bore'])
                        <br><small>Cold Bore</small>
                    @elseif($row['alrha_block'])
                        <br><small>{{ ucfirst($row['alrha_block']) }} block</small>
                    @endif
                </div>

                @foreach($row['shots'] as $shot)
                    <div class="bubble" style="left: {{ $mm($shot['hit']['x'] - L::BUBBLE_DIAMETER_MM / 2) }}; top: {{ $mm($shot['hit']['y'] - L::BUBBLE_DIAMETER_MM / 2) }}; width: {{ $mm(L::BUBBLE_DIAMETER_MM) }}; height: {{ $mm(L::BUBBLE_DIAMETER_MM) }};"></div>
                    <div class="bubble-label" style="left: {{ $mm($shot['hit']['x'] - 3) }}; top: {{ $mm($shot['hit']['y'] - 1.5) }}; width: 6mm;">H</div>

                    <div class="bubble" style="left: {{ $mm($shot['miss']['x'] - L::BUBBLE_DIAMETER_MM / 2) }}; top: {{ $mm($shot['miss']['y'] - L::BUBBLE_DIAMETER_MM / 2) }}; width: {{ $mm(L::BUBBLE_DIAMETER_MM) }}; height: {{ $mm(L::BUBBLE_DIAMETER_MM) }};"></div>
                    <div class="bubble-label" style="left: {{ $mm($shot['miss']['x'] - 3) }}; top: {{ $mm($shot['miss']['y'] - 1.5) }}; width: 6mm;">M</div>
                @endforeach
            @endforeach
        </div>

        {{-- ─── Signature area (below the scan block, not imported) ─── --}}
        <div class="footer">
            <div style="flex: 1;">
                <div class="sig-row">
                    <div class="sig-line">Shooter signature</div>
                    <div class="sig-line">Range officer</div>
                </div>
                <div style="margin-top: 2mm; font-size: 7pt; color: #94a3b8;">
                    Signatures below the scan block are not read by the camera.
                </div>
            </div>
            <div style="text-align: right;">
                Printed {{ $sheet->printed_at?->format('d M Y H:i') ?? 'now' }}
            </div>
        </div>
    </div>
@endforeach
</body>
</html>
