<?php

namespace App\Services\MatchBook;

use App\Models\MatchBookStage;
use App\Models\Setting;

/**
 * Calculates stage difficulty score (0-100) from five weighted drivers.
 *
 * Ported from MatchBook Pro's StageDifficultyService.
 */
class StageDifficultyService
{
    public const RIMFIRE_BANDS = [
        'close' => 75,
        'typical' => 150,
        'far' => 225,
        'extreme' => 226,
    ];

    public const CENTERFIRE_BANDS = [
        'close' => 300,
        'typical' => 600,
        'far' => 800,
        'extreme' => 801,
    ];

    public const WEIGHTS = [
        'targets' => 0.32,
        'time' => 0.23,
        'movement' => 0.15,
        'distance' => 0.18,
        'acquisition' => 0.12,
    ];

    public const DEFAULT_INTENSITY_FACTOR = 1.0;

    public const INTENSITY_FACTOR_MIN = 0.5;

    public const INTENSITY_FACTOR_MAX = 2.5;

    public function calculate(MatchBookStage $stage): array
    {
        $discipline = $stage->matchBook->match_type ?? 'centerfire';
        $shots = $stage->shots;

        if ($shots->isEmpty()) {
            return $this->emptyResult($discipline);
        }

        $K = max(1, $stage->positions_count ?? $stage->uniquePositionCount());
        $M = $stage->movement_meters ?? 0;
        $targetCount = $this->countUniqueGongs($shots);
        $timeLimitSeconds = $stage->time_limit ?? 105;
        $roundCount = max(1, $stage->round_count ?? $shots->count());

        $targetMetrics = $this->calculateTargetMetrics($shots);

        $targetScoreResult = $this->calculateTargetScoreFromMil($targetMetrics['avgMil'], $targetMetrics['avgMilUnknown']);
        $targetScore = $targetScoreResult['score'];
        $targetEstimated = $targetScoreResult['estimated'];

        $timeScore = $this->calculateTimeScore($timeLimitSeconds, $roundCount);
        $positionsMovementScore = $this->calculatePositionsMovementScore($K, $M);
        $distanceResult = $this->calculateDistanceScore($targetMetrics['avgDistanceM'], $targetMetrics['maxDistanceM'], $discipline);
        $acquisitionScore = $this->calculateAcquisitionScore($targetCount);

        $score100 = $this->calculateScore100($targetScore, $timeScore, $positionsMovementScore, $distanceResult['score'], $acquisitionScore);
        $label = $this->getLabelFromScore100($score100);
        $color = $this->getColorFromScore100($score100);

        $drivers = [
            'targets' => ['score' => $targetScore, 'avgMil' => round($targetMetrics['avgMil'], 2), 'avgDistanceM' => round($targetMetrics['avgDistanceM'], 1), 'estimated' => $targetEstimated],
            'time' => ['score' => $timeScore, 'secondsPerShot' => round($timeLimitSeconds / $roundCount, 1), 'timeLimitSeconds' => $timeLimitSeconds, 'roundCount' => $roundCount],
            'movement' => ['score' => $positionsMovementScore, 'positions' => $K, 'movementMeters' => $M],
            'distance' => ['score' => $distanceResult['score'], 'avgDistanceM' => round($targetMetrics['avgDistanceM'], 1), 'maxDistanceM' => round($targetMetrics['maxDistanceM'], 1), 'label' => $distanceResult['label'], 'band' => $distanceResult['band']],
            'acquisition' => ['score' => $acquisitionScore, 'targetCount' => $targetCount],
        ];

        $explanation = $this->generateExplanation($discipline, $score100, $drivers, $targetEstimated);

        return [
            'discipline' => $discipline,
            'disciplineDisplay' => $discipline === 'rimfire' ? 'Rimfire (PR22)' : 'Centerfire (PRS)',
            'score100' => $score100,
            'overallLabel' => $label,
            'overallColor' => $color,
            'drivers' => $drivers,
            'explanation' => $explanation,
            'hasTargets' => true,
        ];
    }

    /**
     * Calculate difficulty from raw arrays (for live preview without persisted models).
     */
    public function calculateFromRaw(array $shotsData, array $stageData, string $discipline = 'centerfire'): array
    {
        if (empty($shotsData)) {
            return $this->emptyResult($discipline);
        }

        $shots = collect($shotsData);
        $K = max(1, $stageData['positions_count'] ?? $shots->pluck('position')->unique()->count());
        $M = $stageData['movement_meters'] ?? 0;
        $targetCount = $this->countUniqueGongs($shots);
        $timeLimitSeconds = $stageData['time_limit'] ?? 105;
        $roundCount = max(1, $stageData['round_count'] ?? $shots->count());

        $targetMetrics = $this->calculateTargetMetrics($shots);
        $targetScoreResult = $this->calculateTargetScoreFromMil($targetMetrics['avgMil'], $targetMetrics['avgMilUnknown']);

        $timeScore = $this->calculateTimeScore($timeLimitSeconds, $roundCount);
        $positionsMovementScore = $this->calculatePositionsMovementScore($K, $M);
        $distanceResult = $this->calculateDistanceScore($targetMetrics['avgDistanceM'], $targetMetrics['maxDistanceM'], $discipline);
        $acquisitionScore = $this->calculateAcquisitionScore($targetCount);
        $score100 = $this->calculateScore100($targetScoreResult['score'], $timeScore, $positionsMovementScore, $distanceResult['score'], $acquisitionScore);

        return [
            'discipline' => $discipline,
            'score100' => $score100,
            'overallLabel' => $this->getLabelFromScore100($score100),
            'overallColor' => $this->getColorFromScore100($score100),
            'hasTargets' => true,
        ];
    }

    protected function countUniqueGongs($items): int
    {
        $uniqueKeys = [];

        foreach ($items as $item) {
            $label = is_array($item) ? ($item['gong_label'] ?? '') : ($item->gong_label ?? '');
            $name = is_array($item) ? ($item['gong_name'] ?? '') : ($item->gong_name ?? '');
            $distance = is_array($item) ? ($item['distance_m'] ?? 0) : ($item->distance_m ?? 0);
            $size = is_array($item) ? ($item['size_mm'] ?? 0) : ($item->size_mm ?? 0);
            $uniqueKeys["{$label}|{$name}|{$distance}|{$size}"] = true;
        }

        return count($uniqueKeys);
    }

    protected function calculateTargetMetrics($items): array
    {
        $totalWeight = 0;
        $weightedMilSum = 0;
        $weightedDistanceSum = 0;
        $maxDistance = 0;
        $hasSizeMm = false;
        $missingSizeMm = false;

        foreach ($items as $item) {
            $distanceM = (float) (is_array($item) ? ($item['distance_m'] ?? 100) : ($item->distance_m ?? 100));
            $sizeMm = is_array($item) ? ($item['size_mm'] ?? null) : ($item->size_mm ?? null);

            if ($distanceM > 0) {
                $weightedDistanceSum += $distanceM;
                $totalWeight++;
                if ($distanceM > $maxDistance) {
                    $maxDistance = $distanceM;
                }
                if ($sizeMm !== null && $sizeMm > 0) {
                    $hasSizeMm = true;
                    $weightedMilSum += $sizeMm / $distanceM;
                } else {
                    $missingSizeMm = true;
                }
            }
        }

        if ($totalWeight === 0) {
            return ['avgMil' => 0.58, 'avgDistanceM' => 100, 'maxDistanceM' => 100, 'avgMilUnknown' => true];
        }

        if ($hasSizeMm && ! $missingSizeMm) {
            return ['avgMil' => $weightedMilSum / $totalWeight, 'avgDistanceM' => $weightedDistanceSum / $totalWeight, 'maxDistanceM' => $maxDistance, 'avgMilUnknown' => false];
        }

        return ['avgMil' => 0.58, 'avgDistanceM' => $weightedDistanceSum / $totalWeight, 'maxDistanceM' => $maxDistance, 'avgMilUnknown' => true];
    }

    public function calculateTargetScoreFromMil(float $avgMil, bool $unknown = false): array
    {
        if ($unknown) {
            return ['score' => 3, 'estimated' => true];
        }
        if ($avgMil >= 0.75) {
            return ['score' => 1, 'estimated' => false];
        }
        if ($avgMil >= 0.60) {
            return ['score' => 2, 'estimated' => false];
        }
        if ($avgMil >= 0.45) {
            return ['score' => 3, 'estimated' => false];
        }
        if ($avgMil >= 0.30) {
            return ['score' => 4, 'estimated' => false];
        }

        return ['score' => 5, 'estimated' => false];
    }

    public function calculateTimeScore(int $timeLimitSeconds, int $roundCount): int
    {
        $sps = $roundCount > 0 ? $timeLimitSeconds / $roundCount : 12;
        if ($sps >= 12) {
            return 1;
        }
        if ($sps >= 10) {
            return 2;
        }
        if ($sps >= 8) {
            return 3;
        }
        if ($sps >= 6) {
            return 4;
        }

        return 5;
    }

    public function calculatePositionsMovementScore(int $positions, int $movementMeters = 0): int
    {
        $posScore = max(0, min(4, $positions - 1));
        $moveScore = max(0, min(2, (int) floor($movementMeters / 10)));

        return max(1, min(5, $posScore + $moveScore));
    }

    public function calculateAcquisitionScore(int $targetCount): int
    {
        if ($targetCount <= 2) {
            return 1;
        }
        if ($targetCount <= 4) {
            return 2;
        }
        if ($targetCount <= 6) {
            return 3;
        }
        if ($targetCount <= 9) {
            return 4;
        }

        return 5;
    }

    public function calculateDistanceScore(float $avgDistanceM, float $maxDistanceM, string $discipline): array
    {
        $bands = $discipline === 'rimfire' ? self::RIMFIRE_BANDS : self::CENTERFIRE_BANDS;
        $avgBand = $this->getDistanceBand($avgDistanceM, $bands);
        $maxBand = $this->getDistanceBand($maxDistanceM, $bands);
        $bandScores = ['close' => 1, 'typical' => 2, 'far' => 4, 'extreme' => 5];
        $distanceScore = $bandScores[$avgBand];
        $bandOrder = ['close', 'typical', 'far', 'extreme'];

        if (array_search($maxBand, $bandOrder) > array_search($avgBand, $bandOrder)) {
            $distanceScore = min(5, $distanceScore + 1);
        }

        return [
            'score' => $distanceScore,
            'label' => ucfirst($avgBand).' (avg '.round($avgDistanceM).'m, max '.round($maxDistanceM).'m)',
            'band' => $avgBand,
        ];
    }

    public function getDistanceBand(float $distanceM, array $bands): string
    {
        if ($distanceM <= $bands['close']) {
            return 'close';
        }
        if ($distanceM <= $bands['typical']) {
            return 'typical';
        }
        if ($distanceM <= $bands['far']) {
            return 'far';
        }

        return 'extreme';
    }

    public function calculateScore100(int $targetScore, int $timeScore, int $movementScore, int $distanceScore, int $acquisitionScore, ?float $intensityFactor = null): int
    {
        $norm = fn ($x) => ($x - 1) / 4;
        $score01 = self::WEIGHTS['targets'] * $norm($targetScore) + self::WEIGHTS['time'] * $norm($timeScore) + self::WEIGHTS['movement'] * $norm($movementScore) + self::WEIGHTS['distance'] * $norm($distanceScore) + self::WEIGHTS['acquisition'] * $norm($acquisitionScore);

        $intensityFactor ??= $this->getIntensityFactor();
        $score01Scaled = max(0, min(1, $score01 * $intensityFactor));

        return (int) round(100 * $score01Scaled);
    }

    public function getIntensityFactor(): float
    {
        try {
            $factor = Setting::get('difficulty_intensity_factor', self::DEFAULT_INTENSITY_FACTOR);
        } catch (\Exception) {
            $factor = self::DEFAULT_INTENSITY_FACTOR;
        }

        return max(self::INTENSITY_FACTOR_MIN, min(self::INTENSITY_FACTOR_MAX, (float) $factor));
    }

    public function getLabelFromScore100(int $score100): string
    {
        if ($score100 <= 30) {
            return 'Easy';
        }
        if ($score100 <= 55) {
            return 'Moderate';
        }
        if ($score100 <= 75) {
            return 'Hard';
        }

        return 'Brutal';
    }

    public function getColorFromScore100(int $score100): string
    {
        if ($score100 <= 30) {
            return 'green';
        }
        if ($score100 <= 55) {
            return 'amber';
        }
        if ($score100 <= 75) {
            return 'red';
        }

        return 'slate';
    }

    protected function generateExplanation(string $discipline, int $score100, array $drivers, bool $targetEstimated): string
    {
        $disciplineDisplay = $discipline === 'rimfire' ? 'rimfire' : 'centerfire';
        $label = $this->getLabelFromScore100($score100);
        $norm = fn ($x) => ($x - 1) / 4;

        $contributions = [
            'targets' => self::WEIGHTS['targets'] * $norm($drivers['targets']['score']),
            'time' => self::WEIGHTS['time'] * $norm($drivers['time']['score']),
            'movement' => self::WEIGHTS['movement'] * $norm($drivers['movement']['score']),
            'distance' => self::WEIGHTS['distance'] * $norm($drivers['distance']['score']),
            'acquisition' => self::WEIGHTS['acquisition'] * $norm($drivers['acquisition']['score']),
        ];

        arsort($contributions);
        $topDrivers = array_slice(array_keys($contributions), 0, 2);

        $reasons = [];
        foreach ($topDrivers as $driver) {
            $reasons[] = match ($driver) {
                'acquisition' => "target acquisition load ({$drivers['acquisition']['targetCount']} gongs)",
                'targets' => $targetEstimated ? 'target difficulty (size not provided)' : "small targets (avg {$drivers['targets']['avgMil']} mil)",
                'time' => "time pressure ({$drivers['time']['secondsPerShot']}s/shot)",
                'movement' => $drivers['movement']['movementMeters'] > 0
                    ? "movement ({$drivers['movement']['positions']} positions, ~{$drivers['movement']['movementMeters']}m)"
                    : "{$drivers['movement']['positions']} positions",
                'distance' => "long distance ({$drivers['distance']['label']})",
            };
        }

        $sentence = "{$label} for {$disciplineDisplay} due to ".implode(' and ', $reasons).'.';

        $extras = [];
        if (! in_array('distance', $topDrivers)) {
            $extras[] = "Distance is {$drivers['distance']['label']}";
        }
        if (! in_array('time', $topDrivers)) {
            $extras[] = "Time pressure {$drivers['time']['secondsPerShot']}s/shot";
        }
        if (! in_array('movement', $topDrivers)) {
            $extras[] = "{$drivers['movement']['positions']} position".($drivers['movement']['positions'] !== 1 ? 's' : '');
        }
        if (! in_array('acquisition', $topDrivers)) {
            $extras[] = "{$drivers['acquisition']['targetCount']} gongs";
        }

        if (! empty($extras)) {
            $sentence .= ' '.implode(', ', $extras).'.';
        }

        if ($targetEstimated) {
            $sentence .= ' Target sizes not provided; angular difficulty estimated.';
        }

        return $sentence;
    }

    protected function emptyResult(string $discipline): array
    {
        return [
            'discipline' => $discipline,
            'disciplineDisplay' => $discipline === 'rimfire' ? 'Rimfire (PR22)' : 'Centerfire (PRS)',
            'score100' => 0,
            'overallLabel' => 'No Data',
            'overallColor' => 'slate',
            'drivers' => [
                'targets' => ['score' => 0, 'avgMil' => 0, 'avgDistanceM' => 0, 'estimated' => true],
                'time' => ['score' => 0, 'secondsPerShot' => 0, 'timeLimitSeconds' => 0, 'roundCount' => 0],
                'movement' => ['score' => 0, 'positions' => 0, 'movementMeters' => 0],
                'distance' => ['score' => 0, 'avgDistanceM' => 0, 'maxDistanceM' => 0, 'label' => 'N/A', 'band' => 'close'],
                'acquisition' => ['score' => 0, 'targetCount' => 0],
            ],
            'explanation' => 'Add targets to see difficulty analysis.',
            'hasTargets' => false,
        ];
    }

    /**
     * Calculate match-level aggregate difficulty.
     */
    public function calculateMatchDifficulty(\App\Models\MatchBook $matchBook): array
    {
        $stages = $matchBook->stages()->with('shots')->get();
        $stageScores = [];
        $totalScore = 0;
        $countWithTargets = 0;

        foreach ($stages as $stage) {
            $difficulty = $this->calculate($stage);
            $stageScores[$stage->id] = $difficulty;
            if ($difficulty['hasTargets']) {
                $totalScore += $difficulty['score100'];
                $countWithTargets++;
            }
        }

        if ($countWithTargets === 0) {
            return ['avgScore100' => 0, 'avgLabel' => 'No Data', 'avgColor' => 'slate', 'stageScores' => $stageScores, 'hasData' => false];
        }

        $avgScore100 = (int) round($totalScore / $countWithTargets);

        return [
            'avgScore100' => $avgScore100,
            'avgLabel' => $this->getLabelFromScore100($avgScore100),
            'avgColor' => $this->getColorFromScore100($avgScore100),
            'stageScores' => $stageScores,
            'hasData' => true,
        ];
    }
}
