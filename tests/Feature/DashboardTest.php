<?php

use App\Enums\FeedbackStatus;
use App\Models\Feedback;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $user = User::factory()->withOrganization()->create();
    $organization = $user->currentOrganization;

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->withOrganization()->create();
    $organization = $user->currentOrganization;

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
});

test('dashboard includes pending invitations for the authenticated user', function () {
    $owner = User::factory()->withOrganization()->create(['name' => 'Taylor Otwell']);
    $invitedUser = User::factory()->withOrganization()->create(['email' => 'invited@example.com']);
    $organization = Organization::factory()->create(['name' => 'Laravel Organization']);

    $organization->addMember($owner, 'Owner');

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('pendingInvitations', 1)
        ->where('pendingInvitations.0.code', $invitation->code)
        ->where('pendingInvitations.0.inviterName', 'Taylor Otwell')
        ->where('pendingInvitations.0.organization.name', 'Laravel Organization')
        ->where('pendingInvitations.0.organization.slug', $organization->slug)
        ->missing('pendingInvitations.0.organizationName'),
    );
});

test('dashboard does not include accepted invitations', function () {
    $owner = User::factory()->withOrganization()->create();
    $invitedUser = User::factory()->withOrganization()->create(['email' => 'invited@example.com']);
    $organization = Organization::factory()->create();

    $organization->addMember($owner, 'Owner');

    OrganizationInvitation::factory()->accepted()->create([
        'organization_id' => $organization->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('pendingInvitations', 0),
    );
});

test('dashboard excludes expired invitations without deleting them', function () {
    $owner = User::factory()->withOrganization()->create();
    $invitedUser = User::factory()->withOrganization()->create(['email' => 'invited@example.com']);
    $organization = Organization::factory()->create();

    $organization->addMember($owner, 'Owner');

    $invitation = OrganizationInvitation::factory()->expired()->create([
        'organization_id' => $organization->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('pendingInvitations', 0),
    );

    $this->assertDatabaseHas('organization_invitations', [
        'id' => $invitation->id,
    ]);
});

test('dashboard does not include or delete other users invitations', function () {
    $owner = User::factory()->withOrganization()->create();
    $invitedUser = User::factory()->withOrganization()->create(['email' => 'invited@example.com']);
    $organization = Organization::factory()->create();

    $organization->addMember($owner, 'Owner');

    $invitation = OrganizationInvitation::factory()->expired()->create([
        'organization_id' => $organization->id,
        'email' => 'someone@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('pendingInvitations', 0),
    );

    $this->assertDatabaseHas('organization_invitations', [
        'id' => $invitation->id,
    ]);
});

test('recent requests exclude completed and declined feedback', function () {
    $user = User::factory()->withOrganization()->create();
    $project = Project::factory()->for($user->currentOrganization)->public()->create();

    $open = Feedback::factory()->for($project)->create();
    Feedback::factory()->for($project)->create(['status' => FeedbackStatus::Completed]);
    Feedback::factory()->for($project)->create(['status' => FeedbackStatus::Declined]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentFeedback', 1)
            ->where('recentFeedback.0.id', $open->id)
            ->where('recentFeedback.0.type', $open->type->value)
            ->where('recentFeedback.0.typeLabel', 'Feature'),
        );
});

test('the dashboard refreshes when feedback on a visible project changes', function () {
    $user = User::factory()->withOrganization()->create();
    $project = Project::factory()->for($user->currentOrganization)->public()->create();

    $liveProps = $this->actingAs($user)->get(route('dashboard'))->viewData('page')['liveProps'];

    expect($liveProps['stats']['listeners'])->toBe([
        [
            'channel' => ['name' => "organizations.{$user->current_organization_id}", 'type' => 'private'],
            'events' => ['projects.changed', 'modules.changed'],
        ],
        [
            'channel' => ['name' => "projects.{$project->id}", 'type' => 'public'],
            'events' => ['feedback.changed', 'feedback.voted', 'feedback.commented'],
        ],
    ])->and($liveProps)->toHaveKeys(['projects', 'recentFeedback']);
});

test('a dashboard with no visible projects only listens for new projects', function () {
    $user = User::factory()->withOrganization()->create();

    $liveProps = $this->actingAs($user)->get(route('dashboard'))->viewData('page')['liveProps'];

    expect($liveProps['stats']['listeners'])->toHaveCount(1)
        ->and($liveProps['stats']['listeners'][0]['events'])->toBe(['projects.changed', 'modules.changed']);
});
