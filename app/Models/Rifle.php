<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rifle extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'caliber', 'action_brand', 'barrel_brand_length',
        'trigger_brand', 'stock_chassis_brand', 'muzzle_brake_silencer_brand',
        'scope_brand_type', 'scope_mount_brand', 'bipod_brand', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ammoLoads(): HasMany
    {
        return $this->hasMany(AmmoLoad::class);
    }

    public function defaultAmmo(): ?AmmoLoad
    {
        return $this->ammoLoads()->where('is_default', true)->first();
    }

    public function summary(): string
    {
        $parts = array_filter([
            $this->caliber,
            $this->action_brand,
            $this->barrel_brand_length,
        ]);
        return implode(' · ', $parts) ?: $this->name;
    }
}
