<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrsStageResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'stage_id',
        'shooter_id',
        'hits',
        'misses',
        'not_taken',
        'raw_time_seconds',
        'official_time_seconds',
        'completed_at',
        'completed_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'misses' => 'integer',
            'not_taken' => 'integer',
            'raw_time_seconds' => 'decimal:2',
            'official_time_seconds' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function shooter(): BelongsTo
    {
        return $this->belongsTo(Shooter::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(TargetSet::class, 'stage_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(ShootingMatch::class, 'match_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
