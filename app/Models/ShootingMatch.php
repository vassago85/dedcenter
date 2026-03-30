<?php

namespace App\Models;

use App\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ShootingMatch extends Model
{
    use HasFactory;
    protected $table = 'matches';

    protected $fillable = [
        'name',
        'date',
        'location',
        'status',
        'scoring_type',
        'side_bet_enabled',
        'elr_scoring_profile_id',
        'notes',
        'created_by',
        'organization_id',
        'entry_fee',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => MatchStatus::class,
            'side_bet_enabled' => 'boolean',
            'entry_fee' => 'decimal:2',
        ];
    }

    // ── Relationships ──

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function targetSets(): HasMany
    {
        return $this->hasMany(TargetSet::class, 'match_id');
    }

    public function squads(): HasMany
    {
        return $this->hasMany(Squad::class, 'match_id');
    }

    public function shooters(): HasManyThrough
    {
        return $this->hasManyThrough(Shooter::class, Squad::class, 'match_id', 'squad_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(MatchRegistration::class, 'match_id');
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(MatchDivision::class, 'match_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(MatchCategory::class, 'match_id');
    }

    public function elrStages(): HasMany
    {
        return $this->hasMany(ElrStage::class, 'match_id')->orderBy('sort_order');
    }

    public function elrScoringProfile(): BelongsTo
    {
        return $this->belongsTo(ElrScoringProfile::class, 'elr_scoring_profile_id');
    }


    // ── Computed Attributes ──

    public function getTotalShootersAttribute(): int
    {
        return $this->shooters()->count();
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === MatchStatus::Active;
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === MatchStatus::Completed;
    }

    public function isFree(): bool
    {
        return ! $this->entry_fee || (float) $this->entry_fee <= 0;
    }

    public function isPrs(): bool
    {
        return $this->scoring_type === 'prs';
    }

    public function isStandard(): bool
    {
        return $this->scoring_type === 'standard' || ! $this->scoring_type;
    }

    public function isElr(): bool
    {
        return $this->scoring_type === 'elr';
    }
}
