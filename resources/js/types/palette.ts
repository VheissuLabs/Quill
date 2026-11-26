import type { LucideIcon } from '@lucide/vue';
import type { SelectOption } from '@/types/projects';
import type { InvoiceProjectOption } from '@/types/invoices';
import type { IssueFormOptions } from '@/types/issues';
import type { PermissionOption, RoleOption } from '@/types/organizations';

export type PaletteResultType =
    | 'issue'
    | 'feedback'
    | 'project'
    | 'sprint'
    | 'team'
    | 'column'
    | 'client'
    | 'contact'
    | 'domain'
    | 'address'
    | 'ticket'
    | 'attachment'
    | 'comment'
    | 'invoice'
    | 'invoice line'
    | 'payment'
    | 'time'
    | 'work type'
    | 'member'
    | 'role'
    | 'organization';

export type PaletteResult = {
    type: PaletteResultType;
    title: string;
    subtitle: string | null;
    url: string;
};

export type PaletteActionName =
    | 'create-issue'
    | 'create-project'
    | 'create-team'
    | 'create-client'
    | 'create-contact'
    | 'create-invoice'
    | 'create-ticket'
    | 'create-sprint'
    | 'create-feedback'
    | 'create-role'
    | 'invite-member'
    | 'create-work-type'
    | 'create-organization';

export type PaletteCommand = {
    id: string;
    title: string;
    icon: LucideIcon;
    url?: string;
    action?: PaletteActionName;
};

export type PaletteOptions = {
    issue: IssueFormOptions | null;
    visibilities: SelectOption[];
    clients: { id: number; name: string; slug: string }[];
    invoiceProjects: InvoiceProjectOption[];
    feedbackProjects: { name: string; slug: string }[];
    sprintBoard: string;
    roles: RoleOption[];
    permissions: PermissionOption[];
    workTypeColors: string[];
};
