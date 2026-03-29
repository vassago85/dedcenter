<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageTime extends Model
{
    use HasFactory;

    protected $fillable = [
        'shooter_id',
        'target_set_id',
        'time_seconds',
        'device_id',
        'recorded_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'time_seconds' => 'decimal:2',
            'recorded_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function shooter(): BelongsTo
    {
        return $this->belongsTo(Shooter::class);
    }

    public function targetSet(): BelongsTo
    {
        return $this->belongsTo(TargetSet::class);
    }
}
