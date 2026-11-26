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
    deletedAt?: string | null;
    contacts?: {
        id: number;
        name: string | null;
        title: string | null;
        email: string;
        phone: string | null;
    }[];
    addresses?: Address[];
    domains?: { id: number; domain: string }[];
};

export type Address = {
    id: number;
    type: string;
    typeLabel: string;
    line1: string;
    line2: string | null;
    city: string;
    region: string | null;
    postalCode: string | null;
    country: string | null;
    lines: string[];
};

export type AddressTypeOption = { value: string; label: string };

export type ClientContactDetail = {
    id: number;
    name: string | null;
    title: string | null;
    email: string;
    phone: string | null;
    addresses: Address[];
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
