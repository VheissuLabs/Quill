<?php

use App\Models\User;

test('guests visiting the root are sent to login', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('authenticated users visiting the root are sent to their dashboard', function () {
    $user = User::factory()->withOrganization()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('dashboard', ['current_organization' => $user->currentOrganization->slug]));
});

test('authenticated users without an organization are sent to organization settings', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('organizations.index'));
});
