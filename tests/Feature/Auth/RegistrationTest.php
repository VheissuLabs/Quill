<?php

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('registration screen includes organization invitation context', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['name' => 'Laravel Organization']);
    $organization->addMember($owner, 'Owner');

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this->get(route('register', ['invitation' => $invitation->code]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/Register')
        ->where('organizationInvitation.code', $invitation->code)
        ->where('organizationInvitation.organizationName', 'Laravel Organization'),
    );
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();

    $response->assertRedirect(route('organizations.index'));
});

test('registering after browsing a public board returns to that board', function () {
    $project = Project::factory()->public()->create();
    $board = route('portal.projects.show', [$project->organization, $project]);

    $this->get($board)->assertOk();

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect($board);
});
