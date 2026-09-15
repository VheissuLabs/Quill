<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Lock, Pencil, ShieldCheck, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import RoleFormModal from '@/components/RoleFormModal.vue';
import { Button } from '@/components/ui/button';
import { destroy as destroyRole } from '@/routes/organizations/roles';
import { index, show } from '@/routes/roles';
import type {
    Organization,
    OrganizationPermissions,
    OrganizationRoleItem,
    PermissionOption,
} from '@/types';

type Props = {
    currentOrganization: Organization;
    roles: OrganizationRoleItem[];
    availablePermissions: PermissionOption[];
    permissions: OrganizationPermissions;
};

defineProps<Props>();

defineOptions({
    layout: (props: { currentOrganization: Organization }) => ({
        breadcrumbs: [
            {
                title: 'Roles',
                href: index(props.currentOrganization.slug),
            },
        ],
    }),
});

const roleDialogOpen = ref(false);
const roleBeingEdited = ref<OrganizationRoleItem | null>(null);
const deleteRoleDialogOpen = ref(false);
const roleToDelete = ref<OrganizationRoleItem | null>(null);

const openRoleDialog = (role: OrganizationRoleItem | null) => {
    roleBeingEdited.value = role;
    roleDialogOpen.value = true;
};

const confirmDeleteRole = (role: OrganizationRoleItem) => {
    roleToDelete.value = role;
    deleteRoleDialogOpen.value = true;
};

const permissionSummary = (role: OrganizationRoleItem) =>
    role.isOwner
        ? 'Every permission'
        : role.permissions.length === 0
          ? 'No permissions'
          : `${role.permissions.length} ${role.permissions.length === 1 ? 'permission' : 'permissions'}`;
</script>

<template>
    <Head title="Roles" />

    <div class="flex flex-col gap-8 p-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Roles"
                description="Roles are named sets of permissions. Define the ones your organization needs; the owner role is fixed."
            />

            <Button
                v-if="permissions.canManageRoles"
                data-test="create-role-button"
                @click="openRoleDialog(null)"
            >
                <ShieldCheck /> New role
            </Button>
        </div>

        <div class="space-y-3">
            <div
                v-for="role in roles"
                :key="role.id"
                data-test="role-row"
                class="flex items-center justify-between gap-4 rounded-lg border p-4"
            >
                <Link
                    :href="show([currentOrganization.slug, role.id])"
                    class="focus-visible:ring-ring/50 min-w-0 flex-1 rounded-md outline-none focus-visible:ring-[3px]"
                    data-test="role-link"
                >
                    <div
                        class="flex items-center gap-2 font-medium hover:underline"
                    >
                        {{ role.name }}
                        <Lock
                            v-if="role.isOwner"
                            class="text-muted-foreground size-3.5"
                            aria-label="Locked"
                        />
                    </div>
                    <div class="text-muted-foreground text-sm tabular-nums">
                        {{ permissionSummary(role) }} ·
                        {{ role.membersCount }}
                        {{ role.membersCount === 1 ? 'member' : 'members' }}
                    </div>
                    <p
                        v-if="role.members.length > 0"
                        class="text-muted-foreground mt-2 text-sm"
                        data-test="role-members"
                    >
                        {{
                            role.members.map((member) => member.name).join(', ')
                        }}
                    </p>
                </Link>

                <div
                    v-if="permissions.canManageRoles && !role.isOwner"
                    class="flex shrink-0 items-center gap-2"
                >
                    <Button
                        data-test="edit-role-button"
                        variant="ghost"
                        size="sm"
                        @click="openRoleDialog(role)"
                    >
                        <Pencil class="h-4 w-4" />
                    </Button>
                    <Button
                        data-test="delete-role-button"
                        variant="ghost"
                        size="sm"
                        :disabled="role.membersCount > 0"
                        :title="
                            role.membersCount > 0
                                ? 'Move its members to another role first'
                                : 'Delete role'
                        "
                        @click="confirmDeleteRole(role)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>

    <RoleFormModal
        v-if="permissions.canManageRoles"
        :organization="currentOrganization"
        :role="roleBeingEdited"
        :available-permissions="availablePermissions"
        :open="roleDialogOpen"
        @update:open="roleDialogOpen = $event"
    />

    <ConfirmDeleteModal
        title="Delete role"
        confirm-label="Delete role"
        :href="
            roleToDelete
                ? destroyRole([currentOrganization.slug, roleToDelete.id])
                : null
        "
        :open="deleteRoleDialogOpen"
        @update:open="deleteRoleDialogOpen = $event"
    >
        The role "{{ roleToDelete?.name }}" will be removed. Nobody holds it, so
        no one loses access.
    </ConfirmDeleteModal>
</template>
