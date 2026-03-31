<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'stage_id',
        'sequence_number',
        'target_name',
        'target_reference',
        'distance_meters',
        'target_size_mm',
        'target_size_mrad',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'distance_meters' => 'integer',
            'target_size_mm' => 'decimal:2',
            'target_size_mrad' => 'decimal:3',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(TargetSet::class, 'stage_id');
    }
}
