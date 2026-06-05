<?php

namespace App\Services\Scoring;

use App\Models\ElrSquadTeamOrder;
use App\Models\ElrTeamStageEntry;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Team;

class ElrSquadTeamOrderService
{
    /**
     * Persist squad firing order from a completed/in-progress team stage entry.
     */
    public function recordFromTeamStageEntry(\App\Models\ElrTeamStageEntry $entry): void
    {
        if ($entry->squad_id === null) {
            return;
        }

        ElrSquadTeamOrder::updateOrCreate(
            [
                'squad_id' => $entry->squad_id,
                'elr_stage_id' => $entry->elr_stage_id,
                'team_id' => $entry->team_id,
            ],
            [
                'position' => $entry->position ?? 1,
                'shooter_first_id' => $entry->first_shooter_id,
            ],
        );
    }

    /**
     * Suggested team firing order + leadoff shooter for a squad at a stage.
     * Rotation rule: the team that fired last on the previous stage moves to first.
     *
     * @return array<int, array{team_id:int, position:int, first_shooter_id:int|null}>
     */
    public static function getNextFiringOrder(ShootingMatch $match, int $squadId, int $stageId): array
    {
        $stage = $match->elrStages()->whereKey($stageId)->first();
        if (! $stage) {
            return [];
        }

        $teamIds = Shooter::query()
            ->where('squad_id', $squadId)
            ->whereNotNull('team_id')
            ->distinct()
            ->pluck('team_id');

        $teams = Team::whereIn('id', $teamIds)->orderBy('sort_order')->get();
        if ($teams->isEmpty()) {
            return [];
        }

        $prevStage = $match->elrStages()
            ->where('sort_order', '<', $stage->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        $orderedTeamIds = $teams->pluck('id')->values()->all();

        if ($prevStage) {
            $prevOrders = ElrSquadTeamOrder::query()
                ->where('squad_id', $squadId)
                ->where('elr_stage_id', $prevStage->id)
                ->orderBy('position')
                ->get();

            if ($prevOrders->isNotEmpty()) {
                $sorted = $prevOrders->sortBy('position')->values();
                $last = $sorted->last();
                $rotated = $sorted->slice(0, -1)->prepend($last)->values();
                $orderedTeamIds = $rotated->pluck('team_id')->all();
            }
        }

        $prevFirstByTeam = $prevStage
            ? ElrSquadTeamOrder::query()
                ->where('squad_id', $squadId)
                ->where('elr_stage_id', $prevStage->id)
                ->pluck('shooter_first_id', 'team_id')
            : collect();

        $out = [];
        foreach ($orderedTeamIds as $i => $teamId) {
            $team = $teams->firstWhere('id', $teamId);
            if (! $team) {
                continue;
            }
            $members = $team->shooters()->orderBy('sort_order')->pluck('id');
            $prevFirst = $prevFirstByTeam[$teamId] ?? null;
            $firstShooterId = null;
            if ($members->count() >= 2 && $prevFirst) {
                $firstShooterId = $members->first(fn ($id) => $id !== $prevFirst) ?? $members->first();
            } else {
                $firstShooterId = $members->first();
            }

            $out[] = [
                'team_id' => (int) $teamId,
                'position' => $i + 1,
                'first_shooter_id' => $firstShooterId ? (int) $firstShooterId : null,
            ];
        }

        return $out;
    }
}
