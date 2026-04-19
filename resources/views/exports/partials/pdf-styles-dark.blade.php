{{-- Shared PDF styles (DARK EDITION) — used by every DeadCenter PDF and report.

     Palette sourced from the app shell so a downloaded PDF reads as part of
     the same product as the web UI. DomPDF does not support CSS custom
     properties (var()), so every value is inlined as a hex literal. Keep it
     that way — if you need a new token, add a semantic class here rather
     than sprinkling ad-hoc colours through template files.

     ─────────────────────────────────────────────
     SURFACE SCALE (darkest → lightest)
       #071327  page-bg        (matches app body tone)
       #0c1a33  surface-1      (cards, panels)
       #1d2d4a  surface-2      (nested / elevated — matches --color-surface)
       #243757  surface-3      (hover / active — matches --color-surface-2)
       #31486d  border         (matches --color-border)
       #1e293b  border-soft    (dividers, zebra seams)

     INK (brightest → dimmest)
       #f8fafc  ink-primary    (titles, values)
       #cbd5e1  ink-secondary  (body)
       #94a3b8  ink-muted      (labels, eyebrows)
       #64748b  ink-dim        (sub-labels, captions)

     ACCENT
       #ff2b2b  accent         (matches --color-accent — live UI red)
       #e10600  accent-strong  (brand lockup + bottom rail — archival red)

     SEMANTIC
       #22c55e  hit     /  rgba(34,197,94,0.12)  hit-tint
       #ef4444  miss    /  rgba(239,68,68,0.12)  miss-tint
       #475569  neutral /  rgba(148,163,184,0.08) neutral-tint

     PODIUM (re-derived for dark — NO light-mode gradients here)
       Gold    #fbbf24 / rgba(251,191,36,0.10) / rail #d97706
       Silver  #cbd5e1 / rgba(203,213,225,0.08) / rail #64748b
       Bronze  #fb923c / rgba(251,146,60,0.10) / rail #c2410c
     ─────────────────────────────────────────────
--}}
<style>
    /* ─── Reset + Base ─── */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body {
        font-family: 'DejaVu Sans', Arial, sans-serif;
        color: #cbd5e1;
        background: #071327;
        font-size: 9.5pt;
        line-height: 1.4;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* ─── Header strip ─── */
    .pdf-header {
        background: #071327;
        color: #f8fafc;
        padding: 12px 18px;
        border-bottom: 2px solid #e10600;
    }
    .pdf-header-table { width: 100%; border-collapse: collapse; }
    .pdf-header-table td { vertical-align: middle; }

    .brand-left  { width: 26%; }
    .brand-center { width: 48%; text-align: center; }
    .brand-right { width: 26%; text-align: right; }

    .brand-inner { border-collapse: collapse; }
    .brand-inner .brand-mark { padding-right: 10px; vertical-align: middle; }
    .brand-inner .brand-word {
        font-size: 14pt;
        font-weight: 800;
        letter-spacing: 0.01em;
        vertical-align: middle;
    }
    .brand-inner .brand-word .dead   { color: #f8fafc; }
    .brand-inner .brand-word .center { color: #e10600; }

    .brand-center .eyebrow {
        font-size: 6.5pt;
        font-weight: 700;
        color: #ff2b2b;
        letter-spacing: 0.28em;
        text-transform: uppercase;
        margin-bottom: 4px;
    }
    .brand-center .match-name {
        font-size: 14pt;
        font-weight: 800;
        letter-spacing: -0.01em;
        line-height: 1.15;
        color: #f8fafc;
    }
    .brand-center .match-meta {
        font-size: 7.5pt;
        color: #94a3b8;
        margin-top: 3px;
        letter-spacing: 0.03em;
    }
    .brand-center .match-meta .dot { margin: 0 6px; color: #475569; }

    .rf-mark { margin-left: auto; border-collapse: collapse; }
    .rf-mark .rf-suits { padding-right: 8px; vertical-align: middle; }
    .rf-mark .rf-word { text-align: left; vertical-align: middle; line-height: 1; }
    .rf-mark .rf-line1 {
        font-size: 7pt;
        font-weight: 700;
        color: #94a3b8;
        letter-spacing: 0.28em;
        text-transform: uppercase;
    }
    .rf-mark .rf-line2 {
        font-size: 11pt;
        font-weight: 900;
        color: #f8fafc;
        letter-spacing: 0.12em;
        margin-top: 2px;
    }

    /* ─── Footer ─── */
    .pdf-footer {
        margin-top: 16px;
        padding-top: 8px;
        border-top: 1px solid #1e293b;
        text-align: center;
        font-size: 6.5pt;
        color: #64748b;
        letter-spacing: 0.16em;
        text-transform: uppercase;
    }
    .pdf-footer strong { color: #f8fafc; font-weight: 800; letter-spacing: 0.2em; }
    .pdf-footer .sep { color: #31486d; margin: 0 8px; }

    /* ─── Section headings ─── */
    .section-title {
        font-size: 8pt;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.2em;
        color: #f8fafc;
        margin: 14px 0 8px;
        padding-bottom: 5px;
        border-bottom: 1px solid #1e293b;
    }
    .section-title .accent {
        display: inline-block;
        width: 10px;
        height: 8px;
        background: #ff2b2b;
        border-radius: 1px;
        margin-right: 8px;
        vertical-align: -1px;
        font-size: 0;
        line-height: 0;
        color: transparent;
        overflow: hidden;
    }
    .section-title .accent * { display: none; }
    .section-title .muted {
        font-weight: 500;
        color: #94a3b8;
        font-size: 7.5pt;
        letter-spacing: 0.04em;
        text-transform: none;
        margin-left: 10px;
    }

    /* ─── Generic table utilities ─── */
    .tbl { width: 100%; border-collapse: collapse; }
    .tbl th, .tbl td { padding: 5px 8px; font-size: 8pt; color: #cbd5e1; }
    .tbl thead th {
        background: #243757;
        color: #f8fafc;
        font-weight: 700;
        text-align: left;
        border-bottom: 1px solid #31486d;
        font-size: 6.5pt;
        letter-spacing: 0.16em;
        text-transform: uppercase;
    }
    .tbl tbody tr { border-bottom: 1px solid #1e293b; }
    .tbl tbody tr:nth-child(even) td { background: #0c1a33; }
    .tbl .num { text-align: right; font-variant-numeric: tabular-nums; }

    /* Status pills */
    .pill-hit  { background: rgba(34,197,94,0.16);  color: #22c55e; font-weight: 700; font-size: 7.5pt; padding: 1px 6px; border-radius: 3px; }
    .pill-miss { background: rgba(239,68,68,0.16);  color: #ef4444; font-weight: 600; font-size: 7.5pt; padding: 1px 6px; border-radius: 3px; }
    .pill-none { background: rgba(148,163,184,0.08); color: #94a3b8; font-size: 7.5pt; padding: 1px 6px; border-radius: 3px; }

    /* Card component */
    .card {
        border: 1px solid #31486d;
        border-radius: 4px;
        padding: 10px 12px;
        background: #0c1a33;
        page-break-inside: avoid;
    }
    .card-head {
        font-size: 6.5pt;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.18em;
        margin-bottom: 5px;
    }

    /* Page controls */
    .page-break { page-break-after: always; }
    .no-break   { page-break-inside: avoid; }

    /* ─── Podium tiles (shared across executive-summary / shooter-report) ───
     *
     * Flat tinted backgrounds + a rail on top. DomPDF renders flat colours
     * reliably; gradients are unreliable across engines. The rail colour
     * gives the podium signal even when a reader is colourblind or the
     * file is printed B&W. */
    .podium {
        border-collapse: separate;
        border-spacing: 8px 0;
        width: 100%;
        margin-top: 10px;
    }
    .podium td {
        padding: 14px 14px 12px;
        vertical-align: top;
        border: 1px solid #31486d;
        border-radius: 5px;
        background: #0c1a33;
    }
    .podium td.p1 {
        background: rgba(251,191,36,0.10);
        border-color: rgba(251,191,36,0.35);
        border-top: 2px solid #d97706;
        width: 36%;
    }
    .podium td.p2 {
        background: rgba(203,213,225,0.08);
        border-color: rgba(203,213,225,0.28);
        border-top: 2px solid #64748b;
        width: 32%;
    }
    .podium td.p3 {
        background: rgba(251,146,60,0.10);
        border-color: rgba(251,146,60,0.32);
        border-top: 2px solid #c2410c;
        width: 32%;
    }
    .podium .rk {
        font-size: 6.5pt;
        font-weight: 800;
        letter-spacing: 0.28em;
        text-transform: uppercase;
    }
    .podium .p1 .rk { color: #fbbf24; }
    .podium .p2 .rk { color: #cbd5e1; }
    .podium .p3 .rk { color: #fb923c; }
    .podium .nm {
        font-size: 11pt;
        font-weight: 800;
        color: #f8fafc;
        margin-top: 4px;
        line-height: 1.2;
        letter-spacing: -0.01em;
    }
    .podium .cal {
        font-size: 7.5pt;
        color: #94a3b8;
        margin-top: 2px;
        letter-spacing: 0.02em;
    }
    .podium .sc {
        margin-top: 8px;
        font-size: 18pt;
        font-weight: 800;
        color: #f8fafc;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
        line-height: 1;
    }
    .podium .p1 .sc { color: #fbbf24; }
    .podium .sub {
        font-size: 7pt;
        color: #94a3b8;
        margin-top: 4px;
        letter-spacing: 0.04em;
    }
</style>
