<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ElrScoringProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'name',
        'multipliers',
    ];

    protected function casts(): array
    {
        return [
            'multipliers' => 'array',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(ShootingMatch::class, 'match_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ElrStage::class, 'elr_scoring_profile_id');
    }

    public function multiplierForShot(int $shotNumber): float
    {
        $index = $shotNumber - 1;
        return (float) ($this->multipliers[$index] ?? 0);
    }
}
