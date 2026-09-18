<?php

namespace App\Http\Requests\Organizations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationModulesRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'feedback_enabled' => ['required', 'boolean'],
            'feedback_boards_enabled' => ['required', 'boolean'],
            'feedback_voting_enabled' => ['required', 'boolean'],
            'development_enabled' => ['required', 'boolean'],
            'development_issues_enabled' => ['required', 'boolean'],
            'development_sprints_enabled' => ['required', 'boolean'],
            'development_workflow_enabled' => ['required', 'boolean'],
            'teams_enabled' => ['required', 'boolean'],
        ];
    }
}
