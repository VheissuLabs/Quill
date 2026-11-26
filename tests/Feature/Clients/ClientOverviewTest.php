<?php

use App\Enums\AddressType;
use App\Enums\ProjectVisibility;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

function overviewClient(): array
{
    $organization = Organization::factory()->create();
    config(['services.resend.support_organization' => $organization->slug]);

    return [$organization, Client::factory()->for($organization)->create()];
}

test('a client overview lists its contacts, addresses, projects and recent tickets', function () {
    [$organization, $client] = overviewClient();
    $admin = User::factory()->create();
    $organization->addMember($admin, 'Admin');
    ClientContact::factory()->for($client)->create(['name' => 'Jane Doe']);
    $client->addresses()->create(['type' => AddressType::Billing, 'line1' => '1 Main St', 'city' => 'Dallas']);
    Project::factory()->for($organization)->public()->create(['client_id' => $client->id, 'name' => 'Website']);
    Ticket::factory()->forClient($client)->create();

    $this->actingAs($admin)
        ->get(route('clients.show', ['current_organization' => $organization, 'client' => $client]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/Show')
            ->where('client.contacts.0.name', 'Jane Doe')
            ->where('client.addresses.0.line1', '1 Main St')
            ->has('addressTypes')
            ->where('projects.0.name', 'Website')
            ->has('tickets', 1)
            ->where('invoices', []));
});

test('invoice managers see the client\'s invoices on its overview', function () {
    [$organization, $client] = overviewClient();
    $admin = User::factory()->create();
    $organization->addMember($admin, 'Admin');
    $invoice = Invoice::factory()->for($organization)->for($client)->create();
    Invoice::factory()->for($organization)->create();

    $this->actingAs($admin)
        ->get(route('clients.show', ['current_organization' => $organization, 'client' => $client]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('invoices', 1)
            ->where('invoices.0.id', $invoice->id)
            ->where('invoices.0.number', $invoice->number));
});

test('members see the overview without tickets or private projects of teams they are not on', function () {
    [$organization, $client] = overviewClient();
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');
    Project::factory()->for($organization)->create(['client_id' => $client->id, 'visibility' => ProjectVisibility::Private])
        ->teams()->attach(Team::factory()->for($organization)->create());
    Ticket::factory()->forClient($client)->create();

    $this->actingAs($member)
        ->get(route('clients.show', ['current_organization' => $organization, 'client' => $client]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects', 0)
            ->has('tickets', 0)
            ->where('invoices', null));
});

test('a client from another organization is not found', function () {
    [$organization] = overviewClient();
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');

    $this->actingAs($member)
        ->get(route('clients.show', ['current_organization' => $organization, 'client' => Client::factory()->create()]))
        ->assertNotFound();
});

test('the current period\'s invoice is listed first and marked', function () {
    [$organization, $client] = overviewClient();
    $admin = User::factory()->create();
    $organization->addMember($admin, 'Admin');
    $sent = Invoice::factory()->sent()->for($organization)->for($client)->create(['issue_date' => '2026-11-27']);
    $draft = Invoice::factory()->for($organization)->for($client)
        ->cycle(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-13'))
        ->create();

    $this->actingAs($admin)
        ->get(route('clients.show', ['current_organization' => $organization, 'client' => $client]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('invoices.0.id', $draft->id)
            ->where('invoices.0.isCurrentPeriod', true)
            ->where('invoices.1.id', $sent->id)
            ->where('invoices.1.isCurrentPeriod', false));
});
