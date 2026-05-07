<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'office_id' => Office::factory(),
            'date' => fake()->date(),
            'in_at' => fake()->dateTimeThisMonth(),
            'out_at' => fake()->optional()->dateTimeThisMonth(),
            'in_latitude' => fake()->latitude(-8, -1),
            'in_longitude' => fake()->longitude(95, 141),
            'proof_photo' => null,
            'status' => fake()->randomElement(AttendanceStatus::cases()),
        ];
    }
}
