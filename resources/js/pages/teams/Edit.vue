<script setup lang="ts">
    import { Form, Head } from '@inertiajs/vue3'
    import { Mail, UserPlus, X } from '@lucide/vue'
    import { computed, ref } from 'vue'
    import CancelInvitationModal from '@/components/CancelInvitationModal.vue'
    import DeleteTeamModal from '@/components/DeleteTeamModal.vue'
    import Heading from '@/components/Heading.vue'
    import InviteMemberModal from '@/components/InviteMemberModal.vue'
    import RemoveMemberModal from '@/components/RemoveMemberModal.vue'
    import Stack from '@/components/Stack.vue'
    import TextInput from '@/components/TextInput.vue'
    import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
    import { Badge } from '@/components/ui/badge'
    import { Button } from '@/components/ui/button'
    import {
        Tooltip,
        TooltipContent,
        TooltipProvider,
        TooltipTrigger,
    } from '@/components/ui/tooltip'
    import { useInitials } from '@/composables/useInitials'
    import { usePermissions } from '@/composables/usePermissions'
    import { edit, index, update } from '@/routes/teams'
    import type { Team, TeamInvitation, TeamMember } from '@/types'

    type Props = {
        team: Team
        members: TeamMember[]
        invitations: TeamInvitation[]
    }

    const props = defineProps<Props>()

    defineOptions({
        layout: (props: { team: Team }) => ({
            breadcrumbs: [
                {
                    title: 'Teams',
                    href: index(),
                },
                {
                    title: props.team.name,
                    href: edit(props.team.slug),
                },
            ],
        }),
    })

    const { getInitials } = useInitials()
    const { can } = usePermissions()

    const inviteDialogOpen = ref(false)
    const deleteDialogOpen = ref(false)
    const removeMemberDialogOpen = ref(false)
    const memberToRemove = ref<TeamMember | null>(null)
    const cancelInvitationDialogOpen = ref(false)
    const invitationToCancel = ref<TeamInvitation | null>(null)

    const pageTitle = computed(() =>
        can('team:update')
            ? `Edit ${props.team.name}`
            : `View ${props.team.name}`,
    )

    const confirmRemoveMember = (member: TeamMember) => {
        memberToRemove.value = member
        removeMemberDialogOpen.value = true
    }

    const confirmCancelInvitation = (invitation: TeamInvitation) => {
        invitationToCancel.value = invitation
        cancelInvitationDialogOpen.value = true
    }
</script>

<template>
    <Head :title="pageTitle" />

    <h1 class="sr-only">{{ pageTitle }}</h1>

    <div class="flex flex-col space-y-10">
        <!-- Team Name Section -->
        <div v-if="can('team:update')" class="space-y-6">
            <Heading
                variant="small"
                title="Team settings"
                description="Update your team name and settings"
            />

            <Form
                v-bind="update.form(team.slug)"
                v-slot="{ errors, processing }"
            >
                <Stack>
                    <TextInput
                        id="name"
                        name="name"
                        label="Team name"
                        :error="errors.name"
                        data-test="team-name-input"
                        :default-value="team.name"
                        required
                    />

                    <div class="flex items-center gap-4">
                        <Button
                            type="submit"
                            data-test="team-save-button"
                            :disabled="processing"
                        >
                            Save
                        </Button>
                    </div>
                </Stack>
            </Form>
        </div>

        <div v-else class="space-y-6">
            <Heading variant="small" :title="team.name" />
        </div>

        <!-- Members Section -->
        <Stack>
            <div class="flex items-center justify-between">
                <Heading
                    variant="small"
                    title="Team members"
                    :description="
                        can('invitation:create')
                            ? 'Manage who belongs to this team'
                            : ''
                    "
                />

                <Button
                    v-if="can('invitation:create')"
                    data-test="invite-member-button"
                    @click="inviteDialogOpen = true"
                >
                    <UserPlus /> Invite member
                </Button>
            </div>

            <Stack gap="3">
                <div
                    v-for="member in members"
                    :key="member.id"
                    data-test="member-row"
                    class="flex items-center justify-between rounded-lg border p-4"
                >
                    <div class="flex items-center gap-4">
                        <Avatar class="h-10 w-10">
                            <AvatarImage
                                v-if="member.avatar"
                                :src="member.avatar"
                                :alt="member.name"
                            />
                            <AvatarFallback>{{
                                getInitials(member.name)
                            }}</AvatarFallback>
                        </Avatar>
                        <div>
                            <div class="font-medium">
                                {{ member.name }}
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{ member.email }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Badge v-if="member.isOwner" variant="secondary">
                            Owner
                        </Badge>

                        <TooltipProvider
                            v-if="!member.isOwner && can('member:remove')"
                        >
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        data-test="member-remove-button"
                                        variant="ghost"
                                        size="sm"
                                        @click="confirmRemoveMember(member)"
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Remove member</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                </div>
            </Stack>
        </Stack>

        <!-- Pending Invitations Section -->
        <div v-if="invitations.length > 0" class="space-y-6">
            <Heading
                variant="small"
                title="Pending invitations"
                description="Invitations that haven't been accepted yet"
            />

            <div class="space-y-3">
                <div
                    v-for="invitation in invitations"
                    :key="invitation.code"
                    data-test="invitation-row"
                    class="flex items-center justify-between rounded-lg border p-4"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-muted"
                        >
                            <Mail class="h-5 w-5 text-muted-foreground" />
                        </div>
                        <div>
                            <div class="font-medium">
                                {{ invitation.email }}
                            </div>
                        </div>
                    </div>

                    <TooltipProvider v-if="can('invitation:cancel')">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    data-test="invitation-cancel-button"
                                    variant="ghost"
                                    size="sm"
                                    @click="confirmCancelInvitation(invitation)"
                                >
                                    <X class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Cancel invitation</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </div>
        </div>

        <!-- Danger Zone -->
        <div v-if="can('team:delete') && !team.isPersonal" class="space-y-6">
            <Heading
                variant="small"
                title="Delete team"
                description="Permanently delete your team"
            />
            <div
                class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
            >
                <div
                    class="relative space-y-0.5 text-red-600 dark:text-red-100"
                >
                    <p class="font-medium">Warning</p>
                    <p class="text-sm">
                        Please proceed with caution, this cannot be undone.
                    </p>
                </div>
                <Button
                    data-test="delete-team-button"
                    variant="destructive"
                    @click="deleteDialogOpen = true"
                    >Delete team</Button
                >
            </div>
        </div>
    </div>

    <InviteMemberModal
        v-if="can('invitation:create')"
        :team="team"
        :open="inviteDialogOpen"
        @update:open="inviteDialogOpen = $event"
    />

    <RemoveMemberModal
        :team="team"
        :member="memberToRemove"
        :open="removeMemberDialogOpen"
        @update:open="removeMemberDialogOpen = $event"
    />

    <CancelInvitationModal
        :team="team"
        :invitation="invitationToCancel"
        :open="cancelInvitationDialogOpen"
        @update:open="cancelInvitationDialogOpen = $event"
    />

    <DeleteTeamModal
        v-if="can('team:delete') && !team.isPersonal"
        :team="team"
        :open="deleteDialogOpen"
        @update:open="deleteDialogOpen = $event"
    />
</template>
