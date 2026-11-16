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

export type ClientBilling = {
    rate: string | null;
    contactId: number | null;
    billedThroughClientId: number | null;
    cycle?: string | null;
    cycleStartsOn?: string | null;
    ccEmails: string[];
    paymentTermsDays: number | null;
    currentPeriod?: { start: string; end: string } | null;
    cycles?: { value: string; label: string }[];
    contacts: { id: number; name: string | null; email: string }[];
    billedThroughOptions: { id: number; name: string }[];
};
