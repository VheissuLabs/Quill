<?php

namespace Database\Factories;

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailDirection;
use App\Models\Comment;
use App\Models\EmailMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<EmailMessage> */
class EmailMessageFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'comment_id' => Comment::factory(),
            'direction' => EmailDirection::Inbound,
            'delivery_status' => EmailDeliveryStatus::Received,
            'from_email' => fake()->safeEmail(),
            'from_name' => fake()->name(),
            'to' => ['support@vheissulabs.com'],
            'cc' => [],
            'message_id' => Str::uuid().'@mail.example.com',
        ];
    }

    public function outbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => EmailDirection::Outbound,
            'delivery_status' => EmailDeliveryStatus::Queued,
            'from_email' => 'support@vheissulabs.com',
        ]);
    }
}
