<?php

namespace App\Http\Resources;

use App\Models\Client;
use App\Models\Feedback;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;

class PaletteResultResource extends JsonResource
{
    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    public function toArray(Request $request): array
    {
        return match (true) {
            $this->resource instanceof Issue => $this->issue($this->resource),
            $this->resource instanceof Feedback => $this->feedback($this->resource),
            $this->resource instanceof Project => $this->project($this->resource),
            $this->resource instanceof Sprint => $this->sprint($this->resource),
            $this->resource instanceof Team => $this->team($this->resource),
            $this->resource instanceof Client => $this->client($this->resource),
            default => throw new RuntimeException('Unsearchable record in palette results.'),
        };
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function issue(Issue $issue): array
    {
        return [
            'type' => 'issue',
            'title' => $issue->title,
            'subtitle' => "{$issue->project->name} #{$issue->number}",
            'url' => route('issues.show', [
                'current_organization' => $issue->project->organization,
                'issue' => $issue,
            ]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function feedback(Feedback $feedback): array
    {
        return [
            'type' => 'feedback',
            'title' => $feedback->title,
            'subtitle' => $feedback->project->name,
            'url' => route('portal.feedback.show', [
                'organization' => $feedback->project->organization,
                'project' => $feedback->project,
                'feedback' => $feedback,
            ]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function project(Project $project): array
    {
        return [
            'type' => 'project',
            'title' => $project->name,
            'subtitle' => $project->client?->name,
            'url' => route('portal.projects.show', [
                'organization' => $project->organization,
                'project' => $project,
            ]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function sprint(Sprint $sprint): array
    {
        return [
            'type' => 'sprint',
            'title' => $sprint->name,
            'subtitle' => $sprint->owner->name,
            'url' => route('sprints.show', [
                'current_organization' => $sprint->organization(),
                'sprint' => $sprint,
            ]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function team(Team $team): array
    {
        return [
            'type' => 'team',
            'title' => $team->name,
            'subtitle' => null,
            'url' => route('teams.edit', [
                'current_organization' => $team->organization,
                'team' => $team,
            ]),
        ];
    }

    /** @return array{type: string, title: string, subtitle: string|null, url: string} */
    private function client(Client $client): array
    {
        return [
            'type' => 'client',
            'title' => $client->name,
            'subtitle' => null,
            'url' => route('clients.edit', [
                'current_organization' => $client->organization,
                'client' => $client,
            ]),
        ];
    }
}
