<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ── Relationships ──

    public function registrations(): HasMany
    {
        return $this->hasMany(MatchRegistration::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_admins')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function ownedOrganizations(): BelongsToMany
    {
        return $this->organizations()->wherePivot('role', 'owner');
    }

    // ── Helpers ──

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isAdmin(): bool
    {
        return $this->isOwner();
    }

    public function isShooter(): bool
    {
        return $this->role === 'shooter';
    }

    public function orgRole(Organization $organization): ?string
    {
        return $this->organizations()
            ->where('organization_id', $organization->id)
            ->first()?->pivot->role;
    }

    public function isOrgOwner(Organization $organization): bool
    {
        return $this->isOwner() || $this->orgRole($organization) === 'owner';
    }

    public function isOrgMatchDirector(Organization $organization): bool
    {
        return $this->isOwner() || in_array($this->orgRole($organization), ['owner', 'match_director']);
    }

    public function isOrgRangeOfficer(Organization $organization): bool
    {
        return $this->isOwner() || in_array($this->orgRole($organization), ['owner', 'match_director', 'range_officer']);
    }

    public function isOrgAdmin(Organization $organization): bool
    {
        return $this->isOrgRangeOfficer($organization);
    }

    public function canScore(): bool
    {
        return $this->isOwner() || $this->organizations()->exists();
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'owner' => 'Site Owner',
            'shooter' => 'Shooter',
            default => ucfirst(str_replace('_', ' ', $this->role)),
        };
    }
}
