<?php

namespace Database\Factories;

use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Office>
 */
class OfficeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'address' => fake()->address(),
            'latitude' => fake()->latitude(-8, -1),
            'longitude' => fake()->longitude(95, 141),
            'radius' => fake()->numberBetween(20, 100),
            'photo' => null,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the office is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
