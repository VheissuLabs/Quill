<?php

namespace App\Http\Resources;

readonly class OrganizationPermissions
{
    public function __construct(
        public bool $canUpdateOrganization,
        public bool $canDeleteOrganization,
        public bool $canAddMember,
        public bool $canUpdateMember,
        public bool $canRemoveMember,
        public bool $canCreateInvitation,
        public bool $canCancelInvitation,
        public bool $canManageTeams,
        public bool $canManageClients,
        public bool $canManageProjects,
        public bool $canManageFeatures,
        public bool $canManageIssues,
        public bool $canManageSprints,
        public bool $canViewActivity,
        public bool $canManageRoles,
    ) {}
}
