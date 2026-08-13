<script setup lang="ts">
    import { Head } from '@inertiajs/vue3'
    import OrganizationActivityTable from '@/components/OrganizationActivityTable.vue'
    import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue'
    import PendingOrganizationInvitationsModal from '@/components/PendingOrganizationInvitationsModal.vue'
    import PlaceholderPattern from '@/components/PlaceholderPattern.vue'
    import { dashboard } from '@/routes'
    import type {
        ActivityEntry,
        DashboardInvitation,
        DashboardOrganizationInvitation,
        Paginated,
        Team,
    } from '@/types'

    defineProps<{
        pendingInvitations?: DashboardInvitation[]
        pendingOrganizationInvitations?: DashboardOrganizationInvitation[]
        activity?: Paginated<ActivityEntry> | null
    }>()

    defineOptions({
        layout: (props: { currentTeam?: Team | null }) => ({
            breadcrumbs: [
                {
                    title: 'Dashboard',
                    href: props.currentTeam
                        ? dashboard(props.currentTeam.slug)
                        : '/',
                },
            ],
        }),
    })
</script>

<template>
    <Head title="Dashboard" />

    <PendingInvitationsModal
        v-if="pendingInvitations && pendingInvitations.length > 0"
        :invitations="pendingInvitations"
    />

    <PendingOrganizationInvitationsModal
        v-if="
            pendingOrganizationInvitations &&
            pendingOrganizationInvitations.length > 0
        "
        :invitations="pendingOrganizationInvitations"
    />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
        </div>
        <OrganizationActivityTable v-if="activity" :activity="activity" />

        <div
            v-else
            class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border"
        >
            <PlaceholderPattern />
        </div>
    </div>
</template>
