<script setup lang="ts">
    import { usePage } from '@inertiajs/vue3'
    import { Bell } from '@lucide/vue'
    import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuLabel,
        DropdownMenuSeparator,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu'
    import { SidebarMenuButton } from '@/components/ui/sidebar'
    import type { NotificationGroup, UserNotification } from '@/types'

    const page = usePage()

    /**
     * Notifications that arrived over the websocket since the last page load. Kept
     * separate from the shared prop so a reload — which returns them from the
     * database anyway — replaces rather than duplicates them.
     */
    const arrivals = ref<UserNotification[]>([])

    const notifications = computed(() => [
        ...arrivals.value,
        ...(page.props.notifications ?? []),
    ])

    const unreadCount = computed(
        () => (page.props.unreadNotificationCount ?? 0) + arrivals.value.length,
    )

    watch(
        () => page.props.notifications,
        () => (arrivals.value = []),
    )

    const channel = (): string | null => {
        const id = page.props.auth?.user?.id

        return id ? `App.Models.User.${id}` : null
    }

    onMounted(() => {
        const name = channel()

        /** Echo is absent when the transport cannot work; see resources/js/echo.ts. */
        if (!name || !window.Echo) {
            return
        }

        window.Echo.private(name).notification(
            (payload: Record<string, string>) => {
                arrivals.value.unshift({
                    id: payload.id,
                    title: payload.title ?? 'Notification',
                    organizationName: payload.organization_name ?? null,
                    createdAtDiff: payload.created_at_diff ?? 'just now',
                    isRead: false,
                })
            },
        )
    })

    onUnmounted(() => {
        const name = channel()

        if (name && window.Echo) {
            window.Echo.leave(name)
        }
    })

    /**
     * Grouped by the organization each notification belongs to, mirroring how the
     * team switcher groups by client. Order follows the feed, which is newest
     * first, so the organization with the most recent activity leads.
     */
    const groups = computed<NotificationGroup[]>(() => {
        const grouped = new Map<string, NotificationGroup>()

        for (const notification of notifications.value) {
            const label = notification.organizationName ?? 'Other'

            if (!grouped.has(label)) {
                grouped.set(label, { label, notifications: [] })
            }

            grouped.get(label)!.notifications.push(notification)
        }

        return [...grouped.values()]
    })
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <SidebarMenuButton
                data-test="notification-bell-trigger"
                tooltip="Notifications"
                class="relative"
            >
                <Bell />
                <span>Notifications</span>

                <!--
                    Two indicators for the same count, because a collapsed sidebar
                    is an 8x8 overflow-hidden box: the numeric badge is clipped
                    there, so a dot positioned inside the box takes over. Keeping
                    `Bell` a direct child preserves the sidebar's [&>svg]:size-4.
                -->
                <span
                    v-if="unreadCount > 0"
                    data-test="notification-bell-count"
                    class="ml-auto rounded-md bg-sidebar-accent px-1.5 text-xs font-medium group-data-[collapsible=icon]:hidden"
                >
                    {{ unreadCount }}
                </span>
                <span
                    v-if="unreadCount > 0"
                    data-test="notification-bell-dot"
                    class="absolute top-1 right-1 hidden size-2 rounded-full bg-primary group-data-[collapsible=icon]:block"
                />
            </SidebarMenuButton>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            class="w-(--reka-dropdown-menu-trigger-width) min-w-80 rounded-lg"
            side="right"
            align="end"
            :side-offset="4"
        >
            <DropdownMenuLabel
                class="flex items-center justify-between text-xs text-muted-foreground"
            >
                <span>Notifications</span>
                <span v-if="unreadCount > 0">{{ unreadCount }} unread</span>
            </DropdownMenuLabel>

            <template v-if="groups.length > 0">
                <template v-for="(group, index) in groups" :key="group.label">
                    <DropdownMenuSeparator v-if="index > 0" />
                    <DropdownMenuLabel
                        data-test="notification-group"
                        class="text-xs text-muted-foreground"
                    >
                        {{ group.label }}
                    </DropdownMenuLabel>
                    <DropdownMenuItem
                        v-for="notification in group.notifications"
                        :key="notification.id"
                        data-test="notification-item"
                        class="cursor-default items-start gap-2 p-2"
                    >
                        <span
                            class="mt-1.5 size-1.5 shrink-0 rounded-full"
                            :class="
                                notification.isRead
                                    ? 'bg-transparent'
                                    : 'bg-primary'
                            "
                        />
                        <span class="flex-1 text-sm leading-snug">
                            {{ notification.title }}
                        </span>
                        <span
                            class="shrink-0 text-xs text-muted-foreground"
                            :title="notification.createdAtDiff"
                        >
                            {{ notification.createdAtDiff }}
                        </span>
                    </DropdownMenuItem>
                </template>
            </template>

            <DropdownMenuLabel
                v-else
                data-test="notification-empty"
                class="py-6 text-center text-xs font-normal text-muted-foreground"
            >
                Nothing yet
            </DropdownMenuLabel>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
