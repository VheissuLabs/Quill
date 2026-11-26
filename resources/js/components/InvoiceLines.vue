<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { ExternalLink, GripVertical, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import draggable from 'vuedraggable';
import InputError from '@/components/InputError.vue';
import InvoiceIssuePicker from '@/components/InvoiceIssuePicker.vue';
import type { IssueSuggestion } from '@/components/InvoiceIssuePicker.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { groupLinesByDay, hasDatedLines } from '@/lib/invoiceDays';
import { lookup, show as showIssue } from '@/routes/issues';
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
    draftCompletedAt: string;
};

const props = defineProps<Props>();

const none = 'none';
const editable = () => props.invoice.isEditable;
const canEditDetails = () => props.invoice.canUpdate;

const issueHref = (issueId: number | null) =>
    issueId === null ? null : showIssue([props.organization, issueId]).url;

const workTypeName = (id: number | null) =>
    props.invoice.options.workTypes.find((workType) => workType.id === id)
        ?.name ?? null;
const lookupUrl = lookup(props.organization, {
    query: { invoice: props.invoice.id },
}).url;

const toRows = (previous: Row[] = []): Row[] =>
    props.invoice.lines.map((line) => {
        const before = previous.find((row) => row.id === line.id);

        return {
            ...line,
            draftDescription:
                before && before.description === line.description
                    ? before.draftDescription
                    : line.description,
            draftQuantity:
                before && before.quantity === line.quantity
                    ? before.draftQuantity
                    : line.quantity,
            draftRate:
                before && before.rate === line.rate
                    ? before.draftRate
                    : line.rate,
            draftReferenceUrl:
                before && before.referenceUrl === line.referenceUrl
                    ? before.draftReferenceUrl
                    : (line.referenceUrl ?? ''),
            draftCompletedAt:
                before && before.completedAt === line.completedAt
                    ? before.draftCompletedAt
                    : (line.completedAt ?? ''),
        };
    });

const rows = ref<Row[]>(toRows());
const days = ref(groupLinesByDay(rows.value));
const showDays = computed(() => hasDatedLines(rows.value));
const errors = ref<Record<number, Record<string, string>>>({});

watch(
    () => props.invoice.lines,
    () => {
        rows.value = toRows(rows.value);
        days.value = groupLinesByDay(rows.value);
    },
);

const money = (value: string | number) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(Number(value));

const patch = (row: Row, data: Record<string, string | number | null>) => {
    router.visit(update([props.organization, props.invoice.id, row.id]), {
        data,
        preserveScroll: true,
        preserveState: true,
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

const saveCompletedAt = (row: Row) => {
    if (row.draftCompletedAt !== (row.completedAt ?? '')) {
        patch(row, { completed_at: row.draftCompletedAt || null });
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
        data: {
            line_ids: days.value.flatMap((day) =>
                day.lines.map((row) => row.id),
            ),
        },
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
    completed_at: '',
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
        completed_at: data.completed_at || null,
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
    <Card class="gap-0 overflow-hidden py-0" data-test="invoice-lines">
        <CardHeader class="border-b py-4 [.border-b]:pb-4">
            <CardTitle>Lines</CardTitle>
        </CardHeader>

        <Table>
            <TableHeader>
                <TableRow class="hover:bg-transparent">
                    <TableHead class="w-8" />
                    <TableHead>Description</TableHead>
                    <TableHead class="w-24 text-right">Qty</TableHead>
                    <TableHead class="w-28">Unit</TableHead>
                    <TableHead class="w-28 text-right">Rate</TableHead>
                    <TableHead class="w-28 text-right">Amount</TableHead>
                    <TableHead class="w-12" />
                </TableRow>
            </TableHeader>

            <template v-if="rows.length > 0">
                <draggable
                    v-for="day in days"
                    :key="day.day ?? 'undated'"
                    :list="day.lines"
                    tag="tbody"
                    item-key="id"
                    handle="[data-handle]"
                    :disabled="!editable()"
                    :animation="150"
                    data-test="invoice-line-day"
                    @change="onReorder"
                >
                    <template #header>
                        <TableRow
                            v-if="showDays"
                            class="bg-muted/40 hover:bg-muted/40"
                            data-test="invoice-line-day-header"
                        >
                            <TableCell />
                            <TableCell class="font-medium">
                                {{ day.label ?? 'No date' }}
                            </TableCell>
                            <TableCell
                                class="text-muted-foreground text-right tabular-nums"
                            >
                                <template v-if="day.hours > 0">
                                    {{ day.hours.toFixed(2) }}
                                </template>
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                <template v-if="day.hours > 0">Hours</template>
                            </TableCell>
                            <TableCell />
                            <TableCell
                                class="text-right font-medium tabular-nums"
                            >
                                {{ money(day.amount) }}
                            </TableCell>
                            <TableCell />
                        </TableRow>
                    </template>
                    <template #item="{ element: row }">
                        <TableRow data-test="invoice-line-row">
                            <TableCell class="align-top">
                                <button
                                    v-if="editable()"
                                    type="button"
                                    data-handle
                                    class="text-muted-foreground hover:text-foreground mt-2.5 cursor-grab"
                                    aria-label="Drag to reorder"
                                >
                                    <GripVertical class="size-4" />
                                </button>
                            </TableCell>

                            <TableCell class="align-top whitespace-normal">
                                <div class="grid gap-2">
                                    <template v-if="canEditDetails()">
                                        <Input
                                            v-model="row.draftDescription"
                                            aria-label="Description"
                                            data-test="invoice-line-description"
                                            @blur="saveDescription(row)"
                                        />
                                        <InputError
                                            :message="
                                                errors[row.id]?.description
                                            "
                                        />
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <InvoiceIssuePicker
                                                v-if="
                                                    editable() ||
                                                    row.issueReference
                                                "
                                                :lookup="lookupUrl"
                                                :reference="row.issueReference"
                                                :href="issueHref(row.issueId)"
                                                :disabled="!editable()"
                                                @select="pickIssue(row, $event)"
                                            />
                                            <Input
                                                v-model="row.draftReferenceUrl"
                                                type="url"
                                                placeholder="Reference link"
                                                aria-label="Reference link"
                                                class="h-8 min-w-40 flex-1"
                                                data-test="invoice-line-reference"
                                                @blur="saveReference(row)"
                                            />
                                            <Input
                                                v-model="row.draftCompletedAt"
                                                type="date"
                                                aria-label="Date"
                                                class="h-8 w-40"
                                                data-test="invoice-line-date"
                                                @change="saveCompletedAt(row)"
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
                                                                : Number(
                                                                      $event,
                                                                  ),
                                                    })
                                                "
                                            >
                                                <SelectTrigger
                                                    size="sm"
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
                                                        :value="
                                                            String(workType.id)
                                                        "
                                                    >
                                                        {{ workType.name }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <InputError
                                            :message="
                                                errors[row.id]?.reference_url ||
                                                errors[row.id]?.completed_at
                                            "
                                        />
                                    </template>
                                    <template v-else>
                                        <p class="pt-2">
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
                                            <Link
                                                v-if="row.issueReference"
                                                :href="issueHref(row.issueId)!"
                                                class="hover:text-foreground hover:underline"
                                            >
                                                {{ row.issueReference }}
                                            </Link>
                                            <span
                                                v-if="
                                                    workTypeName(row.workTypeId)
                                                "
                                            >
                                                {{
                                                    workTypeName(row.workTypeId)
                                                }}
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
                            </TableCell>

                            <template v-if="editable()">
                                <TableCell class="align-top">
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
                                </TableCell>
                                <TableCell class="align-top">
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
                                </TableCell>
                                <TableCell class="align-top">
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
                                </TableCell>
                            </template>
                            <template v-else>
                                <TableCell
                                    class="pt-4 text-right align-top tabular-nums"
                                >
                                    {{ row.quantity }}
                                </TableCell>
                                <TableCell class="pt-4 align-top">
                                    {{
                                        row.unit === 'hours' ? 'Hours' : 'Units'
                                    }}
                                </TableCell>
                                <TableCell
                                    class="pt-4 text-right align-top tabular-nums"
                                >
                                    {{ money(row.rate) }}
                                </TableCell>
                            </template>

                            <TableCell
                                class="pt-4 text-right align-top font-medium tabular-nums"
                                data-test="invoice-line-amount"
                            >
                                {{ money(row.amount) }}
                            </TableCell>

                            <TableCell class="align-top">
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
                            </TableCell>
                        </TableRow>
                    </template>
                </draggable>
            </template>
            <TableBody v-else>
                <TableRow class="hover:bg-transparent">
                    <TableCell
                        :colspan="7"
                        class="text-muted-foreground py-6 text-center"
                    >
                        No lines yet.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <form
            v-if="editable()"
            class="bg-muted/30 grid items-start gap-3 border-t p-4 sm:grid-cols-[minmax(0,1fr)_6rem_7rem_7rem_auto]"
            data-test="invoice-line-form"
            @submit.prevent="add"
        >
            <div class="grid gap-2">
                <Input
                    v-model="form.description"
                    placeholder="Description"
                    aria-label="New line description"
                    data-test="new-line-description"
                />
                <div class="flex flex-wrap items-center gap-2">
                    <InvoiceIssuePicker
                        :lookup="lookupUrl"
                        :reference="form.issueReference"
                        @select="pickNewIssue"
                    />
                    <Input
                        v-model="form.completed_at"
                        type="date"
                        aria-label="New line date"
                        class="h-8 w-40"
                        data-test="new-line-date"
                    />
                </div>
                <InputError
                    :message="
                        form.errors.description ||
                        form.errors.issue_id ||
                        form.errors.completed_at
                    "
                />
            </div>
            <div class="grid gap-1">
                <Input
                    v-model="form.quantity"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="Qty"
                    aria-label="New line quantity"
                    class="text-right"
                    data-test="new-line-quantity"
                />
                <InputError :message="form.errors.quantity" />
            </div>
            <Select v-model="form.unit">
                <SelectTrigger class="w-full" aria-label="New line unit">
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
                    placeholder="Rate"
                    aria-label="New line rate"
                    class="text-right"
                    data-test="new-line-rate"
                />
                <InputError :message="form.errors.rate" />
            </div>
            <Button
                type="submit"
                data-test="new-line-add"
                :disabled="form.processing"
            >
                <Plus /> Add line
            </Button>
        </form>
    </Card>
</template>
