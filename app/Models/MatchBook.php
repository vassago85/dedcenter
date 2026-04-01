<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchBook extends Model
{
    protected $fillable = [
        'match_id',
        'subtitle',
        'cover_image_path',
        'venue',
        'gps_coordinates',
        'venue_maps_link',
        'range_maps_link',
        'hospital_maps_link',
        'directions',
        'match_director_name',
        'match_director_phone',
        'match_director_email',
        'emergency_hospital_name',
        'emergency_hospital_address',
        'emergency_phone',
        'program',
        'procedures',
        'safety',
        'timetable',
        'match_breakdown',
        'welcome_note',
        'custom_notes',
        'sponsor_acknowledgement',
        'primary_color',
        'secondary_color',
        'accent_color',
        'text_color',
        'highlight_color',
        'include_summary_cards',
        'include_dope_card',
        'include_score_sheet',
        'match_type',
        'federation_logo_path',
        'club_logo_path',
        'status',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'timetable' => 'array',
            'include_summary_cards' => 'boolean',
            'include_dope_card' => 'boolean',
            'include_score_sheet' => 'boolean',
            'generated_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function match(): BelongsTo
    {
        return $this->belongsTo(ShootingMatch::class, 'match_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(MatchBookLocation::class)->orderBy('display_order');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(MatchBookStage::class)->orderBy('stage_number');
    }

    // ── Helpers ──

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isRimfire(): bool
    {
        return $this->match_type === 'rimfire';
    }

    public function isCenterfire(): bool
    {
        return $this->match_type === 'centerfire' || ! $this->match_type;
    }

    /**
     * Get CSS custom properties for theming.
     */
    public function cssVariables(): array
    {
        return [
            '--color-primary' => $this->primary_color ?? '#1e3a5f',
            '--color-secondary' => $this->secondary_color ?? '#475569',
            '--color-accent' => $this->accent_color ?? '#f59e0b',
            '--color-text' => $this->text_color ?? '#1f2937',
            '--color-highlight' => $this->highlight_color ?? '#166534',
        ];
    }
}
