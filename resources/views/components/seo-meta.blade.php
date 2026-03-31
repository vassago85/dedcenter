@props([
    'title' => 'DeadCenter — Precision Shooting Scoring Platform',
    'description' => 'A modern scoring platform for shooting sports. Capture scores offline on tablets, sync across devices, and publish live results.',
    'canonical' => null,
    'ogImage' => null,
    'ogType' => 'website',
    'twitterCard' => 'summary_large_image',
    'schema' => null,
])

<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">

@if($canonical)
    <link rel="canonical" href="{{ $canonical }}">
@endif

{{-- Open Graph --}}
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:site_name" content="DeadCenter">
<meta property="og:url" content="{{ $canonical ?? request()->url() }}">
@if($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
@endif

{{-- Twitter Card --}}
<meta name="twitter:card" content="{{ $twitterCard }}">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
@if($ogImage)
    <meta name="twitter:image" content="{{ $ogImage }}">
@endif

{{-- JSON-LD Structured Data --}}
@if($schema)
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
@endif
