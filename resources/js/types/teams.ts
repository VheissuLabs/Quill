export type Team = {
    id: string
    name: string
    slug: string
    isPersonal: boolean
    isOwner?: boolean
    parentName?: string | null
    parentType?: 'organization' | 'client' | null
    isCurrent?: boolean
}

export type TeamGroup = {
    label: string
    teams: Team[]
}

export type TeamMember = {
    id: string
    name: string
    email: string
    avatar?: string | null
    isOwner: boolean
}

export type TeamInvitation = {
    code: string
    email: string
    created_at: string
}

export type TeamInvitationContext = {
    code: string
    teamName: string
}

export type DashboardInvitation = {
    code: string
    inviterName: string
    team: {
        name: string
        slug: string
    }
}
