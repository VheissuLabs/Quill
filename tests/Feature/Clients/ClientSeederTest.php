<?php

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;

test('every organization is seeded with at least one client', function () {
    $this->seed();

    expect(Organization::count())->toBe(3);

    Organization::each(function (Organization $organization) {
        expect($organization->clients)->not->toBeEmpty();
    });
});

test('clients are seeded with real names and generated slugs', function () {
    $this->seed();

    expect(Client::count())->toBe(6);

    $acme = Client::where('name', 'Acme Title Co')->firstOrFail();

    expect($acme->slug)->toBe('acme-title-co');
    expect($acme->organization->name)->toBe('NotaryDash');
});

test('the test user can view every seeded client', function () {
    $this->seed();

    $user = User::where('email', 'karl@vheissulabs.com')->firstOrFail();

    expect(Client::count())->toBe(6);

    Client::each(fn (Client $client) => expect($user->can('view', $client))->toBeTrue());
});

test('every seeded client has a uuid key', function () {
    $this->seed();

    expect(Client::count())->toBe(6);

    Client::each(fn (Client $client) => expect(Str::isUuid($client->id))->toBeTrue());
});
