<?php

namespace App\Http\Requests\Feedback;

use App\Enums\FeedbackType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeedbackRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['sometimes', 'required', Rule::enum(FeedbackType::class)],
        ];
    }
}
