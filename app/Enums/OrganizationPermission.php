<?php

namespace App\Enums;

enum OrganizationPermission: string
{
    case UpdateOrganization = 'organization:update';
    case DeleteOrganization = 'organization:delete';

    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';

    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';

    case ManageTeams = 'team:manage';
    case ManageClients = 'client:manage';
    case ManageProjects = 'project:manage';
    case ManageFeatures = 'feature:manage';
    case ManageIssues = 'issue:manage';
    case ManageSprints = 'sprint:manage';

    case ViewActivity = 'activity:view';

    case ManageRoles = 'role:manage';

    /**
     * Get the display label for the permission.
     */
    public function label(): string
    {
        return match ($this) {
            self::UpdateOrganization => __('Edit organization settings'),
            self::DeleteOrganization => __('Delete the organization'),
            self::AddMember => __('Add members'),
            self::UpdateMember => __('Change member roles'),
            self::RemoveMember => __('Remove members'),
            self::CreateInvitation => __('Send invitations'),
            self::CancelInvitation => __('Cancel invitations'),
            self::ManageTeams => __('Manage teams'),
            self::ManageClients => __('Manage clients'),
            self::ManageProjects => __('Manage projects and boards'),
            self::ManageFeatures => __('Manage feature requests'),
            self::ManageIssues => __('Manage issues'),
            self::ManageSprints => __('Manage sprints'),
            self::ViewActivity => __('View activity history'),
            self::ManageRoles => __('Manage roles and permissions'),
        };
    }

    /**
     * Get the group the permission is shown under.
     */
    public function group(): string
    {
        return match ($this) {
            self::UpdateOrganization, self::DeleteOrganization, self::ManageRoles => __('Organization'),
            self::AddMember, self::UpdateMember, self::RemoveMember, self::CreateInvitation, self::CancelInvitation => __('People'),
            self::ManageTeams, self::ManageClients, self::ManageProjects, self::ManageFeatures, self::ManageIssues, self::ManageSprints => __('Work'),
            self::ViewActivity => __('Insights'),
        };
    }

    /**
     * Get every permission as an option for the roles form.
     *
     * @return array<int, array{value: string, label: string, group: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $permission) => [
            'value' => $permission->value,
            'label' => $permission->label(),
            'group' => $permission->group(),
        ], self::cases());
    }
}
