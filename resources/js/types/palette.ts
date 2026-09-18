import type { LucideIcon } from '@lucide/vue';
import type { SelectOption } from '@/types/projects';
import type { IssueFormOptions } from '@/types/issues';

export type PaletteResultType =
    | 'issue'
    | 'feature'
    | 'project'
    | 'sprint'
    | 'team'
    | 'client';

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
    | 'create-client';

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
};
