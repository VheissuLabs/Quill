<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Briefcase,
    FolderKanban,
    LayoutGrid,
    ListTodo,
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
import { index as organizations } from '@/routes/organizations';
import { index as projects } from '@/routes/projects';
import { index as sprints } from '@/routes/sprints';
import { index as teams } from '@/routes/teams';
import type { NavItem } from '@/types';

type NavGroup = {
    label?: string;
    items: NavItem[];
};

const page = usePage();

const organizationSlug = computed(() => page.props.currentOrganization?.slug);

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
        groups.push(
            {
                label: 'Work',
                items: [
                    {
                        title: 'Projects',
                        href: projects(slug),
                        icon: FolderKanban,
                    },
                    { title: 'Issues', href: issues(slug), icon: ListTodo },
                    { title: 'Sprints', href: sprints(slug), icon: Timer },
                ],
            },
            {
                label: 'Organization',
                items: [
                    { title: 'Clients', href: clients(slug), icon: Briefcase },
                    { title: 'Teams', href: teams(slug), icon: Users },
                ],
            },
        );
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
