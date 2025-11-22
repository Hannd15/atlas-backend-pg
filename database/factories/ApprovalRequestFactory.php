<?php

namespace Database\Factories;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApprovalRequest>
 */
class ApprovalRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(6),
            'description' => $this->faker->paragraph(),
            'requested_by' => User::factory(),
            'action_key' => 'noop',
            'action_payload' => ['requested' => $this->faker->word()],
            'status' => ApprovalRequest::STATUS_PENDING,
            'resolved_decision' => null,
            'resolved_at' => null,
        ];
    }
}
