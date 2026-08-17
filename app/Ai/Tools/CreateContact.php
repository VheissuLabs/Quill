<?php

namespace App\Ai\Tools;

use App\Actions\Organizations\InviteContact;
use App\Ai\Contracts\AssistantTool;
use App\Ai\Tools\Concerns\MatchesNames;
use App\Ai\Tools\Concerns\ScopedToCurrentOrganization;
use App\Models\Client;
use App\Models\Organization;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateContact implements AssistantTool
{
    use MatchesNames;
    use ScopedToCurrentOrganization;

    public function name(): string
    {
        return 'create_contact';
    }

    public function capability(): string
    {
        return 'Invite someone to be a contact for a client, or re-send an invitation they did not receive.';
    }

    public function description(): Stringable|string
    {
        return 'Invite someone to be a contact for one of the organization\'s clients, or re-send an invitation they did not receive. Requires the client name and the person\'s email address. Only call this when you have both; ask for whichever is missing. Calling it again for the same email re-sends the existing invitation rather than creating a second one, so use it for "resend", "send it again", or "they did not get it". The person is invited, not added — they join when they accept.';
    }

    public function handle(Request $request): Stringable|string
    {
        $organization = $this->organization();

        if ($organization === null) {
            return $this->withoutOrganization();
        }

        if (! $this->user->can('member:add')) {
            return $this->refused('invite a contact');
        }

        $email = mb_strtolower(trim((string) $request['email']));

        if ($email === '') {
            return 'A contact needs an email address. Ask the user for it — do not invent one.';
        }

        if (Validator::make(['email' => $email], ['email' => ['email']])->fails()) {
            return "\"{$email}\" is not a valid email address, so nobody was invited.";
        }

        $client = $this->resolveClient($organization, $request['client'] ?? null);

        if (is_string($client)) {
            return $client;
        }

        $invitation = app(InviteContact::class)->handle(
            $this->user,
            $client,
            $email,
            $request['name'] ?? null,
        );

        $delivery = $invitation->wasRecentlyCreated
            ? 'They have been sent an invitation'
            : 'They already had a pending invitation, so it was sent again';

        return "Invited {$email} as a contact for {$client->name}. {$delivery}. ".
            'They will appear as a contact once they accept.';
    }

    /** @return array<string, \Illuminate\JsonSchema\Types\Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'client' => $schema->string()->description('The name of the client this person is a contact for.')->required(),
            'email' => $schema->string()->description('The email address to invite. Never invent this — ask for it.')->required(),
            'name' => $schema->string()->description('Optional. The person\'s name, if the user gave one.'),
        ];
    }

    protected function resolveClient(Organization $organization, ?string $clientName): Client|string
    {
        $clients = $organization->clients()->orderBy('name')->pluck('name');

        if ($clientName === null || trim($clientName) === '') {
            return 'A contact has to belong to a client. Ask which client this person represents. '.
                ($clients->isEmpty()
                    ? "{$organization->name} has no clients yet."
                    : 'The clients are: '.$clients->join(', ').'.');
        }

        $matches = $this->matchingNames($clients, $clientName, 'client');

        if ($matches->count() === 1) {
            return $organization->clients()->where('name', $matches->sole())->sole();
        }

        if ($matches->count() > 1) {
            return "More than one client in {$organization->name} matches \"{$clientName}\": ".
                $matches->join(', ').'. Nobody was invited — ask which one.';
        }

        return "There is no client called {$clientName} in {$organization->name}, so nobody was invited. ".
            ($clients->isEmpty()
                ? 'The organization has no clients at all.'
                : 'The clients are: '.$clients->join(', ').'.');
    }
}
