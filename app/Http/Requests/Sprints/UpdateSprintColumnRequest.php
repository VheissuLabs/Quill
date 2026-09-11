<?php

namespace App\Http\Requests\Sprints;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSprintColumnRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
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
