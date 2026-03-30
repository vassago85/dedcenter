<?php

namespace App\Models;

use App\Enums\ElrStageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ElrStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'label',
        'stage_type',
        'elr_scoring_profile_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'stage_type' => ElrStageType::class,
            'sort_order' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(ShootingMatch::class, 'match_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(ElrTarget::class)->orderBy('sort_order');
    }

    public function scoringProfile(): BelongsTo
    {
        return $this->belongsTo(ElrScoringProfile::class, 'elr_scoring_profile_id');
    }

    public function isLadder(): bool
    {
        return $this->stage_type === ElrStageType::Ladder;
    }

    public function resolvedProfile(): ?ElrScoringProfile
    {
        return $this->scoringProfile ?? $this->match?->elrScoringProfile;
    }
}
