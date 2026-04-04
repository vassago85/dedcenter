<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmmoLoad extends Model
{
    use HasFactory;

    protected $fillable = [
        'rifle_id', 'name', 'bullet_brand_type', 'bullet_weight', 'muzzle_velocity', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function rifle(): BelongsTo
    {
        return $this->belongsTo(Rifle::class);
    }

    public function summary(): string
    {
        $parts = array_filter([
            $this->bullet_brand_type,
            $this->bullet_weight,
            $this->muzzle_velocity ? "@ {$this->muzzle_velocity}" : null,
        ]);
        return implode(' ', $parts) ?: $this->name;
    }
}
