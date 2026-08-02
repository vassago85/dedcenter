<?php

namespace App\Console\Commands;

use App\Models\PrsStageResult;
use App\Models\ShootingMatch;
use App\Models\TargetSet;
use Illuminate\Console\Command;

/**
 * Recompute PRS `official_time_seconds` from the preserved `raw_time_seconds`
 * using the corrected rule: official = min(raw, par).
 *
 * Background: earlier scoring/correction code bumped official_time to the
 * stage par time for any shooter who didn't hit EVERY gong on the stage
 * (`$hits === total_shots`). That treated a MISS like a time-out, so every
 * shooter who dropped a shot on a timed tiebreaker stage flattened to par
 * (e.g. 105 s) — collapsing the tiebreaker into a mass tie and wiping the
 * real recorded time. The raw time was stored untouched, so we can rebuild
 * the official time correctly.
 *
 * Safe to re-run: only rows whose official time actually differs are written.
 * Only rows with a recorded raw time are considered.
 */
class RecomputePrsOfficialTimes extends Command
{
    protected $signature = 'prs:recompute-times
        {match? : Match ID to fix (omit to process every PRS match)}
        {--dry-run : List the changes without writing them}';

    protected $description = 'Recompute PRS official stage times as min(raw, par) so misses no longer flatten to par (fixes the timed-tiebreaker).';

    public function handle(): int
    {
        $matchIds = $this->argument('match') !== null
            ? [(int) $this->argument('match')]
            : ShootingMatch::where('scoring_type', 'prs')->pluck('id')->all();

        if (empty($matchIds)) {
            $this->warn('No PRS matches found.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $totalChanged = 0;

        foreach ($matchIds as $matchId) {
            $parByStage = TargetSet::where('match_id', $matchId)
                ->pluck('par_time_seconds', 'id');

            $results = PrsStageResult::where('match_id', $matchId)
                ->whereNotNull('raw_time_seconds')
                ->get();

            $changed = 0;
            foreach ($results as $r) {
                $par = $parByStage[$r->stage_id] ?? null;
                $raw = round((float) $r->raw_time_seconds, 2);
                $newOfficial = $par !== null ? min($raw, round((float) $par, 2)) : $raw;
                $oldOfficial = $r->official_time_seconds !== null
                    ? round((float) $r->official_time_seconds, 2)
                    : null;

                if ($oldOfficial === null || abs($oldOfficial - $newOfficial) > 0.001) {
                    $this->line(sprintf(
                        '  match %d · stage %d · shooter %d: %s → %.2f  (raw %.2f, par %s)',
                        $matchId,
                        $r->stage_id,
                        $r->shooter_id,
                        $oldOfficial === null ? 'null' : number_format($oldOfficial, 2),
                        $newOfficial,
                        $raw,
                        $par !== null ? number_format((float) $par, 2) : '—',
                    ));

                    if (! $dry) {
                        $r->official_time_seconds = $newOfficial;
                        $r->save();
                    }

                    $changed++;
                }
            }

            if ($changed > 0) {
                $this->info(sprintf(
                    'Match %d: %d stage result(s) %s.',
                    $matchId,
                    $changed,
                    $dry ? 'would change' : 'updated',
                ));
            }

            $totalChanged += $changed;
        }

        $this->info(sprintf(
            '%s %d PRS stage-result time(s) across %d match(es).',
            $dry ? 'Would update' : 'Updated',
            $totalChanged,
            count($matchIds),
        ));

        return self::SUCCESS;
    }
}
