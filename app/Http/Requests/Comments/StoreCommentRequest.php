<?php

namespace App\Http\Requests\Comments;

use App\Models\Feature;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $feature = $this->route('feature');

        abort_if(! $feature instanceof Feature, 404);

        return [
            'body' => ['required', 'string', 'max:5000'],
            'internal' => ['boolean'],
            'parent_id' => ['nullable', 'integer', Rule::exists('comments', 'id')->where('feature_id', $feature->id)],
            'mentions' => ['array', 'max:20'],
            'mentions.*' => ['integer', 'distinct', Rule::in($feature->mentionableUsers()->pluck('id'))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mentions.*.in' => __('You can only mention people involved with this request.'),
        ];
    }
}
