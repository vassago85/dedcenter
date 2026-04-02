<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'notification_preferences',
        'email_verification_code',
        'email_verification_code_expires_at',
        'accepted_terms_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_code_expires_at' => 'datetime',
            'accepted_terms_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
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

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
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

    public function wantsNotification(string $type): bool
    {
        $prefs = $this->notification_preferences ?? [];
        return ($prefs[$type] ?? true) !== false;
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'owner' => 'Site Owner',
            'shooter' => 'Shooter',
            default => ucfirst(str_replace('_', ' ', $this->role)),
        };
    }

    // ── Email Verification ──

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function generateVerificationCode(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->update([
            'email_verification_code' => $code,
            'email_verification_code_expires_at' => now()->addMinutes(30),
        ]);

        return $code;
    }

    public function verifyWithCode(string $code): bool
    {
        if ($this->email_verification_code !== $code) {
            return false;
        }

        if ($this->email_verification_code_expires_at?->isPast()) {
            return false;
        }

        $this->update([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_code_expires_at' => null,
        ]);

        return true;
    }
}
