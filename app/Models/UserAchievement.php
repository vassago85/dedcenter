<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAchievement extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'achievement_id',
        'match_id',
        'stage_id',
        'shooter_id',
        'metadata',
        'awarded_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'awarded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
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
