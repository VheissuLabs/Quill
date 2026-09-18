export type ProjectVisibility = 'public' | 'private';

export type SelectOption = {
    value: string;
    label: string;
};

export type ProjectListItem = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    visibility: ProjectVisibility;
    visibilityLabel: string;
    clientName: string | null;
    feedbackCount: number;
};

export type ProjectSettings = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    visibility: ProjectVisibility;
    clientId: number | null;
    teamIds: number[];
};

export type ClientOption = {
    id: number;
    name: string;
};

export type TeamOption = {
    id: number;
    name: string;
    slug: string;
    runsSprints?: boolean;
};
