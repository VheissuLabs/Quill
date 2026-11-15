<?php

namespace App\Http\Requests\Billing;

use App\Enums\InvoiceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InvoiceIndexRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string'],
            'client' => ['nullable', 'integer'],
        ];
    }

    /** @return array{status: string|null, client: int|null} */
    public function filters(): array
    {
        return [
            'status' => InvoiceStatus::tryFrom((string) $this->query('status'))?->value,
            'client' => $this->integer('client') ?: null,
        ];
    }
}
