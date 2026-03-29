<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TargetSet extends Model
{
    protected $fillable = [
        'match_id',
        'label',
        'distance_meters',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'distance_meters' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(ShootingMatch::class, 'match_id');
    }

    public function gongs(): HasMany
    {
        return $this->hasMany(Gong::class);
    }
}
