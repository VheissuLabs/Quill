<?php

namespace App\Http\Requests\Projects;

use App\Models\IssueType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'issue_type_id' => [
                'required',
                Rule::exists(IssueType::class, 'id')
                    ->where('organization_id', $this->user()->current_organization_id)
                    ->whereNull('archived_at'),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'acceptance_criteria' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
