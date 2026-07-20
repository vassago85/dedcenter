<?php

namespace App\Services\Scoring;

use App\Models\ShootingMatch;
use App\Models\Squad;
use Illuminate\Support\Collection;

/**
 * Enforces the ALRHA shared-rifle rule at squad-assignment time.
 *
 * Rule (per MD):
 *   Two shooters can share a rifle across relays only when their relays
 *   do NOT overlap. In practice we treat any two relays whose Nth and
 *   (N+1)th sort orders are consecutive as overlapping — because the
 *   next relay's setup begins during the previous relay's shooting
 *   window (see the Parys 6 June squadding sheet: Varmint R1 09:00-10:10
 *   vs R2 09:40-10:50).
 *
 * A conflict is anything that violates this rule. Callers may treat
 * conflicts as blocking or as a warning banner.
 */
class AlrhaSharedRifleValidator
{
    /**
     * @return array<int, array{key:string, shooters:array<int, array{id:int, name:string, squad:string, squad_id:int}>}>
     */
    public function findConflicts(ShootingMatch $match): array
    {
        if (! $match->isAlrha()) {
            return [];
        }

        $squads = $match->squads()->orderBy('sort_order')->with(['shooters' => function ($q) {
            $q->whereNotNull('shared_rifle_key')
              ->where('shared_rifle_key', '!=', '')
              ->select('id', 'name', 'squad_id', 'shared_rifle_key');
        }])->get();

        // (rifle_key => [squad_index => Collection<Shooter>])
        $byRifle = [];
        foreach ($squads as $squadIndex => $squad) {
            foreach ($squad->shooters as $shooter) {
                $byRifle[$shooter->shared_rifle_key][$squadIndex] = $byRifle[$shooter->shared_rifle_key][$squadIndex] ?? collect();
                $byRifle[$shooter->shared_rifle_key][$squadIndex]->push([
                    'shooter' => $shooter,
                    'squad' => $squad,
                ]);
            }
        }

        $conflicts = [];
        foreach ($byRifle as $rifleKey => $bySquad) {
            $conflicts = array_merge($conflicts, $this->conflictsForRifle($rifleKey, $bySquad));
        }

        return $conflicts;
    }

    /**
     * A shooter about to be moved into $targetSquad — return the list of
     * peer shooters who share their rifle_key and would end up in an
     * adjacent (overlapping) squad.
     *
     * @return array<int, array{shooter_id:int, shooter_name:string, squad_id:int, squad_name:string}>
     */
    public function findConflictsForShooter(ShootingMatch $match, ?string $rifleKey, Squad $targetSquad): array
    {
        if (! $match->isAlrha() || $rifleKey === null || $rifleKey === '') {
            return [];
        }

        $squads = $match->squads()->orderBy('sort_order')->get();
        $indexById = [];
        foreach ($squads as $index => $squad) {
            $indexById[$squad->id] = $index;
        }
        $targetIndex = $indexById[$targetSquad->id] ?? null;
        if ($targetIndex === null) {
            return [];
        }

        $peers = \App\Models\Shooter::query()
            ->join('squads', 'shooters.squad_id', '=', 'squads.id')
            ->where('squads.match_id', $match->id)
            ->where('shooters.shared_rifle_key', $rifleKey)
            ->where('shooters.squad_id', '!=', $targetSquad->id)
            ->select('shooters.id', 'shooters.name', 'shooters.squad_id', 'squads.name as squad_name')
            ->get();

        $conflicts = [];
        foreach ($peers as $peer) {
            $peerIndex = $indexById[$peer->squad_id] ?? null;
            if ($peerIndex === null) {
                continue;
            }
            if (abs($peerIndex - $targetIndex) <= 1) {
                $conflicts[] = [
                    'shooter_id' => (int) $peer->id,
                    'shooter_name' => $peer->name,
                    'squad_id' => (int) $peer->squad_id,
                    'squad_name' => $peer->squad_name,
                ];
            }
        }

        return $conflicts;
    }

    /**
     * @param  array<int, Collection>  $bySquad  squad_index → collection of (shooter, squad)
     * @return array<int, array{key:string, shooters:array<int, array{id:int, name:string, squad:string, squad_id:int}>}>
     */
    private function conflictsForRifle(string $rifleKey, array $bySquad): array
    {
        $indexes = array_keys($bySquad);
        sort($indexes);

        $flagged = [];
        for ($i = 0; $i < count($indexes); $i++) {
            for ($j = $i + 1; $j < count($indexes); $j++) {
                if ($indexes[$j] - $indexes[$i] <= 1) {
                    $flagged[$indexes[$i]] = true;
                    $flagged[$indexes[$j]] = true;
                }
            }
        }

        if (empty($flagged)) {
            return [];
        }

        $shooters = [];
        foreach ($flagged as $squadIndex => $_) {
            foreach ($bySquad[$squadIndex] as $entry) {
                $shooters[] = [
                    'id' => (int) $entry['shooter']->id,
                    'name' => $entry['shooter']->name,
                    'squad' => $entry['squad']->name,
                    'squad_id' => (int) $entry['squad']->id,
                ];
            }
        }

        return [[
            'key' => $rifleKey,
            'shooters' => $shooters,
        ]];
    }
}
