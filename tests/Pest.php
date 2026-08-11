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

function memberOf(Organization $organization, OrganizationRole $role = OrganizationRole::Owner): User
{
    $user = User::factory()->create();

    $organization->members()->attach($user, ['role' => $role->value]);
    $user->switchOrganization($organization);

    return $user->refresh();
}

/**
 * @return array{Organization, User}
 */
function organizationWith(OrganizationRole $role = OrganizationRole::Owner, string $name = 'NotaryDash'): array
{
    $organization = Organization::factory()->create(['name' => $name]);

    return [$organization, memberOf($organization, $role)];
}

function userInOrganization(string $organizationName = 'NotaryDash', OrganizationRole $role = OrganizationRole::Owner): User
{
    [, $user] = organizationWith($role, $organizationName);

    return $user;
}

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

function toolRequest(array $arguments = []): Request
{
    return new Request($arguments);
}

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

function sendToAssistant(User $user, string $message): string
{
    $response = test()->actingAs($user)
        ->post(route('assistant.messages.store'), ['message' => $message]);

    $response->assertOk();

    return $response->streamedContent();
}
