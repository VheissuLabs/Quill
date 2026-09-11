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
}
