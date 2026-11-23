<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store } from '@/routes/invoices';
import type { InvoiceProjectOption } from '@/types';

const props = defineProps<{
    organization: string;
    clients: { id: number; name: string }[];
    projects: InvoiceProjectOption[];
    clientId?: number;
}>();

const open = defineModel<boolean>('open', { default: false });
const none = 'none';
const formKey = ref(0);

const form = useForm({
    client_id: props.clientId ? String(props.clientId) : '',
    project_id: none,
});

const availableProjects = computed(() =>
    form.client_id === ''
        ? props.projects
        : props.projects.filter(
              (project) => String(project.billToClientId) === form.client_id,
          ),
);

watch(
    () => form.client_id,
    () => {
        if (
            !availableProjects.value.some(
                (project) => String(project.id) === form.project_id,
            )
        ) {
            form.project_id = none;
        }
    },
);

watch(
    () => form.project_id,
    (projectId) => {
        const project = props.projects.find(
            (option) => String(option.id) === projectId,
        );

        if (project && form.client_id === '') {
            form.client_id = String(project.billToClientId);
        }
    },
);

const needsClient = computed(() => form.client_id === '');

function handleOpenChange(value: boolean) {
    open.value = value;

    if (!value) {
        form.reset();
        form.clearErrors();
        formKey.value++;
    }
}

const submit = () => {
    form.transform((data) => ({
        client_id: data.client_id === '' ? null : Number(data.client_id),
        project_id: data.project_id === none ? null : Number(data.project_id),
    })).post(store.url(props.organization), {
        onSuccess: () => (open.value = false),
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogTrigger v-if="$slots.default" as-child>
            <slot />
        </DialogTrigger>
        <DialogContent>
            <form class="space-y-6" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>New invoice</DialogTitle>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="invoice-client">Client</Label>
                    <Select
                        v-model="form.client_id"
                        data-test="create-invoice-client"
                    >
                        <SelectTrigger id="invoice-client" class="w-full">
                            <SelectValue placeholder="Select a client" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="client in clients"
                                :key="client.id"
                                :value="String(client.id)"
                            >
                                {{ client.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.client_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="invoice-project">Project</Label>
                    <Select
                        v-model="form.project_id"
                        data-test="create-invoice-project"
                    >
                        <SelectTrigger id="invoice-project" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="none">None</SelectItem>
                            <SelectItem
                                v-for="project in availableProjects"
                                :key="project.id"
                                :value="String(project.id)"
                            >
                                {{ project.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.project_id" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        data-test="create-invoice-submit"
                        :disabled="form.processing || needsClient"
                    >
                        Create invoice
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
