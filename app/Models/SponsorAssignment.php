<?php

namespace App\Models;

use App\Enums\PlacementKey;
use App\Enums\SponsorScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorAssignment extends Model
{
    protected $fillable = [
        'sponsor_id',
        'scope_type',
        'scope_id',
        'placement_key',
        'label_override',
        'active',
        'display_order',
        'starts_at',
        'ends_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scope_type' => SponsorScope::class,
            'placement_key' => PlacementKey::class,
            'active' => 'boolean',
            'display_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    // ── Relationships ──

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
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

    public function scopeForPlacement($query, PlacementKey $key)
    {
        return $query->where('placement_key', $key);
    }

    public function scopePlatform($query)
    {
        return $query->where('scope_type', SponsorScope::Platform);
    }

    public function scopeForMatch($query, int $matchId)
    {
        return $query->where('scope_type', SponsorScope::Match)
            ->where('scope_id', $matchId);
    }

    public function scopeForMatchbook($query, int $matchBookId)
    {
        return $query->where('scope_type', SponsorScope::Matchbook)
            ->where('scope_id', $matchBookId);
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

    /**
     * Get the display label for this assignment (override or default).
     */
    public function displayLabel(): string
    {
        return $this->label_override ?? 'Sponsored by';
    }
}
