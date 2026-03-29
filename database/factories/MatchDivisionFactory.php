<?php

namespace Database\Factories;

use App\Models\MatchDivision;
use App\Models\ShootingMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MatchDivision> */
class MatchDivisionFactory extends Factory
{
    protected $model = MatchDivision::class;

    public function definition(): array
    {
        return [
            'match_id' => ShootingMatch::factory(),
            'name' => fake()->randomElement(['Minor', 'Major', 'Open', 'Production']),
            'sort_order' => fake()->numberBetween(1, 5),
        ];
    }
}
