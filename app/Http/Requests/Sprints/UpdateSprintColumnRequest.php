<?php

namespace App\Http\Requests\Sprints;

use App\Models\Issue;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateSprintColumnRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('current_organization');

        if (! $organization instanceof Organization) {
            return true;
        }

        return Issue::whereIn('id', Arr::wrap($this->input('issue_ids')))
            ->whereIn('project_id', $organization->projects()->select('id'))
            ->get()
            ->every(fn (Issue $issue) => Gate::allows('update', $issue));
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $organization = $this->route('current_organization');

        abort_if(! $organization instanceof Organization, 404);

        return [
            'issue_ids' => ['present', 'array'],
            'issue_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('issues', 'id')->whereIn('project_id', $organization->projects()->pluck('id')),
            ],
        ];
    }
}
