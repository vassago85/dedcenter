<?php

namespace App\Http\Controllers;

use App\Enums\PlacementKey;
use App\Models\MatchBook;
use App\Models\ShootingMatch;
use App\Services\PdfDocumentRenderer;
use App\Services\SponsorPlacementResolver;
use Illuminate\Support\Str;

class MatchBookController extends Controller
{
    /**
     * Match book hub URL: redirect to the Volt editor (creates draft book on first visit).
     */
    public function show(ShootingMatch $match)
    {
        if (request()->routeIs('org.*')) {
            return redirect()->route('org.matches.matchbook.edit', [
                'organization' => $match->organization,
                'match' => $match,
            ]);
        }

        return redirect()->route('admin.matches.matchbook.edit', [
            'match' => $match,
        ]);
    }

    /**
     * Browser preview (print-friendly HTML).
     */
    public function preview(ShootingMatch $match)
    {
        $matchBook = $match->matchBook;
        abort_unless($matchBook, 404, 'No match book exists for this match.');

        $matchBook->load(['locations', 'stages.shots']);

        return view('matchbook.preview', $this->bookData($match, $matchBook));
    }

    /**
     * Download match book as PDF.
     */
    public function download(ShootingMatch $match, PdfDocumentRenderer $renderer)
    {
        $matchBook = $match->matchBook;
        abort_unless($matchBook, 404, 'No match book exists for this match.');

        $matchBook->load(['locations', 'stages.shots']);

        $filename = Str::slug($match->name).'_MatchBook.pdf';

        return $renderer->stream('matchbook.pdf', $this->bookData($match, $matchBook), $filename);
    }

    /**
     * Debug: show raw HTML for the PDF template.
     */
    public function htmlPreview(ShootingMatch $match)
    {
        $matchBook = $match->matchBook;
        abort_unless($matchBook, 404);
        $matchBook->load(['locations', 'stages.shots']);

        return view('matchbook.pdf', $this->bookData($match, $matchBook));
    }

    /**
     * Assemble all data needed for match book rendering.
     */
    protected function bookData(ShootingMatch $match, MatchBook $matchBook): array
    {
        $match->load(['organization', 'targetSets.gongs']);

        $allShots = $matchBook->stages->flatMap->shots;
        $matchStats = [
            'total_stages' => $matchBook->stages->count(),
            'total_shots' => $allShots->count(),
            'total_rounds' => $matchBook->stages->sum('round_count'),
            'total_time' => $matchBook->stages->sum('time_limit'),
            'total_positions' => $matchBook->stages->sum(fn ($s) => $s->uniquePositionCount()),
            'min_distance' => $allShots->count() > 0 ? $allShots->min('distance_m') : 0,
            'max_distance' => $allShots->count() > 0 ? $allShots->max('distance_m') : 0,
            'min_size' => $allShots->where('size_mm', '>', 0)->count() > 0 ? $allShots->where('size_mm', '>', 0)->min('size_mm') : 0,
            'max_size' => $allShots->where('size_mm', '>', 0)->count() > 0 ? $allShots->where('size_mm', '>', 0)->max('size_mm') : 0,
        ];

        $difficultyService = app(\App\Services\MatchBook\StageDifficultyService::class);
        $matchDifficulty = $difficultyService->calculateMatchDifficulty($matchBook);

        $resolver = app(SponsorPlacementResolver::class);
        $sponsorAssignment = $resolver->resolve(PlacementKey::MatchbookCover, $match->id, $matchBook->id);

        return [
            'match' => $match,
            'matchBook' => $matchBook,
            'matchStats' => $matchStats,
            'matchDifficulty' => $matchDifficulty,
            'sponsorAssignment' => $sponsorAssignment,
        ];
    }
}
