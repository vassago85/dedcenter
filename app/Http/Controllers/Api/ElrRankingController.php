<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShootingMatch;
use App\Services\Scoring\ElrRankingService;
use Illuminate\Http\Request;

/**
 * Read-only ELR ranking endpoint. Public like the scoreboard, but respects the
 * same scores-published gate so non-staff can't peek at unpublished results.
 */
class ElrRankingController extends Controller
{
    public function show(Request $request, ShootingMatch $match, ElrRankingService $service)
    {
        if (! $match->isElr()) {
            abort(404);
        }

        if (! $match->scoresArePublic()) {
            $user = $request->user();
            $canView = $user && ($user->isOwner() || $match->created_by === $user->id
                || ($match->organization && $user->isOrgRangeOfficer($match->organization)));

            if (! $canView) {
                return response()->json([
                    'match' => [
                        'id' => $match->id,
                        'name' => $match->name,
                        'scoring_type' => $match->scoring_type ?? 'standard',
                        'scores_published' => false,
                    ],
                    'message' => 'Scores for this match have not been published yet.',
                    'stages' => [],
                    'overall' => [],
                    'teams' => [],
                    'divisions' => [],
                ]);
            }
        }

        return response()->json($service->build($match));
    }
}
