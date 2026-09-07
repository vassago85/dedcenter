{{--
    ELR Reports panel.
    ─────────────────────────────────
    ELR-specific catalogue. The Rankings PDF is the headline deliverable
    (podium, per-stage normalized scores, teams, divisions) and the
    Rankings CSV is what season standings services consume — both get
    prominent placement.

    The 1/0 shots template is a match-day sheet: print, score by hand,
    scan back in. Sits with the CSVs because that's what it is.

    Standard "Standings CSV / PDF" tiles are omitted — ELR doesn't
    populate the `scores` table those exports read from, so they'd
    render empty. Detailed PDF is dropped for the same reason (ELR's
    per-stage view lives inside the Rankings PDF).

    Consumes:
      $urls['elr']['rankingsOverall']    ─ CSV, overall view
      $urls['elr']['rankingsTeams']      ─ CSV, teams view
      $urls['elr']['rankingsDivisions']  ─ CSV, divisions view
      $urls['elr']['shotsTemplate']      ─ CSV, 1/0 fill-in template
      $urls['elr']['pdfRankings']        ─ PDF, print-ready rankings
      $urls['pdf']['postMatch']          ─ Full Match Report PDF
      $urls['pdf']['executiveSummary']   ─ Executive summary PDF
--}}

<section class="mt-4 rounded-2xl border border-accent/40 bg-accent/5 p-5 sm:p-6">
    <x-reports-section-header
        title="ELR rankings"
        description="Normalized podium, per-stage points, and division splits — the definitive ELR result document."
        icon="trophy"
    />

    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        <x-reports-download-tile
            :href="$urls['elr']['pdfRankings']"
            title="Rankings PDF"
            subtitle="print-ready, prize-table format"
            icon="file-text"
            variant="accent"
        />
        <x-reports-download-tile
            :href="$urls['elr']['rankingsOverall']"
            title="Rankings CSV — overall"
            subtitle="one row per shooter"
            icon="download"
            variant="zinc"
            :external="false"
        />
        <x-reports-download-tile
            :href="$urls['elr']['shotsTemplate']"
            title="Shots template CSV"
            subtitle="1/0 fill-in sheet"
            icon="download"
            variant="zinc"
            :external="false"
        />
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px]">
        <span class="uppercase tracking-wider text-muted">More CSVs:</span>
        <a href="{{ $urls['elr']['rankingsTeams'] }}" class="text-accent hover:underline">Teams CSV</a>
        <span class="text-muted">·</span>
        <a href="{{ $urls['elr']['rankingsDivisions'] }}" class="text-accent hover:underline">Divisions CSV</a>
    </div>
</section>

<section class="mt-4 rounded-2xl border border-border bg-surface p-5 sm:p-6">
    <x-reports-section-header
        title="Post-match PDFs"
        description="Long-form narrative and sponsor pack."
        icon="file-text"
    />

    <div class="grid gap-2 sm:grid-cols-2">
        <x-reports-download-tile
            :href="$urls['pdf']['postMatch']"
            title="Full match report"
            subtitle="ELR podium + per-stage heatmap"
            icon="file-text"
            variant="accent"
        />
        <x-reports-download-tile
            :href="$urls['pdf']['executiveSummary']"
            title="Executive summary"
            subtitle="sponsor / board pack"
            icon="file-text"
            variant="emerald"
        />
    </div>
</section>
