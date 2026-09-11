export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    timezone: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type AppNotification = {
    id: string;
    title: string;
    body: string;
    url: string | null;
    readAt: string | null;
    createdAt: string;
};

export type NotificationsSummary = {
    unreadCount: number;
    items: AppNotification[];
};

export type EmailAddress = {
    id: number;
    email: string;
    verified: boolean;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
