<?php

namespace App\Http\Requests\Issues;

use App\Enums\IssueStatus;
use Illuminate\Foundation\Http\FormRequest;

class IssueIndexRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /** @return array{status: string|null, project: string|null, assignee: string|null} */
    public function filters(): array
    {
        return [
            'status' => IssueStatus::tryFrom((string) $this->query('status'))?->value,
            'project' => $this->string('project')->toString() ?: null,
            'assignee' => $this->string('assignee')->toString() ?: null,
        ];
    }
}
