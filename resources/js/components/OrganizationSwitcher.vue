<script setup lang="ts">
    import { router, usePage } from '@inertiajs/vue3'
    import { Building2, Check, ChevronsUpDown } from '@lucide/vue'
    import { computed, onMounted, onUnmounted, ref } from 'vue'
    import { Button } from '@/components/ui/button'
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuLabel,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu'
    import { switchMethod } from '@/routes/organizations'
    import type { Organization } from '@/types'

    const props = withDefaults(
        defineProps<{
            inHeader?: boolean
        }>(),
        {
            inHeader: false,
        },
    )

    const page = usePage()
    const isMobile = ref(false)
    let mediaQuery: MediaQueryList | null = null
    const updateIsMobile = () => {
        if (mediaQuery) {
            isMobile.value = mediaQuery.matches
        }
    }

    const currentOrganization = computed(() => page.props.currentOrganization)
    const organizations = computed(() => page.props.organizations ?? [])

    /**
     * A switcher with nothing to switch to is just a label taking up a row, so it
     * hides until the user belongs to more than one organization. The consequence
     * is deliberate: someone in a single organization never sees its name here.
     */
    const canSwitch = computed(() => organizations.value.length > 1)

    const menuContentClass = computed(() =>
        props.inHeader
            ? 'w-56'
            : 'w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg',
    )
    const itemClass = computed(() =>
        props.inHeader ? 'cursor-pointer gap-2' : 'cursor-pointer gap-2 p-2',
    )
    const checkIconClass = computed(() =>
        props.inHeader ? 'ml-auto size-4' : 'ml-auto h-4 w-4',
    )

    /**
     * Organizations do not appear in the URL yet, so there is no path segment to
     * rewrite the way TeamSwitcher does. A reload is enough to pick up the new
     * current organization from the shared props.
     */
    const switchOrganization = (organization: Organization) => {
        if (organization.id === currentOrganization.value?.id) {
            return
        }

        router.visit(switchMethod(organization.slug), {
            onFinish: () => router.reload(),
        })
    }

    onMounted(() => {
        mediaQuery = window.matchMedia('(max-width: 767px)')
        updateIsMobile()
        mediaQuery.addEventListener('change', updateIsMobile)
    })

    onUnmounted(() => {
        mediaQuery?.removeEventListener('change', updateIsMobile)
    })
</script>

<template>
    <DropdownMenu v-if="canSwitch">
        <DropdownMenuTrigger as-child>
            <Button
                data-test="organization-switcher-trigger"
                variant="ghost"
                :class="
                    props.inHeader
                        ? 'h-8 gap-1 px-2'
                        : 'w-full justify-start px-2 has-[>svg]:px-2 data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground'
                "
            >
                <Building2
                    :class="
                        props.inHeader
                            ? 'hidden'
                            : 'hidden size-4 shrink-0 group-data-[collapsible=icon]:block'
                    "
                />
                <div
                    :class="
                        props.inHeader
                            ? 'grid flex-1 text-left text-sm leading-tight'
                            : 'grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden'
                    "
                >
                    <span
                        :class="
                            props.inHeader
                                ? 'max-w-[120px] truncate font-medium'
                                : 'truncate font-semibold'
                        "
                    >
                        {{ currentOrganization?.name ?? 'Select organization' }}
                    </span>
                </div>
                <ChevronsUpDown
                    :class="
                        props.inHeader
                            ? 'size-4 opacity-50'
                            : 'ml-auto group-data-[collapsible=icon]:hidden'
                    "
                />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            :class="menuContentClass"
            :side="props.inHeader ? undefined : isMobile ? 'bottom' : 'right'"
            :align="props.inHeader ? 'end' : 'start'"
            :side-offset="props.inHeader ? undefined : 4"
        >
            <DropdownMenuLabel class="text-xs text-muted-foreground">
                Organizations
            </DropdownMenuLabel>
            <DropdownMenuItem
                v-for="organization in organizations"
                :key="organization.id"
                data-test="organization-switcher-item"
                :class="itemClass"
                @click="switchOrganization(organization)"
            >
                <span class="truncate">{{ organization.name }}</span>
                <Check
                    v-if="currentOrganization?.id === organization.id"
                    :class="checkIconClass"
                />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
