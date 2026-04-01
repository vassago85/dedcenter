<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchBookStage extends Model
{
    protected $fillable = [
        'match_book_id',
        'stage_number',
        'name',
        'brief',
        'notes',
        'engagement_rules',
        'compulsory_sequence',
        'timed',
        'time_limit',
        'round_count',
        'positions_count',
        'movement_meters',
        'prop_image_path',
        'sequence_display_format',
    ];

    protected function casts(): array
    {
        return [
            'stage_number' => 'integer',
            'compulsory_sequence' => 'boolean',
            'timed' => 'boolean',
            'time_limit' => 'integer',
            'round_count' => 'integer',
            'positions_count' => 'integer',
            'movement_meters' => 'integer',
        ];
    }

    // ── Relationships ──

    public function matchBook(): BelongsTo
    {
        return $this->belongsTo(MatchBook::class);
    }

    public function shots(): HasMany
    {
        return $this->hasMany(MatchBookShot::class)->orderBy('shot_number');
    }

    // ── Helpers ──

    public function usesBlockDisplay(): bool
    {
        return $this->sequence_display_format === 'blocks';
    }

    public function usesTableDisplay(): bool
    {
        return $this->sequence_display_format === 'table';
    }

    /**
     * Get unique gongs from the shots (for the targets table display).
     */
    public function uniqueGongs(): \Illuminate\Support\Collection
    {
        return $this->shots
            ->unique(fn ($shot) => $shot->gong_label.'|'.$shot->gong_name.'|'.$shot->distance_m.'|'.$shot->size_mm)
            ->values();
    }

    /**
     * Get shots grouped by position (for block display).
     */
    public function shotsByPosition(): \Illuminate\Support\Collection
    {
        $groups = collect();
        $currentPosition = null;
        $currentGroup = null;

        foreach ($this->shots->sortBy('shot_number') as $shot) {
            if ($shot->position !== $currentPosition) {
                if ($currentGroup !== null) {
                    $groups->push($currentGroup);
                }
                $currentGroup = collect([$shot]);
                $currentPosition = $shot->position;
            } else {
                $currentGroup->push($shot);
            }
        }

        if ($currentGroup !== null && $currentGroup->isNotEmpty()) {
            $groups->push($currentGroup);
        }

        return $groups;
    }

    /**
     * Count unique target positions.
     */
    public function uniquePositionCount(): int
    {
        return $this->shots->pluck('position')->unique()->count();
    }
}
