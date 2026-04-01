@php
    $sponsorAssignment = $sponsorAssignment ?? null;
    $resolveImage = static function (?string $path): ?string {
        if (!$path) return null;
        $full = public_path('storage/' . ltrim(str_replace('\\', '/', $path), '/'));
        if (!is_file($full)) return null;
        return 'data:' . (mime_content_type($full) ?: 'image/png') . ';base64,' . base64_encode(file_get_contents($full));
    };
    $orgLogoSrc = $resolveImage($match->organization?->logo_path);
    $coverImageSrc = $resolveImage($matchBook->cover_image_path);
    $fedLogoSrc = $resolveImage($matchBook->federation_logo_path);
    $clubLogoSrc = $resolveImage($matchBook->club_logo_path);
    $sponsorLogoSrc = $sponsorAssignment?->sponsor?->logo_path ? $resolveImage($sponsorAssignment->sponsor->logo_path) : null;
@endphp

<div class="page" style="display:flex;flex-direction:column;justify-content:space-between;background:linear-gradient(145deg,var(--color-primary) 0%,var(--color-secondary) 55%,var(--color-primary) 100%);color:#fff;padding:14mm 16mm 18mm;">
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;text-align:center;padding-top:8mm;">
        @if($orgLogoSrc)
            <div style="margin-bottom:10mm;">
                <img src="{{ $orgLogoSrc }}" alt="" style="max-height:52px;max-width:200px;object-fit:contain;filter:drop-shadow(0 2px 8px rgba(0,0,0,0.25));">
            </div>
        @endif

        @if($coverImageSrc)
            <div style="width:100%;max-width:160mm;margin:0 auto 8mm;border-radius:4px;overflow:hidden;border:2px solid rgba(255,255,255,0.35);">
                <img src="{{ $coverImageSrc }}" alt="" style="display:block;width:100%;max-height:85mm;object-fit:cover;">
            </div>
        @endif

        <h1 style="font-family:var(--font-head);font-weight:700;font-size:28pt;line-height:1.12;letter-spacing:0.03em;text-transform:uppercase;text-shadow:0 2px 18px rgba(0,0,0,0.35);margin:0 0 6mm;">{{ $match->name }}</h1>

        @if(filled($matchBook->subtitle))
            <p style="font-size:12pt;font-weight:500;opacity:0.95;margin:0 0 8mm;">{{ $matchBook->subtitle }}</p>
        @endif

        <p style="font-size:11pt;line-height:1.5;opacity:0.92;">
            @if($match->date) {{ $match->date->format('l, j F Y') }} @endif
            @if($match->date && filled($match->location)) · @endif
            @if(filled($match->location)) {{ $match->location }} @endif
        </p>

        @if($fedLogoSrc || $clubLogoSrc)
            <div style="display:flex;align-items:center;justify-content:center;gap:12px;margin-top:6mm;">
                @if($fedLogoSrc) <img src="{{ $fedLogoSrc }}" alt="" style="max-height:40px;max-width:120px;object-fit:contain;opacity:0.95;"> @endif
                @if($clubLogoSrc) <img src="{{ $clubLogoSrc }}" alt="" style="max-height:40px;max-width:120px;object-fit:contain;opacity:0.95;"> @endif
            </div>
        @endif

        <span style="display:inline-block;margin-top:5mm;padding:4px 12px;font-size:7.5pt;letter-spacing:0.14em;text-transform:uppercase;border:1px solid rgba(255,255,255,0.45);border-radius:2px;opacity:0.9;">Match Book</span>
    </div>

    @if($sponsorAssignment?->sponsor)
        <div style="margin-top:auto;padding-top:8mm;">
            <div style="padding:10px 14px;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.28);border-radius:4px;text-align:center;">
                <span style="display:block;font-size:8pt;text-transform:uppercase;letter-spacing:0.12em;opacity:0.85;margin-bottom:6px;">{{ $sponsorAssignment->displayLabel() }}</span>
                @if($sponsorLogoSrc)
                    <img src="{{ $sponsorLogoSrc }}" alt="" style="max-height:36px;max-width:180px;object-fit:contain;margin:0 auto 4px;display:block;">
                @endif
                <span style="font-family:var(--font-head);font-size:11pt;font-weight:600;">{{ $sponsorAssignment->sponsor->name }}</span>
            </div>
        </div>
    @endif
</div>
