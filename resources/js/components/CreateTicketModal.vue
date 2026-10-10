<script setup lang="ts">
import { useForm, useHttp } from '@inertiajs/vue3';
import { UserPlus, X } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
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
import { index as lookupContacts } from '@/routes/client-contacts';
import { store } from '@/routes/tickets';
import type { ClientContactOption, TicketClientOption } from '@/types';

const props = defineProps<{
    organization: string;
    clients: TicketClientOption[];
}>();

const open = defineModel<boolean>('open', { default: false });
const none = 'none';

const form = useForm({
    client_contact_id: null as number | null,
    requester_name: '',
    requester_email: '',
    client_id: null as number | null,
    subject: '',
    body: '',
    send: true,
});

const contact = ref<ClientContactOption | null>(null);
const creatingContact = ref(false);
const matches = ref<ClientContactOption[]>([]);
const highlighted = ref(0);
const contactRequest = useHttp<Record<string, never>, ClientContactOption[]>();

const clientValue = computed({
    get: () => (form.client_id === null ? none : String(form.client_id)),
    set: (value: string) => {
        form.client_id = value === none ? null : Number(value);
    },
});

const options = computed(() => [
    ...matches.value.map((match) => ({ kind: 'contact' as const, match })),
    ...(form.requester_name.trim().length > 0
        ? [{ kind: 'new' as const, match: null }]
        : []),
]);

const showOptions = computed(
    () =>
        contact.value === null &&
        !creatingContact.value &&
        options.value.length > 0,
);

const search = useDebounceFn(async () => {
    const term = form.requester_name.trim();

    if (term.length < 2) {
        matches.value = [];

        return;
    }

    contactRequest.cancel();

    try {
        matches.value = await contactRequest.get(
            lookupContacts(props.organization, { query: { q: term } }).url,
        );
        highlighted.value = 0;
    } catch {
        matches.value = [];
    }
}, 200);

const pickContact = (picked: ClientContactOption) => {
    contact.value = picked;
    form.client_contact_id = picked.id;
    form.requester_name = picked.name ?? picked.email;
    form.requester_email = picked.email;
    form.client_id = picked.client?.id ?? null;
    matches.value = [];
};

const startNewContact = () => {
    creatingContact.value = true;
    matches.value = [];
};

const choose = (index: number) => {
    const option = options.value[index];

    if (option?.kind === 'contact') {
        pickContact(option.match);
    } else if (option?.kind === 'new') {
        startNewContact();
    }
};

const clearCustomer = () => {
    contact.value = null;
    creatingContact.value = false;
    form.client_contact_id = null;
    form.requester_name = '';
    form.requester_email = '';
    form.client_id = null;
};

const onCustomerKeydown = (event: KeyboardEvent) => {
    if (!showOptions.value) {
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        highlighted.value = (highlighted.value + 1) % options.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlighted.value =
            (highlighted.value - 1 + options.value.length) %
            options.value.length;
    } else if (event.key === 'Enter') {
        event.preventDefault();
        choose(highlighted.value);
    }
};

const savesContact = computed(
    () => contact.value === null && form.client_id !== null,
);

const handleOpenChange = (value: boolean) => {
    open.value = value;

    if (!value) {
        form.reset();
        form.clearErrors();
        contact.value = null;
        creatingContact.value = false;
        matches.value = [];
    }
};

const submit = () => {
    form.post(store(props.organization).url, {
        onSuccess: () => handleOpenChange(false),
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogTrigger v-if="$slots.default" as-child>
            <slot />
        </DialogTrigger>
        <DialogContent class="sm:max-w-xl">
            <form class="space-y-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>Open a ticket</DialogTitle>
                    <DialogDescription>
                        For a request that did not arrive by email. Replies from
                        the customer land on this ticket.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="requester_name">Customer</Label>

                    <div
                        v-if="contact"
                        class="flex items-center justify-between gap-3 rounded-md border px-3 py-2 text-sm"
                        data-test="ticket-picked-contact"
                    >
                        <span class="min-w-0 truncate">
                            <span class="font-medium">
                                {{ contact.name ?? contact.email }}
                            </span>
                            <span class="text-muted-foreground">
                                · {{ contact.email }}
                                <template v-if="contact.client">
                                    · {{ contact.client.name }}
                                </template>
                            </span>
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            aria-label="Choose someone else"
                            @click="clearCustomer"
                        >
                            <X />
                        </Button>
                    </div>

                    <div v-else class="relative">
                        <Input
                            id="requester_name"
                            v-model="form.requester_name"
                            autocomplete="off"
                            placeholder="Search contacts or type a new name"
                            data-test="ticket-customer"
                            required
                            @input="
                                () => {
                                    creatingContact = false;
                                    void search();
                                }
                            "
                            @keydown="onCustomerKeydown"
                        />
                        <ul
                            v-if="showOptions"
                            class="bg-popover text-popover-foreground absolute right-0 left-0 z-20 mt-1 max-h-56 overflow-y-auto rounded-md border p-1 shadow-md"
                            role="listbox"
                            data-test="ticket-customer-options"
                        >
                            <li
                                v-for="(option, index) in options"
                                :key="option.match?.id ?? 'new'"
                                role="option"
                                :aria-selected="index === highlighted"
                                :class="[
                                    'flex cursor-pointer items-baseline gap-2 rounded-sm px-2 py-1.5 text-sm',
                                    index === highlighted &&
                                        'bg-accent text-accent-foreground',
                                ]"
                                @mousedown.prevent="choose(index)"
                                @mouseenter="highlighted = index"
                            >
                                <template v-if="option.kind === 'contact'">
                                    <span class="font-medium">
                                        {{
                                            option.match.name ??
                                            option.match.email
                                        }}
                                    </span>
                                    <span
                                        class="text-muted-foreground truncate text-xs"
                                    >
                                        {{ option.match.email }}
                                        <template v-if="option.match.client">
                                            · {{ option.match.client.name }}
                                        </template>
                                    </span>
                                </template>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-2"
                                    data-test="ticket-new-contact-option"
                                >
                                    <UserPlus class="size-3.5" />
                                    New contact:
                                    {{ form.requester_name.trim() }}
                                </span>
                            </li>
                        </ul>
                    </div>
                    <InputError :message="form.errors.requester_name" />
                </div>

                <div v-if="!contact" class="grid gap-2">
                    <Label for="requester_email">Email</Label>
                    <Input
                        id="requester_email"
                        v-model="form.requester_email"
                        type="email"
                        data-test="ticket-requester-email"
                        required
                    />
                    <InputError :message="form.errors.requester_email" />
                </div>

                <div class="grid gap-2">
                    <Label>Client</Label>
                    <Select v-model="clientValue">
                        <SelectTrigger class="w-full" data-test="ticket-client">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="none">Unassigned</SelectItem>
                            <SelectItem
                                v-for="client in clients"
                                :key="client.id"
                                :value="String(client.id)"
                            >
                                {{ client.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p
                        v-if="savesContact"
                        class="text-muted-foreground text-xs"
                    >
                        {{ form.requester_name.trim() || 'This customer' }} is
                        saved as a contact of this client.
                    </p>
                    <InputError :message="form.errors.client_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="subject">Subject</Label>
                    <Input
                        id="subject"
                        v-model="form.subject"
                        data-test="ticket-subject"
                        required
                    />
                    <InputError :message="form.errors.subject" />
                </div>

                <div class="grid gap-2">
                    <Label>First message</Label>
                    <RichTextEditor
                        v-model="form.body"
                        data-test="ticket-body"
                        class="min-h-32"
                    />
                    <InputError :message="form.errors.body" />
                </div>

                <label
                    class="text-muted-foreground flex items-center gap-2 text-sm"
                >
                    <Checkbox
                        :model-value="form.send"
                        data-test="ticket-send"
                        @update:model-value="form.send = $event === true"
                    />
                    Email this message to the customer
                </label>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Button
                        type="submit"
                        data-test="ticket-submit"
                        :disabled="form.processing"
                    >
                        {{ form.send ? 'Open and send' : 'Open ticket' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
