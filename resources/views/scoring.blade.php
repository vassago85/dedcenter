<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>DeadCenter Scoring</title>
    @PwaHead
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,900" rel="stylesheet" />
    @vite(['resources/css/scoring.css', 'resources/js/scoring-app/main.js'])
</head>
<body class="bg-app">
    <div id="scoring-app"></div>
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(function(registrations) {
                var cleared = false;
                registrations.forEach(function(reg) {
                    if (reg.active && reg.active.scriptURL && !reg.active.scriptURL.endsWith('/sw.js')) {
                        reg.unregister();
                        cleared = true;
                    }
                });
                if (cleared) {
                    caches.keys().then(function(names) {
                        names.forEach(function(name) { caches.delete(name); });
                    });
                }
            });
            navigator.serviceWorker.register('/sw.js').then(
                function(r) { console.log('SW registered:', r.scope); },
                function(e) { console.error('SW failed:', e); }
            );
        }
    </script>
</body>
</html>
