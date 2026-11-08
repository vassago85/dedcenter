<?php

namespace App\Concerns;

use Flux\Flux;

/**
 * Shared refusal for squadding pages. The match model owns the rule;
 * this trait only stops the action and tells the match director why.
 */
trait RefusesLockedSquadEdits
{
    protected function refuseBulkSquadEdit(): bool
    {
        return $this->refuseSquadEdit('bulk');
    }

    protected function refuseIndividualSquadEdit(): bool
    {
        return $this->refuseSquadEdit('individual');
    }

    private function refuseSquadEdit(string $kind): bool
    {
        if (! isset($this->match) || $this->match === null) {
            return false;
        }

        $this->match->refresh();

        $locked = $kind === 'bulk'
            ? $this->match->bulkSquadEditsLocked()
            : $this->match->individualSquadEditsLocked();

        if (! $locked) {
            return false;
        }

        $message = $kind === 'bulk'
            ? $this->match->bulkSquadEditRefusal()
            : $this->match->individualSquadEditRefusal();

        try {
            Flux::toast($message, variant: 'danger');
        } catch (\Throwable) {
            // Tests and jobs have no Livewire context. The refusal still holds.
        }

        return true;
    }
}
