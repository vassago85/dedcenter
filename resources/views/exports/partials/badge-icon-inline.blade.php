{{--
    Print-safe badge icon.

    Mirrors the glyph set in <x-badge-icon> (resources/views/components/badge-icon.blade.php)
    but emits a standalone inline SVG with no external CSS dependencies so it
    renders identically in browser and in Gotenberg-produced PDFs.

    Why not reuse <x-badge-icon>?
        The platform component composes Tailwind utility classes passed via
        $attributes. In the PDF context we don't ship the compiled Tailwind
        build, so we'd inherit missing class names. This partial takes a
        bare $name and outputs an SVG sized/coloured by the .b-crest CSS
        in the consuming view, keeping the glyph set in lockstep with
        the web UI.

    Keep this file's <switch> cases in 1:1 parity with badge-icon.blade.php
    whenever that component gains a new icon.
--}}
@php
    /** @var string $name */
    $name = $name ?? 'target';
    $isDist = str_starts_with($name, 'dist-');
    $distMeters = $isDist ? substr($name, 5) : null;
@endphp
@if($isDist)
    {{-- Distance badges render the meters inside a ring, mirroring the web
         component's text-based distance glyph. Sized by the enclosing
         crest container via CSS. --}}
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="10"/>
        <text x="12" y="14" text-anchor="middle" font-size="7" font-weight="800" fill="currentColor" stroke="none" font-family="Helvetica, Arial, sans-serif">{{ $distMeters }}m</text>
    </svg>
@else
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
    @case('target')
        <circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>
        @break
    @case('timer')
        <line x1="10" x2="14" y1="2" y2="2"/><line x1="12" x2="15" y1="14" y2="11"/><circle cx="12" cy="14" r="8"/>
        @break
    @case('rocket')
        <path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>
        @break
    @case('medal')
        <path d="M7.21 15 2.66 7.14a2 2 0 0 1 .13-2.2L4.4 2.8A2 2 0 0 1 6 2h12a2 2 0 0 1 1.6.8l1.6 2.14a2 2 0 0 1 .14 2.2L16.79 15"/><path d="M11 12 5.12 2.2"/><path d="m13 12 5.88-9.8"/><path d="M8 7h8"/><circle cx="12" cy="17" r="5"/>
        @break
    @case('medal-1')
        <path d="M7.21 15 2.66 7.14a2 2 0 0 1 .13-2.2L4.4 2.8A2 2 0 0 1 6 2h12a2 2 0 0 1 1.6.8l1.6 2.14a2 2 0 0 1 .14 2.2L16.79 15"/><path d="M11 12 5.12 2.2"/><path d="m13 12 5.88-9.8"/><path d="M8 7h8"/><circle cx="12" cy="17" r="5"/><path d="M11.5 15 12 14.5V19"/><path d="M10.5 19h3"/>
        @break
    @case('medal-2')
        <path d="M7.21 15 2.66 7.14a2 2 0 0 1 .13-2.2L4.4 2.8A2 2 0 0 1 6 2h12a2 2 0 0 1 1.6.8l1.6 2.14a2 2 0 0 1 .14 2.2L16.79 15"/><path d="M11 12 5.12 2.2"/><path d="m13 12 5.88-9.8"/><path d="M8 7h8"/><circle cx="12" cy="17" r="5"/><path d="M10 15.5c0-1 .9-1.5 2-1.5s2 .5 2 1.5c0 .8-1 1.8-4 3.5h4"/>
        @break
    @case('medal-3')
        <path d="M7.21 15 2.66 7.14a2 2 0 0 1 .13-2.2L4.4 2.8A2 2 0 0 1 6 2h12a2 2 0 0 1 1.6.8l1.6 2.14a2 2 0 0 1 .14 2.2L16.79 15"/><path d="M11 12 5.12 2.2"/><path d="m13 12 5.88-9.8"/><path d="M8 7h8"/><circle cx="12" cy="17" r="5"/><path d="M10 14.8c.5-.5 1.2-.8 2-.8 1.2 0 2 .6 2 1.5 0 .6-.5 1.1-1.2 1.4.8.3 1.2.8 1.2 1.5 0 .9-.8 1.6-2 1.6-.8 0-1.5-.3-2-.8"/>
        @break
    @case('trophy')
        <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>
        @break
    @case('link-2')
        <path d="M9 17H7A5 5 0 0 1 7 7h2"/><path d="M15 7h2a5 5 0 1 1 0 10h-2"/><line x1="8" x2="16" y1="12" y2="12"/>
        @break
    @case('flame')
        <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>
        @break
    @case('gauge')
        <path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/>
        @break
    @case('zap')
        <path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>
        @break
    @case('shield')
        <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>
        @break
    @case('circle-check')
        <circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>
        @break
    @case('award')
        <path d="m15.477 12.89 1.515 8.526a.5.5 0 0 1-.81.47l-3.58-2.687a1 1 0 0 0-1.197 0l-3.586 2.686a.5.5 0 0 1-.81-.469l1.514-8.526"/><circle cx="12" cy="8" r="6"/>
        @break
    @case('crown')
        <path d="M11.562 3.266a.5.5 0 0 1 .876 0L15.39 8.87a1 1 0 0 0 1.516.294L21.183 5.5a.5.5 0 0 1 .798.519l-2.834 10.246a1 1 0 0 1-.956.734H5.81a1 1 0 0 1-.957-.734L2.02 6.02a.5.5 0 0 1 .798-.519l4.276 3.664a1 1 0 0 0 1.516-.294z"/><path d="M5 21h14"/>
        @break
    @case('sparkles')
        <path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/>
        @break
    @case('layers')
        <path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>
        @break
    @case('crosshair')
        <circle cx="12" cy="12" r="10"/><line x1="22" x2="18" y1="12" y2="12"/><line x1="6" x2="2" y1="12" y2="12"/><line x1="12" x2="12" y1="6" y2="2"/><line x1="12" x2="12" y1="22" y2="18"/>
        @break
    @case('map-pin')
        <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>
        @break
    @case('sunrise')
        <path d="M12 2v8"/><path d="m4.93 10.93 1.41 1.41"/><path d="M2 18h2"/><path d="M20 18h2"/><path d="m19.07 10.93-1.41 1.41"/><path d="M22 22H2"/><path d="m8 6 4-4 4 4"/><path d="M16 18a4 4 0 0 0-8 0"/>
        @break
    @case('flag')
        <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" x2="4" y1="22" y2="15"/>
        @break
    @case('git-branch')
        <line x1="6" x2="6" y1="3" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/>
        @break
    @case('spade')
        <path d="M5 9c-1.5 1.5-3 3.2-3 5.5A5.5 5.5 0 0 0 7.5 20c1.8 0 3-.5 4.5-2 1.5 1.5 2.7 2 4.5 2a5.5 5.5 0 0 0 5.5-5.5c0-2.3-1.5-4-3-5.5l-7-7-7 7Z"/><path d="M12 18v4"/>
        @break
    @case('chess-queen')
        <path d="M3 6l2.5 7h13L21 6l-3.5 2.5L12 3 6.5 8.5 3 6z"/><path d="M12 3v.01"/><path d="M5.5 13h13"/><path d="M6 17h12"/><path d="M7 17v4h10v-4"/>
        @break
    @case('podium')
        <rect x="2" y="14" width="6" height="7" rx="1"/><rect x="9" y="8" width="6" height="13" rx="1"/><rect x="16" y="11" width="6" height="10" rx="1"/><line x1="1" x2="23" y1="22" y2="22"/>
        @break
    @case('deadcenter')
        <circle cx="12" cy="12" r="9"/><line x1="12" x2="12" y1="3" y2="8"/><line x1="12" x2="12" y1="16" y2="21"/><line x1="3" x2="8" y1="12" y2="12"/><line x1="16" x2="21" y1="12" y2="12"/><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/>
        @break
    @default
        {{-- Graceful fallback matches the platform default. --}}
        <circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/>
@endswitch
</svg>
@endif
