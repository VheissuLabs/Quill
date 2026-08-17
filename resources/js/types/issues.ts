export type IssueListItem = {
    number: number
    title: string
    type: string
    clientName: string | null
}

export type IssueType = {
    id: string
    name: string
}

export type Issue = {
    number: number
    title: string
    description: string
    acceptanceCriteria: string | null
    type: string
    clientName: string | null
    reporterName: string | null
    isOpen: boolean
    createdAt: string | null
    fromConversation: boolean
}
