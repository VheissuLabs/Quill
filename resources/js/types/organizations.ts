export type Organization = {
    id: string
    name: string
    slug: string
    role?: string
    roleLabel?: string
    isCurrent?: boolean
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

export type JoinInvitationContext = {
    code: string
    email: string
    inviterName: string
    organizationName: string
    clientName: string | null
}

export type ActivityEntry = {
    id: string
    summary: string
    causerName: string | null
    happenedAt: string
    happenedAtDiff: string
}

export type Paginated<T> = {
    data: T[]
    current_page: number
    last_page: number
    total: number
    prev_page_url: string | null
    next_page_url: string | null
}

export type UserProject = {
    id: string
    name: string
    slug: string
    ownerName: string | null
    ownerType: string | null
}
