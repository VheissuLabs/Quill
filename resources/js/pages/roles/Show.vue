<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Check,
    ChevronDown,
    Lock,
    Mail,
    Pencil,
    UserPlus,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AssignRoleModal from '@/components/AssignRoleModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import SettingsCard from '@/components/SettingsCard.vue';
import InviteMemberModal from '@/components/InviteMemberModal.vue';
import RemoveMemberModal from '@/components/RemoveMemberModal.vue';
import RoleFormModal from '@/components/RoleFormModal.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useInitials } from '@/composables/useInitials';
import { update as updateMember } from '@/routes/organizations/members';
import { index, show } from '@/routes/roles';
import type {
    Organization,
    OrganizationMember,
    OrganizationPermissions,
    OrganizationRoleItem,
    PermissionOption,
    RoleCandidate,
    RoleOption,
} from '@/types';
import EmailLink from '@/components/EmailLink.vue';

type Props = {
    currentOrganization: Organization;
    role: OrganizationRoleItem;
    availablePermissions: PermissionOption[];
    availableRoles: RoleOption[];
    candidates: RoleCandidate[];
    members: OrganizationMember[];
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
const removeDialogOpen = ref(false);
const memberToRemove = ref<OrganizationMember | null>(null);

const changeRole = (member: OrganizationMember, roleId: number) => {
    router.visit(updateMember([props.currentOrganization.slug, member.id]), {
        data: { role_id: roleId },
        preserveScroll: true,
    });
};

const confirmRemove = (member: OrganizationMember) => {
    memberToRemove.value = member;
    removeDialogOpen.value = true;
};

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

    <div class="grid max-w-7xl items-start gap-6 p-6 lg:grid-cols-2">
        <div
            class="flex flex-wrap items-start justify-between gap-4 lg:col-span-2"
        >
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
                    v-if="permissions.canUpdateMember && candidates.length > 0"
                    data-test="assign-role-button"
                    @click="assignDialogOpen = true"
                >
                    <UserPlus /> Add member
                </Button>
            </div>
        </div>

        <SettingsCard
            title="Members"
            description="People who hold this role in the organization."
        >
            <p
                v-if="role.isOwner && members.length === 1"
                class="text-muted-foreground text-sm"
                data-test="role-last-owner-note"
            >
                Every organization needs an owner. Make someone else an owner
                before {{ members[0].name }} can step down.
            </p>

            <div v-if="members.length > 0" class="space-y-2">
                <div
                    v-for="member in members"
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
                            <EmailLink :email="member.email" />
                        </div>
                    </div>

                    <div class="ml-auto flex shrink-0 items-center gap-2">
                        <DropdownMenu v-if="member.canUpdate">
                            <DropdownMenuTrigger as-child>
                                <Button
                                    data-test="role-member-change-role"
                                    variant="outline"
                                    size="sm"
                                >
                                    Change role
                                    <ChevronDown class="opacity-50" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    v-for="option in availableRoles.filter(
                                        (option) => option.value !== role.id,
                                    )"
                                    :key="option.value"
                                    @click="changeRole(member, option.value)"
                                >
                                    {{ option.label }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <Button
                            v-if="member.canRemove"
                            variant="ghost"
                            size="icon-sm"
                            :aria-label="`Remove ${member.name} from the organization`"
                            data-test="role-member-remove"
                            @click="confirmRemove(member)"
                        >
                            <X />
                        </Button>
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
        </SettingsCard>

        <SettingsCard
            title="Permissions"
            :description="
                role.isOwner
                    ? 'The owner role always grants every permission.'
                    : 'What members holding this role can do.'
            "
        >
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
        </SettingsCard>
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
    <RemoveMemberModal
        v-model:open="removeDialogOpen"
        :organization="currentOrganization"
        :member="memberToRemove"
    />
</template>
