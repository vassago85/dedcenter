<?php

namespace App\Models;

use App\Enums\ElrShotResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElrShot extends Model
{
    use HasFactory;

    protected $fillable = [
        'shooter_id',
        'elr_target_id',
        'shot_number',
        'result',
        'points_awarded',
        'recorded_by',
        'device_id',
        'recorded_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'shot_number' => 'integer',
            'result' => ElrShotResult::class,
            'points_awarded' => 'decimal:2',
            'recorded_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function shooter(): BelongsTo
    {
        return $this->belongsTo(Shooter::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(ElrTarget::class, 'elr_target_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isHit(): bool
    {
        return $this->result === ElrShotResult::Hit;
    }
}
