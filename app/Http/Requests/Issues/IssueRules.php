<?php

namespace App\Http\Requests\Issues;

use App\Enums\IssuePriority;
use App\Models\Organization;
use Illuminate\Validation\Rule;

/**
 * The validation rules shared by every request that writes an issue's editable fields.
 */
class IssueRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function for(Organization $organization): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['required', 'string', Rule::enum(IssuePriority::class)],
            'points' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('organization_id', $organization->id)],
            'sprint_id' => [
                'nullable',
                'integer',
                Rule::exists('sprints', 'id')->whereIn('team_id', $organization->teams()->pluck('id')),
            ],
            'assignee_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_members', 'user_id')->where('organization_id', $organization->id),
            ],
        ];
    }
}
