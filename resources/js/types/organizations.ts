export type OrganizationRole = 'owner' | 'admin' | 'member' | 'client'

export type Organization = {
    id: string
    name: string
    slug: string
    role?: OrganizationRole
    roleLabel?: string
    isCurrent?: boolean
}

export type OrganizationPermissions = {
    canUpdateOrganization: boolean
    canDeleteOrganization: boolean
    canAddMember: boolean
    canUpdateMember: boolean
    canRemoveMember: boolean
    canCreateInvitation: boolean
    canCancelInvitation: boolean
    canCreateClient: boolean
    canUpdateClient: boolean
    canDeleteClient: boolean
}

export type Client = {
    id: string
    name: string
    slug: string
}

export type DashboardOrganizationInvitation = {
    code: string
    inviterName: string
    organizationName: string
    clientName: string | null
    roleLabel: string
}
