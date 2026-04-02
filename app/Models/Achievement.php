<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Achievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'label',
        'description',
        'category',
        'scope',
        'is_repeatable',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_repeatable' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function isRepeatable(): bool
    {
        return $this->category === 'repeatable';
    }

    public function isLifetime(): bool
    {
        return $this->category === 'lifetime';
    }

    public function isMatchSpecial(): bool
    {
        return $this->category === 'match_special';
    }

    public static function bySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->where('is_active', true)->first();
    }
}
