<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Briefcase,
    FolderKanban,
    Inbox,
    LayoutGrid,
    ListTodo,
    Receipt,
    Settings,
    ShieldCheck,
    Timer,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import OrganizationSwitcher from '@/components/OrganizationSwitcher.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as clients } from '@/routes/clients';
import { index as issues } from '@/routes/issues';
import {
    edit as organizationSettings,
    index as organizations,
} from '@/routes/organizations';
import { edit as billingSettings } from '@/routes/organizations/billing';
import { index as projects } from '@/routes/projects';
import { index as roles } from '@/routes/roles';
import { index as sprints } from '@/routes/sprints';
import { index as teams } from '@/routes/teams';
import { index as tickets } from '@/routes/tickets';
import { useLiveModules } from '@/composables/useLiveModules';
import type { NavItem } from '@/types';

type NavGroup = {
    label?: string;
    items: NavItem[];
};

const page = usePage();

useLiveModules();

const organizationSlug = computed(() => page.props.currentOrganization?.slug);

/**
 * A team can switch sprints off for itself; with no current team the organization's own
 * board applies and the organization setting is the only one that matters.
 */
const teamRunsSprints = computed(
    () => page.props.currentTeam?.runsSprints ?? true,
);
const modules = computed(
    () =>
        page.props.currentOrganization?.modules ?? {
            feedback: {
                enabled: true,
                capabilities: { boards: true, voting: true },
            },
            development: {
                enabled: true,
                capabilities: { issues: true, sprints: true, workflow: true },
            },
            teams: { enabled: true },
            billing: { enabled: true, capabilities: { invoices: true } },
        },
);

const dashboardUrl = computed(() =>
    organizationSlug.value
        ? dashboard(organizationSlug.value).url
        : organizations().url,
);

const navGroups = computed<NavGroup[]>(() => {
    const groups: NavGroup[] = [
        {
            items: [
                {
                    title: 'Dashboard',
                    href: dashboardUrl.value,
                    icon: LayoutGrid,
                },
            ],
        },
    ];

    const slug = organizationSlug.value;

    if (slug) {
        const developmentItems = [
            { title: 'Projects', href: projects(slug), icon: FolderKanban },
            ...(modules.value.development.enabled &&
            modules.value.development.capabilities.issues
                ? [{ title: 'Issues', href: issues(slug), icon: ListTodo }]
                : []),
            ...(modules.value.development.enabled &&
            modules.value.development.capabilities.sprints &&
            teamRunsSprints.value
                ? [{ title: 'Sprints', href: sprints(slug), icon: Timer }]
                : []),
        ];

        if (developmentItems.length > 0) {
            groups.push({ label: 'Development', items: developmentItems });
        }

        if (
            page.props.currentOrganization?.receivesSupportMail &&
            page.props.permissions?.canManageTickets
        ) {
            groups.push({
                label: 'Support',
                items: [{ title: 'Tickets', href: tickets(slug), icon: Inbox }],
            });
        }

        groups.push({
            label: 'Organization',
            items: [
                { title: 'Clients', href: clients(slug), icon: Briefcase },
                ...(modules.value.teams.enabled
                    ? [{ title: 'Teams', href: teams(slug), icon: Users }]
                    : []),
                ...(page.props.currentOrganization?.canManageRoles
                    ? [
                          {
                              title: 'Roles',
                              href: roles(slug),
                              icon: ShieldCheck,
                          },
                      ]
                    : []),
                ...(modules.value.billing.enabled &&
                page.props.permissions?.canManageInvoices
                    ? [
                          {
                              title: 'Billing',
                              href: billingSettings(slug),
                              icon: Receipt,
                          },
                      ]
                    : []),
                ...(page.props.currentOrganization?.canUpdate
                    ? [
                          {
                              title: 'Settings',
                              href: organizationSettings(slug),
                              icon: Settings,
                          },
                      ]
                    : []),
            ],
        });
    }

    return groups;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardUrl">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarMenu>
                <SidebarMenuItem>
                    <OrganizationSwitcher />
                </SidebarMenuItem>
                <SidebarMenuItem v-if="modules.teams.enabled">
                    <TeamSwitcher />
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarSeparator class="mx-0 my-1" />
        </SidebarHeader>

        <SidebarContent>
            <NavMain
                v-for="(group, index) in navGroups"
                :key="group.label ?? index"
                :label="group.label"
                :items="group.items"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
