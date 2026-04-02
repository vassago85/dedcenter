<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchCustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'label',
        'type',
        'options',
        'is_required',
        'show_on_scoreboard',
        'show_on_results',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'show_on_scoreboard' => 'boolean',
            'show_on_results' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(ShootingMatch::class, 'match_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(MatchRegistrationCustomValue::class);
    }
}
