<?php

namespace Database\Factories;

use App\Models\MatchCategory;
use App\Models\ShootingMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class MatchCategoryFactory extends Factory
{
    protected $model = MatchCategory::class;

    public function definition(): array
    {
        $name = fake()->randomElement(['Overall', 'Ladies', 'Junior', 'Senior']);

        return [
            'match_id' => ShootingMatch::factory(),
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'sort_order' => fake()->numberBetween(1, 5),
        ];
    }
}
