<?php

namespace Database\Factories;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApprovalRequestRecipient>
 */
class ApprovalRequestRecipientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approval_request_id' => ApprovalRequest::factory(),
            'user_id' => User::factory(),
            'decision' => null,
            'comment' => null,
            'decision_at' => null,
        ];
    }
}
