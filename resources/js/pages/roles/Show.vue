<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Check, Lock, Mail, Pencil, UserPlus, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import AssignRoleModal from '@/components/AssignRoleModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import InviteMemberModal from '@/components/InviteMemberModal.vue';
import RoleFormModal from '@/components/RoleFormModal.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/composables/useInitials';
import { index, show } from '@/routes/roles';
import type {
    Organization,
    OrganizationPermissions,
    OrganizationRoleItem,
    PermissionOption,
    RoleCandidate,
    RoleOption,
} from '@/types';

type Props = {
    currentOrganization: Organization;
    role: OrganizationRoleItem;
    availablePermissions: PermissionOption[];
    availableRoles: RoleOption[];
    candidates: RoleCandidate[];
    permissions: OrganizationPermissions;
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: {
        currentOrganization: Organization;
        role: OrganizationRoleItem;
    }) => ({
        breadcrumbs: [
            { title: 'Roles', href: index(props.currentOrganization.slug) },
            {
                title: props.role.name,
                href: show([props.currentOrganization.slug, props.role.id]),
            },
        ],
    }),
});

const { getInitials } = useInitials();

const editDialogOpen = ref(false);
const assignDialogOpen = ref(false);
const inviteDialogOpen = ref(false);

/**
 * Permissions grouped the way the role editor shows them, marking the ones this role grants.
 * The owner role grants everything.
 */
const permissionGroups = computed(() => {
    const granted = new Set(props.role.permissions);
    const groups = new Map<string, { label: string; granted: boolean }[]>();

    for (const option of props.availablePermissions) {
        const list = groups.get(option.group) ?? [];
        list.push({
            label: option.label,
            granted: props.role.isOwner || granted.has(option.value),
        });
        groups.set(option.group, list);
    }

    return [...groups.entries()].map(([group, items]) => ({ group, items }));
});

const grantedCount = computed(() =>
    props.role.isOwner
        ? props.availablePermissions.length
        : props.role.permissions.length,
);

const canInvite = computed(
    () =>
        props.permissions.canCreateInvitation &&
        props.availableRoles.some((option) => option.value === props.role.id),
);
</script>

<template>
    <Head :title="role.name" />

    <div class="flex flex-col gap-10 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-semibold tracking-tight">
                        {{ role.name }}
                    </h2>
                    <Lock
                        v-if="role.isOwner"
                        class="text-muted-foreground size-4"
                        aria-label="Locked"
                    />
                </div>
                <p class="text-muted-foreground text-sm tabular-nums">
                    {{ grantedCount }} of {{ availablePermissions.length }}
                    permissions ·
                    {{ role.membersCount }}
                    {{ role.membersCount === 1 ? 'member' : 'members' }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="permissions.canManageRoles && !role.isOwner"
                    variant="outline"
                    data-test="edit-role-button"
                    @click="editDialogOpen = true"
                >
                    <Pencil /> Edit role
                </Button>
                <Button
                    v-if="canInvite"
                    variant="outline"
                    data-test="invite-to-role-button"
                    @click="inviteDialogOpen = true"
                >
                    <Mail /> Invite by email
                </Button>
                <Button
                    v-if="permissions.canUpdateMember"
                    data-test="assign-role-button"
                    @click="assignDialogOpen = true"
                >
                    <UserPlus /> Add member
                </Button>
            </div>
        </div>

        <section class="space-y-4">
            <Heading
                variant="small"
                title="Members"
                description="People who hold this role in the organization."
            />

            <div v-if="role.members.length > 0" class="space-y-2">
                <div
                    v-for="member in role.members"
                    :key="member.id"
                    data-test="role-member-row"
                    class="flex items-center gap-3 rounded-lg border p-3"
                >
                    <Avatar class="size-9">
                        <AvatarFallback class="text-xs">
                            {{ getInitials(member.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0">
                        <div class="truncate font-medium">
                            {{ member.name }}
                        </div>
                        <div class="text-muted-foreground truncate text-sm">
                            {{ member.email }}
                        </div>
                    </div>
                </div>
            </div>

            <EmptyState
                v-else
                :icon="Users"
                title="Nobody holds this role yet"
                :description="
                    permissions.canUpdateMember
                        ? 'Give it to an existing member, or invite someone new straight into it.'
                        : 'An admin can give this role to members.'
                "
            >
                <Button
                    v-if="permissions.canUpdateMember"
                    variant="outline"
                    @click="assignDialogOpen = true"
                >
                    <UserPlus /> Add member
                </Button>
            </EmptyState>
        </section>

        <section class="space-y-4">
            <Heading
                variant="small"
                title="Permissions"
                :description="
                    role.isOwner
                        ? 'The owner role always grants every permission.'
                        : 'What members holding this role can do.'
                "
            />

            <div class="grid gap-6 sm:grid-cols-2">
                <div
                    v-for="group in permissionGroups"
                    :key="group.group"
                    class="space-y-2"
                >
                    <h3
                        class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                    >
                        {{ group.group }}
                    </h3>
                    <ul class="space-y-1.5 text-sm">
                        <li
                            v-for="item in group.items"
                            :key="item.label"
                            class="flex items-center gap-2"
                            :class="!item.granted && 'text-muted-foreground/60'"
                        >
                            <Check
                                class="size-4 shrink-0"
                                :class="
                                    item.granted ? 'text-primary' : 'invisible'
                                "
                            />
                            {{ item.label }}
                        </li>
                    </ul>
                </div>
            </div>
        </section>
    </div>

    <RoleFormModal
        v-if="permissions.canManageRoles && !role.isOwner"
        :organization="currentOrganization"
        :role="role"
        :available-permissions="availablePermissions"
        :open="editDialogOpen"
        @update:open="editDialogOpen = $event"
    />

    <AssignRoleModal
        v-if="permissions.canUpdateMember"
        :organization="currentOrganization"
        :role="role"
        :candidates="candidates"
        :open="assignDialogOpen"
        @update:open="assignDialogOpen = $event"
    />

    <InviteMemberModal
        v-if="canInvite"
        :organization="currentOrganization"
        :available-roles="availableRoles"
        :default-role="role.id"
        :open="inviteDialogOpen"
        @update:open="inviteDialogOpen = $event"
    />
</template>
