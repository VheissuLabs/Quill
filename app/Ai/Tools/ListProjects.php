<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AssistantTool;
use App\Ai\Tools\Concerns\ScopedToCurrentOrganization;
use App\Models\Client;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListProjects implements AssistantTool
{
    use ScopedToCurrentOrganization;

    public function name(): string
    {
        return 'list_projects';
    }

    public function capability(): string
    {
        return 'List your projects and who owns each one.';
    }

    public function description(): Stringable|string
    {
        return 'List every project in the organization the user is currently working in, and whether a client or a team owns each. Use this to answer any question about projects, and to check whether a project already exists.';
    }

    public function handle(Request $request): Stringable|string
    {
        $organization = $this->organization();

        if ($organization === null) {
            return $this->withoutOrganization();
        }

        $projects = $organization->projects()->with('owner')->orderBy('name')->get();

        if ($projects->isEmpty()) {
            return "{$organization->name} has no projects yet.";
        }

        return $projects
            ->map(function (Project $project): string {
                $owner = $project->owner;

                $ownedBy = match (true) {
                    $owner instanceof Client => "owned by the client {$owner->name}",
                    $owner instanceof Team => "owned by the team {$owner->name}",
                    default => 'with no recorded owner',
                };

                return "- {$project->name} ({$ownedBy})";
            })
            ->prepend("Projects in {$organization->name}:")
            ->join("\n");
    }

    /** @return array<string, \Illuminate\JsonSchema\Types\Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
