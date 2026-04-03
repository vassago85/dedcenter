<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Advertising Opportunities — DeadCenter + MatchBook Pro</title>
    <style>
        :root {
            --color-primary: #1e293b;
            --color-accent: #f59e0b;
            --color-muted: #64748b;
            --color-border: #e2e8f0;
            --color-bg: #ffffff;
            --color-section: #f8fafc;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: var(--color-primary);
            background: var(--color-bg);
            line-height: 1.7;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        .header {
            text-align: center;
            padding-bottom: 3rem;
            border-bottom: 2px solid var(--color-accent);
            margin-bottom: 3rem;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: 0.5rem;
        }

        .header .subtitle {
            font-size: 1.125rem;
            color: var(--color-muted);
        }

        .badge {
            display: inline-block;
            background: var(--color-accent);
            color: var(--color-primary);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
        }

        .section {
            margin-bottom: 3rem;
        }

        .section h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--color-border);
        }

        .section .content {
            white-space: pre-wrap;
            color: #334155;
        }

        .highlight-box {
            background: var(--color-section);
            border-left: 4px solid var(--color-accent);
            padding: 1.5rem;
            border-radius: 0 0.5rem 0.5rem 0;
            margin: 1rem 0;
        }

        .footer {
            margin-top: 4rem;
            padding-top: 2rem;
            border-top: 2px solid var(--color-border);
            text-align: center;
            color: var(--color-muted);
            font-size: 0.875rem;
        }

        .footer p { margin-bottom: 0.5rem; }

        @media print {
            body { font-size: 11pt; }
            .container { max-width: 100%; padding: 1rem; }
            .section { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="badge">Confidential</div>
            <h1>Advertising Opportunities</h1>
            <p class="subtitle">DeadCenter Competition Platform + MatchBook Pro</p>
        </div>

        @if($content['overview'])
            <div class="section">
                <h2>Overview</h2>
                <div class="content">{{ $content['overview'] }}</div>
            </div>
        @endif

        @if($content['visibility'])
            <div class="section">
                <h2>Brand Visibility Locations</h2>
                <div class="content">{{ $content['visibility'] }}</div>
            </div>
        @endif

        @if($content['matchbook_section'])
            <div class="section">
                <h2>Match Books as a Brand Visibility Product</h2>
                <div class="highlight-box">
                    <div class="content">{{ $content['matchbook_section'] }}</div>
                </div>
            </div>
        @endif

        @if($content['reach'])
            <div class="section">
                <h2>Reach & Footprint</h2>
                <div class="content">{{ $content['reach'] }}</div>
            </div>
        @endif

        @if($content['tiers'])
            <div class="section">
                <h2>Advertising Packages</h2>
                <div class="content">{{ $content['tiers'] }}</div>
                <div class="highlight-box" style="margin-top: 1.5rem;">
                    <strong>Pricing available on request.</strong> Packages tailored to event reach and visibility.
                </div>
            </div>
        @endif

        @if($content['custom_packages'])
            <div class="section">
                <h2>Custom Packages</h2>
                <div class="content">{{ $content['custom_packages'] }}</div>
            </div>
        @endif

        @if($content['contact'])
            <div class="section">
                <h2>Contact & Next Steps</h2>
                <div class="content">{{ $content['contact'] }}</div>
            </div>
        @endif

        <div class="footer">
            <p>This document is intended for potential advertisers only.</p>
            <p>DeadCenter + MatchBook Pro &mdash; {{ date('Y') }}</p>
        </div>
    </div>
</body>
</html>
