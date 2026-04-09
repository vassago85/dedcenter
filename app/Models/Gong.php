<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gong extends Model
{
    use HasFactory;
    protected $fillable = [
        'target_set_id',
        'number',
        'label',
        'distance_meters',
        'target_size',
        'target_size_mm',
        'multiplier',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'distance_meters' => 'integer',
            'multiplier' => 'decimal:2',
            'target_size_mm' => 'decimal:2',
        ];
    }

    public function targetSet(): BelongsTo
    {
        return $this->belongsTo(TargetSet::class);
    }
}
