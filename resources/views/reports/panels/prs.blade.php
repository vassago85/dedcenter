{{--
    PRS Reports panel.
    ─────────────────────────────────
    PRS-specific catalogue. Standings + full results are the two main
    outputs, but the "Full match report" delegates to the type-aware
    PRS full report (per-stage stats, tiebreaker, badges) already built
    in earlier work.

    The dedicated PRS score-sheet PDF is called out as a Phase 3 item —
    a partial (`exports.partials.pdf-prs-score-sheet`) already exists
    but no download route is wired to it yet. Adding that endpoint is
    scoped to Phase 3 so this panel refactor stays behaviour-preserving.

    Consumes the standard $urls shape.
--}}

<section class="mt-4 rounded-2xl border border-border bg-surface p-5 sm:p-6">
    <x-reports-section-header
        title="CSV downloads"
        description="Spreadsheets for season standings and post-event analysis."
        icon="file-down"
    />

    <div class="grid gap-2 sm:grid-cols-2">
        <x-reports-download-tile
            :href="$urls['csv']['standings']"
            title="Standings"
            subtitle="final placements + tiebreaker"
            icon="download"
            variant="zinc"
            :external="false"
        />
        <x-reports-download-tile
            :href="$urls['csv']['detailed']"
            title="Full results"
            subtitle="per-stage hit / miss / points"
            icon="download"
            variant="zinc"
            :external="false"
        />
    </div>
</section>

<section class="mt-4 rounded-2xl border border-border bg-surface p-5 sm:p-6">
    <x-reports-section-header
        title="PDF downloads"
        description="Print-ready PDFs for the prize table and shooter debriefs."
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
            title="Full match report"
            subtitle="PRS podium, stage stats, tiebreaker"
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

    <p class="mt-4 rounded-lg border border-dashed border-border bg-surface-2/40 px-3 py-2 text-[11px] text-muted">
        Coming next: dedicated PRS score-sheet PDF (one row per shooter × stage × target with per-shot detail).
    </p>
</section>
