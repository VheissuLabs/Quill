<?php

namespace App\Http\Requests\Issues;

use App\Enums\IssueLinkType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateIssueLinkRequest extends IssueLinkRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(IssueLinkType::class)],
            'inverse' => ['required', 'boolean'],
        ];
    }
}
