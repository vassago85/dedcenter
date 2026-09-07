{{--
    Royal Flush Reports panel.
    ─────────────────────────────────
    Standard scoring + Royal Flush overlay. The RF-specific report gets
    top billing because it's the headline artefact for these matches
    (per-shot A4 grid, magazine layout, printed for the prize table);
    everything else follows the Standard catalogue.

    Consumes $urls (see reports.panels.standard) plus:
      $urls['reports']['royalFlushHtml'] ─ Royal Flush HTML report (rendered in the browser)
      $urls['csv']['rfShots']            ─ Per-shot RF CSV
--}}

<section class="mt-4 rounded-2xl border border-amber-400/40 bg-amber-500/5 p-5 sm:p-6">
    <x-reports-section-header
        title="Royal Flush report"
        description="A4 portrait, per-shot hit/miss grid grouped by distance — the headline printout for Royal Flush prize-giving."
        icon="crown"
    />

    <div class="grid gap-2 sm:grid-cols-2">
        <x-reports-download-tile
            :href="$urls['reports']['royalFlushHtml']"
            title="Open Royal Flush report"
            subtitle="magazine-style HTML view"
            icon="external-link"
            variant="amber"
        />
        <x-reports-download-tile
            :href="$urls['csv']['rfShots']"
            title="RF shots CSV"
            subtitle="one row per shooter, one column per shot"
            icon="download"
            variant="amber"
            :external="false"
        />
    </div>
</section>

{{-- Base standings/detail catalogue --}}
@include('reports.panels.standard', ['urls' => $urls])
