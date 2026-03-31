<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TargetSet extends Model
{
    use HasFactory;
    protected $fillable = [
        'match_id',
        'label',
        'distance_meters',
        'distance_multiplier',
        'sort_order',
        'is_tiebreaker',
        'par_time_seconds',
        'stage_number',
        'total_shots',
        'is_timed_stage',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'distance_meters' => 'integer',
            'distance_multiplier' => 'decimal:2',
            'sort_order' => 'integer',
            'is_tiebreaker' => 'boolean',
            'par_time_seconds' => 'decimal:2',
            'stage_number' => 'integer',
            'total_shots' => 'integer',
            'is_timed_stage' => 'boolean',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(ShootingMatch::class, 'match_id');
    }

    public function gongs(): HasMany
    {
        return $this->hasMany(Gong::class);
    }

    public function stageTimes(): HasMany
    {
        return $this->hasMany(StageTime::class);
    }

    public function prsShots(): HasMany
    {
        return $this->hasMany(PrsShotScore::class, 'stage_id');
    }

    public function prsResults(): HasMany
    {
        return $this->hasMany(PrsStageResult::class, 'stage_id');
    }

    public function stageTargets(): HasMany
    {
        return $this->hasMany(StageTarget::class, 'stage_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->label ?: "Stage {$this->stage_number}";
    }
}
