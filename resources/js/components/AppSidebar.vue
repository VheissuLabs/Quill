<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Briefcase,
    FolderKanban,
    LayoutGrid,
    ListTodo,
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
import { index as projects } from '@/routes/projects';
import { index as roles } from '@/routes/roles';
import { index as sprints } from '@/routes/sprints';
import { index as teams } from '@/routes/teams';
import type { NavItem } from '@/types';

type NavGroup = {
    label?: string;
    items: NavItem[];
};

const page = usePage();

const organizationSlug = computed(() => page.props.currentOrganization?.slug);
const modules = computed(
    () =>
        page.props.currentOrganization?.modules ?? {
            featureVoting: {
                enabled: true,
                capabilities: { boards: true, voting: true },
            },
            development: {
                enabled: true,
                capabilities: { issues: true, sprints: true, workflow: true },
            },
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
        if (
            modules.value.featureVoting.enabled &&
            modules.value.featureVoting.capabilities.boards
        ) {
            groups.push({
                label: 'Feature voting',
                items: [
                    {
                        title: 'Projects',
                        href: projects(slug),
                        icon: FolderKanban,
                    },
                ],
            });
        }

        const developmentItems = [
            ...(modules.value.development.enabled &&
            modules.value.development.capabilities.issues
                ? [{ title: 'Issues', href: issues(slug), icon: ListTodo }]
                : []),
            ...(modules.value.development.enabled &&
            modules.value.development.capabilities.sprints
                ? [{ title: 'Sprints', href: sprints(slug), icon: Timer }]
                : []),
        ];

        if (developmentItems.length > 0) {
            groups.push({ label: 'Development', items: developmentItems });
        }

        groups.push({
            label: 'Organization',
            items: [
                { title: 'Clients', href: clients(slug), icon: Briefcase },
                { title: 'Teams', href: teams(slug), icon: Users },
                ...(page.props.currentOrganization?.canManageRoles
                    ? [
                          {
                              title: 'Roles',
                              href: roles(slug),
                              icon: ShieldCheck,
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
                <SidebarMenuItem>
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
