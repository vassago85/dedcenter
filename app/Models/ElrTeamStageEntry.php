<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lifecycle of one team's turn at one ELR stage in team gong-sequence mode.
 * See the create_elr_team_stage_entries migration for column rationale.
 */
class ElrTeamStageEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'elr_stage_id',
        'squad_id',
        'first_shooter_id',
        'position',
        'started_at',
        'completed_at',
        'timed_out',
        'device_id',
        'team_total_score',
        'shooter_1_id',
        'shooter_1_score',
        'shooter_2_id',
        'shooter_2_score',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'timed_out' => 'boolean',
            'team_total_score' => 'decimal:2',
            'shooter_1_id' => 'integer',
            'shooter_1_score' => 'decimal:2',
            'shooter_2_id' => 'integer',
            'shooter_2_score' => 'decimal:2',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ElrStage::class, 'elr_stage_id');
    }

    public function squad(): BelongsTo
    {
        return $this->belongsTo(Squad::class);
    }

    public function firstShooter(): BelongsTo
    {
        return $this->belongsTo(Shooter::class, 'first_shooter_id');
    }
}
