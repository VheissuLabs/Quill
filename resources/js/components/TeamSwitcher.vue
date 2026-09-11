<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, Users } from '@lucide/vue';
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
import { index as teamsIndex, switchMethod } from '@/routes/teams';
import type { TeamOption } from '@/types';

const page = usePage();

const organizationSlug = computed(() => page.props.currentOrganization?.slug);
const currentTeam = computed(() => page.props.currentTeam);
const teams = computed(() => page.props.availableTeams ?? []);

const switchTeam = (team: TeamOption) => {
    if (!organizationSlug.value || team.id === currentTeam.value?.id) {
        return;
    }

    router.visit(switchMethod([organizationSlug.value, team.slug]), {
        preserveScroll: true,
    });
};
</script>

<template>
    <DropdownMenu v-if="organizationSlug">
        <DropdownMenuTrigger as-child>
            <Button
                data-test="team-switcher-trigger"
                variant="ghost"
                size="sm"
                class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground text-muted-foreground w-full justify-start px-2 has-[>svg]:px-2"
            >
                <Users class="size-4 shrink-0" />
                <span
                    class="truncate text-sm group-data-[collapsible=icon]:hidden"
                >
                    {{ currentTeam?.name ?? 'No team' }}
                </span>
                <ChevronsUpDown
                    class="ml-auto size-4 opacity-50 group-data-[collapsible=icon]:hidden"
                />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
            side="right"
            align="start"
            :side-offset="4"
        >
            <DropdownMenuLabel class="text-muted-foreground text-xs">
                Working in
            </DropdownMenuLabel>
            <DropdownMenuItem
                v-for="team in teams"
                :key="team.id"
                data-test="team-switcher-item"
                class="cursor-pointer gap-2 p-2"
                @click="switchTeam(team)"
            >
                {{ team.name }}
                <Check
                    v-if="currentTeam?.id === team.id"
                    class="ml-auto h-4 w-4"
                />
            </DropdownMenuItem>
            <p
                v-if="teams.length === 0"
                class="text-muted-foreground px-2 py-1.5 text-xs"
            >
                This organization has no teams yet.
            </p>
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child class="cursor-pointer gap-2 p-2">
                <Link :href="teamsIndex(organizationSlug)">
                    <span class="text-muted-foreground">Manage teams</span>
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
