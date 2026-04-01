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
        $orgIds = $user->organizations()->pluck('organizations.id');

        $matches = ShootingMatch::where('status', MatchStatus::Active)
            ->where(function ($q) use ($user, $orgIds) {
                $q->where('created_by', $user->id)
                  ->orWhereIn('organization_id', $orgIds);
            })
            ->orderBy('date')
            ->get();

        return MatchResource::collection($matches);
    }

    public function show(ShootingMatch $match)
    {
        $eagerLoads = [
            'squads' => fn ($q) => $q->orderBy('sort_order'),
            'squads.shooters' => fn ($q) => $q->active()->orderBy('sort_order'),
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
            }
        }

        if ($match->side_bet_enabled) {
            $eagerLoads[] = 'sideBetShooters';
        }

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
