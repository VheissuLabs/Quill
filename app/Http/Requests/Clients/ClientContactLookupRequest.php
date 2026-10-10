<?php

namespace App\Http\Requests\Clients;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ClientContactLookupRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function term(): string
    {
        return trim($this->string('q')->toString());
    }
}
