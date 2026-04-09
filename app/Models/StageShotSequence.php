<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageShotSequence extends Model
{
    protected $table = 'stage_shot_sequence';

    protected $fillable = [
        'stage_id',
        'shot_number',
        'position_id',
        'gong_id',
    ];

    protected function casts(): array
    {
        return [
            'shot_number' => 'integer',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(TargetSet::class, 'stage_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(StagePosition::class, 'position_id');
    }

    public function gong(): BelongsTo
    {
        return $this->belongsTo(Gong::class);
    }
}
