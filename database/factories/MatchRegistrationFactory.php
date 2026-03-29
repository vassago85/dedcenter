<?php

namespace Database\Factories;

use App\Models\MatchRegistration;
use App\Models\ShootingMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MatchRegistration> */
class MatchRegistrationFactory extends Factory
{
    protected $model = MatchRegistration::class;

    public function definition(): array
    {
        return [
            'match_id' => ShootingMatchFactory::new(),
            'user_id' => UserFactory::new(),
            'payment_reference' => 'DC-' . strtoupper(fake()->lastName()) . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'payment_status' => 'pending_payment',
            'amount' => fake()->randomFloat(2, 50, 500),
        ];
    }

    public function proofSubmitted(): static
    {
        return $this->state(fn () => [
            'payment_status' => 'proof_submitted',
            'proof_of_payment_path' => 'proof-of-payment/test/fake-pop.jpg',
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'payment_status' => 'confirmed',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'payment_status' => 'rejected',
        ]);
    }
}
