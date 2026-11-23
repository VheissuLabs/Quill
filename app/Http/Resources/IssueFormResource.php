<?php

namespace App\Http\Resources;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\OrganizationCapability;
use App\Enums\SprintStatus;
use App\Events\IssuesChanged;
use App\Events\MembersChanged;
use App\Events\ModulesChanged;
use App\Events\ProjectsChanged;
use App\Events\TeamsChanged;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Organization */
class IssueFormResource extends JsonResource
{
    public function __construct(Organization $resource, private User $user, private ?Team $team)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'projects' => $this->projects()
                ->visibleTo($this->user)
                ->orderBy('name')
                ->get()
                ->map(fn (Project $project) => ['id' => $project->id, 'name' => $project->name, 'slug' => $project->slug]),
            'teams' => $this->teams()
                ->with('columns')
                ->orderBy('name')
                ->get()
                ->map(fn (Team $team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'slug' => $team->slug,
                    'columns' => BoardColumnResource::collection($team->columns),
                ]),
            'sprints' => SprintResource::collection(
                Sprint::query()->onBoardFor($this->resource, $this->team)
                    ->where('status', '!=', SprintStatus::Completed)
                    ->with([
                        'owner',
                        'issues' => fn ($query) => $query
                            ->with(['project', 'assignee', 'sprint', 'team.columns', 'boardColumn'])
                            ->orderBy('position')
                            ->orderBy('number'),
                    ])
                    ->withCount('issues')
                    ->withSum('issues', 'points')
                    ->orderBy('starts_at')
                    ->get()
                    ->filter(fn (Sprint $sprint) => $sprint->runsSprints())
                    ->values()
            ),
            'members' => UserResource::collection($this->members()->orderBy('name')->get()),
            'sprintsEnabled' => $this->team
                ? $this->team->canUse(OrganizationCapability::Sprints)
                : $this->resource->canUse(OrganizationCapability::Sprints),
            'boardColumns' => BoardColumnResource::collection($this->user->boardOwnerFor($this->resource)->columns),
            'statuses' => IssueStatus::options(),
            'priorities' => IssuePriority::options(),
        ];
    }

    /** @return array<string, mixed> */
    public function liveProps(): array
    {
        $form = $this->resolve();
        $organizationId = $this->resource->getKey();

        return [
            ...$form,
            'projects' => ProjectsChanged::live($form['projects'], $organizationId),
            'teams' => TeamsChanged::live($form['teams'], $organizationId),
            'sprints' => IssuesChanged::live($form['sprints'], $organizationId),
            'members' => MembersChanged::live($form['members'], $organizationId),
            'sprintsEnabled' => ModulesChanged::live($form['sprintsEnabled'], $organizationId, TeamsChanged::class),
            'boardColumns' => TeamsChanged::live($form['boardColumns'], $organizationId),
        ];
    }
}
