<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ElrTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'elr_stage_id',
        'name',
        'distance_m',
        'base_points',
        'max_shots',
        'must_hit_to_advance',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'distance_m' => 'integer',
            'base_points' => 'decimal:2',
            'max_shots' => 'integer',
            'must_hit_to_advance' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ElrStage::class, 'elr_stage_id');
    }

    public function shots(): HasMany
    {
        return $this->hasMany(ElrShot::class)->orderBy('shot_number');
    }

    /**
     * Divisions for which this target is in play.
     *
     * Empty pivot rows mean "every division shoots this target" — see
     * the elr_division_targets migration for the rationale. The pivot
     * is only consulted by the scoring service when it needs to filter
     * a shooter to their division's target subset (Minor T1–T3 etc.).
     */
    public function divisions(): BelongsToMany
    {
        return $this->belongsToMany(MatchDivision::class, 'elr_division_targets');
    }

    /**
     * Resolved multiplier for the given shot number from the stage's profile.
     * Returns 0 if no profile is configured.
     */
    public function multiplierForShot(int $shotNumber): float
    {
        $profile = $this->stage?->resolvedProfile();
        if (! $profile) {
            return 0.0;
        }

        return (float) $profile->multiplierForShot($shotNumber);
    }

    /**
     * Calculate points awarded for a hit on a given shot number.
     *
     * Two scoring modes:
     *   - distance × multiplier (Peregrine ELR Challenge): used when the
     *     parent match has `elr_distance_based_scoring = true`. Score is
     *     raw metres × the profile multiplier. base_points is ignored.
     *   - base_points × multiplier (legacy): the original DeadCenter ELR
     *     behaviour. Kept for backwards compatibility with existing
     *     matches set up before the toggle.
     *
     * Returns 0 with no profile so a half-configured match can't write
     * accidental points.
     */
    public function pointsForShot(int $shotNumber): float
    {
        $profile = $this->stage?->resolvedProfile();
        if (! $profile) {
            return $shotNumber === 1 ? (float) $this->base_points : 0;
        }

        $multiplier = $profile->multiplierForShot($shotNumber);
        $match = $this->stage?->match;
        $useDistance = (bool) ($match?->elr_distance_based_scoring ?? false);

        $baseValue = $useDistance
            ? (float) $this->distance_m
            : (float) $this->base_points;

        return round($baseValue * $multiplier, 2);
    }

    /**
     * Points awarded for a hit that lands as the Nth IMPACT on this gong
     * (team gong-sequence mode). Identical math to pointsForShot() but the
     * multiplier is indexed by impact number (hits only) rather than shot
     * number, so a miss never burns a multiplier slot.
     */
    public function pointsForImpact(int $impactNumber): float
    {
        $profile = $this->stage?->resolvedProfile();
        if (! $profile) {
            return $impactNumber === 1 ? (float) $this->base_points : 0;
        }

        $multiplier = $profile->multiplierForShot($impactNumber);
        $match = $this->stage?->match;
        $useDistance = (bool) ($match?->elr_distance_based_scoring ?? false);

        $baseValue = $useDistance
            ? (float) $this->distance_m
            : (float) $this->base_points;

        return round($baseValue * $multiplier, 2);
    }
}
