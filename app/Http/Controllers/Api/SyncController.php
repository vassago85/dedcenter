<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ElrShot;
use App\Models\PrsShotScore;
use App\Models\PrsStageResult;
use App\Models\Score;
use App\Models\ShootingMatch;
use App\Models\StageTime;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function scores(Request $request, ShootingMatch $match)
    {
        $since = $request->query('since');

        $scores = Score::whereHas('shooter', fn ($q) => $q->whereHas('squad', fn ($sq) => $sq->where('match_id', $match->id)));
        $stageTimes = StageTime::whereHas('shooter', fn ($q) => $q->whereHas('squad', fn ($sq) => $sq->where('match_id', $match->id)));

        if ($since) {
            $scores = $scores->where('updated_at', '>', $since);
            $stageTimes = $stageTimes->where('updated_at', '>', $since);
        }

        $scoreData = $scores->get()->map(fn ($s) => [
            'id' => $s->id,
            'shooter_id' => $s->shooter_id,
            'gong_id' => $s->gong_id,
            'is_hit' => (bool) $s->is_hit,
            'device_id' => $s->device_id,
            'recorded_at' => $s->recorded_at?->toIso8601String(),
            'updated_at' => $s->updated_at?->toIso8601String(),
        ]);

        $stageTimeData = $stageTimes->get()->map(fn ($st) => [
            'id' => $st->id,
            'shooter_id' => $st->shooter_id,
            'target_set_id' => $st->target_set_id,
            'time_seconds' => (float) $st->time_seconds,
            'device_id' => $st->device_id,
            'recorded_at' => $st->recorded_at?->toIso8601String(),
            'updated_at' => $st->updated_at?->toIso8601String(),
        ]);

        $elrShots = collect();
        if ($match->isElr()) {
            $query = ElrShot::whereHas('shooter', fn ($q) => $q->whereHas('squad', fn ($sq) => $sq->where('match_id', $match->id)));
            if ($since) $query = $query->where('updated_at', '>', $since);
            $elrShots = $query->get()->map(fn ($s) => [
                'id' => $s->id,
                'shooter_id' => $s->shooter_id,
                'elr_target_id' => $s->elr_target_id,
                'shot_number' => $s->shot_number,
                'result' => $s->result instanceof \BackedEnum ? $s->result->value : $s->result,
                'points_awarded' => (float) $s->points_awarded,
                'device_id' => $s->device_id,
                'recorded_at' => $s->recorded_at?->toIso8601String(),
                'updated_at' => $s->updated_at?->toIso8601String(),
            ]);
        }

        $prsShots = collect();
        $prsResults = collect();
        if ($match->isPrs()) {
            $shotQuery = PrsShotScore::where('match_id', $match->id);
            $resultQuery = PrsStageResult::where('match_id', $match->id);
            if ($since) {
                $shotQuery = $shotQuery->where('updated_at', '>', $since);
                $resultQuery = $resultQuery->where('updated_at', '>', $since);
            }
            $prsShots = $shotQuery->get()->map(fn ($s) => [
                'id' => $s->id,
                'shooter_id' => $s->shooter_id,
                'stage_id' => $s->stage_id,
                'shot_number' => $s->shot_number,
                'result' => $s->result instanceof \BackedEnum ? $s->result->value : $s->result,
                'updated_at' => $s->updated_at?->toIso8601String(),
            ]);
            $prsResults = $resultQuery->get()->map(fn ($r) => [
                'id' => $r->id,
                'shooter_id' => $r->shooter_id,
                'stage_id' => $r->stage_id,
                'hits' => $r->hits,
                'misses' => $r->misses,
                'not_taken' => $r->not_taken,
                'raw_time_seconds' => $r->raw_time_seconds ? (float) $r->raw_time_seconds : null,
                'official_time_seconds' => $r->official_time_seconds ? (float) $r->official_time_seconds : null,
                'updated_at' => $r->updated_at?->toIso8601String(),
            ]);
        }

        return response()->json([
            'scores' => $scoreData,
            'stage_times' => $stageTimeData,
            'elr_shots' => $elrShots,
            'prs_shots' => $prsShots,
            'prs_results' => $prsResults,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
