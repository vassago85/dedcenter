<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = fake()->company().' '.fake()->randomElement(['League', 'Club', 'Competition']);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 9999),
            'description' => fake()->optional()->sentence(),
            'type' => fake()->randomElement(['league', 'club', 'competition', 'challenge']),
            'parent_id' => null,
            'status' => 'active',
            'created_by' => User::factory(),
            'logo_path' => null,
            'primary_color' => '#dc2626',
            'secondary_color' => '#1e293b',
            'hero_text' => null,
            'hero_description' => null,
            'portal_enabled' => false,
            'portal_entitled' => false,
            'portal_ad_rights' => false,
            'best_of' => null,
            'entry_fee_default' => null,
        ];
    }

    public function league(): static
    {
        return $this->state(fn () => ['type' => 'league']);
    }

    public function club(): static
    {
        return $this->state(fn () => ['type' => 'club']);
    }

    public function competition(): static
    {
        return $this->state(fn () => ['type' => 'competition']);
    }

    public function challenge(): static
    {
        return $this->state(fn () => ['type' => 'challenge']);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function withBestOf(int $count): static
    {
        return $this->state(fn () => ['best_of' => $count]);
    }

    public function withEntryFee(float $fee): static
    {
        return $this->state(fn () => ['entry_fee_default' => $fee]);
    }

    public function withPortal(): static
    {
        return $this->state(fn () => [
            'portal_enabled' => true,
            'hero_text' => fake()->catchPhrase(),
            'hero_description' => fake()->sentence(),
        ]);
    }

    public function withPortalAdRights(): static
    {
        return $this->state(fn () => ['portal_ad_rights' => true]);
    }
}
