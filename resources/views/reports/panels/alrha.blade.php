{{--
    ALRHA Reports panel.
    ─────────────────────────────────
    ALRHA-specific catalogue. The Standings PDF is wired to the ALRHA
    per-class data builder in an earlier commit — it now prints per-class
    prize tables (Hunters + Varmint) instead of an empty gong grid.

    The dedicated "Prize Book PDF" (all prize sections in one document
    matching the printed programme — Hunter Team → Hunter Individual →
    Hunter Junior → Varmint Open → Varmint Ladies → Varmint Junior → CBC
    per class) is scoped to Phase 3. Flagged here so the MD sees the
    plan.

    CBC-only tile is also a Phase 3 add — the CBC prize table currently
    lives inside the ALRHA scoreboard's Cold Bore tab but not as a
    standalone PDF for the prize-giving table.

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
            subtitle="final placements (all classes combined)"
            icon="download"
            variant="zinc"
            :external="false"
        />
        <x-reports-download-tile
            :href="$urls['csv']['detailed']"
            title="Full results"
            subtitle="per-shooter per-target per-shot"
            icon="download"
            variant="zinc"
            :external="false"
        />
    </div>
</section>

<section class="mt-4 rounded-2xl border border-border bg-surface p-5 sm:p-6">
    <x-reports-section-header
        title="PDF downloads"
        description="Print-ready PDFs for the prize table."
        icon="file-text"
    />

    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <x-reports-download-tile
            :href="$urls['pdf']['standings']"
            title="Standings PDF"
            subtitle="per-class prize tables"
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
            subtitle="podium, class breakdown, CBC"
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
        Coming next: <strong class="text-secondary">ALRHA Prize Book PDF</strong> — all prize sections in the
        printed-programme order (Hunter Team → Hunter Individual → Hunter Junior → Varmint Open → Varmint Ladies →
        Varmint Junior → CBC per class), plus a standalone Cold Bore Challenge PDF for the season-long CBC leaderboard.
    </p>
</section>
