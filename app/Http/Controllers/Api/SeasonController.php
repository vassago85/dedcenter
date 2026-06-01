<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Services\SeasonStandingsService;
use Illuminate\Http\Request;

class SeasonController extends Controller
{
    public function index()
    {
        $seasons = Season::query()
            ->withCount('matches')
            ->orderByDesc('year')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'year' => $s->year,
                'start_date' => $s->start_date?->toDateString(),
                'end_date' => $s->end_date?->toDateString(),
                'matches_count' => $s->matches_count,
            ]);

        return response()->json(['seasons' => $seasons]);
    }

    public function standings(Season $season, Request $request)
    {
        $service = new SeasonStandingsService();
        $division = $request->query('division');
        $standings = $service->calculate($season, $division);

        // Surface the union of MatchDivision names across this season's
        // matches so the Vue leaderboard can render tabs without a second
        // round-trip. We dedupe by lowercase name so "Minor" and "minor"
        // don't render twice for two different match definitions.
        $divisions = $season->matches()
            ->with('divisions:id,match_id,name')
            ->get()
            ->flatMap(fn ($m) => $m->divisions)
            ->unique(fn ($d) => strtolower($d->name))
            ->values()
            ->map(fn ($d) => ['name' => $d->name])
            ->all();

        $org = $season->organization;
        $bestOf = $org && $org->best_of > 0
            ? (int) $org->best_of
            : SeasonStandingsService::DEFAULT_BEST_OF;

        return response()->json([
            'season' => [
                'id' => $season->id,
                'name' => $season->name,
                'year' => $season->year,
                'start_date' => $season->start_date?->toDateString(),
                'end_date' => $season->end_date?->toDateString(),
                'best_of' => $bestOf,
            ],
            'divisions' => $divisions,
            'active_division' => $division,
            'standings' => $standings,
        ]);
    }
}
