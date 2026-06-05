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

        // Full roster on every pull (it's small) so devices that imported the
        // match earlier converge on walk-ins added later, re-squads, division
        // assignments, and status/DQ changes — without a destructive
        // re-import. The device upserts by the cloud shooter id, so this is
        // idempotent. Not `since`-filtered on purpose: a walk-in added between
        // two device pulls must not be skipped just because the cursor moved.
        $match->loadMissing([
            'squads' => fn ($q) => $q->orderBy('sort_order'),
            'squads.shooters' => fn ($q) => $q->orderBy('sort_order'),
            'squads.shooters.division',
            'squads.shooters.team',
            'squads.shooters.categories',
        ]);

        $squadData = $match->squads->map(fn ($sq) => [
            'id' => $sq->id,
            'name' => $sq->name,
            'sort_order' => $sq->sort_order,
        ])->values();

        $shooterData = $match->squads->flatMap(fn ($sq) => $sq->shooters->map(fn ($sh) => [
            'id' => $sh->id,
            'squad_id' => $sq->id,
            'name' => $sh->name,
            'bib_number' => $sh->bib_number,
            'sort_order' => $sh->sort_order,
            'division_id' => $sh->match_division_id,
            // Division name doubles as the cross-device key — the offline DB
            // has no cloud id for divisions, so the app maps by name.
            'division' => $sh->division?->name,
            'team' => $sh->team?->name,
            'category_ids' => $sh->categories->pluck('id')->values(),
            'status' => $sh->status ?? 'active',
            'updated_at' => $sh->updated_at?->toIso8601String(),
        ]))->values();

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
                // impact_number drives the per-target impact/multiplier grid in
                // team gong-sequence scoring; without it pulled team shots can't
                // reconstruct which multiplier slot each hit occupies.
                'impact_number' => $s->impact_number,
                'result' => $s->result instanceof \BackedEnum ? $s->result->value : $s->result,
                'points_awarded' => (float) $s->points_awarded,
                'distance_at_score' => $s->distance_at_score,
                'multiplier_at_score' => $s->multiplier_at_score !== null ? (float) $s->multiplier_at_score : null,
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
                'device_id' => $s->device_id,
                'recorded_at' => $s->recorded_at?->toIso8601String(),
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
            // Volatile match-level flags so a device that imported the match
            // earlier learns when the MD enables the side-bet pot / royal flush
            // on the web — otherwise the local toggle would keep returning 422
            // and those screens stay unusable on every device but the host.
            'side_bet_enabled' => (bool) $match->side_bet_enabled,
            'royal_flush_enabled' => (bool) $match->royal_flush_enabled,
            'squads' => $squadData,
            'shooters' => $shooterData,
            'scores' => $scoreData,
            'stage_times' => $stageTimeData,
            'elr_shots' => $elrShots,
            'prs_shots' => $prsShots,
            'prs_results' => $prsResults,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
