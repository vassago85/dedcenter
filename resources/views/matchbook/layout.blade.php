<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $match->name ?? 'Match Book' }} — MatchBook Pro</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&display=swap');

        @page { size: A4 portrait; margin: 10mm; }

        *, *::before, *::after { box-sizing: border-box; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        :root {
        @foreach ($matchBook->cssVariables() as $name => $value)
            {{ $name }}: {{ $value }};
        @endforeach
            --font-head: "Oswald", system-ui, sans-serif;
            --font-sans: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --color-muted: #64748b;
            --color-light: #f8fafc;
            --color-border: #e2e8f0;
        }

        html, body { margin: 0; padding: 0; }
        body {
            font-family: var(--font-sans);
            font-size: 10.5pt;
            line-height: 1.45;
            color: var(--color-text);
            background: #fff;
        }

        .page {
            width: 100%;
            min-height: 277mm;
            position: relative;
            page-break-after: always;
            padding-bottom: 12mm;
        }
        .page:last-child { page-break-after: auto; }

        .section-stripe { border-left: 4px solid var(--color-primary); padding-left: 12px; margin: 0 0 1em; }
        .section-stripe--info { border-left-color: #2563eb; background: #f8fafc; padding: 8px 12px 10px 14px; margin-bottom: 12px; }
        .section-stripe--safety { border-left-color: #dc2626; background: #fef2f2; padding: 8px 12px 10px 14px; margin-bottom: 12px; }
        .section-stripe--stage { border-left-color: #16a34a; background: #fafffe; padding: 8px 12px 12px 14px; margin-bottom: 16px; }
        .section-stripe--sponsor { border-left-color: #f59e0b; background: #fffbeb; padding: 8px 12px 10px 14px; margin-bottom: 12px; }
        .section-stripe--summary { border-left-color: #7c3aed; background: #f5f3ff; padding: 8px 12px 10px 14px; margin-bottom: 12px; }
        .section-stripe--dope { border-left-color: #64748b; background: #f8fafc; padding: 8px 12px 10px 14px; margin-bottom: 12px; }

        .page-header {
            border-bottom: 2px solid var(--color-primary);
            padding-bottom: 6px;
            margin-bottom: 14px;
            font-family: var(--font-head);
            font-weight: 600;
            font-size: 14pt;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: var(--color-primary);
        }

        .page-footer-fixed {
            position: fixed;
            left: 10mm; right: 10mm; bottom: 6mm;
            font-size: 7pt; line-height: 1.3;
            padding-top: 4px;
            border-top: 1px solid var(--color-muted);
            color: var(--color-muted);
            background: #fff;
        }

        h1, h2, h3, h4 { font-family: var(--font-head); font-weight: 600; }

        .mb-label { font-size: 8pt; color: #64748b; text-transform: uppercase; font-weight: 700; margin-bottom: 2px; }
        .mb-table { width: 100%; border-collapse: collapse; }
        .mb-table td { vertical-align: top; }

        .data-table { width: 100%; border-collapse: collapse; font-size: 8pt; margin: 6px 0 12px 0; }
        .data-table th { background: #166534; color: #fff; padding: 5px 6px; text-align: left; font-weight: 600; }
        .data-table td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .data-table tr:nth-child(even) td { background: #f8fafc; }
    </style>
    @stack('styles')
</head>
<body>
    @yield('content')

    <div class="page-footer-fixed">
        {{ $match->name }} · {{ $match->organization?->name ?? '' }} · MatchBook Pro
    </div>
</body>
</html>
