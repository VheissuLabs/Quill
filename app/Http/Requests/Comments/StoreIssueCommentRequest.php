<?php

namespace App\Http\Requests\Comments;

use App\Models\Issue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueCommentRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $issue = $this->route('issue');

        abort_if(! $issue instanceof Issue, 404);

        return [
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', Rule::exists('comments', 'id')
                ->where('commentable_type', $issue->getMorphClass())
                ->where('commentable_id', $issue->getKey())],
            'mentions' => ['array', 'max:20'],
            'mentions.*' => ['integer', 'distinct', Rule::in($issue->mentionableUsers()->pluck('id'))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mentions.*.in' => __('You can only mention people in this organization.'),
        ];
    }
}
