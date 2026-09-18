<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { useLocalStorage } from '@vueuse/core';
import { computed } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

const props = defineProps<{
    items: NavItem[];
    label?: string;
}>();

const { isCurrentUrl } = useCurrentUrl();
const { state } = useSidebar();

/**
 * Labelled groups can be folded away; the choice is remembered per group in this browser.
 * In icon mode there are no labels to click, so every group stays open.
 */
const collapsedGroups = useLocalStorage<string[]>(
    'quill.sidebar.collapsed-groups',
    [],
);

const open = computed({
    get: () =>
        !props.label ||
        state.value === 'collapsed' ||
        !collapsedGroups.value.includes(props.label),
    set: (value: boolean) => {
        if (!props.label) {
            return;
        }

        collapsedGroups.value = value
            ? collapsedGroups.value.filter((label) => label !== props.label)
            : [...collapsedGroups.value, props.label];
    },
});
</script>

<template>
    <Collapsible v-model:open="open" as-child>
        <SidebarGroup class="px-2 py-0">
            <CollapsibleTrigger v-if="label" as-child>
                <SidebarGroupLabel
                    as="button"
                    type="button"
                    :disabled="state === 'collapsed'"
                    :aria-hidden="state === 'collapsed'"
                    :tabindex="state === 'collapsed' ? -1 : undefined"
                    class="group/label w-full cursor-pointer justify-between group-data-[collapsible=icon]:pointer-events-none"
                    :data-test="`nav-group-${label}`"
                >
                    {{ label }}
                    <ChevronDown
                        class="size-3.5 transition-transform group-data-[state=closed]/label:-rotate-90"
                    />
                </SidebarGroupLabel>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in items" :key="item.title">
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl(item.href)"
                            :tooltip="item.title"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </CollapsibleContent>
        </SidebarGroup>
    </Collapsible>
</template>
