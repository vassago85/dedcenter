<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchDivision extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'name',
        'description',
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

    public function shooters(): HasMany
    {
        return $this->hasMany(Shooter::class);
    }

    /**
     * Targets this division is allowed to engage in an ELR match.
     *
     * Empty = "no restriction, this division shoots every target on every
     * station". The scoring service treats absence of pivot rows as an
     * unrestricted division (legacy default), and only filters when the
     * match director has populated it (e.g. Minor T1\u2013T3, Major T2\u2013T4).
     */
    public function elrTargets(): BelongsToMany
    {
        return $this->belongsToMany(ElrTarget::class, 'elr_division_targets');
    }
}
