<?php

namespace App\Services\Scoring;

use App\Models\ElrSquadTeamOrder;
use App\Models\ElrTeamStageEntry;

class ElrSquadTeamOrderService
{
    /**
     * Persist squad firing order from a completed/in-progress team stage entry.
     */
    public function recordFromTeamStageEntry(ElrTeamStageEntry $entry): void
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
}
