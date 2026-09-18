<?php

namespace App\Http\Requests\Palette;

use Illuminate\Foundation\Http\FormRequest;

class PaletteSearchRequest extends FormRequest
{
    /** @return array<string, mixed> */
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
