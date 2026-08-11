<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AssistantTool;
use App\Ai\Tools\Concerns\ScopedToCurrentOrganization;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListClients implements AssistantTool
{
    use ScopedToCurrentOrganization;

    public function name(): string
    {
        return 'list_clients';
    }

    public function capability(): string
    {
        return 'List your clients, who holds each one, and the contacts at each.';
    }

    public function description(): Stringable|string
    {
        return 'List every client in the organization the user is currently working in, along with what holds each one (the organization directly, or one of its teams) and who the contacts are at each client. Use this to answer any question about clients or about who to talk to at a client, and to check whether a client already exists.';
    }

    public function handle(Request $request): Stringable|string
    {
        $organization = $this->organization();

        if ($organization === null) {
            return $this->withoutOrganization();
        }

        $clients = $organization->clients()
            ->with(['parent', 'contacts.user'])
            ->orderBy('name')
            ->get();

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

                $contacts = $client->contacts
                    ->map(fn (OrganizationMembership $contact) => $contact->user->name.' <'.$contact->user->email.'>')
                    ->join(', ');

                return "- {$client->name} ({$heldBy}). Contacts: ".
                    ($contacts === '' ? 'none yet' : $contacts);
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
