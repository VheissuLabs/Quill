<?php

namespace App\Http\Requests\Billing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowPublicInvoiceRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'orderId' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function squareOrderId(): ?string
    {
        return $this->validated('orderId');
    }
}
