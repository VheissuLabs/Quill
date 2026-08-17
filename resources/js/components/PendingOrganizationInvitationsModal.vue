<script setup lang="ts">
    import { router } from '@inertiajs/vue3'
    import { ref } from 'vue'
    import OrganizationMembershipController from '@/actions/App/Http/Controllers/Organizations/OrganizationMembershipController'
    import Stack from '@/components/Stack.vue'
    import { Button } from '@/components/ui/button'
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog'
    import type { DashboardOrganizationInvitation } from '@/types'

    type Props = {
        invitations: DashboardOrganizationInvitation[]
    }

    const props = defineProps<Props>()

    const open = ref(true)
    const processingCode = ref<string | null>(null)

    const accept = (invitation: DashboardOrganizationInvitation) => {
        router.visit(OrganizationMembershipController.store(invitation), {
            onStart: () => (processingCode.value = invitation.code),
            onFinish: () => (processingCode.value = null),
        })
    }

    const decline = (invitation: DashboardOrganizationInvitation) => {
        router.visit(OrganizationMembershipController.destroy(invitation), {
            onStart: () => (processingCode.value = invitation.code),
            onFinish: () => (processingCode.value = null),
            onSuccess: () => {
                if (props.invitations.length === 1) {
                    open.value = false
                }
            },
        })
    }
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent data-test="pending-organization-invitations-modal">
            <DialogHeader>
                <DialogTitle>Pending invitations</DialogTitle>
                <DialogDescription>
                    Accept or decline the organizations you have been invited
                    to.
                </DialogDescription>
            </DialogHeader>

            <Stack gap="4">
                <div
                    v-for="invitation in props.invitations"
                    :key="invitation.code"
                    data-test="pending-organization-invitation-row"
                    class="rounded-lg border p-4"
                >
                    <div class="space-y-1">
                        <p class="font-medium">
                            {{ invitation.organizationName }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ invitation.inviterName }} invited you
                            <template v-if="invitation.clientName">
                                as a contact for
                                {{ invitation.clientName }}.
                            </template>
                            <template v-else>
                                as {{ invitation.roleLabel }}.
                            </template>
                        </p>
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <Button
                            variant="secondary"
                            data-test="pending-organization-invitation-decline"
                            :disabled="processingCode === invitation.code"
                            @click="decline(invitation)"
                        >
                            Decline
                        </Button>

                        <Button
                            data-test="pending-organization-invitation-accept"
                            :disabled="processingCode === invitation.code"
                            @click="accept(invitation)"
                        >
                            Accept
                        </Button>
                    </div>
                </div>
            </Stack>
        </DialogContent>
    </Dialog>
</template>
