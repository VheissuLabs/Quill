<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AssistantTool;
use App\Ai\Tools\Concerns\MatchesNames;
use App\Ai\Tools\Concerns\ScopedToCurrentOrganization;
use App\Enums\OrganizationPermission;
use App\Models\Client;
use App\Models\Organization;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class RenameClient implements AssistantTool
{
    use MatchesNames;
    use ScopedToCurrentOrganization;

    public function name(): string
    {
        return 'rename_client';
    }

    public function capability(): string
    {
        return 'Rename one of your clients.';
    }

    public function description(): Stringable|string
    {
        return 'Change the name of an existing client. Requires the client\'s current name and the new name. This only changes the name — it cannot move, merge or delete a client.';
    }

    public function handle(Request $request): Stringable|string
    {
        $organization = $this->organization();

        if ($organization === null) {
            return $this->withoutOrganization();
        }

        if (! $this->user->hasOrganizationPermission($organization, OrganizationPermission::UpdateClient)) {
            return $this->refused('rename a client');
        }

        $newName = trim((string) $request['new_name']);

        if ($newName === '') {
            return 'A new name is needed. Ask the user what the client should be called.';
        }

        $client = $this->resolveTarget($organization, (string) $request['client']);

        if (is_string($client)) {
            return $client;
        }

        if ($this->comparableName($client->name) === $this->comparableName($newName)) {
            return "{$client->name} is already called that, so nothing was changed.";
        }

        $clash = $organization->clients()->get()->first(
            fn (Client $other) => ! $other->is($client)
                && $this->comparableName($other->name) === $this->comparableName($newName)
        );

        if ($clash !== null) {
            return "{$organization->name} already has a client called {$clash->name}, so nothing was renamed.";
        }

        $previous = $client->name;

        $client->update(['name' => $newName]);

        return "Renamed the client {$previous} to {$client->name}.";
    }

    /** @return array<string, \Illuminate\JsonSchema\Types\Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'client' => $schema->string()->description('The current name of the client to rename.')->required(),
            'new_name' => $schema->string()->description('The name the client should have.')->required(),
        ];
    }

    protected function resolveTarget(Organization $organization, string $wanted): Client|string
    {
        $names = $organization->clients()->orderBy('name')->pluck('name');
        $matches = $this->matchingNames($names, $wanted, 'client');

        if ($matches->count() === 1) {
            return $organization->clients()->where('name', $matches->sole())->sole();
        }

        if ($matches->count() > 1) {
            return "More than one client matches \"{$wanted}\": ".
                $matches->join(', ').'. Nothing was renamed — ask which one.';
        }

        return "There is no client called {$wanted} in {$organization->name}, so nothing was renamed. ".
            ($names->isEmpty()
                ? 'The organization has no clients at all.'
                : 'The clients are: '.$names->join(', ').'.');
    }
}
