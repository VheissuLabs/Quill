<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Copy, Link2, Send, Trash2, Ban } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import { show as showClient } from '@/routes/clients';
import { destroy, index, show, update } from '@/routes/invoices';
import { store as duplicate } from '@/routes/invoices/duplicate';
import { store as send } from '@/routes/invoices/send';
import { store as voidInvoice } from '@/routes/invoices/void';
import { edit as editProject } from '@/routes/projects';
import { show as showSprint } from '@/routes/sprints';
import { formatDate } from '@/lib/dates';
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
const duplicating = ref(false);

const duplicateInvoice = () => {
    if (duplicating.value) {
        return;
    }

    duplicating.value = true;

    router.post(
        duplicate.url([props.currentOrganization.slug, props.invoice.id]),
        {},
        {
            onFinish: () => (duplicating.value = false),
        },
    );
};

const recipient = computed(() => {
    const contact = props.invoice.options.contacts.find(
        (option) => option.id === props.invoice.contactId,
    );

    return contact?.name
        ? `${contact.name} <${contact.email}>`
        : (contact?.email ?? '');
});

const contact = computed(() =>
    props.invoice.options.contacts.find(
        (option) => option.id === props.invoice.contactId,
    ),
);

const projectFor = (id: number | string | null) =>
    props.invoice.options.projects.find(
        (project) => String(project.id) === String(id),
    ) ?? null;

const sprintFor = (id: number | string | null) =>
    props.invoice.options.sprints.find(
        (sprint) => String(sprint.id) === String(id),
    ) ?? null;

const clientHref = computed(() =>
    showClient([props.currentOrganization.slug, props.invoice.clientSlug]),
);

const sendsOn = computed(() =>
    props.invoice.closesAt
        ? new Date(props.invoice.closesAt).toLocaleString(undefined, {
              month: 'short',
              day: 'numeric',
          })
        : '',
);

const sendsAt = computed(() =>
    props.invoice.closesAt
        ? new Date(props.invoice.closesAt).toLocaleTimeString(undefined, {
              hour: 'numeric',
              minute: '2-digit',
          })
        : '',
);

const servicePeriod = computed(() => {
    const { servicePeriodStart: start, servicePeriodEnd: end } = props.invoice;

    if (start && end) {
        return `${formatDate(start)} – ${formatDate(end)}`;
    }

    return start ? formatDate(start) : end ? formatDate(end) : null;
});

const ccList = computed(() => props.invoice.ccEmails.join(', '));

const copyPublicLink = async () => {
    await navigator.clipboard.writeText(props.invoice.publicUrl ?? '');
    linkCopied.value = true;
    setTimeout(() => (linkCopied.value = false), 2000);
};

const select = (value: number | string | null) =>
    value === null ? none : String(value);

const headerFields = () => ({
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

const form = useForm(headerFields());

watch(
    () => props.invoice.id,
    () => {
        form.defaults(headerFields());
        form.reset();
        form.clearErrors();
    },
);

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
        onSuccess: () => form.defaults(),
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

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-8 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-semibold tracking-tight">
                        {{ invoice.number }}
                    </h1>
                    <InvoiceStatusBadge
                        :status="invoice.status"
                        :label="invoice.statusLabel"
                    />
                </div>
                <p class="text-muted-foreground text-sm">
                    <Link
                        :href="clientHref"
                        class="text-foreground hover:underline"
                        data-test="invoice-client-link"
                    >
                        {{ invoice.clientName }}
                    </Link>
                    · Issued
                    {{ formatDate(invoice.issueDate) }} · Due
                    {{ formatDate(invoice.dueDate) }}
                </p>
                <p
                    v-if="invoice.closesAt && !invoice.sentAt"
                    class="text-muted-foreground text-sm"
                    data-test="invoice-sends-at"
                >
                    Sends automatically {{ sendsOn }} at {{ sendsAt }}
                </p>
            </div>

            <div
                class="flex flex-wrap items-center gap-2"
                data-test="invoice-actions"
            >
                <Button
                    v-if="invoice.sentAt"
                    variant="outline"
                    data-test="invoice-copy-link-button"
                    @click="copyPublicLink"
                >
                    <Link2 /> {{ linkCopied ? 'Copied' : 'Copy public link' }}
                </Button>
                <Button
                    v-if="invoice.canDuplicate"
                    variant="outline"
                    data-test="invoice-duplicate-button"
                    :disabled="duplicating"
                    @click="duplicateInvoice"
                >
                    <Copy /> Duplicate
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
                    v-if="invoice.canDelete"
                    variant="outline"
                    data-test="invoice-delete-button"
                    @click="deleteDialogOpen = true"
                >
                    <Trash2 /> Delete
                </Button>
                <Button
                    v-if="invoice.canSend"
                    data-test="invoice-send-button"
                    @click="sendDialogOpen = true"
                >
                    <Send /> Send
                </Button>
            </div>
        </div>

        <div
            v-if="invoice.voidedAt"
            class="border-destructive/40 bg-destructive/10 text-destructive rounded-lg border px-4 py-3 text-sm"
            data-test="invoice-void-notice"
        >
            Voided {{ formatDate(invoice.voidedAt) }}. This invoice can no
            longer be edited or paid.
        </div>

        <ConfirmActionModal
            v-model:open="sendDialogOpen"
            title="Send this invoice?"
            confirm-label="Send invoice"
            :href="send([currentOrganization.slug, invoice.id])"
        >
            This emails the invoice to
            {{ recipient }}{{ ccList ? ` and copies ${ccList}` : '' }}.
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

        <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="flex min-w-0 flex-col gap-10">
                <InvoiceLines
                    :organization="currentOrganization.slug"
                    :invoice="invoice"
                />

                <section class="space-y-3">
                    <Heading variant="small" title="Details" />

                    <form
                        v-if="invoice.canUpdate"
                        class="grid gap-6 rounded-lg border p-5"
                        data-test="invoice-header-form"
                        @submit.prevent="submit"
                    >
                        <div class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="invoice-contact">Contact</Label>
                                <Select v-model="form.contact_id">
                                    <SelectTrigger
                                        id="invoice-contact"
                                        class="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="none">
                                            None
                                        </SelectItem>
                                        <SelectItem
                                            v-for="option in invoice.options
                                                .contacts"
                                            :key="option.id"
                                            :value="String(option.id)"
                                        >
                                            {{ option.name ?? option.email }}
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
                                <Label for="invoice-issue-date">
                                    Issue date
                                </Label>
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
                                <Label for="invoice-period-start">
                                    Service period start
                                </Label>
                                <Input
                                    id="invoice-period-start"
                                    v-model="form.service_period_start"
                                    type="date"
                                />
                                <InputError
                                    :message="form.errors.service_period_start"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="invoice-period-end">
                                    Service period end
                                </Label>
                                <Input
                                    id="invoice-period-end"
                                    v-model="form.service_period_end"
                                    type="date"
                                />
                                <InputError
                                    :message="form.errors.service_period_end"
                                />
                            </div>

                            <div class="grid gap-2">
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <Label for="invoice-project">Project</Label>
                                    <Link
                                        v-if="projectFor(form.project_id)"
                                        :href="
                                            editProject([
                                                currentOrganization.slug,
                                                projectFor(form.project_id)!
                                                    .slug,
                                            ])
                                        "
                                        class="text-muted-foreground hover:text-foreground text-xs hover:underline"
                                    >
                                        Open project
                                    </Link>
                                </div>
                                <Select v-model="form.project_id">
                                    <SelectTrigger
                                        id="invoice-project"
                                        class="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="none">
                                            None
                                        </SelectItem>
                                        <SelectItem
                                            v-for="project in invoice.options
                                                .projects"
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
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <Label for="invoice-sprint">Sprint</Label>
                                    <Link
                                        v-if="sprintFor(form.sprint_id)"
                                        :href="
                                            showSprint([
                                                currentOrganization.slug,
                                                Number(form.sprint_id),
                                            ])
                                        "
                                        class="text-muted-foreground hover:text-foreground text-xs hover:underline"
                                    >
                                        Open sprint
                                    </Link>
                                </div>
                                <Select v-model="form.sprint_id">
                                    <SelectTrigger
                                        id="invoice-sprint"
                                        class="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="none">
                                            None
                                        </SelectItem>
                                        <SelectItem
                                            v-for="sprint in invoice.options
                                                .sprints"
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

                        <div
                            v-if="invoice.isEditable"
                            class="grid gap-x-6 gap-y-5 border-t pt-5 sm:grid-cols-3"
                        >
                            <div class="grid content-start gap-2">
                                <Label for="invoice-tax-rate">
                                    Tax rate (%)
                                </Label>
                                <Input
                                    id="invoice-tax-rate"
                                    v-model="form.tax_rate"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    data-test="invoice-tax-rate-input"
                                />
                                <InputError :message="form.errors.tax_rate" />
                            </div>

                            <div class="grid content-start gap-2">
                                <Label for="invoice-discount-type">
                                    Discount
                                </Label>
                                <div class="flex gap-2">
                                    <Select v-model="form.discount_type">
                                        <SelectTrigger
                                            id="invoice-discount-type"
                                            class="w-32"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="none">
                                                None
                                            </SelectItem>
                                            <SelectItem value="percentage">
                                                %
                                            </SelectItem>
                                            <SelectItem value="fixed">
                                                Fixed
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        v-model="form.discount_value"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        aria-label="Discount value"
                                        :disabled="form.discount_type === none"
                                    />
                                </div>
                                <InputError
                                    :message="
                                        form.errors.discount_type ||
                                        form.errors.discount_value
                                    "
                                />
                            </div>

                            <div class="grid content-start gap-2">
                                <Label for="invoice-deposit-type">
                                    Deposit
                                </Label>
                                <div class="flex gap-2">
                                    <Select v-model="form.deposit_type">
                                        <SelectTrigger
                                            id="invoice-deposit-type"
                                            class="w-32"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="none">
                                                None
                                            </SelectItem>
                                            <SelectItem value="percentage">
                                                %
                                            </SelectItem>
                                            <SelectItem value="fixed">
                                                Fixed
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        v-model="form.deposit_value"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        aria-label="Deposit value"
                                        :disabled="form.deposit_type === none"
                                    />
                                </div>
                                <InputError
                                    :message="
                                        form.errors.deposit_type ||
                                        form.errors.deposit_value
                                    "
                                />
                            </div>
                        </div>

                        <div
                            class="grid gap-x-6 gap-y-5 border-t pt-5 sm:grid-cols-2"
                        >
                            <div class="grid gap-2">
                                <Label for="invoice-notes">Notes</Label>
                                <Textarea
                                    id="invoice-notes"
                                    v-model="form.notes"
                                    rows="4"
                                />
                                <InputError :message="form.errors.notes" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="invoice-terms">Terms</Label>
                                <Textarea
                                    id="invoice-terms"
                                    v-model="form.terms"
                                    rows="4"
                                />
                                <InputError :message="form.errors.terms" />
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                data-test="invoice-save-button"
                                :disabled="form.processing || !form.isDirty"
                            >
                                Save details
                            </Button>
                        </div>
                    </form>

                    <div
                        v-else
                        class="grid gap-6 rounded-lg border p-5 text-sm"
                        data-test="invoice-details"
                    >
                        <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-muted-foreground">Bill to</dt>
                                <dd class="font-medium">
                                    <Link
                                        :href="clientHref"
                                        class="hover:underline"
                                    >
                                        {{ invoice.clientName }}
                                    </Link>
                                </dd>
                                <dd v-if="contact">
                                    <Link
                                        :href="clientHref"
                                        class="hover:underline"
                                    >
                                        {{ contact.name ?? contact.email }}
                                    </Link>
                                    <span
                                        v-if="contact.name"
                                        class="text-muted-foreground"
                                    >
                                        · {{ contact.email }}
                                    </span>
                                </dd>
                                <dd v-if="ccList" class="text-muted-foreground">
                                    CC {{ ccList }}
                                </dd>
                            </div>
                            <div v-if="servicePeriod">
                                <dt class="text-muted-foreground">
                                    Service period
                                </dt>
                                <dd>{{ servicePeriod }}</dd>
                            </div>
                            <div v-if="projectFor(invoice.projectId)">
                                <dt class="text-muted-foreground">Project</dt>
                                <dd>
                                    <Link
                                        :href="
                                            editProject([
                                                currentOrganization.slug,
                                                projectFor(invoice.projectId)!
                                                    .slug,
                                            ])
                                        "
                                        class="hover:underline"
                                    >
                                        {{
                                            projectFor(invoice.projectId)!.name
                                        }}
                                    </Link>
                                </dd>
                            </div>
                            <div v-if="sprintFor(invoice.sprintId)">
                                <dt class="text-muted-foreground">Sprint</dt>
                                <dd>
                                    <Link
                                        :href="
                                            showSprint([
                                                currentOrganization.slug,
                                                invoice.sprintId!,
                                            ])
                                        "
                                        class="hover:underline"
                                    >
                                        {{ sprintFor(invoice.sprintId)!.name }}
                                    </Link>
                                </dd>
                            </div>
                        </dl>
                        <div
                            v-if="invoice.notes || invoice.terms"
                            class="grid gap-x-6 gap-y-4 border-t pt-5 sm:grid-cols-2"
                        >
                            <div v-if="invoice.notes">
                                <p class="text-muted-foreground">Notes</p>
                                <p class="whitespace-pre-line">
                                    {{ invoice.notes }}
                                </p>
                            </div>
                            <div v-if="invoice.terms">
                                <p class="text-muted-foreground">Terms</p>
                                <p class="whitespace-pre-line">
                                    {{ invoice.terms }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="flex flex-col gap-8 lg:sticky lg:top-6">
                <section
                    class="bg-muted/30 rounded-lg border p-5 text-sm"
                    data-test="invoice-totals"
                >
                    <p class="text-muted-foreground">Amount due</p>
                    <p
                        class="mt-1 text-3xl font-semibold tracking-tight tabular-nums"
                    >
                        {{ money(invoice.amountDue) }}
                    </p>

                    <dl class="mt-5 space-y-2 border-t pt-4">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Subtotal</dt>
                            <dd class="tabular-nums">
                                {{ money(invoice.subtotal) }}
                            </dd>
                        </div>
                        <div
                            v-if="Number(invoice.taxAmount) > 0"
                            class="flex justify-between"
                        >
                            <dt class="text-muted-foreground">
                                Tax ({{ Number(invoice.taxRate) }}%)
                            </dt>
                            <dd class="tabular-nums">
                                {{ money(invoice.taxAmount) }}
                            </dd>
                        </div>
                        <div
                            v-if="Number(invoice.discountAmount) > 0"
                            class="flex justify-between"
                        >
                            <dt class="text-muted-foreground">Discount</dt>
                            <dd class="tabular-nums">
                                −{{ money(invoice.discountAmount) }}
                            </dd>
                        </div>
                        <div class="flex justify-between font-medium">
                            <dt>Total</dt>
                            <dd class="tabular-nums">
                                {{ money(invoice.total) }}
                            </dd>
                        </div>
                        <div
                            v-if="Number(invoice.depositAmount) > 0"
                            class="flex justify-between"
                        >
                            <dt class="text-muted-foreground">Deposit</dt>
                            <dd class="tabular-nums">
                                {{ money(invoice.depositAmount) }}
                            </dd>
                        </div>
                        <div
                            v-if="Number(invoice.amountPaid) > 0"
                            class="flex justify-between"
                        >
                            <dt class="text-muted-foreground">Paid</dt>
                            <dd class="tabular-nums">
                                −{{ money(invoice.amountPaid) }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <InvoicePayments
                    :organization="currentOrganization.slug"
                    :invoice="invoice"
                />
            </aside>
        </div>
    </div>
</template>
