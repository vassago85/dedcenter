<?php

namespace App\Services\Scoring;

use App\Models\ShootingMatch;
use InvalidArgumentException;

class ScoringEngineFactory
{
    public static function make(ShootingMatch $match): ScoringEngineInterface
    {
        return match ($match->scoring_type) {
            'elr' => new ELRScoringService(),
            default => throw new InvalidArgumentException("Scoring engine for [{$match->scoring_type}] not implemented via service layer."),
        };
    }
}
