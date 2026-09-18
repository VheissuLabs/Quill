<?php

namespace App\Http\Requests\Issues;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IssueLookupRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'except' => ['nullable', 'integer'],
        ];
    }

    public function term(): string
    {
        return trim($this->string('q')->toString());
    }
}
