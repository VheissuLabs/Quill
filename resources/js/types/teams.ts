export type TeamListItem = {
    id: number;
    name: string;
    slug: string;
    membersCount: number;
};

export type Team = {
    id: number;
    name: string;
    slug: string;
};

export type TeamMergeTarget = {
    id: number;
    name: string;
};

export type TeamMember = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
};
