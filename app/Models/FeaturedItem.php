<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeaturedItem extends Model
{
    protected $fillable = [
        'type',
        'item_id',
        'placement',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ── Relationships ──

    public function getItemAttribute()
    {
        return match ($this->type) {
            'match' => ShootingMatch::find($this->item_id),
            'organization' => Organization::find($this->item_id),
            default => null,
        };
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeInPlacement($query, string $placement)
    {
        return $query->where('placement', $placement);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
