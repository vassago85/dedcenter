<?php

namespace App\Models;

use App\Enums\PrsShotResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrsShotScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'stage_id',
        'shooter_id',
        'shot_number',
        'result',
        'is_reshoot',
        'reshoot_reason',
        'device_id',
        'recorded_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'result' => PrsShotResult::class,
            'shot_number' => 'integer',
            'is_reshoot' => 'boolean',
            'recorded_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
