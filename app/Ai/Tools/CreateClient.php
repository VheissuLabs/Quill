<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AssistantTool;
use App\Ai\Tools\Concerns\MatchesNames;
use App\Ai\Tools\Concerns\ScopedToCurrentOrganization;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateClient implements AssistantTool
{
    use MatchesNames;
    use ScopedToCurrentOrganization;

    public function name(): string
    {
        return 'create_client';
    }

    public function capability(): string
    {
        return 'Create a new client, optionally held by one of your teams.';
    }

    public function description(): Stringable|string
    {
        return 'Create a new client in the organization the user is currently working in. Only call this when the user has given a name for the client. Optionally name a team that should hold the client; otherwise the organization holds it directly.';
    }

    public function handle(Request $request): Stringable|string
    {
        $organization = $this->organization();

        if ($organization === null) {
            return $this->withoutOrganization();
        }

        if (! $this->user->can('client:create')) {
            return $this->refused('create a client', 'client:create');
        }

        $name = trim((string) $request['name']);

        if ($name === '') {
            return 'A client needs a name. Ask the user what the client is called.';
        }

        $existing = $organization->clients()->get()->first(
            fn (Client $client) => $this->comparableName($client->name) === $this->comparableName($name)
        );

        if ($existing !== null) {
            return "{$organization->name} already has a client called {$existing->name}, so nothing was created.";
        }

        $parent = $this->resolveParent($organization, $request['team'] ?? null);

        if (is_string($parent)) {
            return $parent;
        }

        $client = Client::create([
            'organization_id' => $organization->id,
            'parent_type' => $parent::class,
            'parent_id' => $parent->id,
            'name' => $name,
        ]);

        $heldBy = $parent instanceof Team
            ? "held by the team {$parent->name}"
            : 'held by the organization directly';

        return "Created the client {$client->name} in {$organization->name}, {$heldBy}.";
    }

    /** @return array<string, \Illuminate\JsonSchema\Types\Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The name of the client company.')->required(),
            'team' => $schema->string()->description('Optional. The name of an existing team that should hold this client.'),
        ];
    }

    protected function resolveParent(Organization $organization, ?string $teamName): Organization|Team|string
    {
        if ($teamName === null || trim($teamName) === '') {
            return $organization;
        }

        $teams = $organization->teams()->orderBy('name')->pluck('name');
        $matches = $this->matchingNames($teams, $teamName, 'team');

        if ($matches->count() === 1) {
            return $organization->teams()->where('name', $matches->sole())->sole();
        }

        if ($matches->count() > 1) {
            return "More than one team in {$organization->name} matches \"{$teamName}\": ".
                $matches->join(', ').'. No client was created — ask which one.';
        }

        return "There is no team called {$teamName} in {$organization->name}, so no client was created. ".
            ($teams->isEmpty()
                ? 'The organization has no teams at all.'
                : 'The teams are: '.$teams->join(', ').'.');
    }
}
