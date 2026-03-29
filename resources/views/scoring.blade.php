<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>DeadCenter Scoring</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/logo.png">
    <link rel="apple-touch-icon" href="/logo.png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,900" rel="stylesheet" />
    @vite(['resources/css/scoring.css', 'resources/js/scoring-app/main.js'])
</head>
<body class="bg-slate-900">
    <div id="scoring-app"></div>
</body>
</html>
