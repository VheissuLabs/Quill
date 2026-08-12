<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AssistantTool;
use App\Ai\Tools\Concerns\MatchesNames;
use App\Ai\Tools\Concerns\ScopedToCurrentOrganization;
use App\Enums\OrganizationPermission;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateProject implements AssistantTool
{
    use MatchesNames;
    use ScopedToCurrentOrganization;

    public function name(): string
    {
        return 'create_project';
    }

    public function capability(): string
    {
        return 'Create a new project owned by one of your clients or teams.';
    }

    public function description(): Stringable|string
    {
        return 'Create a new project. Requires a name and the client or team that owns it — every project has an owner, so ask which if the user has not said. Name either "client" or "team", not both.';
    }

    public function handle(Request $request): Stringable|string
    {
        $organization = $this->organization();

        if ($organization === null) {
            return $this->withoutOrganization();
        }

        if (! $this->user->hasOrganizationPermission($organization, OrganizationPermission::CreateProject)) {
            return $this->refused('create a project');
        }

        $name = trim((string) $request['name']);

        if ($name === '') {
            return 'A project needs a name. Ask the user what it should be called.';
        }

        $existing = $organization->projects()->get()->first(
            fn (Project $project) => $this->comparableName($project->name) === $this->comparableName($name)
        );

        if ($existing !== null) {
            return "{$organization->name} already has a project called {$existing->name}, so nothing was created.";
        }

        $owner = $this->resolveOwner($organization, $request['client'] ?? null, $request['team'] ?? null);

        if (is_string($owner)) {
            return $owner;
        }

        $project = Project::create([
            'organization_id' => $organization->id,
            'owner_type' => $owner::class,
            'owner_id' => $owner->id,
            'name' => $name,
        ]);

        $ownedBy = $owner instanceof Client
            ? "owned by the client {$owner->name}"
            : "owned by the team {$owner->name}";

        return "Created the project {$project->name} in {$organization->name}, {$ownedBy}.";
    }

    /** @return array<string, \Illuminate\JsonSchema\Types\Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The name of the project.')->required(),
            'client' => $schema->string()->description('The client that owns this project. Give this or team, not both.'),
            'team' => $schema->string()->description('The team that owns this project. Give this or client, not both.'),
        ];
    }

    protected function resolveOwner(Organization $organization, ?string $clientName, ?string $teamName): Client|Team|string
    {
        $hasClient = filled($clientName);
        $hasTeam = filled($teamName);

        if ($hasClient && $hasTeam) {
            return 'A project is owned by a client or by a team, not both. Ask which one.';
        }

        if (! $hasClient && ! $hasTeam) {
            return 'Every project has an owner. Ask whether this project belongs to a client or to one of the teams.';
        }

        return $hasClient
            ? $this->matchOne($organization, 'client', (string) $clientName)
            : $this->matchOne($organization, 'team', (string) $teamName);
    }

    protected function matchOne(Organization $organization, string $noun, string $wanted): Client|Team|string
    {
        $query = $noun === 'client' ? $organization->clients() : $organization->teams();
        $names = $query->orderBy('name')->pluck('name');
        $matches = $this->matchingNames($names, $wanted, $noun);

        if ($matches->count() === 1) {
            return $noun === 'client'
                ? $organization->clients()->where('name', $matches->sole())->sole()
                : $organization->teams()->where('name', $matches->sole())->sole();
        }

        if ($matches->count() > 1) {
            return "More than one {$noun} matches \"{$wanted}\": ".
                $matches->join(', ').'. No project was created — ask which one.';
        }

        return "There is no {$noun} called {$wanted} in {$organization->name}, so no project was created. ".
            ($names->isEmpty()
                ? "The organization has no {$noun}s at all."
                : "The {$noun}s are: ".$names->join(', ').'.');
    }
}
