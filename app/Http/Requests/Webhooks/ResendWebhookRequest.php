<?php

namespace App\Http\Requests\Webhooks;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResendWebhookRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string'],
            'data.email_id' => ['required_if:type,email.received', 'string'],
        ];
    }

    public function isReceivedEmail(): bool
    {
        return $this->validated('type') === 'email.received';
    }

    public function emailId(): string
    {
        return (string) $this->validated('data.email_id');
    }
}
