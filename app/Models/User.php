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

    /**
     * Email suffix used by importers (currently just
     * RoyalFlushEquipmentImportService) when they auto-create a User row
     * for an imported shooter whose real email isn't known. These users
     * are unreachable — they can't log in, they can't receive email, and
     * their profile page is meaningless — so for anything that asks
     * "is this a real claimable identity?" we treat them as placeholders.
     *
     * Keep this in sync with importEmailForRow() in
     * RoyalFlushEquipmentImportService.
     */
    public const IMPORT_PLACEHOLDER_EMAIL_SUFFIX = '@import.invalid';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'notification_preferences',
        'email_verification_code',
        'email_verification_code_expires_at',
        'accepted_terms_at',
        'onboarded_at',
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
            'onboarded_at' => 'datetime',
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
            ->withPivot(['is_owner', 'is_match_director', 'is_range_officer', 'is_shooter'])
            ->withTimestamps();
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function equipmentProfiles(): HasMany
    {
        return $this->hasMany(UserEquipmentProfile::class);
    }

    public function rifles(): HasMany
    {
        return $this->hasMany(Rifle::class);
    }

    public function ownedOrganizations(): BelongsToMany
    {
        return $this->organizations()->wherePivot('is_owner', true);
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

    public function isMatchDirector(): bool
    {
        return $this->role === 'match_director';
    }

    public function isAdmin(): bool
    {
        return $this->isOwner() || $this->isMatchDirector();
    }

    public function isShooter(): bool
    {
        return $this->role === 'shooter';
    }

    /**
     * True when this user was auto-created by an importer as a placeholder
     * for a shooter whose real email wasn't known. These users cannot log
     * in and aren't reachable, so the scoreboard + claim flows should
     * treat shooters linked to them as unclaimed.
     */
    public function isImportPlaceholder(): bool
    {
        return is_string($this->email)
            && str_ends_with(strtolower($this->email), self::IMPORT_PLACEHOLDER_EMAIL_SUFFIX);
    }

    public function isOnboarded(): bool
    {
        return $this->onboarded_at !== null;
    }

    /**
     * All active role keys for this user in the given organization.
     */
    public function orgRoles(Organization $organization): array
    {
        $pivot = $this->organizations()
            ->where('organization_id', $organization->id)
            ->first()?->pivot;

        if (! $pivot) {
            return [];
        }

        $roles = [];
        if ($pivot->is_owner) {
            $roles[] = 'owner';
        }
        if ($pivot->is_match_director) {
            $roles[] = 'match_director';
        }
        if ($pivot->is_range_officer) {
            $roles[] = 'range_officer';
        }
        if ($pivot->is_shooter) {
            $roles[] = 'shooter';
        }

        return $roles;
    }

    public function hasOrgRole(Organization $organization, string $role): bool
    {
        $key = "is_{$role}";

        return $this->organizations()
            ->where('organization_id', $organization->id)
            ->wherePivot($key, true)
            ->exists();
    }

    public function isOrgOwner(Organization $organization): bool
    {
        return $this->isOwner() || $this->hasOrgRole($organization, 'owner');
    }

    public function isOrgMatchDirector(Organization $organization): bool
    {
        return $this->isOwner() || $this->isMatchDirector()
            || $this->hasOrgRole($organization, 'owner')
            || $this->hasOrgRole($organization, 'match_director');
    }

    public function isOrgRangeOfficer(Organization $organization): bool
    {
        return $this->isOwner() || $this->isMatchDirector()
            || $this->hasOrgRole($organization, 'owner')
            || $this->hasOrgRole($organization, 'match_director')
            || $this->hasOrgRole($organization, 'range_officer');
    }

    public function isOrgAdmin(Organization $organization): bool
    {
        return $this->isOrgRangeOfficer($organization);
    }

    public function canScore(): bool
    {
        return $this->isOwner() || $this->isMatchDirector()
            || $this->organizations()
                ->where(function ($q) {
                    $q->where('organization_admins.is_owner', true)
                        ->orWhere('organization_admins.is_match_director', true)
                        ->orWhere('organization_admins.is_range_officer', true);
                })
                ->exists();
    }

    public function wantsNotification(string $type): bool
    {
        $prefs = $this->notification_preferences ?? [];
        return ($prefs[$type] ?? true) !== false;
    }

    public function wantsEmailNotification(string $type): bool
    {
        $prefs = $this->notification_preferences ?? [];
        return ($prefs["email_{$type}"] ?? false) !== false;
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'owner' => 'Site Owner',
            'match_director' => 'Match Director',
            'shooter' => 'Shooter',
            default => ucfirst(str_replace('_', ' ', $this->role)),
        };
    }

    // ── Password Reset ──

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
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
        $this->refresh();

        $code = trim($code);

        if ($this->email_verification_code !== $code) {
            return false;
        }

        if ($this->email_verification_code_expires_at?->isPast()) {
            return false;
        }

        // email_verified_at is not mass-assignable; plain update() would strip it and only clear the PIN.
        $this->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_code_expires_at' => null,
        ])->save();

        return true;
    }
}
