<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'DeadCenter Scoreboard' }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,900" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="bg-slate-950 text-white antialiased">
    {{ $slot }}

    @fluxScripts
</body>
</html>
