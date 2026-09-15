<?php

namespace App\Http\Requests\Organizations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UpdateOrganizationLogoRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'logo' => ['required', File::types(['png', 'jpg', 'jpeg', 'webp'])->max(2 * 1024)],
        ];
    }
}
