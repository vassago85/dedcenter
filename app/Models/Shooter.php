<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shooter extends Model
{
    use HasFactory;
    protected $fillable = [
        'squad_id',
        'name',
        'bib_number',
        'user_id',
        'match_division_id',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isWithdrawn(): bool
    {
        return $this->status === 'withdrawn';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
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

    public function stageTimes(): HasMany
    {
        return $this->hasMany(StageTime::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(MatchDivision::class, 'match_division_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(MatchCategory::class, 'match_category_shooter');
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

    public function getPrsScoreAttribute(): int
    {
        return $this->hit_count;
    }

    public function getTotalTimeAttribute(): float
    {
        return (float) $this->stageTimes()->sum('time_seconds');
    }
}
