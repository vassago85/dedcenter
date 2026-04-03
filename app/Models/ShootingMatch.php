<?php

namespace App\Models;

use App\Enums\AdvertisingMode;
use App\Enums\MatchStatus;
use App\Enums\MdPackageStatus;
use App\Enums\PlacementKey;
use App\Enums\Province;
use App\Enums\SponsorScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShootingMatch extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'matches';

    protected $fillable = [
        'name',
        'date',
        'location',
        'status',
        'scoring_type',
        'scores_published',
        'side_bet_enabled',
        'royal_flush_enabled',
        'concurrent_relays',
        'max_squad_size',
        'elr_scoring_profile_id',
        'notes',
        'public_bio',
        'created_by',
        'organization_id',
        'season_id',
        'entry_fee',
        'device_lock_mode',
        'corrections_pin',
        'image_url',
        'province',
        'registration_closes_at',
        'advertising_mode',
        'md_package_status',
        'full_package_brand_id',
        'md_package_price',
        'individual_placement_price',
    ];

    protected $hidden = [
        'corrections_pin',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => MatchStatus::class,
            'scores_published' => 'boolean',
            'side_bet_enabled' => 'boolean',
            'royal_flush_enabled' => 'boolean',
            'concurrent_relays' => 'integer',
            'max_squad_size' => 'integer',
            'entry_fee' => 'decimal:2',
            'province' => Province::class,
            'registration_closes_at' => 'datetime',
            'advertising_mode' => AdvertisingMode::class,
            'md_package_status' => MdPackageStatus::class,
            'md_package_price' => 'decimal:2',
            'individual_placement_price' => 'decimal:2',
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

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * Per-match match directors and range officers (users may still shoot elsewhere).
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'match_staff', 'match_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function targetSets(): HasMany
    {
        return $this->hasMany(TargetSet::class, 'match_id');
    }

    public function squads(): HasMany
    {
        return $this->hasMany(Squad::class, 'match_id');
    }

    public function sideBetShooters(): BelongsToMany
    {
        return $this->belongsToMany(Shooter::class, 'side_bet_shooters', 'match_id', 'shooter_id');
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

    public function prsShots(): HasMany
    {
        return $this->hasMany(PrsShotScore::class, 'match_id');
    }

    public function prsResults(): HasMany
    {
        return $this->hasMany(PrsStageResult::class, 'match_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ScoreAuditLog::class, 'match_id');
    }

    public function disqualifications(): HasMany
    {
        return $this->hasMany(Disqualification::class, 'match_id');
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(MatchCustomField::class, 'match_id');
    }

    public function matchBook(): HasOne
    {
        return $this->hasOne(MatchBook::class, 'match_id');
    }

    public function fullPackageBrand(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class, 'full_package_brand_id');
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

    public function isPreRegistration(): bool
    {
        return $this->status === MatchStatus::PreRegistration;
    }

    public function isRegistrationOpen(): bool
    {
        return $this->status === MatchStatus::RegistrationOpen;
    }

    public function isRegistrationClosed(): bool
    {
        return $this->status === MatchStatus::RegistrationClosed;
    }

    public function isSquaddingOpen(): bool
    {
        return $this->status === MatchStatus::SquaddingOpen;
    }

    public function canRegister(): bool
    {
        return in_array($this->status, [
            MatchStatus::PreRegistration,
            MatchStatus::RegistrationOpen,
        ]);
    }

    public function canSquad(): bool
    {
        return $this->status === MatchStatus::SquaddingOpen;
    }

    public function isFree(): bool
    {
        return ! $this->entry_fee || (float) $this->entry_fee <= 0;
    }

    public function defaultRegistrationCloseDate(): ?\Carbon\Carbon
    {
        return $this->date?->copy()->subHours(72);
    }

    public function isRegistrationPastDeadline(): bool
    {
        $deadline = $this->registration_closes_at ?? $this->defaultRegistrationCloseDate();

        return $deadline && now()->gte($deadline);
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

    public function scoresArePublic(): bool
    {
        return (bool) ($this->scores_published ?? true);
    }

    /**
     * Group relays into concurrent blocks based on concurrent_relays setting.
     * E.g. concurrent_relays=2, 8 squads → [[1,2],[3,4],[5,6],[7,8]] (using squad IDs).
     */
    public function concurrentRelayGroups(): array
    {
        $squads = $this->squads()->orderBy('sort_order')->pluck('id')->all();
        $size = max(1, $this->concurrent_relays ?? 2);

        return array_chunk($squads, $size);
    }

    /**
     * Active matches whose event date is today — used for “Live Now” so past-dated
     * matches do not stay promoted after their day (before cron marks them completed).
     */
    public function scopeActiveLiveToday(Builder $query): Builder
    {
        return $query
            ->where('status', MatchStatus::Active)
            ->whereDate('date', today());
    }

    public function userCanEditInOrg(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->isOwner()) {
            return true;
        }
        if ($this->organization_id && $user->isOrgMatchDirector($this->organization)) {
            return true;
        }

        return (int) $this->created_by === (int) $user->id;
    }

    public function userCanManageSquadding(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->isOwner()) {
            return true;
        }
        if ($this->organization_id && $user->isOrgRangeOfficer($this->organization)) {
            return true;
        }

        return (int) $this->created_by === (int) $user->id;
    }

    // ── Advertising Helpers ──

    public function isFullPackageSold(): bool
    {
        return $this->full_package_brand_id !== null;
    }

    public function hasIndividualPlacements(): bool
    {
        return SponsorAssignment::forMatch($this->id)
            ->whereIn('placement_key', PlacementKey::advertisingPlacements())
            ->whereNotNull('sponsor_id')
            ->active()
            ->exists();
    }

    public function soldPlacementKeys(): array
    {
        return SponsorAssignment::forMatch($this->id)
            ->whereIn('placement_key', PlacementKey::advertisingPlacements())
            ->whereNotNull('sponsor_id')
            ->active()
            ->pluck('placement_key')
            ->all();
    }

    public function availablePlacementKeys(): array
    {
        if ($this->isFullPackageSold()) {
            return [];
        }

        $sold = collect($this->soldPlacementKeys())->map(fn ($k) => $k->value)->all();

        return collect(PlacementKey::advertisingPlacements())
            ->filter(fn ($k) => ! in_array($k->value, $sold))
            ->values()
            ->all();
    }

    public function isPackageAvailable(): bool
    {
        return $this->advertising_mode === AdvertisingMode::PublicOpen
            && ! $this->isFullPackageSold()
            && ! $this->hasIndividualPlacements();
    }
}
