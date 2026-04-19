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
        'team_id',
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

    public function isDq(): bool
    {
        return $this->status === 'dq';
    }

    public function isNoShow(): bool
    {
        return $this->status === 'no_show';
    }

    /**
     * True when this row's result still needs to be claimed by a real
     * DeadCenter account — either because no user is linked at all
     * (walk-in entry), or because the "linked" user is an import
     * placeholder (see User::isImportPlaceholder()) with no reachable
     * email / login. Drives the "Claim your result" banner and chips
     * on the scoreboard and the /claim page filters.
     *
     * Note: DQ / no-show status is orthogonal — callers decide whether
     * to offer a claim for those rows. This method only answers
     * "is the identity side of this row claimable?".
     */
    public function isUnclaimedResult(): bool
    {
        if ($this->user_id === null) {
            return true;
        }

        $user = $this->relationLoaded('user') ? $this->user : $this->user()->first();

        return $user === null || $user->isImportPlaceholder();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompeting($query)
    {
        return $query->whereIn('status', ['active', 'withdrawn']);
    }

    public function scopePresent($query)
    {
        return $query->whereNotIn('status', ['no_show']);
    }

    /**
     * Rows where the result hasn't been claimed by a real DeadCenter
     * account yet — mirrors isUnclaimedResult() as a SQL predicate so
     * the /claim page and admin tooling can filter efficiently.
     *
     *   user_id IS NULL
     *   OR linked user has an @import.invalid placeholder email
     */
    public function scopeUnclaimedResult($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('shooters.user_id')
                ->orWhereHas('user', function ($uq) {
                    $uq->where('email', 'like', '%'.User::IMPORT_PLACEHOLDER_EMAIL_SUFFIX);
                });
        });
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'active' => 'Active',
            'withdrawn' => 'Withdrawn',
            'dq' => 'DQ',
            'no_show' => 'No Show',
            default => ucfirst($this->status ?? 'active'),
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'active' => 'text-green-400',
            'withdrawn' => 'text-amber-400',
            'dq' => 'text-red-400',
            'no_show' => 'text-zinc-500',
            default => 'text-muted',
        };
    }

    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            'active' => 'bg-green-500/10 text-green-400 border-green-500/20',
            'withdrawn' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
            'dq' => 'bg-red-500/10 text-red-400 border-red-500/20',
            'no_show' => 'bg-zinc-500/10 text-zinc-400 border-zinc-500/20',
            default => 'bg-surface-2 text-muted border-border',
        };
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

    public function prsStageResults(): HasMany
    {
        return $this->hasMany(PrsStageResult::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(MatchDivision::class, 'match_division_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(MatchCategory::class, 'match_category_shooter');
    }

    public function sideBetMatches(): BelongsToMany
    {
        return $this->belongsToMany(ShootingMatch::class, 'side_bet_shooters', 'shooter_id', 'match_id');
    }

    public function disqualifications(): HasMany
    {
        return $this->hasMany(Disqualification::class);
    }

    public function matchDq(): HasMany
    {
        return $this->hasMany(Disqualification::class)->whereNull('target_set_id');
    }

    public function stageDqs(): HasMany
    {
        return $this->hasMany(Disqualification::class)->whereNotNull('target_set_id');
    }

    // ── Computed Attributes ──

    public function getTotalScoreAttribute(): float
    {
        return (float) $this->scores()
            ->where('is_hit', true)
            ->join('gongs', 'scores.gong_id', '=', 'gongs.id')
            ->join('target_sets', 'gongs.target_set_id', '=', 'target_sets.id')
            ->selectRaw('COALESCE(SUM(COALESCE(target_sets.distance_multiplier, 1) * gongs.multiplier), 0) as total')
            ->value('total');
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
