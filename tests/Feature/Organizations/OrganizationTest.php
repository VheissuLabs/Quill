<?php

use App\Enums\OrganizationPermission;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the organizations index page can be rendered', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('organizations.index'));

    $response->assertOk();
});

test('organizations can be created', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('organizations.store'), [
            'name' => 'Test Organization',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('organizations', [
        'name' => 'Test Organization',
    ]);
});

test('organization slug uses next available suffix', function () {
    $user = User::factory()->create();

    Organization::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
    Organization::factory()->create(['name' => 'Acme One', 'slug' => 'acme-1']);
    Organization::factory()->create(['name' => 'Acme Ten', 'slug' => 'acme-10']);

    $this
        ->actingAs($user)
        ->post(route('organizations.store'), [
            'name' => 'Acme',
        ]);

    $this->assertDatabaseHas('organizations', [
        'name' => 'Acme',
        'slug' => 'acme-11',
    ]);
});

test('the organization edit page can be rendered', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->addMember($user, 'Owner');

    $response = $this
        ->actingAs($user)
        ->get(route('organizations.edit', $organization));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/Edit')
            ->where('members.0.role', 'Owner')
            ->where('members.0.isOwner', true)
            ->where('availableRoles', fn ($roles) => collect($roles)->pluck('label')->all() === ['Admin', 'Member']),
        );
});

test('organizations can be updated by owners', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['name' => 'Original Name']);

    $organization->addMember($user, 'Owner');

    $response = $this
        ->actingAs($user)
        ->patch(route('organizations.update', $organization), [
            'name' => 'Updated Name',
        ]);

    $response->assertRedirect(route('organizations.edit', $organization->fresh()));

    $this->assertDatabaseHas('organizations', [
        'id' => $organization->id,
        'name' => 'Updated Name',
    ]);
});

test('organizations cannot be updated by members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->addMember($owner, 'Owner');
    $organization->addMember($member, 'Member');

    $response = $this
        ->actingAs($member)
        ->patch(route('organizations.update', $organization), [
            'name' => 'Updated Name',
        ]);

    $response->assertForbidden();
});

test('organizations can be deleted by owners', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->addMember($user, 'Owner');

    $response = $this
        ->actingAs($user)
        ->delete(route('organizations.destroy', $organization), [
            'name' => $organization->name,
        ]);

    $response->assertRedirect();

    $this->assertSoftDeleted('organizations', [
        'id' => $organization->id,
    ]);
});

test('organization deletion requires name confirmation', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->addMember($user, 'Owner');

    $response = $this
        ->actingAs($user)
        ->delete(route('organizations.destroy', $organization), [
            'name' => 'Wrong Name',
        ]);

    $response->assertSessionHasErrors('name');

    $this->assertDatabaseHas('organizations', [
        'id' => $organization->id,
        'deleted_at' => null,
    ]);
});

test('deleting current organization switches to alphabetically first remaining organization', function () {
    $user = User::factory()->create(['name' => 'Mike']);

    $zuluOrganization = Organization::factory()->create(['name' => 'Zulu Organization']);
    $zuluOrganization->addMember($user, 'Owner');

    $alphaOrganization = Organization::factory()->create(['name' => 'Alpha Organization']);
    $alphaOrganization->addMember($user, 'Owner');

    $betaOrganization = Organization::factory()->create(['name' => 'Beta Organization']);
    $betaOrganization->addMember($user, 'Owner');

    $user->update(['current_organization_id' => $zuluOrganization->id]);

    $response = $this
        ->actingAs($user)
        ->delete(route('organizations.destroy', $zuluOrganization), [
            'name' => $zuluOrganization->name,
        ]);

    $response->assertRedirect();

    $this->assertSoftDeleted('organizations', [
        'id' => $zuluOrganization->id,
    ]);

    expect($user->fresh()->current_organization_id)->toEqual($alphaOrganization->id);
});

test('deleting current organization falls back to the remaining organization', function () {
    $user = User::factory()->withOrganization()->create();
    $remainingOrganization = $user->currentOrganization;
    $organization = Organization::factory()->create(['name' => 'Zulu Organization']);
    $organization->addMember($user, 'Owner');

    $user->update(['current_organization_id' => $organization->id]);

    $response = $this
        ->actingAs($user)
        ->delete(route('organizations.destroy', $organization), [
            'name' => $organization->name,
        ]);

    $response->assertRedirect();

    $this->assertSoftDeleted('organizations', [
        'id' => $organization->id,
    ]);

    expect($user->fresh()->current_organization_id)->toEqual($remainingOrganization->id);
});

test('deleting the only organization leaves the user without a current organization', function () {
    $user = User::factory()->withOrganization()->create();
    $organization = $user->currentOrganization;

    $response = $this
        ->actingAs($user)
        ->delete(route('organizations.destroy', $organization), [
            'name' => $organization->name,
        ]);

    $response->assertRedirect(route('organizations.index'));

    expect($user->fresh()->current_organization_id)->toBeNull();
});

test('deleting non current organization leaves current organization unchanged', function () {
    $user = User::factory()->withOrganization()->create();
    $currentOrganization = $user->currentOrganization;
    $organization = Organization::factory()->create();
    $organization->addMember($user, 'Owner');

    $user->update(['current_organization_id' => $currentOrganization->id]);

    $response = $this
        ->actingAs($user)
        ->delete(route('organizations.destroy', $organization), [
            'name' => $organization->name,
        ]);

    $response->assertRedirect();

    $this->assertSoftDeleted('organizations', [
        'id' => $organization->id,
    ]);

    expect($user->fresh()->current_organization_id)->toEqual($currentOrganization->id);
});

test('members can leave organizations', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->addMember($owner, 'Owner');
    $organization->addMember($member, 'Member');

    $response = $this
        ->actingAs($member)
        ->delete(route('organizations.leave', $organization));

    $response->assertRedirect(route('organizations.index'));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => "You left the organization \"{$organization->name}\""]);

    expect($member->fresh()->belongsToOrganization($organization))->toBeFalse();
});

test('leaving current organization switches to alphabetically first remaining organization', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['name' => 'Mike']);

    $zuluOrganization = Organization::factory()->create(['name' => 'Zulu Organization']);
    $zuluOrganization->addMember($owner, 'Owner');
    $zuluOrganization->addMember($member, 'Member');

    $alphaOrganization = Organization::factory()->create(['name' => 'Alpha Organization']);
    $alphaOrganization->addMember($member, 'Member');

    $betaOrganization = Organization::factory()->create(['name' => 'Beta Organization']);
    $betaOrganization->addMember($member, 'Member');

    $member->update(['current_organization_id' => $zuluOrganization->id]);

    $response = $this
        ->actingAs($member)
        ->delete(route('organizations.leave', $zuluOrganization));

    $response->assertRedirect(route('organizations.index'));

    expect($member->fresh()->belongsToOrganization($zuluOrganization))->toBeFalse();
    expect($member->fresh()->current_organization_id)->toEqual($alphaOrganization->id);
});

test('the last owner cannot leave the organization', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->addMember($owner, 'Owner');

    $response = $this
        ->actingAs($owner)
        ->delete(route('organizations.leave', $organization));

    $response->assertForbidden();

    expect($owner->fresh()->belongsToOrganization($organization))->toBeTrue();
});

test('an owner can leave while another owner remains', function () {
    $owner = User::factory()->create();
    $secondOwner = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');
    $organization->addMember($secondOwner, 'Owner');

    $this
        ->actingAs($owner)
        ->delete(route('organizations.leave', $organization))
        ->assertRedirect(route('organizations.index'));

    expect($owner->fresh()->belongsToOrganization($organization))->toBeFalse();
    expect($organization->owners()->pluck('id')->all())->toBe([$secondOwner->id]);
});

test('users cannot leave organizations they dont belong to', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('organizations.leave', $organization));

    $response->assertForbidden();
});

test('deleting organization switches other affected users to a remaining organization', function () {
    $owner = User::factory()->create();
    $member = User::factory()->withOrganization()->create();
    $memberOrganization = $member->currentOrganization;

    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');
    $organization->addMember($member, 'Member');

    $owner->update(['current_organization_id' => $organization->id]);
    $member->update(['current_organization_id' => $organization->id]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('organizations.destroy', $organization), [
            'name' => $organization->name,
        ]);

    $response->assertRedirect();

    expect($member->fresh()->current_organization_id)->toEqual($memberOrganization->id);
});

test('organizations cannot be deleted by non owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->addMember($owner, 'Owner');
    $organization->addMember($member, 'Member');

    $response = $this
        ->actingAs($member)
        ->delete(route('organizations.destroy', $organization), [
            'name' => $organization->name,
        ]);

    $response->assertForbidden();
});

test('users can switch organizations', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->addMember($user, 'Member');

    $response = $this
        ->actingAs($user)
        ->post(route('organizations.switch', $organization));

    $response->assertRedirect();

    expect($user->fresh()->current_organization_id)->toEqual($organization->id);
});

test('switching organizations lands on the same page, the dashboard, or back, depending on where the user was', function () {
    $user = User::factory()->withOrganization()->create();
    $previous = $user->currentOrganization;
    $next = Organization::factory()->create();
    $next->addMember($user, 'Member');
    $project = Project::factory()->for($previous)->create();
    $issue = Issue::factory()->for($project)->create();

    $this->actingAs($user)
        ->from(route('issues.index', ['current_organization' => $previous, 'status' => 'done']))
        ->post(route('organizations.switch', $next))
        ->assertRedirect(route('issues.index', ['current_organization' => $next, 'status' => 'done']));

    $this->actingAs($user)
        ->from(route('issues.show', ['current_organization' => $previous, 'issue' => $issue]))
        ->post(route('organizations.switch', $next))
        ->assertRedirect(route('dashboard', ['current_organization' => $next]));

    $this->actingAs($user)
        ->from(route('organizations.index'))
        ->post(route('organizations.switch', $next))
        ->assertRedirect(route('organizations.index'));
});

test('users cannot switch to organization they dont belong to', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('organizations.switch', $organization));

    $response->assertForbidden();
});

test('guests cannot access organizations', function () {
    $response = $this->get(route('organizations.index'));

    $response->assertRedirect(route('login'));
});

test('a new organization starts with only the locked owner role holding every permission', function () {
    $organization = Organization::create(['name' => 'Fresh Start']);
    $otherOrganization = Organization::create(['name' => 'Other']);

    expect($organization->roles()->pluck('name')->all())->toBe(['Owner']);
    expect($organization->ownerRole()->permissions)->toHaveCount(count(OrganizationPermission::cases()));
    expect($organization->assignableRoles())->toBeEmpty();
    expect($otherOrganization->ownerRole()->isNot($organization->ownerRole()))->toBeTrue();
});
