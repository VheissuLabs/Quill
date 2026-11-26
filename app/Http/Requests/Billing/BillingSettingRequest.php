<?php

namespace App\Http\Requests\Billing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BillingSettingRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function validated($key = null, $default = null): array
    {
        return array_filter(
            parent::validated(),
            fn (mixed $value, string $field): bool => filled($value) || ! in_array($field, ['square_access_token', 'square_webhook_signature_key'], true),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'invoice_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'default_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'square_environment' => ['sometimes', 'required', 'in:sandbox,production'],
            'square_access_token' => ['nullable', 'string', 'max:500'],
            'square_location_id' => ['nullable', 'string', 'max:100'],
            'square_webhook_signature_key' => ['nullable', 'string', 'max:500'],
            'payment_instructions' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
