<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (squad × station × team). Materialises the rotation order
 * for an ELR squad — i.e. "for Squad A at Station Warrior, Team 3 shoots
 * first, then Team 1, Team 5, Team 7, Team 2". The leadoff shooter
 * inside each team is also captured so the UI can alternate "wind reader"
 * duty per station.
 *
 * Maintained by the match director up front and (rarely) overridden
 * mid-day if a team withdraws. The default rotation rule — last team
 * moves to first, everyone shifts back — is implemented as a service
 * helper that writes these rows for the next station; this table just
 * records the result so the scoring UI never has to recompute.
 */
class ElrSquadTeamOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'squad_id',
        'team_id',
        'elr_stage_id',
        'position',
        'shooter_first_id',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function squad(): BelongsTo
    {
        return $this->belongsTo(Squad::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ElrStage::class, 'elr_stage_id');
    }

    public function shooterFirst(): BelongsTo
    {
        return $this->belongsTo(Shooter::class, 'shooter_first_id');
    }
}
