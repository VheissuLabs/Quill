export type OrganizationModules = {
    featureVoting: {
        enabled: boolean;
        capabilities: {
            boards: boolean;
            voting: boolean;
        };
    };
    development: {
        enabled: boolean;
        capabilities: {
            issues: boolean;
            sprints: boolean;
            workflow: boolean;
        };
    };
    teams: {
        enabled: boolean;
    };
};

export type Organization = {
    id: number;
    name: string;
    slug: string;
    logoUrl?: string | null;
    role?: string | null;
    isOwner?: boolean;
    canUpdate?: boolean;
    canManageRoles?: boolean;
    modules: OrganizationModules;
    isCurrent?: boolean;
};

export type OrganizationMember = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    role: string | null;
    isOwner: boolean;
    canUpdate: boolean;
    canRemove: boolean;
};

export type OrganizationInvitation = {
    code: string;
    email: string;
    role: string;
    created_at: string;
};

export type OrganizationInvitationContext = {
    code: string;
    organizationName: string;
};

export type DashboardInvitation = {
    code: string;
    inviterName: string;
    organization: {
        name: string;
        slug: string;
    };
};

export type OrganizationPermissions = {
    canUpdateOrganization: boolean;
    canDeleteOrganization: boolean;
    canAddMember: boolean;
    canUpdateMember: boolean;
    canRemoveMember: boolean;
    canCreateInvitation: boolean;
    canCancelInvitation: boolean;
    canManageTeams: boolean;
    canManageClients: boolean;
    canManageProjects: boolean;
    canManageFeatures: boolean;
    canManageIssues: boolean;
    canManageSprints: boolean;
    canViewActivity: boolean;
    canManageRoles: boolean;
};

export type RoleOption = {
    value: number;
    label: string;
};

export type OrganizationRoleItem = {
    id: number;
    name: string;
    isOwner: boolean;
    permissions: string[];
    membersCount: number;
    members: { id: number; name: string; email: string }[];
};

export type RoleCandidate = {
    id: number;
    name: string;
    email: string;
    role: string | null;
};

export type PermissionOption = {
    value: string;
    label: string;
    group: string;
};
