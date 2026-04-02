<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrectionLog extends Model
{
    protected $fillable = [
        'match_id',
        'stage_id',
        'shooter_id',
        'action',
        'details',
        'device_id',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'performed_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(ShootingMatch::class, 'match_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(TargetSet::class, 'stage_id');
    }

    public function shooter(): BelongsTo
    {
        return $this->belongsTo(Shooter::class);
    }
}
