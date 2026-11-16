<?php

namespace App\Http\Requests\Billing;

use App\Models\BillingSetting;
use App\Support\SquareClient;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SquareWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $settings = $this->route('billingSetting');

        return $settings instanceof BillingSetting
            && filled($settings->square_webhook_signature_key)
            && SquareClient::verifySignature(
                (string) $settings->square_webhook_signature_key,
                $settings->webhookUrl(),
                $this->getContent(),
                $this->header('x-square-hmacsha256-signature'),
            );
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string'],
            'data.object.payment' => ['nullable', 'array'],
        ];
    }

    public function isPaymentEvent(): bool
    {
        return in_array($this->validated('type'), ['payment.created', 'payment.updated'], true)
            && is_array($this->validated('data.object.payment'));
    }

    /** @return array<string, mixed> */
    public function payment(): array
    {
        return (array) $this->validated('data.object.payment');
    }
}
