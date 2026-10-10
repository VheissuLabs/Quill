export type ClientListItem = {
    id: number;
    name: string;
    slug: string;
    projectsCount: number;
};

export type Client = {
    id: number;
    name: string;
    slug: string;
    contacts?: { id: number; name: string | null; email: string }[];
    domains?: { id: number; domain: string }[];
};

export type ClientProject = {
    id: number;
    name: string;
    slug: string;
    visibility: 'public' | 'private';
};
