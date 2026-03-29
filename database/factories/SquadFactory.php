<?php

namespace Database\Factories;

use App\Models\ShootingMatch;
use App\Models\Squad;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Squad> */
class SquadFactory extends Factory
{
    protected $model = Squad::class;

    public function definition(): array
    {
        return [
            'match_id' => ShootingMatch::factory(),
            'name' => 'Squad '.fake()->randomLetter(),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
