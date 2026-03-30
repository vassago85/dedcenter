<?php

namespace App\Services\Scoring;

use App\Models\ShootingMatch;
use Illuminate\Support\Collection;

interface ScoringEngineInterface
{
    public function calculateStandings(ShootingMatch $match, array $filters = []): array;
}
