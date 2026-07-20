<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A peer scoring assignment used at ALRHA matches. Two hunter teams are
 * paired to score each other, or three varmint shooters form a triple.
 * The group is scoped to a single relay (squad) so overlapping relays
 * don't get mixed up on paper score sheets.
 */
class AlrhaScoringGroup extends Model
{
    use HasFactory;

    public const TYPE_HUNTER_PAIR = 'hunter_pair';
    public const TYPE_VARMINT_TRIPLE = 'varmint_triple';

    protected $fillable = [
        'match_id',
        'squad_id',
        'type',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(ShootingMatch::class, 'match_id');
    }

    public function squad(): BelongsTo
    {
        return $this->belongsTo(Squad::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(AlrhaScoringGroupMember::class)->orderBy('sort_order');
    }

    public function isHunterPair(): bool
    {
        return $this->type === self::TYPE_HUNTER_PAIR;
    }

    public function isVarmintTriple(): bool
    {
        return $this->type === self::TYPE_VARMINT_TRIPLE;
    }
}
