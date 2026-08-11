<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AssistantTool;
use App\Ai\Tools\Concerns\MatchesNames;
use App\Ai\Tools\Concerns\ScopedToCurrentOrganization;
use App\Enums\OrganizationPermission;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateTeam implements AssistantTool
{
    use MatchesNames;
    use ScopedToCurrentOrganization;

    public function name(): string
    {
        return 'create_team';
    }

    public function capability(): string
    {
        return 'Create a new team, optionally belonging to one of your clients.';
    }

    public function description(): Stringable|string
    {
        return 'Create a new team in the organization the user is currently working in. Only call this when the user has given a name for the team. Optionally name a client the team should belong to; otherwise the team belongs to the organization directly.';
    }

    public function handle(Request $request): Stringable|string
    {
        $organization = $this->organization();

        if ($organization === null) {
            return $this->withoutOrganization();
        }

        if (! $this->user->hasOrganizationPermission($organization, OrganizationPermission::CreateTeam)) {
            return $this->refused('create a team');
        }

        $name = trim((string) $request['name']);

        if ($name === '') {
            return 'A team needs a name. Ask the user what the team should be called.';
        }

        $existing = $organization->teams()->get()->first(
            fn (Team $team) => $this->comparableName($team->name) === $this->comparableName($name)
        );

        if ($existing !== null) {
            return "{$organization->name} already has a team called {$existing->name}, so nothing was created.";
        }

        $parent = $this->resolveParent($organization, $request['client'] ?? null);

        if (is_string($parent)) {
            return $parent;
        }

        $team = Team::create([
            'organization_id' => $organization->id,
            'parent_type' => $parent::class,
            'parent_id' => $parent->id,
            'name' => $name,
        ]);

        $belongsTo = $parent instanceof Client
            ? "under the client {$parent->name}"
            : 'under the organization directly';

        return "Created the team {$team->name} in {$organization->name}, {$belongsTo}.";
    }

    /** @return array<string, \Illuminate\JsonSchema\Types\Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The name of the team.')->required(),
            'client' => $schema->string()->description('Optional. The name of an existing client this team should belong to.'),
        ];
    }

    protected function resolveParent(Organization $organization, ?string $clientName): Organization|Client|string
    {
        if ($clientName === null || trim($clientName) === '') {
            return $organization;
        }

        $clients = $organization->clients()->orderBy('name')->pluck('name');
        $matches = $this->matchingNames($clients, $clientName, 'client');

        if ($matches->count() === 1) {
            return $organization->clients()->where('name', $matches->sole())->sole();
        }

        if ($matches->count() > 1) {
            return "More than one client in {$organization->name} matches \"{$clientName}\": ".
                $matches->join(', ').'. No team was created — ask which one.';
        }

        return "There is no client called {$clientName} in {$organization->name}, so no team was created. ".
            ($clients->isEmpty()
                ? 'The organization has no clients at all.'
                : 'The clients are: '.$clients->join(', ').'.');
    }
}
