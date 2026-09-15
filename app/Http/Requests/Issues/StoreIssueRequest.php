<?php

namespace App\Http\Requests\Issues;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $organization = $this->route('current_organization');

        abort_if(! $organization instanceof Organization, 404);

        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')->where('organization_id', $organization->id)],
            ...IssueRules::for($organization),
        ];
    }
}
