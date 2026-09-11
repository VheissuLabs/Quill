<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { useEchoNotification } from '@laravel/echo-vue';
import { Bell, X } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDate } from '@/lib/dates';
import { destroy, destroyAll, read, readAll } from '@/routes/notifications';
import type { AppNotification } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth.user);
const notifications = computed(() => page.props.notifications);

/**
 * New notifications arrive on the user's private channel; refetch just the shared prop so the
 * badge and list update without a full visit.
 */
useEchoNotification(`App.Models.User.${user.value.id}`, () => {
    router.reload({ only: ['notifications'] });
});

const open = (notification: AppNotification) => {
    router.visit(read(notification.id));
};

const markAllRead = () => {
    router.visit(readAll(), { preserveScroll: true });
};

const dismiss = (notification: AppNotification) => {
    router.visit(destroy(notification.id), { preserveScroll: true });
};

const clearAll = () => {
    router.visit(destroyAll(), { preserveScroll: true });
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative"
                data-test="notifications-trigger"
                :aria-label="`Notifications, ${notifications.unreadCount} unread`"
            >
                <Bell />
                <span
                    v-if="notifications.unreadCount > 0"
                    data-test="notifications-unread"
                    class="bg-foreground text-background absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-semibold tabular-nums"
                >
                    {{
                        notifications.unreadCount > 9
                            ? '9+'
                            : notifications.unreadCount
                    }}
                </span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80">
            <div class="flex items-center justify-between px-2 py-1.5">
                <DropdownMenuLabel class="p-0">Notifications</DropdownMenuLabel>
                <span class="flex items-center gap-3 text-xs">
                    <button
                        v-if="notifications.unreadCount > 0"
                        type="button"
                        data-test="notifications-read-all"
                        class="text-muted-foreground hover:text-foreground"
                        @click="markAllRead"
                    >
                        Mark all as read
                    </button>
                    <button
                        v-if="notifications.items.length > 0"
                        type="button"
                        data-test="notifications-clear-all"
                        class="text-muted-foreground hover:text-foreground"
                        @click="clearAll"
                    >
                        Clear all
                    </button>
                </span>
            </div>
            <DropdownMenuSeparator />
            <template v-if="notifications.items.length > 0">
                <DropdownMenuItem
                    v-for="notification in notifications.items"
                    :key="notification.id"
                    data-test="notification-item"
                    class="group flex cursor-pointer items-start gap-2 p-2"
                    @click="open(notification)"
                >
                    <span
                        class="mt-1.5 size-1.5 shrink-0 rounded-full"
                        :class="
                            notification.readAt
                                ? 'bg-transparent'
                                : 'bg-foreground'
                        "
                    />
                    <span class="min-w-0 space-y-0.5">
                        <span class="block text-sm leading-snug font-medium">
                            {{ notification.title }}
                        </span>
                        <span
                            v-if="notification.body"
                            class="text-muted-foreground line-clamp-2 block text-xs"
                        >
                            {{ notification.body }}
                        </span>
                        <span class="text-muted-foreground block text-[11px]">
                            {{ formatDate(notification.createdAt) }}
                        </span>
                    </span>
                    <button
                        type="button"
                        data-test="notification-dismiss"
                        class="text-muted-foreground hover:text-foreground ml-auto shrink-0 rounded p-0.5 opacity-0 transition-opacity group-hover:opacity-100 focus:opacity-100"
                        aria-label="Clear notification"
                        @click.stop="dismiss(notification)"
                    >
                        <X class="size-3.5" />
                    </button>
                </DropdownMenuItem>
            </template>
            <p
                v-else
                class="text-muted-foreground px-2 py-6 text-center text-sm"
            >
                You're all caught up.
            </p>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
