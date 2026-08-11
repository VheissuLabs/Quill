<script setup lang="ts">
    import { Link, usePage } from '@inertiajs/vue3'
    import { LayoutGrid, Sparkles } from '@lucide/vue'
    import { computed } from 'vue'
    import AppLogo from '@/components/AppLogo.vue'
    import NavMain from '@/components/NavMain.vue'
    import NavUser from '@/components/NavUser.vue'
    import NotificationBell from '@/components/NotificationBell.vue'
    import OrganizationSwitcher from '@/components/OrganizationSwitcher.vue'
    import TeamSwitcher from '@/components/TeamSwitcher.vue'
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar'
    import { assistant, dashboard } from '@/routes'
    import type { NavItem } from '@/types'

    const page = usePage()

    const dashboardUrl = computed(() =>
        page.props.currentTeam
            ? dashboard(page.props.currentTeam.slug).url
            : '/',
    )

    const mainNavItems = computed<NavItem[]>(() => [
        {
            title: 'Dashboard',
            href: dashboardUrl.value,
            icon: LayoutGrid,
        },
        {
            title: 'Assistant',
            href: assistant().url,
            icon: Sparkles,
        },
    ])
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
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu>
                <SidebarMenuItem>
                    <NotificationBell />
                </SidebarMenuItem>
                <SidebarMenuItem>
                    <OrganizationSwitcher />
                </SidebarMenuItem>
                <SidebarMenuItem>
                    <TeamSwitcher />
                </SidebarMenuItem>
            </SidebarMenu>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
