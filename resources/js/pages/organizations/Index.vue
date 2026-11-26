<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye, LogOut, Pencil, Plus } from '@lucide/vue';
import { ref } from 'vue';
import CreateOrganizationModal from '@/components/CreateOrganizationModal.vue';
import SectionCard from '@/components/SectionCard.vue';
import LeaveOrganizationModal from '@/components/LeaveOrganizationModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { edit, index } from '@/routes/organizations';
import type { Organization } from '@/types';

type Props = {
    organizations: Organization[];
};

defineProps<Props>();

const leaveOrganizationDialogOpen = ref(false);
const organizationLeaving = ref<Organization | null>(null);

const canLeaveOrganization = (organization: Organization) =>
    !organization.isOwner;

const openLeaveOrganizationDialog = (organization: Organization) => {
    organizationLeaving.value = organization;
    leaveOrganizationDialogOpen.value = true;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Organizations',
                href: index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Organizations" />

    <h1 class="sr-only">Organizations</h1>

    <SectionCard
        title="Organizations"
        description="Manage your organizations and organization memberships"
    >
        <template #action>
            <CreateOrganizationModal>
                <Button data-test="organizations-new-organization-button">
                    <Plus /> New organization
                </Button>
            </CreateOrganizationModal>
        </template>

        <div class="space-y-3">
            <div
                v-for="organization in organizations"
                :key="organization.id"
                data-test="organization-row"
                class="flex items-center justify-between gap-4 rounded-lg border p-4"
            >
                <Link
                    :href="edit(organization.slug)"
                    class="focus-visible:ring-ring/50 min-w-0 flex-1 rounded-md outline-none focus-visible:ring-[3px]"
                    data-test="organization-link"
                >
                    <div class="font-medium hover:underline">
                        {{ organization.name }}
                    </div>
                    <span class="text-muted-foreground text-sm">
                        {{ organization.role }}
                    </span>
                </Link>

                <TooltipProvider>
                    <div class="flex items-center gap-2">
                        <Tooltip v-if="canLeaveOrganization(organization)">
                            <TooltipTrigger as-child>
                                <Button
                                    data-test="organization-leave-button"
                                    variant="ghost"
                                    size="sm"
                                    @click="
                                        openLeaveOrganizationDialog(
                                            organization,
                                        )
                                    "
                                >
                                    <LogOut class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Leave organization</p>
                            </TooltipContent>
                        </Tooltip>

                        <Tooltip v-if="!organization.canUpdate">
                            <TooltipTrigger as-child>
                                <Button
                                    data-test="organization-view-button"
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                >
                                    <Link :href="edit(organization.slug)">
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>View organization</p>
                            </TooltipContent>
                        </Tooltip>

                        <Tooltip v-else>
                            <TooltipTrigger as-child>
                                <Button
                                    data-test="organization-edit-button"
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                >
                                    <Link :href="edit(organization.slug)">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Edit organization</p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </TooltipProvider>
            </div>

            <p
                v-if="organizations.length === 0"
                class="text-muted-foreground py-8 text-center"
            >
                You don't belong to any organizations yet.
            </p>
        </div>
    </SectionCard>

    <LeaveOrganizationModal
        v-model:open="leaveOrganizationDialogOpen"
        :organization="organizationLeaving"
    />
</template>
