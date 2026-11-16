<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ExternalLink, GripVertical, Plus, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import draggable from 'vuedraggable';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import InvoiceIssuePicker from '@/components/InvoiceIssuePicker.vue';
import type { IssueSuggestion } from '@/components/InvoiceIssuePicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { lookup } from '@/routes/issues';
import { destroy, store, update } from '@/routes/invoices/lines';
import { update as updateOrder } from '@/routes/invoices/lines/order';
import type { Invoice, InvoiceLine } from '@/types';

type Props = {
    organization: string;
    invoice: Invoice;
};

type Row = InvoiceLine & {
    draftDescription: string;
    draftQuantity: string;
    draftRate: string;
    draftReferenceUrl: string;
};

const props = defineProps<Props>();

const none = 'none';
const editable = () => props.invoice.isEditable;
const canEditDetails = () => props.invoice.canUpdate;
const columns =
    'grid grid-cols-[1.25rem_minmax(0,1fr)_6rem_6.5rem_7rem_7rem_2.25rem] items-start gap-x-3';

const workTypeName = (id: number | null) =>
    props.invoice.options.workTypes.find((workType) => workType.id === id)
        ?.name ?? null;
const lookupUrl = lookup(props.organization, {
    query: { invoice: props.invoice.id },
}).url;

const toRows = (): Row[] =>
    props.invoice.lines.map((line) => ({
        ...line,
        draftDescription: line.description,
        draftQuantity: line.quantity,
        draftRate: line.rate,
        draftReferenceUrl: line.referenceUrl ?? '',
    }));

const rows = ref<Row[]>(toRows());
const errors = ref<Record<number, Record<string, string>>>({});

watch(
    () => props.invoice.lines,
    () => (rows.value = toRows()),
);

const money = (value: string) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(Number(value));

const patch = (row: Row, data: Record<string, string | number | null>) => {
    router.visit(update([props.organization, props.invoice.id, row.id]), {
        data,
        preserveScroll: true,
        onError: (bag) => (errors.value = { ...errors.value, [row.id]: bag }),
        onSuccess: () => delete errors.value[row.id],
    });
};

const saveDescription = (row: Row) => {
    if (row.draftDescription.trim() !== row.description) {
        patch(row, { description: row.draftDescription.trim() });
    }
};

const saveReference = (row: Row) => {
    if (row.draftReferenceUrl !== (row.referenceUrl ?? '')) {
        patch(row, { reference_url: row.draftReferenceUrl || null });
    }
};

const saveQuantity = (row: Row) => {
    if (row.draftQuantity !== row.quantity && row.draftQuantity !== '') {
        patch(row, { quantity: row.draftQuantity });
    }
};

const saveRate = (row: Row) => {
    if (row.draftRate !== row.rate && row.draftRate !== '') {
        patch(row, { rate: row.draftRate });
    }
};

const pickIssue = (row: Row, issue: IssueSuggestion | null) => {
    patch(row, {
        issue_id: issue?.id ?? null,
        ...(issue && row.draftDescription.trim() === ''
            ? { description: issue.title }
            : {}),
    });
};

const onReorder = () => {
    router.visit(updateOrder([props.organization, props.invoice.id]), {
        method: 'put',
        data: { line_ids: rows.value.map((row) => row.id) },
        preserveScroll: true,
        preserveState: true,
    });
};

const remove = (row: Row) => {
    router.delete(destroy([props.organization, props.invoice.id, row.id]), {
        preserveScroll: true,
    });
};

const form = useForm({
    description: '',
    issue_id: null as number | null,
    issueReference: null as string | null,
    quantity: '',
    unit: 'hours',
    rate: '',
});

const pickNewIssue = (issue: IssueSuggestion | null) => {
    form.issue_id = issue?.id ?? null;
    form.issueReference = issue?.reference ?? null;

    if (issue && form.description.trim() === '') {
        form.description = issue.title;
    }
};

const add = () => {
    form.transform((data) => ({
        description: data.description || null,
        issue_id: data.issue_id,
        quantity: data.quantity,
        unit: data.unit,
        rate: data.rate || null,
    })).post(store.url([props.organization, props.invoice.id]), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <section class="space-y-3" data-test="invoice-lines">
        <Heading variant="small" title="Lines" />

        <div class="overflow-x-auto rounded-lg border">
            <div class="min-w-[44rem]">
                <div
                    :class="columns"
                    class="text-muted-foreground border-b px-3 py-2 text-xs font-medium"
                >
                    <span />
                    <span>Description</span>
                    <span class="text-right">Qty</span>
                    <span>Unit</span>
                    <span class="text-right">Rate</span>
                    <span class="text-right">Amount</span>
                    <span />
                </div>

                <draggable
                    v-if="rows.length > 0"
                    :list="rows"
                    item-key="id"
                    handle="[data-handle]"
                    :disabled="!editable()"
                    :animation="150"
                    class="divide-y"
                    @change="onReorder"
                >
                    <template #item="{ element: row }">
                        <div
                            :class="columns"
                            class="px-3 py-3"
                            data-test="invoice-line-row"
                        >
                            <button
                                v-if="editable()"
                                type="button"
                                data-handle
                                class="text-muted-foreground hover:text-foreground mt-2.5 cursor-grab"
                                aria-label="Drag to reorder"
                            >
                                <GripVertical class="size-4" />
                            </button>
                            <span v-else />

                            <div class="grid gap-2">
                                <template v-if="canEditDetails()">
                                    <Input
                                        v-model="row.draftDescription"
                                        aria-label="Description"
                                        data-test="invoice-line-description"
                                        @blur="saveDescription(row)"
                                    />
                                    <InputError
                                        :message="errors[row.id]?.description"
                                    />
                                </template>
                                <template v-else>
                                    <p class="pt-2 text-sm">
                                        {{ row.description }}
                                    </p>
                                    <p
                                        v-if="
                                            row.issueReference ||
                                            row.workTypeId ||
                                            row.referenceUrl
                                        "
                                        class="text-muted-foreground flex flex-wrap items-center gap-x-2 text-xs"
                                    >
                                        <span v-if="row.issueReference">
                                            {{ row.issueReference }}
                                        </span>
                                        <span
                                            v-if="workTypeName(row.workTypeId)"
                                        >
                                            {{ workTypeName(row.workTypeId) }}
                                        </span>
                                        <a
                                            v-if="row.referenceUrl"
                                            :href="row.referenceUrl"
                                            target="_blank"
                                            rel="noopener"
                                            class="hover:text-foreground inline-flex items-center gap-1 underline-offset-2 hover:underline"
                                        >
                                            Reference
                                            <ExternalLink class="size-3" />
                                        </a>
                                    </p>
                                </template>
                            </div>

                            <template v-if="editable()">
                                <div class="grid gap-1">
                                    <Input
                                        v-model="row.draftQuantity"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        aria-label="Quantity"
                                        class="text-right"
                                        data-test="invoice-line-quantity"
                                        @blur="saveQuantity(row)"
                                    />
                                    <InputError
                                        :message="errors[row.id]?.quantity"
                                    />
                                </div>
                                <Select
                                    :model-value="row.unit"
                                    @update:model-value="
                                        patch(row, { unit: String($event) })
                                    "
                                >
                                    <SelectTrigger
                                        class="w-full"
                                        aria-label="Unit"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="hours">
                                            Hours
                                        </SelectItem>
                                        <SelectItem value="units">
                                            Units
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <div class="grid gap-1">
                                    <Input
                                        v-model="row.draftRate"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        aria-label="Rate"
                                        class="text-right"
                                        data-test="invoice-line-rate"
                                        @blur="saveRate(row)"
                                    />
                                    <InputError
                                        :message="errors[row.id]?.rate"
                                    />
                                </div>
                            </template>
                            <template v-else>
                                <span
                                    class="pt-2 text-right text-sm tabular-nums"
                                >
                                    {{ row.quantity }}
                                </span>
                                <span class="pt-2 text-sm">
                                    {{
                                        row.unit === 'hours' ? 'Hours' : 'Units'
                                    }}
                                </span>
                                <span
                                    class="pt-2 text-right text-sm tabular-nums"
                                >
                                    {{ money(row.rate) }}
                                </span>
                            </template>

                            <span
                                class="pt-2 text-right text-sm font-medium tabular-nums"
                                data-test="invoice-line-amount"
                            >
                                {{ money(row.amount) }}
                            </span>

                            <Button
                                v-if="editable()"
                                variant="ghost"
                                size="icon"
                                aria-label="Remove line"
                                data-test="invoice-line-delete"
                                @click="remove(row)"
                            >
                                <Trash2 />
                            </Button>

                            <div
                                v-if="canEditDetails()"
                                class="col-span-5 col-start-2 mt-2 grid gap-1"
                            >
                                <div
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <InvoiceIssuePicker
                                        v-if="
                                            editable() || row.issueReference
                                        "
                                        :lookup="lookupUrl"
                                        :reference="row.issueReference"
                                        :disabled="!editable()"
                                        @select="pickIssue(row, $event)"
                                    />
                                    <Input
                                        v-model="row.draftReferenceUrl"
                                        type="url"
                                        placeholder="Reference link"
                                        aria-label="Reference link"
                                        class="min-w-48 flex-1"
                                        data-test="invoice-line-reference"
                                        @blur="saveReference(row)"
                                    />
                                    <Select
                                        :model-value="
                                            row.workTypeId === null
                                                ? none
                                                : String(row.workTypeId)
                                        "
                                        @update:model-value="
                                            patch(row, {
                                                work_type_id:
                                                    $event === none
                                                        ? null
                                                        : Number($event),
                                            })
                                        "
                                    >
                                        <SelectTrigger
                                            class="w-40"
                                            aria-label="Work type"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="none">
                                                No work type
                                            </SelectItem>
                                            <SelectItem
                                                v-for="workType in invoice
                                                    .options.workTypes"
                                                :key="workType.id"
                                                :value="String(workType.id)"
                                            >
                                                {{ workType.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <InputError
                                    :message="errors[row.id]?.reference_url"
                                />
                            </div>
                        </div>
                    </template>
                </draggable>
                <p
                    v-else
                    class="text-muted-foreground px-3 py-6 text-center text-sm"
                >
                    No lines yet.
                </p>

                <form
                    v-if="editable()"
                    :class="columns"
                    class="bg-muted/40 border-t px-3 py-3"
                    data-test="invoice-line-form"
                    @submit.prevent="add"
                >
                    <Plus class="text-muted-foreground mt-2.5 size-4" />
                    <div class="grid gap-2">
                        <Input
                            v-model="form.description"
                            placeholder="Description"
                            aria-label="New line description"
                            data-test="new-line-description"
                        />
                        <InvoiceIssuePicker
                            :lookup="lookupUrl"
                            :reference="form.issueReference"
                            @select="pickNewIssue"
                        />
                        <InputError
                            :message="
                                form.errors.description || form.errors.issue_id
                            "
                        />
                    </div>
                    <div class="grid gap-1">
                        <Input
                            v-model="form.quantity"
                            type="number"
                            min="0"
                            step="0.01"
                            aria-label="New line quantity"
                            class="text-right"
                            data-test="new-line-quantity"
                        />
                        <InputError :message="form.errors.quantity" />
                    </div>
                    <Select v-model="form.unit">
                        <SelectTrigger
                            class="w-full"
                            aria-label="New line unit"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="hours">Hours</SelectItem>
                            <SelectItem value="units">Units</SelectItem>
                        </SelectContent>
                    </Select>
                    <div class="grid gap-1">
                        <Input
                            v-model="form.rate"
                            type="number"
                            min="0"
                            step="0.01"
                            aria-label="New line rate"
                            class="text-right"
                            data-test="new-line-rate"
                        />
                        <InputError :message="form.errors.rate" />
                    </div>
                    <Button
                        type="submit"
                        class="col-span-2"
                        data-test="new-line-add"
                        :disabled="form.processing"
                    >
                        Add line
                    </Button>
                </form>
            </div>
        </div>
    </section>
</template>
