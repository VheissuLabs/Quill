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

    case ViewActivity = 'activity:view';

    case CreateTeam = 'team:create';
    case UpdateTeam = 'team:update';
    case DeleteTeam = 'team:delete';

    case CreateProject = 'project:create';
    case UpdateProject = 'project:update';
    case DeleteProject = 'project:delete';

    case CreateClient = 'client:create';
    case UpdateClient = 'client:update';
    case DeleteClient = 'client:delete';
}
