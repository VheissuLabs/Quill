<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { GripVertical, Trash2 } from '@lucide/vue';
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

        <draggable
            v-if="rows.length > 0"
            :list="rows"
            item-key="id"
            handle="[data-handle]"
            :disabled="!invoice.isEditable"
            :animation="150"
            class="divide-y rounded-lg border"
            @change="onReorder"
        >
            <template #item="{ element: row }">
                <div
                    class="flex flex-wrap items-start gap-2 p-3"
                    data-test="invoice-line-row"
                >
                    <button
                        v-if="editable()"
                        type="button"
                        data-handle
                        class="text-muted-foreground hover:text-foreground mt-2 cursor-grab"
                        aria-label="Drag to reorder"
                    >
                        <GripVertical class="size-4" />
                    </button>

                    <div class="grid min-w-48 flex-1 gap-1">
                        <Input
                            v-model="row.draftDescription"
                            aria-label="Description"
                            data-test="invoice-line-description"
                            @blur="saveDescription(row)"
                        />
                        <InputError :message="errors[row.id]?.description" />
                    </div>

                    <InvoiceIssuePicker
                        :lookup="lookupUrl"
                        :reference="row.issueReference"
                        :disabled="!editable()"
                        @select="pickIssue(row, $event)"
                    />

                    <div class="grid w-44 gap-1">
                        <Input
                            v-model="row.draftReferenceUrl"
                            type="url"
                            aria-label="Reference link"
                            data-test="invoice-line-reference"
                            @blur="saveReference(row)"
                        />
                        <InputError :message="errors[row.id]?.reference_url" />
                    </div>

                    <Select
                        :model-value="
                            row.workTypeId === null
                                ? none
                                : String(row.workTypeId)
                        "
                        @update:model-value="
                            patch(row, {
                                work_type_id:
                                    $event === none ? null : Number($event),
                            })
                        "
                    >
                        <SelectTrigger class="w-36" aria-label="Work type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="none">None</SelectItem>
                            <SelectItem
                                v-for="workType in invoice.options.workTypes"
                                :key="workType.id"
                                :value="String(workType.id)"
                            >
                                {{ workType.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <div class="grid w-24 gap-1">
                        <Input
                            v-model="row.draftQuantity"
                            type="number"
                            min="0"
                            step="0.01"
                            aria-label="Quantity"
                            :disabled="!editable()"
                            data-test="invoice-line-quantity"
                            @blur="saveQuantity(row)"
                        />
                        <InputError :message="errors[row.id]?.quantity" />
                    </div>

                    <Select
                        :model-value="row.unit"
                        :disabled="!editable()"
                        @update:model-value="patch(row, { unit: String($event) })"
                    >
                        <SelectTrigger class="w-28" aria-label="Unit">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="hours">Hours</SelectItem>
                            <SelectItem value="units">Units</SelectItem>
                        </SelectContent>
                    </Select>

                    <div class="grid w-28 gap-1">
                        <Input
                            v-model="row.draftRate"
                            type="number"
                            min="0"
                            step="0.01"
                            aria-label="Rate"
                            :disabled="!editable()"
                            data-test="invoice-line-rate"
                            @blur="saveRate(row)"
                        />
                        <InputError :message="errors[row.id]?.rate" />
                    </div>

                    <span
                        class="w-28 pt-2 text-right text-sm tabular-nums"
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
                </div>
            </template>
        </draggable>
        <p v-else class="text-muted-foreground text-sm">No lines yet.</p>

        <form
            v-if="editable()"
            class="flex flex-wrap items-start gap-2"
            data-test="invoice-line-form"
            @submit.prevent="add"
        >
            <div class="grid min-w-48 flex-1 gap-1">
                <Input
                    v-model="form.description"
                    aria-label="New line description"
                    data-test="new-line-description"
                />
                <InputError
                    :message="form.errors.description || form.errors.issue_id"
                />
            </div>
            <InvoiceIssuePicker
                :lookup="lookupUrl"
                :reference="form.issueReference"
                @select="pickNewIssue"
            />
            <div class="grid w-24 gap-1">
                <Input
                    v-model="form.quantity"
                    type="number"
                    min="0"
                    step="0.01"
                    aria-label="New line quantity"
                    data-test="new-line-quantity"
                />
                <InputError :message="form.errors.quantity" />
            </div>
            <Select v-model="form.unit">
                <SelectTrigger class="w-28" aria-label="New line unit">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="hours">Hours</SelectItem>
                    <SelectItem value="units">Units</SelectItem>
                </SelectContent>
            </Select>
            <div class="grid w-28 gap-1">
                <Input
                    v-model="form.rate"
                    type="number"
                    min="0"
                    step="0.01"
                    aria-label="New line rate"
                    data-test="new-line-rate"
                />
                <InputError :message="form.errors.rate" />
            </div>
            <Button
                type="submit"
                data-test="new-line-add"
                :disabled="form.processing"
            >
                Add line
            </Button>
        </form>
    </section>
</template>
