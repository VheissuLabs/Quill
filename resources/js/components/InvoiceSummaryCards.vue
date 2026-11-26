<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarClock, CalendarDays, CalendarRange } from '@lucide/vue';
import { formatDate } from '@/lib/dates';
import { show } from '@/routes/invoices';
import type { InvoiceMonthSummary, InvoiceSummary } from '@/types';

const props = defineProps<{
    organization: string;
    summary: InvoiceSummary;
}>();

const money = (value: string) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(Number(value));

const monthName = (month: string) =>
    formatDate(`${month}-01`, { month: 'long', year: 'numeric' });

const months: {
    label: string;
    icon: typeof CalendarDays;
    data: () => InvoiceMonthSummary;
}[] = [
    {
        label: 'This month',
        icon: CalendarDays,
        data: () => props.summary.thisMonth,
    },
    {
        label: 'Next month',
        icon: CalendarRange,
        data: () => props.summary.nextMonth,
    },
];
</script>

<template>
    <dl class="grid gap-4 md:grid-cols-3" data-test="invoice-summary">
        <div
            class="bg-card flex items-start gap-4 rounded-xl border p-5 shadow-sm"
            data-test="invoice-summary-next-payment"
        >
            <div
                class="bg-muted flex size-10 shrink-0 items-center justify-center rounded-lg"
            >
                <CalendarClock class="text-muted-foreground size-5" />
            </div>
            <div class="min-w-0">
                <dt
                    class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                >
                    Next payment
                </dt>
                <template v-if="summary.nextPayment">
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ money(summary.nextPayment.amountDue) }}
                    </dd>
                    <dd class="truncate text-sm font-medium">
                        {{ summary.nextPayment.clientName }}
                    </dd>
                    <dd
                        class="text-sm"
                        :class="
                            summary.nextPayment.isOverdue
                                ? 'font-medium text-red-600 dark:text-red-400'
                                : 'text-muted-foreground'
                        "
                    >
                        {{
                            summary.nextPayment.isOverdue
                                ? 'Overdue since'
                                : 'Due'
                        }}
                        {{ formatDate(summary.nextPayment.dueDate) }} ·
                        <Link
                            :href="
                                show([
                                    organization,
                                    summary.nextPayment.invoiceId,
                                ])
                            "
                            class="hover:text-foreground underline-offset-2 hover:underline"
                        >
                            {{ summary.nextPayment.number }}
                        </Link>
                        <template v-if="summary.nextPayment.isDraft">
                            · draft
                        </template>
                    </dd>
                </template>
                <dd v-else class="text-muted-foreground mt-1 text-sm">
                    Nothing owed right now.
                </dd>
            </div>
        </div>

        <div
            v-for="month in months"
            :key="month.label"
            class="bg-card flex items-start gap-4 rounded-xl border p-5 shadow-sm"
            :data-test="`invoice-summary-${month.label.toLowerCase().replace(' ', '-')}`"
        >
            <div
                class="bg-muted flex size-10 shrink-0 items-center justify-center rounded-lg"
            >
                <component
                    :is="month.icon"
                    class="text-muted-foreground size-5"
                />
            </div>
            <div class="min-w-0">
                <dt
                    class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                >
                    {{ month.label }} · {{ monthName(month.data().month) }}
                </dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ money(month.data().total) }}
                </dd>
                <dd class="text-muted-foreground text-sm">
                    <template v-if="month.data().invoices > 0">
                        {{ money(month.data().collected) }} collected ·
                        {{ money(month.data().outstanding) }} outstanding
                    </template>
                    <template v-else>No invoices due.</template>
                </dd>
            </div>
        </div>
    </dl>
</template>
