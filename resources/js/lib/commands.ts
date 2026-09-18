import {
    Briefcase,
    FilePlus,
    FolderKanban,
    FolderPlus,
    LayoutGrid,
    ListTodo,
    Settings,
    ShieldCheck,
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
import type { Organization } from '@/types/organizations';
import type { PaletteCommand } from '@/types/palette';

export function navigationCommands(
    organization: Organization,
): PaletteCommand[] {
    const slug = organization.slug;
    const modules = organization.modules;
    const voting = modules.featureVoting.enabled;
    const development = modules.development.enabled;

    return [
        {
            id: 'go-dashboard',
            title: 'Dashboard',
            icon: LayoutGrid,
            url: dashboard(slug).url,
        },
        ...(voting && modules.featureVoting.capabilities.boards
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

export function actionCommands(organization: Organization): PaletteCommand[] {
    const modules = organization.modules;

    return [
        ...(modules.development.enabled &&
        modules.development.capabilities.issues
            ? [
                  {
                      id: 'new-issue',
                      title: 'New issue',
                      icon: FilePlus,
                      action: 'create-issue' as const,
                  },
              ]
            : []),
        ...(modules.featureVoting.enabled &&
        modules.featureVoting.capabilities.boards
            ? [
                  {
                      id: 'new-project',
                      title: 'New project',
                      icon: FolderPlus,
                      action: 'create-project' as const,
                  },
              ]
            : []),
        ...(modules.teams.enabled
            ? [
                  {
                      id: 'new-team',
                      title: 'New team',
                      icon: UserPlus,
                      action: 'create-team' as const,
                  },
              ]
            : []),
        {
            id: 'new-client',
            title: 'New client',
            icon: Briefcase,
            action: 'create-client' as const,
        },
    ];
}

export function matches(command: PaletteCommand, term: string): boolean {
    return command.title.toLowerCase().includes(term.trim().toLowerCase());
}
