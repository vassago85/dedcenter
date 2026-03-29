<?php

namespace Database\Factories;

use App\Models\Gong;
use App\Models\TargetSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Gong> */
class GongFactory extends Factory
{
    protected $model = Gong::class;

    public function definition(): array
    {
        return [
            'target_set_id' => TargetSet::factory(),
            'number' => fake()->numberBetween(1, 10),
            'label' => fake()->optional()->word(),
            'multiplier' => fake()->randomElement([1.00, 1.50, 2.00, 3.00]),
        ];
    }
}
