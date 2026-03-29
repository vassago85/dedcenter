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

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isMember(): bool
    {
        return $this->role === 'member';
    }

    public function isOrgAdmin(Organization $organization): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->organizations()->where('organization_id', $organization->id)->exists();
    }
}
