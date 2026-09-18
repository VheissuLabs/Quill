<?php

namespace App\Http\Requests\Portal;

use App\Enums\FeedbackStatus;
use Illuminate\Foundation\Http\FormRequest;

class ProjectBoardRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function status(): ?FeedbackStatus
    {
        return FeedbackStatus::tryFrom((string) $this->query('status'));
    }

    public function sort(): string
    {
        return $this->query('sort') === 'new' ? 'new' : 'top';
    }

    public function search(): ?string
    {
        return trim($this->string('q')->toString()) ?: null;
    }
}
