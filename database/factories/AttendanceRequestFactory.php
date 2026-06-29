<?php

namespace Database\Factories;

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRequest>
 */
class AttendanceRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 month', '+1 month');
        $endDate = (clone $startDate)->modify('+'.fake()->numberBetween(0, 2).' days');

        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement([
                AttendanceStatus::Sick,
                AttendanceStatus::Leave,
                AttendanceStatus::Permit,
            ]),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'description' => fake()->sentence(),
            'proof_photo' => 'data:image/png;base64,'.base64_encode('proof'),
            'approval_status' => AttendanceRequestStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function approved(?User $reviewer = null): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => AttendanceRequestStatus::Approved,
            'reviewed_by' => $reviewer?->id ?? User::factory()->administrator(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    public function rejected(?User $reviewer = null): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => AttendanceRequestStatus::Rejected,
            'reviewed_by' => $reviewer?->id ?? User::factory()->administrator(),
            'reviewed_at' => now(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }
}
