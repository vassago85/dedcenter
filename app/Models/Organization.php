<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'parent_id',
        'status',
        'created_by',
        'logo_path',
        'primary_color',
        'secondary_color',
        'hero_text',
        'hero_description',
        'portal_enabled',
        'best_of',
        'entry_fee_default',
        'bank_name',
        'bank_account_holder',
        'bank_account_number',
        'bank_branch_code',
        'season_standings_enabled',
        'royal_flush_enabled',
        'province',
    ];

    protected function casts(): array
    {
        return [
            'best_of' => 'integer',
            'entry_fee_default' => 'decimal:2',
            'portal_enabled' => 'boolean',
            'season_standings_enabled' => 'boolean',
            'royal_flush_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $org) {
            if (empty($org->slug)) {
                $org->slug = Str::slug($org->name);
                $i = 1;
                while (static::where('slug', $org->slug)->exists()) {
                    $org->slug = Str::slug($org->name) . '-' . $i++;
                }
            }
        });
    }

    // ── Relationships ──

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_admins')
            ->withPivot(['is_owner', 'is_match_director', 'is_range_officer', 'is_shooter'])
            ->withTimestamps();
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ShootingMatch::class, 'organization_id');
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // ── Helpers ──

    public function isLeague(): bool
    {
        return $this->type === 'league';
    }

    public function isClub(): bool
    {
        return $this->type === 'club';
    }

    public function isCompetition(): bool
    {
        return $this->type === 'competition';
    }

    public function isChallenge(): bool
    {
        return $this->type === 'challenge';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRoyalFlushOrg(): bool
    {
        return (bool) $this->royal_flush_enabled;
    }

    public function hasPortal(): bool
    {
        return $this->portal_enabled && $this->isActive();
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->admins()->wherePivot('is_owner', true)->where('user_id', $user->id)->exists();
    }

    public function userCanManage(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->admins()->where('user_id', $user->id)->exists();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get all match IDs under this org and its child orgs (for league leaderboards).
     */
    public function allMatchIds(): array
    {
        $orgIds = collect([$this->id]);

        if ($this->isLeague()) {
            $orgIds = $orgIds->merge($this->children()->pluck('id'));
        }

        return ShootingMatch::whereIn('organization_id', $orgIds)->pluck('id')->toArray();
    }
}
