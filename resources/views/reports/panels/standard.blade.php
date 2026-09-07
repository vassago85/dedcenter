{{--
    Standard-scoring Reports panel.
    ─────────────────────────────────
    The baseline report catalogue: standings + per-stage detail in both
    CSV and PDF form, plus the Full Match Report / Executive Summary
    (currently the same document — decoupled in Phase 2).

    Consumes an `$urls` array from the parent Volt page:
      $urls['csv']['standings']    ─ Standings CSV
      $urls['csv']['detailed']     ─ Full per-stage results CSV
      $urls['pdf']['standings']    ─ Standings PDF
      $urls['pdf']['detailed']     ─ Per-stage detail PDF
      $urls['pdf']['postMatch']    ─ Full Match Report PDF (post-match narrative)
      $urls['pdf']['executiveSummary'] ─ Executive summary PDF (currently same file)

    Ordered "prize-giving first": the printable prize-table document
    (Standings PDF) is the leftmost tile in the PDF row; sponsor / board
    pack (Executive Summary) sits on the right.
--}}

<section class="mt-4 rounded-2xl border border-border bg-surface p-5 sm:p-6">
    <x-reports-section-header
        title="CSV downloads"
        description="Spreadsheets for season standings, archive, post-event analysis."
        icon="file-down"
    />

    <div class="grid gap-2 sm:grid-cols-2">
        <x-reports-download-tile
            :href="$urls['csv']['standings']"
            title="Standings"
            subtitle="final placements only"
            icon="download"
            variant="zinc"
            :external="false"
        />
        <x-reports-download-tile
            :href="$urls['csv']['detailed']"
            title="Full results"
            subtitle="per-stage / per-gong breakdown"
            icon="download"
            variant="zinc"
            :external="false"
        />
    </div>
</section>

<section class="mt-4 rounded-2xl border border-border bg-surface p-5 sm:p-6">
    <x-reports-section-header
        title="PDF downloads"
        description="Print-ready PDFs for the prize-giving table, sponsor packs, and post-match comms."
        icon="file-text"
    />

    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <x-reports-download-tile
            :href="$urls['pdf']['standings']"
            title="Standings PDF"
            subtitle="single-page leaderboard"
            icon="file-text"
            variant="amber"
        />
        <x-reports-download-tile
            :href="$urls['pdf']['detailed']"
            title="Detailed PDF"
            subtitle="per-stage detail grid"
            icon="file-text"
        />
        <x-reports-download-tile
            :href="$urls['pdf']['postMatch']"
            title="Post-match narrative"
            subtitle="storytelling format"
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
