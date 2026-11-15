<?php

namespace App\Http\Requests\Billing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BillingSettingRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'invoice_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'default_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_notes' => ['nullable', 'string', 'max:5000'],
            'default_terms' => ['nullable', 'string', 'max:5000'],
            'from_address' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
