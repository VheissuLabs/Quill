<?php

namespace App\Http\Requests\Projects;

use App\Enums\ProjectVisibility;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $organization = $this->route('current_organization');

        abort_if(! $organization instanceof Organization, 404);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'visibility' => ['required', 'string', Rule::enum(ProjectVisibility::class)],
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where('organization_id', $organization->id),
            ],
            'team_ids' => ['array'],
            'team_ids.*' => [
                'integer',
                Rule::exists('teams', 'id')->where('organization_id', $organization->id),
            ],
        ];

        if ($this->user() && $organization->allowsInvoiceManagementBy($this->user())) {
            $rules['billing_rate'] = ['nullable', 'numeric', 'min:0', 'max:9999999999.99'];
        }

        return $rules;
    }
}
