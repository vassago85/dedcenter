<?php

namespace Database\Factories;

use App\Enums\MatchStatus;
use App\Models\ShootingMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShootingMatch> */
class ShootingMatchFactory extends Factory
{
    protected $model = ShootingMatch::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Match',
            'date' => fake()->dateTimeBetween('now', '+30 days'),
            'location' => fake()->city(),
            'status' => MatchStatus::Draft,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => MatchStatus::Active]);
    }

    public function completed(): static
    {
        return $this->state(['status' => MatchStatus::Completed]);
    }

    public function prs(): static
    {
        return $this->state(['scoring_type' => 'prs']);
    }

    public function elr(): static
    {
        return $this->state(['scoring_type' => 'elr']);
    }
}
