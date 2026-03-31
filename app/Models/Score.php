<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Score extends Model
{
    use HasFactory;
    protected $fillable = [
        'shooter_id',
        'gong_id',
        'is_hit',
        'is_reshoot',
        'reshoot_reason',
        'recorded_by',
        'device_id',
        'recorded_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_hit' => 'boolean',
            'is_reshoot' => 'boolean',
            'recorded_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function shooter(): BelongsTo
    {
        return $this->belongsTo(Shooter::class);
    }

    public function gong(): BelongsTo
    {
        return $this->belongsTo(Gong::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
