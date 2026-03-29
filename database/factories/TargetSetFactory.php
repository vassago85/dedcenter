<?php

namespace Database\Factories;

use App\Models\ShootingMatch;
use App\Models\TargetSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TargetSet> */
class TargetSetFactory extends Factory
{
    protected $model = TargetSet::class;

    public function definition(): array
    {
        return [
            'match_id' => ShootingMatch::factory(),
            'label' => 'Stage '.fake()->numberBetween(1, 10),
            'distance_meters' => fake()->randomElement([50, 100, 150, 200, 300]),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
