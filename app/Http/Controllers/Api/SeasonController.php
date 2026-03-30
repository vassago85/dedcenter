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

    public function standings(Season $season)
    {
        $service = new SeasonStandingsService();
        $standings = $service->calculate($season);

        return response()->json([
            'season' => [
                'id' => $season->id,
                'name' => $season->name,
                'year' => $season->year,
                'start_date' => $season->start_date?->toDateString(),
                'end_date' => $season->end_date?->toDateString(),
            ],
            'standings' => $standings,
        ]);
    }
}
