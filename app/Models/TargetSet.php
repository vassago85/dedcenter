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
        'sort_order',
        'is_tiebreaker',
        'par_time_seconds',
    ];

    protected function casts(): array
    {
        return [
            'distance_meters' => 'integer',
            'sort_order' => 'integer',
            'is_tiebreaker' => 'boolean',
            'par_time_seconds' => 'decimal:2',
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
}
