<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import NotificationsMenu from '@/components/NotificationsMenu.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Toaster } from '@/components/ui/sonner';
import { useInitials } from '@/composables/useInitials';
import { dashboard, home, login, register } from '@/routes';
import { show as showBoard } from '@/routes/portal/projects';
import type { PortalOrganization, PortalProject } from '@/types';

type Props = {
    organization: PortalOrganization;
    project: PortalProject;
    isMember?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    isMember: false,
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const { getInitials } = useInitials();
const appName = computed(() => page.props.name);

const boardUrl = computed(() =>
    showBoard([props.organization.slug, props.project.slug]),
);
</script>

<template>
    <div class="bg-background text-foreground flex min-h-screen flex-col">
        <header
            class="bg-background/80 sticky top-0 z-10 border-b backdrop-blur"
        >
            <div
                class="mx-auto flex h-14 w-full max-w-6xl items-center justify-between gap-4 px-4 sm:px-6"
            >
                <Link
                    :href="boardUrl"
                    class="flex min-w-0 items-center gap-2.5"
                    aria-label="Back to the board"
                >
                    <span
                        class="bg-primary text-primary-foreground flex size-7 shrink-0 items-center justify-center rounded-md"
                    >
                        <AppLogoIcon class="size-4" />
                    </span>
                    <span class="truncate text-sm font-semibold">
                        {{ organization.name }}
                    </span>
                    <span
                        class="text-muted-foreground hidden text-sm sm:inline"
                    >
                        / {{ project.name }}
                    </span>
                </Link>

                <div class="flex shrink-0 items-center gap-2">
                    <template v-if="user">
                        <Button
                            v-if="isMember"
                            variant="ghost"
                            size="sm"
                            as-child
                        >
                            <Link :href="dashboard(organization.slug)">
                                Back to {{ organization.name }}
                            </Link>
                        </Button>
                        <NotificationsMenu />
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-9 rounded-full"
                                    data-test="portal-user-menu"
                                    :aria-label="`Account menu for ${user.name}`"
                                >
                                    <Avatar class="size-8">
                                        <AvatarImage
                                            v-if="user.avatar"
                                            :src="user.avatar"
                                            :alt="user.name"
                                        />
                                        <AvatarFallback class="text-xs">
                                            {{ getInitials(user.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                class="min-w-56 rounded-lg"
                                align="end"
                                :side-offset="4"
                            >
                                <UserMenuContent :user="user" />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </template>
                    <template v-else>
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="login()">Log in</Link>
                        </Button>
                        <Button size="sm" as-child>
                            <Link :href="register()">Register</Link>
                        </Button>
                    </template>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-10 sm:px-6">
            <slot />
        </main>

        <footer class="border-t">
            <div
                class="text-muted-foreground mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-6 text-xs sm:px-6"
            >
                <span>{{ organization.name }} · {{ project.name }}</span>
                <Link :href="home()" class="hover:text-foreground">
                    Powered by {{ appName }}
                </Link>
            </div>
        </footer>

        <Toaster />
    </div>
</template>
