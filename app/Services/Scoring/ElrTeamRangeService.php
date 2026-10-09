<?php

namespace App\Services\Scoring;

use App\Models\ElrStage;
use App\Models\MatchDivision;
use Illuminate\Support\Facades\DB;

/**
 * Translates the admin-facing per-stage, per-division gong ranges
 * (elr_stage_division_ranges) into the elr_division_targets pivot that the
 * sequence engine, scoring service, and offline clients already consume.
 *
 * Gong numbers are 1-based ordinals within a stage (by target sort order).
 * Ranges are inclusive: gong_start..gong_end. Two divisions may overlap.
 */
class ElrTeamRangeService
{
    /**
     * Upsert a division's gong range for a stage, then re-materialise the
     * pivot rows that belong to this stage's targets. Passing a null/empty
     * range removes the division's configuration for the stage.
     */
    public function saveRange(ElrStage $stage, int $divisionId, ?int $start, ?int $end): void
    {
        if ($start === null || $end === null) {
            $stage->divisionRanges()->where('match_division_id', $divisionId)->delete();
            // Drop the division's whitelist rows for this stage; materializeStage
            // only rebuilds divisions that still have a range row.
            $division = MatchDivision::find($divisionId);
            $division?->elrTargets()->detach($stage->targets()->pluck('id')->all());
        } else {
            [$start, $end] = [min($start, $end), max($start, $end)];
            $stage->divisionRanges()->updateOrCreate(
                ['match_division_id' => $divisionId],
                ['gong_start' => $start, 'gong_end' => $end],
            );
        }

        $this->materializeStage($stage->fresh('divisionRanges'));
    }

    /**
     * Rebuild elr_division_targets rows for every target in this stage from
     * the configured ranges. Only this stage's targets are touched, so ranges
     * on other stages for the same division are preserved.
     */
    public function materializeStage(ElrStage $stage): void
    {
        $targets = $stage->targets()->orderBy('sort_order')->get()->values();
        $stageTargetIds = $targets->pluck('id')->all();

        if (empty($stageTargetIds)) {
            return;
        }

        $ranges = $stage->divisionRanges()->get();
        $divisions = MatchDivision::whereIn('id', $ranges->pluck('match_division_id'))->get()->keyBy('id');

        DB::transaction(function () use ($ranges, $divisions, $targets, $stageTargetIds) {
            foreach ($ranges as $range) {
                $division = $divisions->get($range->match_division_id);
                if (! $division) {
                    continue;
                }

                $inRange = [];
                foreach ($targets as $index => $target) {
                    $gong = $index + 1;
                    if ($gong >= $range->gong_start && $gong <= $range->gong_end) {
                        $inRange[] = $target->id;
                    }
                }

                // Replace only this stage's slice of the division's whitelist.
                $division->elrTargets()->detach($stageTargetIds);
                if (! empty($inRange)) {
                    $division->elrTargets()->attach($inRange);
                }
            }
        });
    }
}
