<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store } from '@/routes/clients/contacts';

const props = defineProps<{
    organization: string;
    client?: string;
    clients?: { name: string; slug: string }[];
}>();

const open = defineModel<boolean>('open', { default: false });
const formKey = ref(0);
const chosenClient = ref(props.client ?? '');

function handleOpenChange(value: boolean) {
    open.value = value;

    if (!value) {
        formKey.value++;
        chosenClient.value = props.client ?? '';
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogTrigger v-if="$slots.default" as-child>
            <slot />
        </DialogTrigger>
        <DialogContent>
            <Form
                :key="formKey"
                v-bind="store.form([organization, chosenClient || '_'])"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <DialogHeader>
                    <DialogTitle>Add a contact</DialogTitle>
                    <DialogDescription>
                        Someone at this client you open tickets for.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <div v-if="!client && clients" class="grid gap-2">
                        <Label for="contact-client">Client</Label>
                        <Select v-model="chosenClient">
                            <SelectTrigger id="contact-client" class="w-full">
                                <SelectValue placeholder="Choose a client" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in clients"
                                    :key="option.slug"
                                    :value="option.slug"
                                >
                                    {{ option.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="contact-name">Name</Label>
                        <Input
                            id="contact-name"
                            name="name"
                            placeholder="Jane Doe"
                            data-test="client-contact-name"
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="contact-title">Title</Label>
                        <Input
                            id="contact-title"
                            name="title"
                            placeholder="CTO"
                            data-test="client-contact-title"
                        />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="contact-phone">Phone</Label>
                        <Input
                            id="contact-phone"
                            name="phone"
                            type="tel"
                            placeholder="+1 555 123 4567"
                            data-test="client-contact-phone"
                        />
                        <InputError :message="errors.phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="contact-email">Email</Label>
                        <Input
                            id="contact-email"
                            name="email"
                            type="email"
                            placeholder="name@acme.com"
                            data-test="client-contact-email"
                            required
                        />
                        <InputError :message="errors.email" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary"> Cancel </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        data-test="client-contact-add"
                        :disabled="processing || !chosenClient"
                    >
                        Add contact
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
