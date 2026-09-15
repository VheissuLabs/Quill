<?php

namespace App\Http\Requests\Features;

use App\Enums\FeatureStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeatureStatusRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(FeatureStatus::class)],
        ];
    }
}
