<?php

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Organization;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the contacts directory lists every contact alphabetically with their client', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');
    $acme = Client::factory()->for($organization)->create(['name' => 'Acme']);
    $globex = Client::factory()->for($organization)->create(['name' => 'Globex']);
    ClientContact::factory()->for($globex)->create(['name' => 'Zoe Park', 'email' => 'zoe@globex.test']);
    ClientContact::factory()->for($acme)->create(['name' => null, 'email' => 'billing@acme.test']);
    ClientContact::factory()->for($acme)->create(['name' => 'Ann Lee', 'email' => 'ann@acme.test']);

    $this->actingAs($member)
        ->get(route('contacts.index', $organization))
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/Index')
            ->has('contacts', 3)
            ->where('contacts.0.name', 'Ann Lee')
            ->where('contacts.1.email', 'billing@acme.test')
            ->where('contacts.2.name', 'Zoe Park')
            ->where('contacts.2.client.slug', $globex->slug));
});

test('the contacts directory leaves out deleted clients and other organizations', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');
    ClientContact::factory()->for(Client::factory()->for($organization))->create(['name' => 'Kept']);
    $deleted = Client::factory()->for($organization)->create();
    ClientContact::factory()->for($deleted)->create();
    $deleted->delete();
    ClientContact::factory()->create();

    $this->actingAs($member)
        ->get(route('contacts.index', $organization))
        ->assertInertia(fn (Assert $page) => $page
            ->has('contacts', 1)
            ->where('contacts.0.name', 'Kept'));
});

test('people outside the organization cannot open its contacts', function () {
    $organization = Organization::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('contacts.index', $organization))
        ->assertForbidden();
});
