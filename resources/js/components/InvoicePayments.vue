<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
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
import { Textarea } from '@/components/ui/textarea';
import { destroy, store, update } from '@/routes/invoices/payments';
import type { Invoice, InvoicePayment } from '@/types';

const props = defineProps<{
    organization: string;
    invoice: Invoice;
}>();

const today = () => new Date().toLocaleDateString('en-CA');

const dialogOpen = ref(false);
const editing = ref<InvoicePayment | null>(null);
const deleting = ref<InvoicePayment | null>(null);
const deleteOpen = ref(false);

const form = useForm({
    amount: '',
    paid_on: today(),
    method: 'check',
    reference: '',
    notes: '',
});

const money = (value: string) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(Number(value));

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.paid_on = today();
    dialogOpen.value = true;
};

const openEdit = (payment: InvoicePayment) => {
    editing.value = payment;
    form.clearErrors();
    form.amount = payment.amount;
    form.paid_on = payment.paidOn;
    form.method = payment.method;
    form.reference = payment.reference ?? '';
    form.notes = payment.notes ?? '';
    dialogOpen.value = true;
};

const openDelete = (payment: InvoicePayment) => {
    deleting.value = payment;
    deleteOpen.value = true;
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
    };

    form.transform((data) => ({
        ...data,
        reference: data.reference || null,
        notes: data.notes || null,
    }));

    if (editing.value) {
        form.patch(
            update.url([
                props.organization,
                props.invoice.id,
                editing.value.id,
            ]),
            options,
        );

        return;
    }

    form.post(store.url([props.organization, props.invoice.id]), options);
};
</script>

<template>
    <Card class="gap-4" data-test="invoice-payments">
        <CardHeader>
            <CardTitle>Payments</CardTitle>
            <CardAction v-if="invoice.canUpdate">
                <Button
                    variant="outline"
                    size="sm"
                    data-test="record-payment-button"
                    @click="openCreate"
                >
                    <Plus /> Record payment
                </Button>
            </CardAction>
        </CardHeader>

        <CardContent>
            <ul v-if="invoice.payments.length > 0" class="divide-y text-sm">
                <li
                    v-for="payment in invoice.payments"
                    :key="payment.id"
                    class="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0"
                    data-test="invoice-payment-row"
                >
                    <span>
                        {{ payment.paidOn }} · {{ payment.methodLabel }}
                        <span
                            v-if="payment.reference"
                            class="text-muted-foreground"
                        >
                            · {{ payment.reference }}
                        </span>
                    </span>
                    <span class="flex items-center gap-2">
                        <span class="tabular-nums">{{
                            money(payment.amount)
                        }}</span>
                        <template v-if="invoice.canUpdate">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Edit payment"
                                data-test="edit-payment-button"
                                @click="openEdit(payment)"
                            >
                                <Pencil />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Delete payment"
                                data-test="delete-payment-button"
                                @click="openDelete(payment)"
                            >
                                <Trash2 />
                            </Button>
                        </template>
                    </span>
                </li>
            </ul>
            <p v-else class="text-muted-foreground text-sm">No payments yet.</p>
        </CardContent>

        <Dialog v-model:open="dialogOpen">
            <DialogContent>
                <form class="space-y-6" @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>
                            {{ editing ? 'Edit payment' : 'Record payment' }}
                        </DialogTitle>
                    </DialogHeader>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="payment-amount">Amount</Label>
                            <Input
                                id="payment-amount"
                                v-model="form.amount"
                                type="number"
                                min="0"
                                step="0.01"
                                data-test="payment-amount-input"
                            />
                            <InputError :message="form.errors.amount" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="payment-date">Paid on</Label>
                            <Input
                                id="payment-date"
                                v-model="form.paid_on"
                                type="date"
                            />
                            <InputError :message="form.errors.paid_on" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="payment-method">Method</Label>
                            <Select v-model="form.method">
                                <SelectTrigger
                                    id="payment-method"
                                    class="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="method in invoice.options
                                            .paymentMethods"
                                        :key="method.value"
                                        :value="method.value"
                                    >
                                        {{ method.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.method" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="payment-reference">Reference</Label>
                            <Input
                                id="payment-reference"
                                v-model="form.reference"
                            />
                            <InputError :message="form.errors.reference" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="payment-notes">Notes</Label>
                        <Textarea
                            id="payment-notes"
                            v-model="form.notes"
                            rows="3"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            data-test="payment-save-button"
                            :disabled="form.processing"
                        >
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDeleteModal
            v-model:open="deleteOpen"
            title="Delete this payment?"
            confirm-label="Delete payment"
            :href="
                deleting
                    ? destroy([organization, invoice.id, deleting.id])
                    : null
            "
        />
    </Card>
</template>
