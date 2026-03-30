<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
     * Calculate points awarded for a hit on a given shot number,
     * using the resolved scoring profile from the stage or match.
     */
    public function pointsForShot(int $shotNumber): float
    {
        $profile = $this->stage?->resolvedProfile();
        if (! $profile) {
            return $shotNumber === 1 ? (float) $this->base_points : 0;
        }

        $multiplier = $profile->multiplierForShot($shotNumber);

        return round((float) $this->base_points * $multiplier, 2);
    }
}
