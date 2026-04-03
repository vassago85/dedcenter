<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'name',
        'max_size',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'max_size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(ShootingMatch::class, 'match_id');
    }

    public function shooters(): HasMany
    {
        return $this->hasMany(Shooter::class);
    }

    public function effectiveMaxSize(): int
    {
        return $this->max_size ?? $this->match?->team_size ?? 3;
    }

    public function isFull(): bool
    {
        return $this->shooters()->count() >= $this->effectiveMaxSize();
    }

    public function spotsRemaining(): int
    {
        return max(0, $this->effectiveMaxSize() - $this->shooters()->count());
    }

    public function totalScore(): float
    {
        return (float) $this->shooters->sum(fn (Shooter $s) => $s->total_score);
    }
}
