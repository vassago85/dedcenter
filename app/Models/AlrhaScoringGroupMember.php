<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membership row for an ALRHA peer scoring group. Exactly one of
 * team_id / shooter_id is set per row:
 *   - hunter_pair    → team_id (2 teams per group)
 *   - varmint_triple → shooter_id (3 shooters per group)
 */
class AlrhaScoringGroupMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'alrha_scoring_group_id',
        'team_id',
        'shooter_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AlrhaScoringGroup::class, 'alrha_scoring_group_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function shooter(): BelongsTo
    {
        return $this->belongsTo(Shooter::class);
    }
}
