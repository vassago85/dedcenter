<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ElrShot;
use App\Models\MatchRegistration;
use App\Models\PrsShotScore;
use App\Models\PrsStageResult;
use App\Models\Score;
use App\Models\ShootingMatch;
use App\Models\StageTime;
use App\Services\ShooterRosterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;

class SyncController extends Controller
{
    public function scores(Request $request, ShootingMatch $match)
    {
        // The sync pull returns the full roster plus every raw (unpublished)
        // score/stage-time for the match — device-to-cloud convergence data
        // that must not be readable by an arbitrary authenticated Sanctum
        // token. Restrict to match staff (score bar).
        abort_unless($request->user()->can('score', $match), 403, 'You are not authorized to sync this match.');

        $validated = $request->validate(['since' => ['nullable', 'date']]);

        // Devices echo server_time back as `since`. Stamp it before reading and
        // compare with >= so a score written mid-pull is in the next pull, not
        // lost; boundary rows come back twice and devices upsert them by id.
        $pulledAt = now();
        $since = isset($validated['since'])
            ? Carbon::parse($validated['since'])->setTimezone(config('app.timezone'))
            : null;

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

        $shooterIds = $match->squads->flatMap->shooters->pluck('id');

        $scores = Score::whereIn('shooter_id', $shooterIds);
        $stageTimes = StageTime::whereIn('shooter_id', $shooterIds);

        if ($since) {
            $scores = $scores->where('updated_at', '>=', $since);
            $stageTimes = $stageTimes->where('updated_at', '>=', $since);
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

        $squadData = $match->squads->map(fn ($sq) => [
            'id' => $sq->id,
            'name' => $sq->name,
            'sort_order' => $sq->sort_order,
        ])->values();

        $calibers = MatchRegistration::query()
            ->where('match_id', $match->id)
            ->whereNotNull('user_id')
            ->pluck('caliber', 'user_id');

        $shooterData = $match->squads->flatMap(fn ($sq) => $sq->shooters->map(fn ($sh) => [
            'id' => $sh->id,
            'squad_id' => $sq->id,
            'name' => $sh->name,
            'bib_number' => $sh->bib_number,
            'sort_order' => $sh->sort_order,
            'division_id' => $sh->match_division_id,
            'caliber' => ShooterRosterService::caliberFor(
                $sh,
                $sh->user_id ? ($calibers[$sh->user_id] ?? null) : null,
            ),
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
        if ($match->usesElrPipeline()) {
            $query = ElrShot::whereIn('shooter_id', $shooterIds);
            if ($since) $query = $query->where('updated_at', '>=', $since);
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
                $shotQuery = $shotQuery->where('updated_at', '>=', $since);
                $resultQuery = $resultQuery->where('updated_at', '>=', $since);
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
            'server_time' => $pulledAt->toIso8601String(),
        ]);
    }

    /**
     * Offline tablets push cartridge, division, team, and squad edits here
     * before they pull, so a later pull cannot put the old roster back.
     */
    public function pushRoster(Request $request, ShootingMatch $match): JsonResponse
    {
        abort_unless($request->user()?->can('squad', $match), 403, 'You are not authorized to manage shooters in this match.');

        $validated = $request->validate([
            'shooters' => ['required', 'array', 'min:1'],
            'shooters.*.id' => ['required', 'integer'],
            'shooters.*.caliber' => ['nullable', 'string', 'max:64'],
            'shooters.*.division' => ['nullable', 'string', 'max:255'],
            'shooters.*.team' => ['nullable', 'string', 'max:255'],
            'shooters.*.squad_id' => ['nullable', 'integer'],
        ]);

        $service = app(ShooterRosterService::class);
        $divisions = $match->divisions()->get(['id', 'name'])->keyBy(fn ($division) => mb_strtolower($division->name));
        $updated = 0;

        foreach ($validated['shooters'] as $row) {
            $shooter = $match->shooters()->whereKey($row['id'])->first();
            if (! $shooter) {
                continue;
            }

            $changes = [
                'caliber' => $row['caliber'] ?? null,
                'team' => $row['team'] ?? null,
            ];

            $divisionName = trim((string) ($row['division'] ?? ''));
            if ($divisionName === '') {
                $changes['division_id'] = null;
            } else {
                $divisionId = $divisions->get(mb_strtolower($divisionName))?->id;
                if ($divisionId !== null) {
                    $changes['division_id'] = $divisionId;
                }
            }

            if (! empty($row['squad_id'])) {
                $changes['squad_id'] = (int) $row['squad_id'];
            }

            try {
                $service->update($match, $shooter, $changes);
                $updated++;
            } catch (RuntimeException) {
                continue;
            }
        }

        return response()->json(['updated' => $updated]);
    }
}
