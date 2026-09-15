<?php

namespace App\Http\Requests\Issues;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIssueOrderRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $organization = $this->route('current_organization');

        abort_if(! $organization instanceof Organization, 404);

        return [
            'issue_ids' => ['required', 'array'],
            'issue_ids.*' => ['integer', 'distinct', Rule::exists('issues', 'id')->whereIn('project_id', $organization->projects()->pluck('id'))],
        ];
    }
}
