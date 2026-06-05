<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The admin-facing source of truth for which gongs a division engages on a
 * given stage in ELR team gong-sequence mode. gong_start / gong_end are
 * 1-based ordinal positions within the stage (by target sort order), inclusive.
 * Saving these materialises the elr_division_targets pivot so the sequence
 * engine + scoring keep working unchanged.
 */
class ElrStageDivisionRange extends Model
{
    use HasFactory;

    protected $fillable = [
        'elr_stage_id',
        'match_division_id',
        'gong_start',
        'gong_end',
    ];

    protected function casts(): array
    {
        return [
            'gong_start' => 'integer',
            'gong_end' => 'integer',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ElrStage::class, 'elr_stage_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(MatchDivision::class, 'match_division_id');
    }
}
