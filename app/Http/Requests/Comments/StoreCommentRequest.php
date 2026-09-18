<?php

namespace App\Http\Requests\Comments;

use App\Models\Comment;
use App\Models\Feedback;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $feedback = $this->route('feedback');

        if (! $feedback instanceof Feedback) {
            return true;
        }

        $parent = Comment::whereKey($this->input('parent_id'))->first();

        if (! $this->boolean('internal') && ! ($parent->is_internal ?? false)) {
            return true;
        }

        return Gate::allows('commentInternally', $feedback);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $feedback = $this->route('feedback');

        abort_if(! $feedback instanceof Feedback, 404);

        return [
            'body' => ['required', 'string', 'max:5000'],
            'internal' => ['boolean'],
            'parent_id' => ['nullable', 'integer', Rule::exists('comments', 'id')
                ->where('commentable_type', $feedback->getMorphClass())
                ->where('commentable_id', $feedback->getKey())],
            'mentions' => ['array', 'max:20'],
            'mentions.*' => ['integer', 'distinct', Rule::in($feedback->mentionableUsers()->pluck('id'))],
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
