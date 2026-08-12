<script setup lang="ts">
    import { Link, usePage } from '@inertiajs/vue3'
    import { FolderOpen } from '@lucide/vue'
    import { computed } from 'vue'
    import {
        SidebarGroup,
        SidebarGroupLabel,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar'
    import { useCurrentUrl } from '@/composables/useCurrentUrl'
    import { show } from '@/routes/projects'

    const page = usePage()
    const { isCurrentUrl } = useCurrentUrl()

    const projects = computed(() => page.props.projects ?? [])
</script>

<template>
    <SidebarGroup v-if="projects.length > 0" class="px-2 py-0">
        <SidebarGroupLabel>Projects</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="project in projects" :key="project.id">
                <SidebarMenuButton
                    as-child
                    data-test="nav-project"
                    :is-active="isCurrentUrl(show(project.slug).url)"
                    :tooltip="
                        project.ownerName
                            ? `${project.name} — ${project.ownerType} ${project.ownerName}`
                            : project.name
                    "
                >
                    <Link :href="show(project.slug)">
                        <FolderOpen />
                        <span>{{ project.name }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
