<?php

namespace App\Services;

use App\Models\Score;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use Illuminate\Support\Facades\DB;

/**
 * Applies a batch of post-squad score corrections for one squad. Callers
 * (the Volt correction page) pass in the *target* state per (shooter,
 * gong) cell — null meaning "no score", true/false meaning hit/miss —
 * plus a required correction note. The service diffs that against the
 * existing Score rows and writes the minimal set of create / update /
 * delete operations, logging every change through ScoreAuditService so
 * the audit trail survives.
 *
 * Why a service (and not just inline in the Volt file):
 *   - Business logic unit-testable without rendering the layout
 *     (the project-wide <flux:tab.group> compile error breaks any
 *     Volt::test render right now).
 *   - Symmetry with ShooterAccountClaimService.
 *   - Keeps the Volt component focused on UI state.
 */
class SquadScoreCorrectionService
{
    /**
     * Apply corrections for a single squad.
     *
     * @param  array<int, array<int, bool|null>>  $targets
     *     shooter_id => (gong_id => true|false|null)
     *     A cell set to null means "no score recorded" — any existing
     *     Score for that (shooter, gong) pair will be deleted.
     * @return array{created:int,updated:int,deleted:int,unchanged:int}
     */
    public function apply(
        ShootingMatch $match,
        Squad $squad,
        array $targets,
        string $reason,
        int $actorUserId,
    ): array {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('A correction note is required.');
        }

        if ($squad->match_id !== $match->id) {
            throw new \InvalidArgumentException('Squad does not belong to this match.');
        }

        // Only shooters actually on this squad can be touched — stops a
        // malformed payload from silently editing a neighbouring squad.
        $shooterIds = Shooter::where('squad_id', $squad->id)->pluck('id')->all();

        // Build the universe of valid gong_ids for this match so we can
        // reject stray cells pointing at another match's gongs.
        $validGongIds = DB::table('gongs')
            ->join('target_sets', 'gongs.target_set_id', '=', 'target_sets.id')
            ->where('target_sets.match_id', $match->id)
            ->pluck('gongs.id')
            ->all();
        $validGongIds = array_flip($validGongIds);

        $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0];

        DB::transaction(function () use (
            $match, $targets, $reason, $actorUserId, $shooterIds, $validGongIds, &$stats,
        ) {
            foreach ($targets as $shooterId => $gongStates) {
                $shooterId = (int) $shooterId;
                if (! in_array($shooterId, $shooterIds, true)) {
                    continue;
                }

                $existing = Score::where('shooter_id', $shooterId)
                    ->whereIn('gong_id', array_keys($gongStates))
                    ->get()
                    ->keyBy('gong_id');

                foreach ($gongStates as $gongId => $desired) {
                    $gongId = (int) $gongId;
                    if (! isset($validGongIds[$gongId])) {
                        continue;
                    }

                    /** @var Score|null $current */
                    $current = $existing->get($gongId);

                    if ($desired === null) {
                        if ($current === null) {
                            $stats['unchanged']++;

                            continue;
                        }
                        ScoreAuditService::logDeleted($match->id, $current, $reason);
                        $current->delete();
                        $stats['deleted']++;

                        continue;
                    }

                    $desiredBool = (bool) $desired;

                    if ($current === null) {
                        $new = Score::create([
                            'shooter_id' => $shooterId,
                            'gong_id' => $gongId,
                            'is_hit' => $desiredBool,
                            'recorded_by' => $actorUserId,
                            'recorded_at' => now(),
                        ]);
                        ScoreAuditService::logCreated($match->id, $new);
                        // Stamp the reason as a dedicated "created via
                        // correction" entry as well so the audit log
                        // always carries the note, not just the raw
                        // creation payload.
                        ScoreAuditService::log(
                            $match->id,
                            $new,
                            'correction',
                            null,
                            ['is_hit' => $desiredBool],
                            $reason,
                        );
                        $stats['created']++;

                        continue;
                    }

                    if ((bool) $current->is_hit === $desiredBool) {
                        $stats['unchanged']++;

                        continue;
                    }

                    $old = $current->toArray();
                    $current->update([
                        'is_hit' => $desiredBool,
                        'recorded_by' => $actorUserId,
                        'recorded_at' => now(),
                    ]);
                    ScoreAuditService::logUpdated($match->id, $current, $old, $reason);
                    $stats['updated']++;
                }
            }
        });

        return $stats;
    }
}
