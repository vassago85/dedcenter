<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DeadCenter Scoring</title>
    <link rel="manifest" href="/manifest.webmanifest">
    @vite(['resources/js/scoring-app/main.js'])
</head>
<body class="bg-slate-900">
    <div id="scoring-app"></div>
</body>
</html>
