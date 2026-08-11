export type UserNotification = {
    id: string
    title: string
    organizationName: string | null
    createdAtDiff: string
    isRead: boolean
}

export type NotificationGroup = {
    label: string
    notifications: UserNotification[]
}
