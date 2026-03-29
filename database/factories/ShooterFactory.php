<?php

namespace Database\Factories;

use App\Models\Shooter;
use App\Models\Squad;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shooter> */
class ShooterFactory extends Factory
{
    protected $model = Shooter::class;

    public function definition(): array
    {
        return [
            'squad_id' => Squad::factory(),
            'name' => fake()->name(),
            'bib_number' => (string) fake()->numberBetween(1, 999),
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }
}
