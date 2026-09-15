<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/organizations/roles';
import type {
    Organization,
    OrganizationRoleItem,
    PermissionOption,
} from '@/types';

type Props = {
    organization: Organization;
    role: OrganizationRoleItem | null;
    availablePermissions: PermissionOption[];
    open: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const form = useForm({
    name: '',
    permissions: [] as string[],
});

/**
 * Load the role being edited, or a blank form for a new one, each time the dialog opens.
 */
watch(
    () => props.open,
    (open) => {
        if (open) {
            form.defaults({
                name: props.role?.name ?? '',
                permissions: [...(props.role?.permissions ?? [])],
            });
            form.reset();
            form.clearErrors();
        }
    },
);

const groups = computed(() => {
    const grouped = new Map<string, PermissionOption[]>();

    for (const permission of props.availablePermissions) {
        grouped.set(permission.group, [
            ...(grouped.get(permission.group) ?? []),
            permission,
        ]);
    }

    return [...grouped.entries()].map(([name, permissions]) => ({
        name,
        permissions,
    }));
});

const togglePermission = (value: string, checked: boolean) => {
    form.permissions = checked
        ? [...new Set([...form.permissions, value])]
        : form.permissions.filter((permission) => permission !== value);
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    };

    if (props.role) {
        form.patch(
            update.url([props.organization.slug, props.role.id]),
            options,
        );
    } else {
        form.post(store.url(props.organization.slug), options);
    }
};
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90svh] overflow-y-auto sm:max-w-lg">
            <form class="space-y-6" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>
                        {{ props.role ? 'Edit role' : 'Create a role' }}
                    </DialogTitle>
                    <DialogDescription>
                        Name the role the way your organization talks about it,
                        then pick what it can do.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="role-name">Role name</Label>
                    <Input
                        id="role-name"
                        v-model="form.name"
                        data-test="role-name"
                        placeholder="Product manager"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="space-y-5">
                    <div
                        v-for="group in groups"
                        :key="group.name"
                        class="space-y-2"
                    >
                        <p
                            class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                        >
                            {{ group.name }}
                        </p>
                        <div class="grid gap-2">
                            <div
                                v-for="permission in group.permissions"
                                :key="permission.value"
                                class="flex items-center gap-2"
                            >
                                <Checkbox
                                    :id="`permission-${permission.value}`"
                                    data-test="role-permission"
                                    :model-value="
                                        form.permissions.includes(
                                            permission.value,
                                        )
                                    "
                                    @update:model-value="
                                        togglePermission(
                                            permission.value,
                                            $event === true,
                                        )
                                    "
                                />
                                <Label
                                    :for="`permission-${permission.value}`"
                                    class="font-normal"
                                >
                                    {{ permission.label }}
                                </Label>
                            </div>
                        </div>
                    </div>
                    <InputError :message="form.errors.permissions" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        data-test="role-submit"
                        :disabled="form.processing"
                    >
                        {{ props.role ? 'Save role' : 'Create role' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
