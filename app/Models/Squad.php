<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Squad extends Model
{
    use HasFactory;
    protected $fillable = [
        'match_id',
        'name',
        'sort_order',
        'max_capacity',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'max_capacity' => 'integer',
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

    public function effectiveCapacity(): ?int
    {
        return $this->max_capacity ?? $this->match?->max_squad_size;
    }

    public function spotsRemaining(): ?int
    {
        $cap = $this->effectiveCapacity();
        if ($cap === null) return null;
        return max(0, $cap - $this->shooters()->count());
    }

    public function isFull(): bool
    {
        $remaining = $this->spotsRemaining();
        return $remaining !== null && $remaining <= 0;
    }
}
