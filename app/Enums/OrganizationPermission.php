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
    case ManageFeedback = 'feedback:manage';
    case ManageIssues = 'issue:manage';
    case ManageSprints = 'sprint:manage';
    case ManageTickets = 'ticket:manage';
    case ManageInvoices = 'invoice:manage';

    case ViewActivity = 'activity:view';

    case ManageRoles = 'role:manage';

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
            self::ManageFeedback => __('Manage feedback'),
            self::ManageIssues => __('Manage issues'),
            self::ManageSprints => __('Manage sprints'),
            self::ManageTickets => __('Manage support tickets'),
            self::ManageInvoices => __('Manage invoices'),
            self::ViewActivity => __('View activity history'),
            self::ManageRoles => __('Manage roles and permissions'),
        };
    }

    public function key(): string
    {
        return 'can'.$this->name;
    }

    public function group(): string
    {
        return match ($this) {
            self::UpdateOrganization, self::DeleteOrganization, self::ManageRoles => __('Organization'),
            self::AddMember, self::UpdateMember, self::RemoveMember, self::CreateInvitation, self::CancelInvitation => __('People'),
            self::ManageTeams, self::ManageClients, self::ManageProjects, self::ManageFeedback, self::ManageIssues, self::ManageSprints, self::ManageTickets => __('Work'),
            self::ManageInvoices => __('Billing'),
            self::ViewActivity => __('Insights'),
        };
    }

    /** @return array<int, array{value: string, label: string, group: string}> */
    public static function options(): array
    {
        return array_map(fn (self $permission) => [
            'value' => $permission->value,
            'label' => $permission->label(),
            'group' => $permission->group(),
        ], self::cases());
    }
}
