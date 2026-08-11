<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ScopedToCurrentOrganization;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListClients implements Tool
{
    use ScopedToCurrentOrganization;

    public function name(): string
    {
        return 'list_clients';
    }

    public function description(): Stringable|string
    {
        return 'List every client in the organization the user is currently working in, along with what holds each one: the organization directly, or one of its teams. Use this to answer any question about clients, and to check whether a client already exists.';
    }

    public function handle(Request $request): Stringable|string
    {
        $organization = $this->organization();

        if ($organization === null) {
            return $this->withoutOrganization();
        }

        $clients = $organization->clients()->with('parent')->orderBy('name')->get();

        if ($clients->isEmpty()) {
            return "{$organization->name} has no clients yet.";
        }

        return $clients
            ->map(function (Client $client): string {
                $parent = $client->parent;

                $heldBy = match (true) {
                    $parent instanceof Team => "held by the team {$parent->name}",
                    $parent instanceof Organization => 'held by the organization directly',
                    default => 'with no recorded owner',
                };

                return "- {$client->name} ({$heldBy})";
            })
            ->prepend("Clients in {$organization->name}:")
            ->join("\n");
    }

    /** @return array<string, \Illuminate\JsonSchema\Types\Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
