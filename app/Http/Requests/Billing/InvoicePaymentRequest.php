<?php

namespace App\Http\Requests\Billing;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoicePaymentRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'paid_on' => ['required', 'date'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
