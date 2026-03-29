<?php

namespace App\Models;

use App\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ShootingMatch extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'name',
        'date',
        'location',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => MatchStatus::class,
        ];
    }

    // ── Relationships ──

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targetSets(): HasMany
    {
        return $this->hasMany(TargetSet::class, 'match_id');
    }

    public function squads(): HasMany
    {
        return $this->hasMany(Squad::class, 'match_id');
    }

    public function shooters(): HasManyThrough
    {
        return $this->hasManyThrough(Shooter::class, Squad::class);
    }

    // ── Computed Attributes ──

    public function getTotalShootersAttribute(): int
    {
        return $this->shooters()->count();
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === MatchStatus::Active;
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === MatchStatus::Completed;
    }
}
