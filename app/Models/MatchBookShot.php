<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchBookShot extends Model
{
    protected $fillable = [
        'match_book_stage_id',
        'shot_number',
        'position',
        'gong_label',
        'gong_name',
        'distance_m',
        'size_mm',
        'shape',
        'mil',
        'moa',
    ];

    protected function casts(): array
    {
        return [
            'shot_number' => 'integer',
            'position' => 'integer',
            'distance_m' => 'decimal:2',
            'size_mm' => 'integer',
            'mil' => 'decimal:2',
            'moa' => 'decimal:2',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(MatchBookStage::class, 'match_book_stage_id');
    }

    // ── Auto-calculation ──

    protected static function booted(): void
    {
        static::saving(function (MatchBookShot $shot) {
            if ($shot->size_mm && $shot->distance_m > 0) {
                $shot->mil = round($shot->size_mm / $shot->distance_m, 2);
                $shot->moa = round(($shot->size_mm / $shot->distance_m) * 3.43775, 2);
            }
        });
    }

    /**
     * Full display label for this shot (e.g. "1A - BIG").
     */
    public function fullLabel(): string
    {
        if ($this->gong_name) {
            return $this->gong_label.' - '.$this->gong_name;
        }

        return $this->gong_label;
    }
}
