<?php

namespace App\Http\Controllers\Api;

use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Models\Score;
use App\Models\ShootingMatch;
use App\Models\StageTime;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Scoring devices should not be invited to cache finished matches —
        // that was the #1 source of phantom rows in the local Room DB
        // ("old match still showing up on the hub client").
        //
        // Default behaviour (no query param): return only statuses a scoring
        // device can meaningfully work with:
        //   - Ready     (match prepped, tablets can pre-download)
        //   - Active    (match in progress)
        //
        // Admins who legitimately need to re-download a completed match for
        // post-match review can opt back in with `?include_completed=1`.
        $includeCompleted = $request->boolean('include_completed', false);

        $importableStatuses = $includeCompleted
            ? [MatchStatus::Ready, MatchStatus::Active, MatchStatus::Completed]
            : [MatchStatus::Ready, MatchStatus::Active];

        // Visibility is enforced by ShootingMatch::scopeVisibleToScoringUser:
        // creator OR in the owning organisation OR nominated as match staff
        // (platform owners / match directors see everything for support).
        $matches = ShootingMatch::query()
            ->visibleToScoringUser($user)
            ->whereIn('status', $importableStatuses)
            ->orderBy('date')
            ->get();

        return MatchResource::collection($matches);
    }

    public function show(Request $request, ShootingMatch $match)
    {
        $user = $request->user();

        // Prevent direct-access bypass of the index-level visibility filter —
        // without this, anyone with a valid token could fetch any match by id.
        $visible = ShootingMatch::query()
            ->visibleToScoringUser($user)
            ->whereKey($match->id)
            ->exists();

        if (! $visible) {
            abort(403, 'You do not have access to this match.');
        }

        $eagerLoads = [
            'squads' => fn ($q) => $q->orderBy('sort_order'),
            'squads.shooters' => fn ($q) => $q->orderBy('sort_order'),
            'squads.shooters.division',
            'squads.shooters.categories',
            'divisions' => fn ($q) => $q->orderBy('sort_order'),
            'categories' => fn ($q) => $q->orderBy('sort_order'),
        ];

        if ($match->isElr()) {
            $eagerLoads['elrStages'] = fn ($q) => $q->orderBy('sort_order');
            $eagerLoads['elrStages.targets'] = fn ($q) => $q->orderBy('sort_order');
            $eagerLoads['elrStages.scoringProfile'] = fn ($q) => $q;
            $eagerLoads['elrScoringProfile'] = fn ($q) => $q;
        } else {
            $eagerLoads['targetSets'] = fn ($q) => $q->orderBy('sort_order');
            $eagerLoads['targetSets.gongs'] = fn ($q) => $q->orderBy('number');
            if ($match->isPrs()) {
                $eagerLoads['targetSets.stageTargets'] = fn ($q) => $q->orderBy('sequence_number');
                $eagerLoads['targetSets.positions'] = fn ($q) => $q->orderBy('sort_order');
                $eagerLoads['targetSets.shotSequence'] = fn ($q) => $q->with(['position', 'gong'])->orderBy('shot_number');
            }
        }

        if ($match->side_bet_enabled) {
            $eagerLoads[] = 'sideBetShooters';
        }

        $eagerLoads['disqualifications'] = fn ($q) => $q->with('issuedBy:id,name');

        $match->load($eagerLoads);

        $shooterIds = $match->squads->flatMap->shooters->pluck('id');

        if (! $match->isElr()) {
            $scores = Score::whereIn('shooter_id', $shooterIds)->get();
            $match->setRelation('scores', $scores);

            if ($match->isPrs()) {
                $stageTimes = StageTime::whereIn('shooter_id', $shooterIds)->get();
                $match->setRelation('stageTimes', $stageTimes);
            }
            if ($match->isPrs()) {
                $prsResults = \App\Models\PrsStageResult::where('match_id', $match->id)->get();
                $match->setRelation('prsResults', $prsResults);
            }
        }

        return new MatchResource($match);
    }
}
