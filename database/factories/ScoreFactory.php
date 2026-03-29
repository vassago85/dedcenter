<?php

namespace Database\Factories;

use App\Models\Gong;
use App\Models\Score;
use App\Models\Shooter;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Score> */
class ScoreFactory extends Factory
{
    protected $model = Score::class;

    public function definition(): array
    {
        return [
            'shooter_id' => Shooter::factory(),
            'gong_id' => Gong::factory(),
            'is_hit' => fake()->boolean(60),
            'device_id' => fake()->uuid(),
            'recorded_at' => now(),
        ];
    }

    public function hit(): static
    {
        return $this->state(['is_hit' => true]);
    }

    public function miss(): static
    {
        return $this->state(['is_hit' => false]);
    }
}
