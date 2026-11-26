<?php

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationPermission;
use App\Mcp\Servers\QuillServer;
use App\Mcp\Tools\Organizations\AcceptInvitationTool;
use App\Mcp\Tools\Organizations\CancelInvitationTool;
use App\Mcp\Tools\Organizations\CreateOrganizationTool;
use App\Mcp\Tools\Organizations\CreateRoleTool;
use App\Mcp\Tools\Organizations\DeclineInvitationTool;
use App\Mcp\Tools\Organizations\DeleteOrganizationTool;
use App\Mcp\Tools\Organizations\DeleteRoleTool;
use App\Mcp\Tools\Organizations\InviteMemberTool;
use App\Mcp\Tools\Organizations\LeaveOrganizationTool;
use App\Mcp\Tools\Organizations\ListInvitationsTool;
use App\Mcp\Tools\Organizations\ListMembersTool;
use App\Mcp\Tools\Organizations\ListPendingInvitationsTool;
use App\Mcp\Tools\Organizations\ListPermissionsTool;
use App\Mcp\Tools\Organizations\ListRolesTool;
use App\Mcp\Tools\Organizations\RemoveMemberTool;
use App\Mcp\Tools\Organizations\RemoveOrganizationLogoTool;
use App\Mcp\Tools\Organizations\SearchTool;
use App\Mcp\Tools\Organizations\ShowDashboardTool;
use App\Mcp\Tools\Organizations\ShowOrganizationTool;
use App\Mcp\Tools\Organizations\ShowRoleTool;
use App\Mcp\Tools\Organizations\SwitchOrganizationTool;
use App\Mcp\Tools\Organizations\UpdateMemberRoleTool;
use App\Mcp\Tools\Organizations\UpdateModuleOrderTool;
use App\Mcp\Tools\Organizations\UpdateOrganizationModulesTool;
use App\Mcp\Tools\Organizations\UpdateOrganizationTool;
use App\Mcp\Tools\Organizations\UpdateRoleTool;
use App\Models\Feedback;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Organizations\OrganizationInvitation as OrganizationInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function organizationToolMember(string $role = 'Owner', ?Organization $organization = null): array
{
    $organization ??= Organization::factory()->create();
    $user = User::factory()->create();
    $organization->addMember($user, $role);
    $user->switchOrganization($organization);

    return [$user, $organization];
}

function organizationToolJoin(Organization $organization, string $role = 'Member'): User
{
    $user = User::factory()->create();
    $organization->addMember($user, $role);

    return $user;
}

test('it shows the organization settings with modules and module order', function () {
    [$user, $organization] = organizationToolMember('Member');

    QuillServer::actingAs($user)->tool(ShowOrganizationTool::class)
        ->assertOk()
        ->assertSee($organization->slug)
        ->assertSee('moduleOrder')
        ->assertSee('development');
});

test('it creates an organization owned by the caller and switches to it', function () {
    [$user] = organizationToolMember();

    QuillServer::actingAs($user)->tool(CreateOrganizationTool::class, ['name' => 'Northwind Labs'])
        ->assertOk()
        ->assertSee('Northwind Labs');

    $organization = Organization::query()->where('name', 'Northwind Labs')->sole();

    expect($organization->isOwner($user))->toBeTrue()
        ->and($user->fresh()->current_organization_id)->toBe($organization->id);
});

test('creating an organization uses the web validation', function () {
    [$user] = organizationToolMember();

    QuillServer::actingAs($user)->tool(CreateOrganizationTool::class, ['name' => ''])->assertHasErrors(['name']);
});

test('it renames an organization when allowed', function () {
    [$user, $organization] = organizationToolMember('Admin');

    QuillServer::actingAs($user)->tool(UpdateOrganizationTool::class, ['name' => 'Renamed Co'])->assertOk()->assertSee('Renamed Co');

    expect($organization->fresh()->name)->toBe('Renamed Co');
});

test('members cannot rename an organization', function () {
    [$user, $organization] = organizationToolMember('Member');

    QuillServer::actingAs($user)->tool(UpdateOrganizationTool::class, ['name' => 'Renamed Co'])->assertHasErrors();

    expect($organization->fresh()->name)->not->toBe('Renamed Co');
});

test('it deletes an organization only with its exact name', function () {
    [$user, $organization] = organizationToolMember();
    [, $other] = organizationToolMember('Owner');
    $other->addMember($user, 'Member');

    QuillServer::actingAs($user)->tool(DeleteOrganizationTool::class, ['organization' => $organization->slug, 'confirm_name' => 'wrong'])
        ->assertHasErrors(['name']);

    expect($organization->fresh())->not->toBeNull();

    QuillServer::actingAs($user)->tool(DeleteOrganizationTool::class, ['organization' => $organization->slug, 'confirm_name' => $organization->name])
        ->assertOk();

    expect(Organization::query()->find($organization->id))->toBeNull()
        ->and($user->fresh()->current_organization_id)->toBe($other->id);
});

test('admins cannot delete an organization', function () {
    [$user, $organization] = organizationToolMember('Admin');

    QuillServer::actingAs($user)->tool(DeleteOrganizationTool::class, ['organization' => $organization->slug, 'confirm_name' => $organization->name])
        ->assertHasErrors();

    expect($organization->fresh())->not->toBeNull();
});

test('it switches to another organization the user belongs to', function () {
    [$user] = organizationToolMember();
    $other = Organization::factory()->create();
    $other->addMember($user, 'Member');

    QuillServer::actingAs($user)->tool(SwitchOrganizationTool::class, ['organization' => $other->slug])->assertOk();

    expect($user->fresh()->current_organization_id)->toBe($other->id);
});

test('it cannot switch to an organization the user is not in', function () {
    [$user, $organization] = organizationToolMember();
    $foreign = Organization::factory()->create();

    QuillServer::actingAs($user)->tool(SwitchOrganizationTool::class, ['organization' => $foreign->slug])->assertHasErrors();

    expect($user->fresh()->current_organization_id)->toBe($organization->id);
});

test('a member can leave an organization', function () {
    [$user, $organization] = organizationToolMember('Member');

    QuillServer::actingAs($user)->tool(LeaveOrganizationTool::class, ['organization' => $organization->slug])->assertOk();

    expect($user->fresh()->belongsToOrganization($organization))->toBeFalse();
});

test('the last owner cannot leave an organization', function () {
    [$user, $organization] = organizationToolMember();

    QuillServer::actingAs($user)->tool(LeaveOrganizationTool::class, ['organization' => $organization->slug])->assertHasErrors();

    expect($organization->isOwner($user->fresh()))->toBeTrue();
});

test('owners can switch modules and turning teams off dissolves teams', function () {
    [$user, $organization] = organizationToolMember();
    Team::factory()->for($organization)->create();

    QuillServer::actingAs($user)->tool(UpdateOrganizationModulesTool::class, ['teams_enabled' => false, 'development_sprints_enabled' => false])->assertOk();

    $organization->refresh();

    expect($organization->teams()->count())->toBe(0)
        ->and($organization->canUse(OrganizationCapability::Sprints))->toBeFalse()
        ->and($organization->canUse(OrganizationCapability::Issues))->toBeTrue();
});

test('non owners cannot switch modules', function () {
    [$user, $organization] = organizationToolMember('Admin');

    QuillServer::actingAs($user)->tool(UpdateOrganizationModulesTool::class, ['development_sprints_enabled' => false])->assertHasErrors();

    expect($organization->fresh()->canUse(OrganizationCapability::Sprints))->toBeTrue();
});

test('owners can reorder the sidebar modules', function () {
    [$user, $organization] = organizationToolMember();

    QuillServer::actingAs($user)->tool(UpdateModuleOrderTool::class, ['order' => ['billing', 'support', 'crm', 'development', 'feedback']])->assertOk();

    expect($organization->fresh()->moduleOrder())->toBe(['billing', 'support', 'crm', 'development', 'feedback']);

    QuillServer::actingAs($user)->tool(UpdateModuleOrderTool::class, ['order' => ['billing']])->assertHasErrors(['order']);
});

test('non owners cannot reorder the sidebar modules', function () {
    [$user] = organizationToolMember('Admin');

    QuillServer::actingAs($user)->tool(UpdateModuleOrderTool::class, ['order' => ['billing', 'support', 'crm', 'development', 'feedback']])->assertHasErrors();
});

test('it removes the organization logo when allowed', function () {
    Storage::fake('public');
    [$user, $organization] = organizationToolMember('Admin');
    [$member] = organizationToolMember('Member', $organization);

    QuillServer::actingAs($member)->tool(RemoveOrganizationLogoTool::class)->assertHasErrors();
    QuillServer::actingAs($user)->tool(RemoveOrganizationLogoTool::class)->assertOk();
});

test('it lists the members of the organization', function () {
    [$user, $organization] = organizationToolMember('Member');
    $colleague = organizationToolJoin($organization);

    QuillServer::actingAs($user)->tool(ListMembersTool::class)
        ->assertOk()
        ->assertSee($colleague->email)
        ->assertSee($user->email);
});

test('owners can change a member role', function () {
    [$owner, $organization] = organizationToolMember();
    $member = organizationToolJoin($organization);

    QuillServer::actingAs($owner)->tool(UpdateMemberRoleTool::class, ['user_id' => $member->id, 'role_id' => $organization->role('Admin')->id])->assertOk();

    expect($member->fresh()->organizationRole($organization)->name)->toBe('Admin');
});

test('non owners cannot grant the owner role', function () {
    [$owner, $organization] = organizationToolMember();
    $organization->defineRole('Manager', [OrganizationPermission::UpdateMember->value]);
    [$manager] = organizationToolMember('Manager', $organization);
    $member = organizationToolJoin($organization);

    QuillServer::actingAs($manager)->tool(UpdateMemberRoleTool::class, ['user_id' => $member->id, 'role_id' => $organization->role('Admin')->id])->assertOk();
    QuillServer::actingAs($manager)->tool(UpdateMemberRoleTool::class, ['user_id' => $member->id, 'role_id' => $organization->ownerRole()->id])->assertHasErrors();

    expect($member->fresh()->organizationRole($organization)->name)->toBe('Admin');
});

test('the last owner cannot be stepped down', function () {
    [$owner, $organization] = organizationToolMember();

    QuillServer::actingAs($owner)->tool(UpdateMemberRoleTool::class, ['user_id' => $owner->id, 'role_id' => $organization->role('Member')->id])->assertHasErrors();

    expect($organization->isOwner($owner->fresh()))->toBeTrue();
});

test('changing a member role cannot reach users outside the organization', function () {
    [$owner, $organization] = organizationToolMember();
    $outsider = User::factory()->create();

    QuillServer::actingAs($owner)->tool(UpdateMemberRoleTool::class, ['user_id' => $outsider->id, 'role_id' => $organization->role('Admin')->id])->assertHasErrors();

    expect($outsider->fresh()->belongsToOrganization($organization))->toBeFalse();
});

test('owners can remove members', function () {
    [$owner, $organization] = organizationToolMember();
    $member = organizationToolJoin($organization);

    QuillServer::actingAs($owner)->tool(RemoveMemberTool::class, ['user_id' => $member->id])->assertOk();

    expect($member->fresh()->belongsToOrganization($organization))->toBeFalse();
});

test('an owner can remove another owner but never the last one', function () {
    [$owner, $organization] = organizationToolMember();
    $coOwner = organizationToolJoin($organization, 'Owner');

    QuillServer::actingAs($owner)->tool(RemoveMemberTool::class, ['user_id' => $coOwner->id])->assertOk();
    QuillServer::actingAs($owner)->tool(RemoveMemberTool::class, ['user_id' => $owner->id])->assertHasErrors();

    expect($organization->isOwner($owner->fresh()))->toBeTrue();
});

test('members without the permission cannot remove members', function () {
    [$admin, $organization] = organizationToolMember('Admin');
    $member = organizationToolJoin($organization);

    QuillServer::actingAs($admin)->tool(RemoveMemberTool::class, ['user_id' => $member->id])->assertHasErrors();

    expect($member->fresh()->belongsToOrganization($organization))->toBeTrue();
});

test('it lists roles and permissions and shows a role', function () {
    [$user, $organization] = organizationToolMember('Member');
    $admin = organizationToolJoin($organization, 'Admin');

    QuillServer::actingAs($user)->tool(ListRolesTool::class)->assertOk()->assertSee('Admin')->assertSee('organization:update');
    QuillServer::actingAs($user)->tool(ListPermissionsTool::class)->assertOk()->assertSee('role:manage');
    QuillServer::actingAs($user)->tool(ShowRoleTool::class, ['role_id' => $organization->role('Admin')->id])->assertOk()->assertSee($admin->email);
});

test('roles of another organization cannot be shown', function () {
    [$user] = organizationToolMember();
    $foreign = Organization::factory()->create();

    QuillServer::actingAs($user)->tool(ShowRoleTool::class, ['role_id' => $foreign->role('Admin')->id])->assertHasErrors();
});

test('owners can create, update and delete roles', function () {
    [$owner, $organization] = organizationToolMember();

    QuillServer::actingAs($owner)->tool(CreateRoleTool::class, ['name' => 'Support', 'permissions' => ['ticket:manage']])->assertOk()->assertSee('Support');

    $role = $organization->role('Support');
    expect($role->hasPermissionTo('ticket:manage'))->toBeTrue();

    QuillServer::actingAs($owner)->tool(UpdateRoleTool::class, ['role_id' => $role->id, 'name' => 'Helpdesk'])->assertOk();

    expect($role->fresh())->name->toBe('Helpdesk')
        ->and($role->fresh()->hasPermissionTo('ticket:manage'))->toBeTrue();

    QuillServer::actingAs($owner)->tool(DeleteRoleTool::class, ['role_id' => $role->id])->assertOk();

    expect($organization->role('Helpdesk'))->toBeNull();
});

test('role names and permissions use the web validation', function () {
    [$owner] = organizationToolMember();

    QuillServer::actingAs($owner)->tool(CreateRoleTool::class, ['name' => 'Admin'])->assertHasErrors(['name']);
    QuillServer::actingAs($owner)->tool(CreateRoleTool::class, ['name' => 'Odd', 'permissions' => ['nope']])->assertHasErrors();
});

test('the owner role cannot be changed or deleted and held roles cannot be deleted', function () {
    [$owner, $organization] = organizationToolMember();
    organizationToolJoin($organization, 'Admin');

    QuillServer::actingAs($owner)->tool(UpdateRoleTool::class, ['role_id' => $organization->ownerRole()->id, 'name' => 'Boss'])->assertHasErrors();
    QuillServer::actingAs($owner)->tool(DeleteRoleTool::class, ['role_id' => $organization->ownerRole()->id])->assertHasErrors();
    QuillServer::actingAs($owner)->tool(DeleteRoleTool::class, ['role_id' => $organization->role('Admin')->id])
        ->assertHasErrors(['Move its members to another role before deleting this one.']);

    expect($organization->role('Owner'))->not->toBeNull()
        ->and($organization->role('Admin'))->not->toBeNull();
});

test('managing roles requires the manage roles permission', function () {
    [$admin, $organization] = organizationToolMember('Admin');

    QuillServer::actingAs($admin)->tool(CreateRoleTool::class, ['name' => 'Support'])->assertHasErrors();
    QuillServer::actingAs($admin)->tool(DeleteRoleTool::class, ['role_id' => $organization->role('Member')->id])->assertHasErrors();

    expect($organization->role('Support'))->toBeNull()
        ->and($organization->role('Member'))->not->toBeNull();
});

test('it invites a member and lists and cancels the invitation', function () {
    Notification::fake();
    [$admin, $organization] = organizationToolMember('Admin');

    QuillServer::actingAs($admin)->tool(InviteMemberTool::class, ['email' => 'new@example.com', 'role_id' => $organization->role('Member')->id])->assertOk();

    $invitation = $organization->invitations()->sole();
    Notification::assertSentOnDemand(OrganizationInvitationNotification::class);

    QuillServer::actingAs($admin)->tool(ListInvitationsTool::class)->assertOk()->assertSee('new@example.com');
    QuillServer::actingAs($admin)->tool(CancelInvitationTool::class, ['code' => $invitation->code])->assertOk();

    expect($organization->invitations()->exists())->toBeFalse();
});

test('invitations cannot grant the owner role or be sent without permission', function () {
    Notification::fake();
    [$admin, $organization] = organizationToolMember('Admin');
    [$member] = organizationToolMember('Member', $organization);

    QuillServer::actingAs($admin)->tool(InviteMemberTool::class, ['email' => 'boss@example.com', 'role_id' => $organization->ownerRole()->id])->assertHasErrors(['selected role id is invalid']);
    QuillServer::actingAs($member)->tool(InviteMemberTool::class, ['email' => 'pal@example.com', 'role_id' => $organization->role('Member')->id])->assertHasErrors();

    expect($organization->invitations()->exists())->toBeFalse();
});

test('invitations of another organization cannot be cancelled', function () {
    [$admin] = organizationToolMember('Admin');
    $invitation = OrganizationInvitation::factory()->create();

    QuillServer::actingAs($admin)->tool(CancelInvitationTool::class, ['code' => $invitation->code])->assertHasErrors();

    expect($invitation->fresh())->not->toBeNull();
});

test('it lists, accepts and declines invitations sent to the user', function () {
    [$user] = organizationToolMember();
    $joining = OrganizationInvitation::factory()->create(['email' => $user->email]);
    $declining = OrganizationInvitation::factory()->create(['email' => $user->email]);

    QuillServer::actingAs($user)->tool(ListPendingInvitationsTool::class)
        ->assertOk()
        ->assertSee($joining->code)
        ->assertSee($declining->code);

    QuillServer::actingAs($user)->tool(AcceptInvitationTool::class, ['code' => $joining->code])->assertOk();
    QuillServer::actingAs($user)->tool(DeclineInvitationTool::class, ['code' => $declining->code])->assertOk();

    expect($user->fresh()->belongsToOrganization($joining->organization))->toBeTrue()
        ->and($user->fresh()->current_organization_id)->toBe($joining->organization_id)
        ->and($declining->fresh())->toBeNull();
});

test('invitations addressed to someone else cannot be accepted or declined', function () {
    [$user] = organizationToolMember();
    $invitation = OrganizationInvitation::factory()->create(['email' => 'someone-else@example.com']);

    QuillServer::actingAs($user)->tool(AcceptInvitationTool::class, ['code' => $invitation->code])->assertHasErrors();
    QuillServer::actingAs($user)->tool(DeclineInvitationTool::class, ['code' => $invitation->code])->assertHasErrors();

    expect($user->fresh()->belongsToOrganization($invitation->organization))->toBeFalse()
        ->and($invitation->fresh())->not->toBeNull();
});

test('expired invitations cannot be accepted', function () {
    [$user] = organizationToolMember();
    $invitation = OrganizationInvitation::factory()->expired()->create(['email' => $user->email]);

    QuillServer::actingAs($user)->tool(AcceptInvitationTool::class, ['code' => $invitation->code])->assertHasErrors();

    expect($user->fresh()->belongsToOrganization($invitation->organization))->toBeFalse();
});

test('it searches the organization like the palette', function () {
    [$user, $organization] = organizationToolMember();
    Project::factory()->for($organization)->create(['name' => 'Moonshot Portal']);
    Project::factory()->create(['name' => 'Moonshot Elsewhere']);

    QuillServer::actingAs($user)->tool(SearchTool::class, ['query' => 'moonshot'])
        ->assertOk()
        ->assertSee('Moonshot Portal')
        ->assertDontSee('Moonshot Elsewhere');

    QuillServer::actingAs($user)->tool(SearchTool::class, ['query' => str_repeat('a', 101)])->assertHasErrors(['q']);
});

test('it shows the dashboard with projects, feedback, stats and pending invitations', function () {
    [$user, $organization] = organizationToolMember();
    $project = Project::factory()->for($organization)->public()->create(['name' => 'Customer Portal']);
    Feedback::factory()->for($project)->create(['title' => 'Dark mode please']);
    $invitation = OrganizationInvitation::factory()->create(['email' => $user->email]);

    QuillServer::actingAs($user)->tool(ShowDashboardTool::class)
        ->assertOk()
        ->assertSee('Customer Portal')
        ->assertSee('Dark mode please')
        ->assertSee('projectsCount')
        ->assertSee($invitation->code);
});

test('organization tools refuse organizations the user is not in', function () {
    [$user] = organizationToolMember();
    $foreign = Organization::factory()->create();

    QuillServer::actingAs($user)->tool(ShowOrganizationTool::class, ['organization' => $foreign->slug])->assertHasErrors();
    QuillServer::actingAs($user)->tool(ListMembersTool::class, ['organization' => $foreign->slug])->assertHasErrors();
    QuillServer::actingAs($user)->tool(ShowDashboardTool::class, ['organization' => $foreign->slug])->assertHasErrors();
});
