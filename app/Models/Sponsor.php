<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Sponsor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'website_url',
        'contact_name',
        'contact_email',
        'short_description',
        'active',
        'assignable_by_match_director',
        'internal_notes',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'assignable_by_match_director' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Sponsor $sponsor) {
            if (empty($sponsor->slug)) {
                $sponsor->slug = Str::slug($sponsor->name);
                $i = 1;
                while (static::where('slug', $sponsor->slug)->exists()) {
                    $sponsor->slug = Str::slug($sponsor->name).'-'.$i++;
                }
            }
        });
    }

    // ── Relationships ──

    public function assignments(): HasMany
    {
        return $this->hasMany(SponsorAssignment::class);
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeCurrentlyValid($query)
    {
        $now = now();

        return $query->active()
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    public function scopeAssignableByMatchDirector($query)
    {
        return $query->where('assignable_by_match_director', true);
    }

    // ── Helpers ──

    public function isCurrentlyValid(): bool
    {
        if (! $this->active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $this->starts_at->isAfter($now)) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isBefore($now)) {
            return false;
        }

        return true;
    }

    public function hasLogo(): bool
    {
        return ! empty($this->logo_path);
    }
}
