<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StagePosition extends Model
{
    protected $fillable = [
        'stage_id',
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(TargetSet::class, 'stage_id');
    }

    public function shotSequenceEntries(): HasMany
    {
        return $this->hasMany(StageShotSequence::class, 'position_id');
    }
}
