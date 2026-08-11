<?php

use App\Enums\OrganizationRole;
use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Add a user to an organization and make it the one they are working in.
 *
 * Takes a role because the write tools are gated on it — a Member may not create
 * a client where an Admin may — so tests need to vary it.
 */
function memberOf(Organization $organization, OrganizationRole $role = OrganizationRole::Owner): User
{
    $user = User::factory()->create();

    $organization->members()->attach($user, ['role' => $role->value]);
    $user->switchOrganization($organization);

    return $user->refresh();
}

/**
 * An organization with someone working in it, which is the starting point for
 * anything to do with tenancy or the assistant.
 *
 * @return array{Organization, User}
 */
function organizationWith(OrganizationRole $role = OrganizationRole::Owner, string $name = 'NotaryDash'): array
{
    $organization = Organization::factory()->create(['name' => $name]);

    return [$organization, memberOf($organization, $role)];
}

/**
 * A user working in an organization of the given name.
 */
function userInOrganization(string $organizationName = 'NotaryDash', OrganizationRole $role = OrganizationRole::Owner): User
{
    [, $user] = organizationWith($role, $organizationName);

    return $user;
}

/**
 * Attach a client contact: a `Client`-role membership carrying the client that
 * person represents.
 */
function contactFor(Client $client, string $name, ?string $email = null): User
{
    $contact = User::factory()->create([
        'name' => $name,
        'email' => $email ?? Str::slug($name, '.').'@example.test',
    ]);

    $client->organization->members()->attach($contact, [
        'role' => OrganizationRole::Client->value,
        'client_id' => $client->id,
    ]);

    return $contact;
}

/**
 * An empty tool request, for calling a tool that takes no arguments.
 */
function toolRequest(array $arguments = []): Request
{
    return new Request($arguments);
}

/**
 * Join the `text_delta` frames out of an SSE body, the way the chat window does.
 */
function assistantDeltas(string $stream): string
{
    return collect(explode("\n\n", $stream))
        ->map(fn (string $frame) => trim(Str::after($frame, 'data: ')))
        ->filter(fn (string $payload) => $payload !== '' && $payload !== '[DONE]')
        ->map(fn (string $payload) => json_decode($payload, true))
        ->where('type', 'text_delta')
        ->pluck('delta')
        ->join('');
}

/**
 * Post a message to the assistant and drain the stream.
 *
 * Draining matters: the conversation is persisted as the generator runs, so a
 * stream nobody reads is a conversation nobody stored.
 */
function sendToAssistant(User $user, string $message): string
{
    $response = test()->actingAs($user)
        ->post(route('assistant.messages.store'), ['message' => $message]);

    $response->assertOk();

    return $response->streamedContent();
}
