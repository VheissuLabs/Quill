import {
    Briefcase,
    Building,
    Contact,
    FilePlus,
    FolderKanban,
    FolderPlus,
    Inbox,
    LayoutGrid,
    ListTodo,
    Mail,
    MessageSquarePlus,
    Receipt,
    Settings,
    ShieldCheck,
    ShieldPlus,
    Tag,
    Timer,
    UserPlus,
    Users,
} from '@lucide/vue';
import { dashboard } from '@/routes';
import { index as clients } from '@/routes/clients';
import { index as issues } from '@/routes/issues';
import { edit as organizationSettings } from '@/routes/organizations';
import { index as projects } from '@/routes/projects';
import { index as roles } from '@/routes/roles';
import { index as sprints } from '@/routes/sprints';
import { index as teams } from '@/routes/teams';
import type {
    Organization,
    OrganizationPermissions,
} from '@/types/organizations';
import type { PaletteCommand } from '@/types/palette';

export function navigationCommands(
    organization: Organization,
): PaletteCommand[] {
    const slug = organization.slug;
    const modules = organization.modules;
    const voting = modules.feedback.enabled;
    const development = modules.development.enabled;

    return [
        {
            id: 'go-dashboard',
            title: 'Dashboard',
            icon: LayoutGrid,
            url: dashboard(slug).url,
        },
        ...(voting && modules.feedback.capabilities.boards
            ? [
                  {
                      id: 'go-projects',
                      title: 'Projects',
                      icon: FolderKanban,
                      url: projects(slug).url,
                  },
              ]
            : []),
        ...(development && modules.development.capabilities.issues
            ? [
                  {
                      id: 'go-issues',
                      title: 'Issues',
                      icon: ListTodo,
                      url: issues(slug).url,
                  },
              ]
            : []),
        ...(development && modules.development.capabilities.sprints
            ? [
                  {
                      id: 'go-sprints',
                      title: 'Sprints',
                      icon: Timer,
                      url: sprints(slug).url,
                  },
              ]
            : []),
        {
            id: 'go-clients',
            title: 'Clients',
            icon: Briefcase,
            url: clients(slug).url,
        },
        ...(modules.teams.enabled
            ? [
                  {
                      id: 'go-teams',
                      title: 'Teams',
                      icon: Users,
                      url: teams(slug).url,
                  },
              ]
            : []),
        ...(organization.canManageRoles
            ? [
                  {
                      id: 'go-roles',
                      title: 'Roles',
                      icon: ShieldCheck,
                      url: roles(slug).url,
                  },
              ]
            : []),
        ...(organization.canUpdate
            ? [
                  {
                      id: 'go-settings',
                      title: 'Organization settings',
                      icon: Settings,
                      url: organizationSettings(slug).url,
                  },
              ]
            : []),
    ];
}

export function actionCommands(
    organization: Organization,
    permissions: Partial<OrganizationPermissions> | null,
): PaletteCommand[] {
    const modules = organization.modules;
    const can = (permission: keyof OrganizationPermissions) =>
        permissions?.[permission] === true;
    const billing =
        modules.billing.enabled && modules.billing.capabilities.invoices;

    const commands: (PaletteCommand | false)[] = [
        modules.development.enabled &&
            modules.development.capabilities.issues && {
                id: 'new-issue',
                title: 'New issue',
                icon: FilePlus,
                action: 'create-issue',
            },
        modules.development.enabled &&
            modules.development.capabilities.sprints &&
            can('canManageSprints') && {
                id: 'new-sprint',
                title: 'New sprint',
                icon: Timer,
                action: 'create-sprint',
            },
        modules.feedback.enabled &&
            modules.feedback.capabilities.boards && {
                id: 'new-project',
                title: 'New project',
                icon: FolderPlus,
                action: 'create-project',
            },
        modules.feedback.enabled &&
            modules.feedback.capabilities.boards && {
                id: 'new-feedback',
                title: 'New feedback',
                icon: MessageSquarePlus,
                action: 'create-feedback',
            },
        organization.receivesSupportMail === true &&
            can('canManageTickets') && {
                id: 'new-ticket',
                title: 'New ticket',
                icon: Inbox,
                action: 'create-ticket',
            },
        can('canManageClients') && {
            id: 'new-client',
            title: 'New client',
            icon: Briefcase,
            action: 'create-client',
        },
        can('canManageClients') && {
            id: 'new-contact',
            title: 'New contact',
            icon: Contact,
            action: 'create-contact',
        },
        billing &&
            can('canManageInvoices') && {
                id: 'new-invoice',
                title: 'New invoice',
                icon: Receipt,
                action: 'create-invoice',
            },
        billing &&
            can('canManageInvoices') && {
                id: 'new-work-type',
                title: 'New work type',
                icon: Tag,
                action: 'create-work-type',
            },
        modules.teams.enabled && {
            id: 'new-team',
            title: 'New team',
            icon: UserPlus,
            action: 'create-team',
        },
        can('canCreateInvitation') && {
            id: 'invite-member',
            title: 'Invite member',
            icon: Mail,
            action: 'invite-member',
        },
        can('canManageRoles') && {
            id: 'new-role',
            title: 'New role',
            icon: ShieldPlus,
            action: 'create-role',
        },
        {
            id: 'new-organization',
            title: 'New organization',
            icon: Building,
            action: 'create-organization',
        },
    ];

    return commands.filter((command): command is PaletteCommand => !!command);
}

export function matches(command: PaletteCommand, term: string): boolean {
    return command.title.toLowerCase().includes(term.trim().toLowerCase());
}
