{{-- Shared PDF styles — used by executive-summary and shooter-report. --}}
<style>
    /*
     * DeadCenter PDF design system
     * ───────────────────────────
     *  ink-900  #0b1220   deep navy (headers, darkest surfaces)
     *  ink-800  #121a2b   slightly softer navy
     *  ink-700  #1e293b   elevated navy
     *  ink-600  #334155   body strong
     *  ink-500  #475569   secondary text
     *  ink-400  #64748b   muted
     *  ink-300  #94a3b8   softest label
     *  ink-200  #cbd5e1   subtle divider strong
     *  ink-150  #dbe2ec   soft divider
     *  ink-100  #e8edf4   faint divider (primary border)
     *  ink-50   #f5f7fa   subtle surface
     *  ink-25   #fbfcfd   barely-there surface (zebra)
     *  red-700  #b91c1c   deep red (rare)
     *  red      #dc2626   brand red (restrained)
     *  red-brand #e10600  deadcenter signature red (accent only)
     *  gold     #b45309   podium gold (restrained)
     *  gold-100 #fef7e0   podium gold tint
     *  silver   #475569   podium silver
     *  silver-100 #f1f4f9 podium silver tint
     *  bronze   #9a3412   podium bronze
     *  bronze-100 #fef2e7 podium bronze tint
     *  green    #15803d   hit
     *  green-tint #e7f6ec hit tint
     */

    /* ─── Reset + Base ─── */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body {
        font-family: 'DejaVu Sans', Arial, sans-serif;
        color: #0b1220;
        font-size: 9.5pt;
        line-height: 1.4;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    body { background: #ffffff; }

    /* ─── Header strip ─── */
    .pdf-header {
        background: #0b1220;
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
        color: #e10600;
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
    .brand-center .match-meta .dot { margin: 0 6px; color: #334155; }

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
        border-top: 1px solid #e8edf4;
        text-align: center;
        font-size: 6.5pt;
        color: #94a3b8;
        letter-spacing: 0.16em;
        text-transform: uppercase;
    }
    .pdf-footer strong { color: #0b1220; font-weight: 800; letter-spacing: 0.2em; }
    .pdf-footer .sep { color: #cbd5e1; margin: 0 8px; }

    /* ─── Section headings ─── */
    .section-title {
        font-size: 8pt;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.2em;
        color: #0b1220;
        margin: 14px 0 8px;
        padding-bottom: 5px;
        border-bottom: 1px solid #e8edf4;
    }
    /*
     * Section-title accent — rendered as a CSS bar (not a glyph) so it is
     * portable across DomPDF / Gotenberg / Chrome print without font fallback.
     * Any child text inside .accent is hidden to be safe.
     */
    .section-title .accent {
        display: inline-block;
        width: 10px;
        height: 8px;
        background: #e10600;
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
    .tbl th, .tbl td { padding: 5px 8px; font-size: 8pt; }
    .tbl thead th {
        background: #f5f7fa;
        color: #334155;
        font-weight: 700;
        text-align: left;
        border-bottom: 1px solid #e8edf4;
        font-size: 6.5pt;
        letter-spacing: 0.16em;
        text-transform: uppercase;
    }
    .tbl tbody tr:nth-child(even) td { background: #fbfcfd; }
    .tbl .num { text-align: right; font-variant-numeric: tabular-nums; }

    /* Status pills */
    .pill-hit  { background: #e7f6ec; color: #15803d; font-weight: 700; font-size: 7.5pt; padding: 1px 6px; border-radius: 3px; }
    .pill-miss { background: #fce8e8; color: #b91c1c; font-weight: 600; font-size: 7.5pt; padding: 1px 6px; border-radius: 3px; }
    .pill-none { background: #f5f7fa; color: #94a3b8;          font-size: 7.5pt; padding: 1px 6px; border-radius: 3px; }

    /* Card component */
    .card {
        border: 1px solid #e8edf4;
        border-radius: 4px;
        padding: 10px 12px;
        background: #ffffff;
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

    /* ─── Podium tiles (shared) ─── */
    .podium {
        border-collapse: separate;
        border-spacing: 8px 0;
        width: 100%;
        margin-top: 10px;
    }
    .podium td {
        padding: 14px 14px 12px;
        vertical-align: top;
        border: 1px solid #e8edf4;
        border-radius: 5px;
        background: #ffffff;
    }
    .podium td.p1 {
        background: linear-gradient(180deg, #fef7e0 0%, #ffffff 80%);
        border-color: #f0c674;
        border-top: 2px solid #b45309;
        width: 36%;
    }
    .podium td.p2 {
        background: linear-gradient(180deg, #f1f4f9 0%, #ffffff 80%);
        border-color: #c9d1dd;
        border-top: 2px solid #475569;
        width: 32%;
    }
    .podium td.p3 {
        background: linear-gradient(180deg, #fef2e7 0%, #ffffff 80%);
        border-color: #f0c4a2;
        border-top: 2px solid #9a3412;
        width: 32%;
    }
    .podium .rk {
        font-size: 6.5pt;
        font-weight: 800;
        letter-spacing: 0.28em;
        text-transform: uppercase;
    }
    .podium .p1 .rk { color: #b45309; }
    .podium .p2 .rk { color: #475569; }
    .podium .p3 .rk { color: #9a3412; }
    .podium .nm {
        font-size: 11pt;
        font-weight: 800;
        color: #0b1220;
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
        color: #0b1220;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
        line-height: 1;
    }
    .podium .p1 .sc { color: #b45309; }
    .podium .sub {
        font-size: 7pt;
        color: #94a3b8;
        margin-top: 4px;
        letter-spacing: 0.04em;
    }
</style>
