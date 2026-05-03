@php
    /**
     * Mobile-first shareable shooter match report.
     *
     * Replaces the in-browser use of `emails.shooter-match-report` (which
     * was a 600px-wide email-table layout — fine in Gmail, terrible to
     * share on WhatsApp). This view is intentionally narrow, dark, single-
     * column, and chrome-free so it reads great on a phone AND screenshots
     * cleanly for sharing in chats / stories.
     *
     * Data shape comes straight from MatchReportService::generateReport()
     * so the email template, the PDF, and this view all stay in lock-step.
     */

    $match      = $report['match'] ?? [];
    $shooter    = $report['shooter'] ?? [];
    $placement  = $report['placement'] ?? [];
    $summary    = $report['summary'] ?? [];
    $stages     = $report['stages'] ?? [];
    $bestStage  = $report['best_stage'] ?? null;
    $worstStage = $report['worst_stage'] ?? null;
    $fieldStats = $report['field_stats'] ?? [];
    $funFacts   = $report['fun_facts'] ?? [];
    $badges     = $report['badges'] ?? [];

    $scoringType = strtolower($match['scoring_type'] ?? 'standard');
    $isPrs       = $scoringType === 'prs';
    $isElr       = $scoringType === 'elr';

    $typeChipColor = $isPrs ? 'bg-amber-500' : ($isElr ? 'bg-sky-500' : 'bg-red-600');
    $scoreLabel    = $isPrs ? 'Hits' : ($isElr ? 'Points' : 'Score');
    $typeLabel     = strtoupper($scoringType);

    $shareTitle = sprintf(
        '%s — %s',
        $shooter['name'] ?? 'My Match',
        $match['name'] ?? 'DeadCenter'
    );

    $shareText = sprintf(
        "%s at %s\n#%s of %s%s — %s%% hit rate%s",
        $shooter['name'] ?? '',
        $match['name'] ?? '',
        $placement['rank'] ?? '?',
        $placement['total'] ?? '?',
        ! empty($placement['summary']) ? " ({$placement['summary']})" : '',
        number_format($summary['hit_rate'] ?? 0, 0),
        $isPrs && ! empty($summary['total_time'])
            ? ' · ' . number_format($summary['total_time'], 1) . 's'
            : ''
    );

    $shareUrl = $shareUrl ?? request()->url();
    $pdfUrl   = $pdfUrl ?? null;

    // Pre-encoded JSON for the share-data <script type="application/json">
    // island below. Building it here (instead of inline `@json([...])`)
    // keeps the script body pure JS so editor linters don't choke on
    // multi-line Blade directives inside <script>.
    $shareDataJson = json_encode(
        [
            'url'   => $shareUrl,
            'title' => $shareTitle,
            'text'  => trim($shareText),
        ],
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
    );
@endphp
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $shareTitle }} — DeadCenter</title>

    {{-- Open Graph for previews when the link is pasted into WhatsApp / iMessage / Slack. --}}
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $shareTitle }}">
    <meta property="og:description" content="{{ trim(str_replace("\n", ' · ', $shareText)) }}">
    <meta property="og:url" content="{{ $shareUrl }}">
    <meta property="og:image" content="{{ asset('icons/icon-512.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $shareTitle }}">
    <meta name="twitter:description" content="{{ trim(str_replace("\n", ' · ', $shareText)) }}">

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <meta name="theme-color" content="#071327">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    @vite(['resources/css/app.css'])

    <style>
        /* Local-only styles: anything that's tedious to express in Tailwind
           or that we want to keep colocated with this view. */
        :root { color-scheme: dark; }

        body {
            background:
                radial-gradient(circle at 50% -10%, rgba(225, 6, 0, 0.18), transparent 55%),
                linear-gradient(180deg, #071327 0%, #0a1a33 100%);
            min-height: 100vh;
            min-height: 100svh;
        }

        /* Gong dot — single shared style for the per-stage strip. */
        .gong-dot {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
            color: #fff;
        }
        .gong-dot--hit       { background: #22c55e; }
        .gong-dot--miss      { background: #ef4444; }
        .gong-dot--not-taken { background: #f59e0b; }
        .gong-dot--none      { background: #1e293b; color: #475569; }

        /* The hero rank tile — pulled out so we can do a subtle inset glow
           that Tailwind's arbitrary-value shadow syntax makes ugly inline. */
        .hero-rank {
            background: linear-gradient(160deg, rgba(225, 6, 0, 0.18) 0%, rgba(225, 6, 0, 0.04) 60%, transparent 100%);
            border: 1px solid rgba(225, 6, 0, 0.35);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08), 0 12px 40px -12px rgba(225, 6, 0, 0.4);
        }

        /* Sticky share bar — safe-area aware on iOS. */
        .share-bar {
            padding-bottom: max(0.75rem, env(safe-area-inset-bottom));
            background: linear-gradient(180deg, rgba(7, 19, 39, 0.6) 0%, rgba(7, 19, 39, 0.95) 35%, #071327 100%);
            backdrop-filter: blur(14px) saturate(1.4);
        }

        /* Anything outside the main share card shouldn't appear in screenshots
           people take of the report; the share-bar uses .no-screenshot so a
           "Save as image" implementation can hide it before rendering. */
        @media print {
            .no-screenshot { display: none !important; }
        }
    </style>
</head>
<body class="text-white antialiased">

<main id="share-card" class="mx-auto w-full max-w-md px-4 pt-5 pb-32 sm:max-w-lg space-y-5">

    {{-- ─── Brand strip ─────────────────────────────────────────────── --}}
    <header class="flex items-center justify-between">
        <a href="{{ url('/') }}" class="text-[15px] font-extrabold tracking-[0.18em]">
            <span class="text-red-500">DEAD</span><span class="text-white">CENTER</span>
        </a>
        <span class="text-[10px] uppercase tracking-[0.2em] text-zinc-400">Match Report</span>
    </header>

    {{-- ─── Match info ──────────────────────────────────────────────── --}}
    <section class="space-y-2">
        <h1 class="text-xl font-bold leading-tight text-white sm:text-2xl">
            {{ $match['name'] ?? 'Match' }}
        </h1>
        <p class="text-[13px] text-zinc-400">
            {{ $match['date'] ?? '' }}
            @if(!empty($match['location']))
                <span class="text-zinc-600"> · </span>{{ $match['location'] }}
            @endif
        </p>
        <div class="flex flex-wrap items-center gap-2 pt-1">
            <span class="inline-flex items-center rounded px-2.5 py-0.5 text-[10px] font-bold tracking-[0.1em] text-white {{ $typeChipColor }}">
                {{ $typeLabel }}
            </span>
            @if(!empty($shooter['division']))
                <span class="inline-flex items-center rounded bg-zinc-800 px-2.5 py-0.5 text-[10px] text-zinc-300">
                    {{ $shooter['division'] }}
                </span>
            @endif
            @if(!empty($shooter['squad']))
                <span class="inline-flex items-center rounded bg-zinc-800 px-2.5 py-0.5 text-[10px] text-zinc-300">
                    Squad {{ $shooter['squad'] }}
                </span>
            @endif
        </div>
    </section>

    {{-- ─── Hero rank tile ──────────────────────────────────────────── --}}
    <section class="hero-rank rounded-2xl px-5 py-6 text-center">
        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-zinc-300">
            {{ $shooter['name'] ?? 'Shooter' }}
            @if(!empty($shooter['bib_number']))
                <span class="text-zinc-500">· #{{ $shooter['bib_number'] }}</span>
            @endif
        </p>

        <div class="mt-3 flex items-baseline justify-center gap-2">
            <span class="text-6xl font-black leading-none text-white sm:text-7xl">
                {{ $placement['rank_ordinal'] ?? ('#' . ($placement['rank'] ?? '—')) }}
            </span>
            <span class="text-base font-medium text-zinc-400">
                of {{ $placement['total'] ?? '—' }}
            </span>
        </div>

        @if(!empty($placement['summary']))
            <p class="mt-2 text-sm font-semibold uppercase tracking-wide text-red-400">
                {{ $placement['summary'] }}
            </p>
        @endif

        <p class="mt-4 text-[13px] text-zinc-300">
            <span class="text-zinc-500">{{ $scoreLabel }}:</span>
            <span class="font-semibold text-white">{{ number_format($summary['total_score'] ?? 0, 1) }}</span>
            <span class="text-zinc-600">/ {{ number_format($summary['max_possible'] ?? 0, 1) }}</span>
            @if($isPrs && !empty($summary['total_time']))
                <span class="text-zinc-600"> · </span>
                <span class="font-semibold text-amber-400">{{ number_format($summary['total_time'], 1) }}s</span>
            @endif
        </p>
    </section>

    {{-- ─── Stat strip (4 tiles) ────────────────────────────────────── --}}
    <section class="grid grid-cols-4 gap-2">
        <div class="rounded-xl bg-zinc-900/60 ring-1 ring-white/5 px-2 py-3 text-center">
            <div class="text-2xl font-bold leading-none text-emerald-400">{{ $summary['hits'] ?? 0 }}</div>
            <div class="mt-1 text-[9px] font-semibold uppercase tracking-wider text-zinc-500">Hits</div>
        </div>
        <div class="rounded-xl bg-zinc-900/60 ring-1 ring-white/5 px-2 py-3 text-center">
            <div class="text-2xl font-bold leading-none text-red-400">{{ $summary['misses'] ?? 0 }}</div>
            <div class="mt-1 text-[9px] font-semibold uppercase tracking-wider text-zinc-500">Miss</div>
        </div>
        <div class="rounded-xl bg-zinc-900/60 ring-1 ring-white/5 px-2 py-3 text-center">
            <div class="text-2xl font-bold leading-none text-white">{{ number_format($summary['hit_rate'] ?? 0, 0) }}%</div>
            <div class="mt-1 text-[9px] font-semibold uppercase tracking-wider text-zinc-500">Rate</div>
        </div>
        <div class="rounded-xl bg-zinc-900/60 ring-1 ring-white/5 px-2 py-3 text-center">
            @if($isPrs && !empty($summary['total_time']))
                <div class="text-2xl font-bold leading-none text-amber-400">{{ number_format($summary['total_time'], 0) }}<span class="text-sm font-medium text-amber-400/70">s</span></div>
                <div class="mt-1 text-[9px] font-semibold uppercase tracking-wider text-zinc-500">Time</div>
            @else
                <div class="text-2xl font-bold leading-none text-zinc-400">{{ ($summary['no_shots'] ?? 0) }}</div>
                <div class="mt-1 text-[9px] font-semibold uppercase tracking-wider text-zinc-500">N/T</div>
            @endif
        </div>
    </section>

    {{-- ─── Per-stage breakdown ─────────────────────────────────────── --}}
    @if(count($stages) > 0)
    <section class="space-y-3">
        <h2 class="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">Per-Stage Breakdown</h2>

        <div class="space-y-2">
            @foreach($stages as $idx => $stage)
                <article class="rounded-xl bg-zinc-900/50 ring-1 ring-white/5 p-3.5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate text-sm font-semibold text-white">
                                {{ $stage['label'] ?? 'Stage ' . ($idx + 1) }}
                                @if(!empty($stage['distance_meters']))
                                    <span class="font-normal text-zinc-500">({{ $stage['distance_meters'] }}m)</span>
                                @endif
                            </h3>
                        </div>
                        <div class="shrink-0 text-right">
                            @if(!$isPrs && !empty($stage['distance_multiplier']) && (float) $stage['distance_multiplier'] !== 1.0)
                                <div class="text-[11px] text-red-400">×{{ number_format($stage['distance_multiplier'], 1) }}</div>
                            @endif
                            @if($isPrs && !empty($stage['time']))
                                <div class="text-[11px] text-amber-400">{{ number_format($stage['time'], 1) }}s</div>
                            @endif
                        </div>
                    </div>

                    @if(!empty($stage['gongs']))
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach($stage['gongs'] as $gong)
                                @php
                                    $r = $gong['result'] ?? '';
                                    $cls = match ($r) {
                                        'hit'        => 'gong-dot gong-dot--hit',
                                        'miss'       => 'gong-dot gong-dot--miss',
                                        'not_taken'  => 'gong-dot gong-dot--not-taken',
                                        default      => 'gong-dot gong-dot--none',
                                    };
                                    $glyph = match ($r) {
                                        'hit'       => '✓',
                                        'miss'      => '✗',
                                        'not_taken' => '–',
                                        default     => '·',
                                    };
                                @endphp
                                <span class="{{ $cls }}" title="{{ $gong['label'] ?? '' }}">{{ $glyph }}</span>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-2.5 flex items-center justify-between text-[12px]">
                        <div class="text-zinc-400">
                            <span class="text-emerald-400">{{ $stage['hits'] ?? 0 }} hits</span>
                            <span class="text-zinc-600"> · </span>
                            <span class="text-red-400">{{ $stage['misses'] ?? 0 }} miss</span>
                            @if(($stage['no_shots'] ?? 0) > 0)
                                <span class="text-zinc-600"> · </span>
                                <span class="text-amber-400">{{ $stage['no_shots'] }} N/T</span>
                            @endif
                        </div>
                        <div class="font-semibold text-white">
                            {{ number_format($stage['score'] ?? 0, 1) }} pts
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
    @endif

    {{-- ─── Best & Worst ────────────────────────────────────────────── --}}
    @if($bestStage || $worstStage)
    <section class="space-y-2">
        <h2 class="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">Best &amp; Worst Stage</h2>
        @if($bestStage)
            <div class="rounded-xl bg-zinc-900/50 ring-1 ring-white/5 border-l-4 border-emerald-500 p-3">
                <div class="flex items-center justify-between gap-3 text-[13px]">
                    <span class="min-w-0 truncate text-zinc-300">
                        <strong class="text-emerald-400">BEST:</strong>
                        {{ $bestStage['label'] ?? '' }}
                        <span class="text-zinc-500">— {{ $bestStage['hits'] ?? 0 }}/{{ $bestStage['targets'] ?? 0 }} impacts</span>
                    </span>
                    <span class="shrink-0 font-semibold text-emerald-400">
                        {{ number_format($bestStage['score'] ?? 0, 1) }} pts
                    </span>
                </div>
            </div>
        @endif
        @if($worstStage)
            <div class="rounded-xl bg-zinc-900/50 ring-1 ring-white/5 border-l-4 border-red-500 p-3">
                <div class="flex items-center justify-between gap-3 text-[13px]">
                    <span class="min-w-0 truncate text-zinc-300">
                        <strong class="text-red-400">WORST:</strong>
                        {{ $worstStage['label'] ?? '' }}
                        <span class="text-zinc-500">— {{ $worstStage['hits'] ?? 0 }}/{{ $worstStage['targets'] ?? 0 }} impacts</span>
                    </span>
                    <span class="shrink-0 font-semibold text-red-400">
                        {{ number_format($worstStage['score'] ?? 0, 1) }} pts
                    </span>
                </div>
            </div>
        @endif
    </section>
    @endif

    {{-- ─── How you compared ────────────────────────────────────────── --}}
    @if(!empty($fieldStats))
    <section class="space-y-2">
        <h2 class="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">How You Compared</h2>
        <div class="rounded-xl bg-zinc-900/50 ring-1 ring-white/5 divide-y divide-white/5 text-[13px]">
            @if(isset($fieldStats['avg_score']))
                <div class="flex items-center justify-between px-3.5 py-2.5">
                    <span class="text-zinc-400">Field Avg {{ $scoreLabel }}</span>
                    <span class="font-semibold text-white">{{ number_format($fieldStats['avg_score'], 1) }}</span>
                </div>
            @endif
            @if(isset($fieldStats['avg_hit_rate']))
                <div class="flex items-center justify-between px-3.5 py-2.5">
                    <span class="text-zinc-400">Field Avg Hit Rate</span>
                    <span class="font-semibold text-white">{{ number_format($fieldStats['avg_hit_rate'], 1) }}%</span>
                </div>
            @endif
            @if(!empty($fieldStats['winner_name']))
                <div class="flex items-center justify-between px-3.5 py-2.5">
                    <span class="min-w-0 truncate text-zinc-400">Winner: <span class="text-zinc-200">{{ $fieldStats['winner_name'] }}</span></span>
                    <span class="shrink-0 font-semibold text-red-400">{{ number_format($fieldStats['winner_score'] ?? 0, 1) }}</span>
                </div>
            @endif
            @if(!empty($fieldStats['hardest_gong']))
                <div class="flex items-center justify-between px-3.5 py-2.5">
                    <span class="min-w-0 truncate text-zinc-400">Hardest: <span class="text-red-400">{{ $fieldStats['hardest_gong']['label'] ?? '' }}</span></span>
                    <span class="shrink-0 font-semibold text-red-400">{{ number_format($fieldStats['hardest_gong']['hit_rate'] ?? 0, 0) }}%</span>
                </div>
            @endif
            @if(!empty($fieldStats['easiest_gong']))
                <div class="flex items-center justify-between px-3.5 py-2.5">
                    <span class="min-w-0 truncate text-zinc-400">Easiest: <span class="text-emerald-400">{{ $fieldStats['easiest_gong']['label'] ?? '' }}</span></span>
                    <span class="shrink-0 font-semibold text-emerald-400">{{ number_format($fieldStats['easiest_gong']['hit_rate'] ?? 0, 0) }}%</span>
                </div>
            @endif
        </div>
    </section>
    @endif

    {{-- ─── Fun facts ───────────────────────────────────────────────── --}}
    @if(count($funFacts) > 0)
    <section class="space-y-2">
        <h2 class="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">Did You Know?</h2>
        <ul class="space-y-1.5">
            @foreach($funFacts as $fact)
                <li class="flex gap-2 rounded-lg bg-zinc-900/40 ring-1 ring-white/5 px-3 py-2 text-[12.5px] leading-snug text-zinc-300">
                    <span class="text-red-500">●</span>
                    <span>{{ $fact }}</span>
                </li>
            @endforeach
        </ul>
    </section>
    @endif

    {{-- ─── Badges ──────────────────────────────────────────────────── --}}
    @if(count($badges) > 0)
    <section class="space-y-2">
        <h2 class="text-[10px] font-bold uppercase tracking-[0.22em] text-red-400">Badges Awarded</h2>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            @foreach($badges as $badge)
                @php
                    $family = $badge['family'] ?? 'prs';
                    $tier = $badge['tier'] ?? 'earned';
                    $accent = $family === 'rf' ? 'text-amber-300' : 'text-sky-300';
                    $ring = $family === 'rf' ? 'ring-amber-500/30' : 'ring-sky-500/30';
                @endphp
                <div class="rounded-xl bg-zinc-900/50 ring-1 {{ $ring }} p-3 text-center">
                    <div class="text-[10px] uppercase tracking-wider {{ $accent }}">{{ $badge['tier_label'] ?? ucfirst($tier) }}</div>
                    <div class="mt-1 text-[12.5px] font-semibold text-white leading-tight">{{ $badge['title'] ?? 'Badge' }}</div>
                    @if(!empty($badge['earn_chip']))
                        <div class="mt-1 text-[10px] text-zinc-500">{{ $badge['earn_chip'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- ─── Footer ──────────────────────────────────────────────────── --}}
    <footer class="pt-2 text-center text-[11px] text-zinc-500">
        deadcenter.co.za — score it, share it, own it.
    </footer>
</main>

{{-- ─── Sticky share bar ────────────────────────────────────────────── --}}
<div class="share-bar fixed inset-x-0 bottom-0 z-40 no-screenshot">
    <div class="mx-auto flex w-full max-w-md items-center gap-2 px-4 pt-3 sm:max-w-lg">
        <button
            type="button"
            data-share-btn
            class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-red-600/30 transition active:scale-[0.98] hover:bg-red-500"
        >
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
            Share
        </button>

        <a
            href="https://wa.me/?text={{ urlencode(trim($shareText) . "\n" . $shareUrl) }}"
            target="_blank"
            rel="noopener"
            class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-lg shadow-emerald-600/30 transition active:scale-95 hover:bg-emerald-500"
            aria-label="Share to WhatsApp"
            title="Share to WhatsApp"
        >
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.967-.94 1.164-.173.198-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.71.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
        </a>

        <button
            type="button"
            data-copy-btn
            class="flex h-12 w-12 items-center justify-center rounded-xl bg-zinc-800 text-zinc-200 ring-1 ring-white/10 transition active:scale-95 hover:bg-zinc-700"
            aria-label="Copy link"
            title="Copy link"
        >
            <svg data-copy-icon viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            <svg data-copied-icon viewBox="0 0 24 24" class="hidden h-5 w-5 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </button>

        @if(!empty($pdfUrl))
            <a
                href="{{ $pdfUrl }}"
                class="flex h-12 w-12 items-center justify-center rounded-xl bg-zinc-800 text-zinc-200 ring-1 ring-white/10 transition active:scale-95 hover:bg-zinc-700"
                aria-label="Download PDF"
                title="Download PDF"
            >
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            </a>
        @endif
    </div>
</div>

{{--
    Share data is embedded as a JSON island so the runtime <script> block
    contains pure JS (no Blade interpolation). That keeps the JS linter
    happy AND keeps the JSON encoding correct via Laravel's @json directive.
--}}
<script type="application/json" id="match-share-data">{!! $shareDataJson !!}</script>

{{--
    Share interactions. Vanilla JS rather than Alpine because:
      a) the marketing/share bundle doesn't preload Alpine,
      b) Web Share API is a one-shot async call — no reactive state needed,
      c) keeps the screenshot path simple (nothing to wait on).
--}}
<script>
    (function () {
        const shareBtn = document.querySelector('[data-share-btn]');
        const copyBtn  = document.querySelector('[data-copy-btn]');

        const dataEl = document.getElementById('match-share-data');
        const data = dataEl ? JSON.parse(dataEl.textContent) : { url: location.href, title: document.title, text: '' };
        const url = data.url;
        const title = data.title;
        const text = data.text;

        if (shareBtn) {
            shareBtn.addEventListener('click', async function () {
                if (navigator.share) {
                    try {
                        await navigator.share({ title, text, url });
                        return;
                    } catch (e) {
                        // User cancelled or platform refused — silently fall through
                        // so we don't surface a scary error for a normal "no thanks".
                        if (e && e.name !== 'AbortError') {
                            console.warn('Web Share failed:', e);
                        }
                        return;
                    }
                }

                // Fallback: copy text + URL to clipboard so the user can paste it
                // wherever they want. Better than throwing them at a half-broken
                // share UI on browsers that don't implement navigator.share.
                try {
                    await navigator.clipboard.writeText(text + '\n' + url);
                    flashCopied(shareBtn, 'Copied!');
                } catch (e) {
                    console.warn('Clipboard write failed:', e);
                }
            });
        }

        if (copyBtn) {
            copyBtn.addEventListener('click', async function () {
                try {
                    await navigator.clipboard.writeText(url);
                    const idle = copyBtn.querySelector('[data-copy-icon]');
                    const done = copyBtn.querySelector('[data-copied-icon]');
                    if (idle && done) {
                        idle.classList.add('hidden');
                        done.classList.remove('hidden');
                        setTimeout(function () {
                            idle.classList.remove('hidden');
                            done.classList.add('hidden');
                        }, 1600);
                    }
                } catch (e) {
                    console.warn('Clipboard copy failed:', e);
                }
            });
        }

        function flashCopied(btn, msg) {
            const original = btn.innerHTML;
            btn.innerHTML = '<span>' + msg + '</span>';
            setTimeout(function () { btn.innerHTML = original; }, 1400);
        }
    })();
</script>

</body>
</html>
