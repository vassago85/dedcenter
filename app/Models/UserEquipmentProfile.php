<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEquipmentProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'caliber',
        'action_brand',
        'bullet_brand_type',
        'bullet_weight',
        'barrel_brand_length',
        'trigger_brand',
        'stock_chassis_brand',
        'muzzle_brake_silencer_brand',
        'scope_brand_type',
        'scope_mount_brand',
        'bipod_brand',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public const EQUIPMENT_FIELDS = [
        'caliber',
        'action_brand',
        'bullet_brand_type',
        'bullet_weight',
        'barrel_brand_length',
        'trigger_brand',
        'stock_chassis_brand',
        'muzzle_brake_silencer_brand',
        'scope_brand_type',
        'scope_mount_brand',
        'bipod_brand',
    ];
}
