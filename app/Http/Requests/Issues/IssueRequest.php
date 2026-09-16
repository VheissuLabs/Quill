<?php

namespace App\Http\Requests\Issues;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class IssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('current_organization');

        if (! $organization instanceof Organization) {
            return true;
        }

        $project = $organization->projects()->whereKey($this->input('project_id'))->first();

        return $project === null || Gate::allows('view', $project);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $organization = $this->route('current_organization');

        abort_if(! $organization instanceof Organization, 404);

        $editing = $this->route('issue') !== null;
        $sent = $editing ? ['sometimes'] : [];

        return [
            ...$editing ? [] : [
                'project_id' => ['required', 'integer', Rule::exists('projects', 'id')->where('organization_id', $organization->id)],
            ],
            ...$editing ? [
                'status' => ['sometimes', 'required', 'string', Rule::in([IssueStatus::Backlog->value, IssueStatus::Done->value, IssueStatus::Cancelled->value])],
                'board_column_id' => ['sometimes', 'required', 'integer', $this->ownedBy($organization, 'board_columns')],
            ] : [],
            'title' => [...$sent, 'required', 'string', 'max:255'],
            'description' => [...$sent, 'nullable', 'string', 'max:10000'],
            'priority' => [...$sent, 'required', 'string', Rule::enum(IssuePriority::class)],
            'points' => [...$sent, 'nullable', 'integer', 'min:0', 'max:1000'],
            'team_id' => [...$sent, 'nullable', 'integer', Rule::exists('teams', 'id')->where('organization_id', $organization->id)],
            'sprint_id' => [...$sent, 'nullable', 'integer', $this->ownedBy($organization, 'sprints')],
            'assignee_id' => [...$sent, 'nullable', 'integer', Rule::exists('organization_members', 'user_id')->where('organization_id', $organization->id)],
        ];
    }

    private function ownedBy(Organization $organization, string $table): Exists
    {
        return Rule::exists($table, 'id')->where(fn ($query) => $query
            ->where(fn ($query) => $query
                ->where('owner_type', $organization->getMorphClass())
                ->where('owner_id', $organization->getKey()))
            ->orWhere(fn ($query) => $query
                ->where('owner_type', (new Team)->getMorphClass())
                ->whereIn('owner_id', $organization->teams()->pluck('id'))));
    }
}
