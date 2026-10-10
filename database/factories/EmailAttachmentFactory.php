<?php

namespace Database\Factories;

use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmailAttachment> */
class EmailAttachmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'email_message_id' => EmailMessage::factory(),
            'filename' => 'screenshot.png',
            'content_type' => 'image/png',
            'size' => 1024,
            'path' => 'ticket-attachments/'.fake()->uuid().'/screenshot.png',
        ];
    }
}
