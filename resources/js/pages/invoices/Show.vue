<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Copy, Link2, Send, Trash2, Ban } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmActionModal from '@/components/ConfirmActionModal.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import InvoiceLines from '@/components/InvoiceLines.vue';
import InvoicePayments from '@/components/InvoicePayments.vue';
import InvoiceStatusBadge from '@/components/InvoiceStatusBadge.vue';
import { Button } from '@/components/ui/button';
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
import { destroy, index, show, update } from '@/routes/invoices';
import { store as duplicate } from '@/routes/invoices/duplicate';
import { store as send } from '@/routes/invoices/send';
import { store as voidInvoice } from '@/routes/invoices/void';
import type { Invoice, Organization } from '@/types';

type Props = {
    currentOrganization: Organization;
    invoice: Invoice;
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: {
        currentOrganization: Organization;
        invoice: Invoice;
    }) => ({
        breadcrumbs: [
            {
                title: 'Invoices',
                href: index(props.currentOrganization.slug),
            },
            {
                title: props.invoice.number,
                href: show([props.currentOrganization.slug, props.invoice.id]),
            },
        ],
    }),
});

const none = 'none';
const deleteDialogOpen = ref(false);
const sendDialogOpen = ref(false);
const voidDialogOpen = ref(false);
const linkCopied = ref(false);

const recipient = computed(() => {
    const contact = props.invoice.options.contacts.find(
        (option) => option.id === props.invoice.contactId,
    );

    return contact?.name
        ? `${contact.name} <${contact.email}>`
        : (contact?.email ?? '');
});

const ccList = computed(() => props.invoice.ccEmails.join(', '));

const copyPublicLink = async () => {
    await navigator.clipboard.writeText(props.invoice.publicUrl ?? '');
    linkCopied.value = true;
    setTimeout(() => (linkCopied.value = false), 2000);
};

const select = (value: number | string | null) =>
    value === null ? none : String(value);

const form = useForm({
    contact_id: select(props.invoice.contactId),
    cc_emails: props.invoice.ccEmails.join(', '),
    issue_date: props.invoice.issueDate,
    due_date: props.invoice.dueDate,
    service_period_start: props.invoice.servicePeriodStart ?? '',
    service_period_end: props.invoice.servicePeriodEnd ?? '',
    project_id: select(props.invoice.projectId),
    sprint_id: select(props.invoice.sprintId),
    notes: props.invoice.notes ?? '',
    terms: props.invoice.terms ?? '',
    tax_rate: props.invoice.taxRate ?? '',
    discount_type: select(props.invoice.discountType),
    discount_value: props.invoice.discountValue ?? '',
    deposit_type: select(props.invoice.depositType),
    deposit_value: props.invoice.depositValue ?? '',
});

const orNull = (value: string) => (value === none ? null : Number(value));

const submit = () => {
    form.transform((data) => ({
        contact_id: orNull(data.contact_id),
        cc_emails: data.cc_emails
            .split(',')
            .map((email) => email.trim())
            .filter((email) => email !== ''),
        issue_date: data.issue_date,
        due_date: data.due_date,
        service_period_start: data.service_period_start || null,
        service_period_end: data.service_period_end || null,
        project_id: orNull(data.project_id),
        sprint_id: orNull(data.sprint_id),
        notes: data.notes,
        terms: data.terms,
        ...(props.invoice.isEditable
            ? {
                  tax_rate: data.tax_rate || null,
                  discount_type:
                      data.discount_type === none ? null : data.discount_type,
                  discount_value: data.discount_value || null,
                  deposit_type:
                      data.deposit_type === none ? null : data.deposit_type,
                  deposit_value: data.deposit_value || null,
              }
            : {}),
    })).patch(update.url([props.currentOrganization.slug, props.invoice.id]), {
        preserveScroll: true,
    });
};

const money = (value: string) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(Number(value));
</script>

<template>
    <Head :title="invoice.number" />

    <div class="flex flex-col gap-10 p-6">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <Heading variant="small" :title="invoice.number" />
                <InvoiceStatusBadge
                    :status="invoice.status"
                    :label="invoice.statusLabel"
                />
            </div>

            <div class="flex items-center gap-2" data-test="invoice-actions">
                <Button
                    v-if="invoice.sentAt"
                    variant="outline"
                    data-test="invoice-copy-link-button"
                    @click="copyPublicLink"
                >
                    <Link2 /> {{ linkCopied ? 'Copied' : 'Copy public link' }}
                </Button>
                <Button
                    v-if="invoice.canSend"
                    data-test="invoice-send-button"
                    @click="sendDialogOpen = true"
                >
                    <Send /> Send
                </Button>
                <Button
                    v-if="invoice.canVoid"
                    variant="outline"
                    data-test="invoice-void-button"
                    @click="voidDialogOpen = true"
                >
                    <Ban /> Void
                </Button>
                <Button
                    v-if="invoice.canDuplicate"
                    variant="outline"
                    data-test="invoice-duplicate-button"
                    @click="
                        router.post(
                            duplicate.url([currentOrganization.slug, invoice.id]),
                        )
                    "
                >
                    <Copy /> Duplicate
                </Button>
                <Button
                    v-if="invoice.canDelete"
                    variant="outline"
                    data-test="invoice-delete-button"
                    @click="deleteDialogOpen = true"
                >
                    <Trash2 /> Delete
                </Button>
            </div>
        </div>

        <ConfirmActionModal
            v-model:open="sendDialogOpen"
            title="Send this invoice?"
            confirm-label="Send invoice"
            :href="send([currentOrganization.slug, invoice.id])"
        >
            This emails the invoice to {{ recipient }}
            <template v-if="ccList"> and copies {{ ccList }}</template
            >.
        </ConfirmActionModal>

        <ConfirmActionModal
            v-model:open="voidDialogOpen"
            title="Void this invoice?"
            confirm-label="Void invoice"
            destructive
            :href="voidInvoice([currentOrganization.slug, invoice.id])"
        >
            A voided invoice can no longer be edited or paid.
        </ConfirmActionModal>

        <ConfirmDeleteModal
            v-model:open="deleteDialogOpen"
            title="Delete this invoice?"
            confirm-label="Delete invoice"
            :href="destroy([currentOrganization.slug, invoice.id])"
        />

        <form
            v-if="invoice.canUpdate"
            class="grid max-w-3xl gap-6"
            data-test="invoice-header-form"
            @submit.prevent="submit"
        >
            <p class="text-sm">
                <span class="text-muted-foreground">Client</span>
                {{ invoice.clientName }}
            </p>

            <div class="grid gap-6 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="invoice-contact">Contact</Label>
                    <Select v-model="form.contact_id">
                        <SelectTrigger id="invoice-contact" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="none">None</SelectItem>
                            <SelectItem
                                v-for="contact in invoice.options.contacts"
                                :key="contact.id"
                                :value="String(contact.id)"
                            >
                                {{ contact.name ?? contact.email }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.contact_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="invoice-cc">CC</Label>
                    <Input
                        id="invoice-cc"
                        v-model="form.cc_emails"
                        data-test="invoice-cc-input"
                    />
                    <InputError :message="form.errors.cc_emails" />
                </div>

                <div class="grid gap-2">
                    <Label for="invoice-issue-date">Issue date</Label>
                    <Input
                        id="invoice-issue-date"
                        v-model="form.issue_date"
                        type="date"
                    />
                    <InputError :message="form.errors.issue_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="invoice-due-date">Due date</Label>
                    <Input
                        id="invoice-due-date"
                        v-model="form.due_date"
                        type="date"
                    />
                    <InputError :message="form.errors.due_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="invoice-period-start"
                        >Service period start</Label
                    >
                    <Input
                        id="invoice-period-start"
                        v-model="form.service_period_start"
                        type="date"
                    />
                    <InputError :message="form.errors.service_period_start" />
                </div>

                <div class="grid gap-2">
                    <Label for="invoice-period-end">Service period end</Label>
                    <Input
                        id="invoice-period-end"
                        v-model="form.service_period_end"
                        type="date"
                    />
                    <InputError :message="form.errors.service_period_end" />
                </div>

                <div class="grid gap-2">
                    <Label for="invoice-project">Project</Label>
                    <Select v-model="form.project_id">
                        <SelectTrigger id="invoice-project" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="none">None</SelectItem>
                            <SelectItem
                                v-for="project in invoice.options.projects"
                                :key="project.id"
                                :value="String(project.id)"
                            >
                                {{ project.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.project_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="invoice-sprint">Sprint</Label>
                    <Select v-model="form.sprint_id">
                        <SelectTrigger id="invoice-sprint" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="none">None</SelectItem>
                            <SelectItem
                                v-for="sprint in invoice.options.sprints"
                                :key="sprint.id"
                                :value="String(sprint.id)"
                            >
                                {{ sprint.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.sprint_id" />
                </div>
            </div>

            <div class="grid gap-6 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="invoice-tax-rate">Tax rate (%)</Label>
                    <Input
                        id="invoice-tax-rate"
                        v-model="form.tax_rate"
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        :disabled="!invoice.isEditable"
                        data-test="invoice-tax-rate-input"
                    />
                    <InputError :message="form.errors.tax_rate" />
                </div>

                <div class="grid gap-2">
                    <Label for="invoice-discount-type">Discount</Label>
                    <Select
                        v-model="form.discount_type"
                        :disabled="!invoice.isEditable"
                    >
                        <SelectTrigger
                            id="invoice-discount-type"
                            class="w-full"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="none">None</SelectItem>
                            <SelectItem value="percentage"
                                >Percentage</SelectItem
                            >
                            <SelectItem value="fixed">Fixed</SelectItem>
                        </SelectContent>
                    </Select>
                    <Input
                        v-model="form.discount_value"
                        type="number"
                        min="0"
                        step="0.01"
                        aria-label="Discount value"
                        :disabled="!invoice.isEditable"
                    />
                    <InputError
                        :message="
                            form.errors.discount_type ||
                            form.errors.discount_value
                        "
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="invoice-deposit-type">Deposit</Label>
                    <Select
                        v-model="form.deposit_type"
                        :disabled="!invoice.isEditable"
                    >
                        <SelectTrigger id="invoice-deposit-type" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="none">None</SelectItem>
                            <SelectItem value="percentage"
                                >Percentage</SelectItem
                            >
                            <SelectItem value="fixed">Fixed</SelectItem>
                        </SelectContent>
                    </Select>
                    <Input
                        v-model="form.deposit_value"
                        type="number"
                        min="0"
                        step="0.01"
                        aria-label="Deposit value"
                        :disabled="!invoice.isEditable"
                    />
                    <InputError
                        :message="
                            form.errors.deposit_type ||
                            form.errors.deposit_value
                        "
                    />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="invoice-notes">Notes</Label>
                <Textarea id="invoice-notes" v-model="form.notes" rows="3" />
                <InputError :message="form.errors.notes" />
            </div>

            <div class="grid gap-2">
                <Label for="invoice-terms">Terms</Label>
                <Textarea id="invoice-terms" v-model="form.terms" rows="3" />
                <InputError :message="form.errors.terms" />
            </div>

            <div>
                <Button
                    type="submit"
                    data-test="invoice-save-button"
                    :disabled="form.processing"
                >
                    Save
                </Button>
            </div>
        </form>

        <InvoiceLines
            :organization="currentOrganization.slug"
            :invoice="invoice"
        />

        <section class="max-w-sm space-y-1 text-sm" data-test="invoice-totals">
            <div class="flex justify-between">
                <span>Subtotal</span>
                <span class="tabular-nums">{{ money(invoice.subtotal) }}</span>
            </div>
            <div class="flex justify-between">
                <span>Tax</span>
                <span class="tabular-nums">{{ money(invoice.taxAmount) }}</span>
            </div>
            <div class="flex justify-between">
                <span>Discount</span>
                <span class="tabular-nums">
                    {{ money(invoice.discountAmount) }}
                </span>
            </div>
            <div class="flex justify-between font-medium">
                <span>Total</span>
                <span class="tabular-nums">{{ money(invoice.total) }}</span>
            </div>
            <div class="flex justify-between">
                <span>Paid</span>
                <span class="tabular-nums">{{
                    money(invoice.amountPaid)
                }}</span>
            </div>
            <div class="flex justify-between font-medium">
                <span>Amount due</span>
                <span class="tabular-nums">{{ money(invoice.amountDue) }}</span>
            </div>
        </section>

        <InvoicePayments
            :organization="currentOrganization.slug"
            :invoice="invoice"
        />
    </div>
</template>
