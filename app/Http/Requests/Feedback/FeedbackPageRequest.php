<?php

namespace App\Http\Requests\Feedback;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FeedbackPageRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'comments' => ['sometimes', 'in:newest,oldest'],
        ];
    }

    public function newestFirst(): bool
    {
        return $this->query('comments') === 'newest';
    }
}
