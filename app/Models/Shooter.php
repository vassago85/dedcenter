<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shooter extends Model
{
    protected $fillable = [
        'squad_id',
        'name',
        'bib_number',
        'user_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    // ── Relationships ──

    public function squad(): BelongsTo
    {
        return $this->belongsTo(Squad::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Computed Attributes ──

    public function getTotalScoreAttribute(): float
    {
        return (float) $this->scores()
            ->where('is_hit', true)
            ->join('gongs', 'scores.gong_id', '=', 'gongs.id')
            ->sum('gongs.multiplier');
    }

    public function getHitCountAttribute(): int
    {
        return $this->scores()->where('is_hit', true)->count();
    }

    public function getMissCountAttribute(): int
    {
        return $this->scores()->where('is_hit', false)->count();
    }
}
