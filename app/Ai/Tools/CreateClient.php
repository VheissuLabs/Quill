<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ScopedToCurrentOrganization;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateClient implements Tool
{
    use ScopedToCurrentOrganization;

    public function name(): string
    {
        return 'create_client';
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

        if (! Gate::forUser($this->user)->allows('create', [Client::class, $organization])) {
            return $this->refused('create a client');
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

    protected function comparableName(string $name): string
    {
        $stripped = preg_replace('/[^\p{L}\p{N}\s]/u', '', mb_strtolower(trim($name)));

        return trim(preg_replace('/\s+/', ' ', $stripped ?? $name) ?? $name);
    }

    protected function normalizeTeamName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/\bteams?\b/', '', $name) ?? $name;
        $name = preg_replace('/^the\s+/', '', trim($name)) ?? $name;

        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }

    protected function resolveParent(Organization $organization, ?string $teamName): Organization|Team|string
    {
        if ($teamName === null || trim($teamName) === '') {
            return $organization;
        }

        $teams = $organization->teams()->orderBy('name')->get();

        $wanted = $this->normalizeTeamName($teamName);

        $matches = $teams->filter(
            fn (Team $team) => $this->normalizeTeamName($team->name) === $wanted
        );

        if ($matches->isEmpty()) {
            $matches = $teams->filter(
                fn (Team $team) => str_contains($this->normalizeTeamName($team->name), $wanted)
            );
        }

        if ($matches->count() === 1) {
            return $matches->sole();
        }

        if ($matches->count() > 1) {
            return "More than one team in {$organization->name} matches \"{$teamName}\": ".
                $matches->pluck('name')->join(', ').'. No client was created — ask which one.';
        }

        return "There is no team called {$teamName} in {$organization->name}, so no client was created. ".
            ($teams->isEmpty()
                ? 'The organization has no teams at all.'
                : 'The teams are: '.$teams->pluck('name')->join(', ').'.');
    }
}
