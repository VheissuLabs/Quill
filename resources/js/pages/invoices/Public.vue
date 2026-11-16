<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Toaster } from '@/components/ui/sonner';
import { formatDate } from '@/lib/dates';
import type { PublicInvoice } from '@/types';

const props = defineProps<{
    invoice: PublicInvoice;
}>();

const paying = ref(false);

const pay = () => {
    router.post(
        props.invoice.payUrl,
        {},
        {
            onFinish: () => (paying.value = false),
            onStart: () => (paying.value = true),
        },
    );
};

const money = (value: string) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(Number(value));
</script>

<template>
    <Head :title="invoice.number">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <main
        class="bg-background text-foreground min-h-screen px-4 py-10 print:bg-white print:p-0 print:text-black"
    >
        <article
            class="mx-auto flex max-w-3xl flex-col gap-8 print:max-w-none"
            data-test="public-invoice"
        >
            <div
                v-if="invoice.isVoid"
                class="rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 print:border-black print:bg-white print:text-black"
                data-test="public-invoice-void"
            >
                This invoice has been voided and is no longer payable.
            </div>

            <header class="flex items-start justify-between gap-6">
                <div class="flex items-start gap-3">
                    <img
                        v-if="invoice.organizationLogoUrl"
                        :src="invoice.organizationLogoUrl"
                        :alt="`${invoice.organizationName} logo`"
                        class="size-12 rounded-md object-contain"
                    />
                    <div>
                        <p class="font-semibold">
                            {{ invoice.organizationName }}
                        </p>
                        <p
                            v-if="invoice.fromAddress"
                            class="text-muted-foreground text-sm whitespace-pre-line print:text-black"
                        >
                            {{ invoice.fromAddress }}
                        </p>
                    </div>
                </div>
                <div class="text-right">
                    <h1 class="text-2xl font-semibold">{{ invoice.number }}</h1>
                    <p class="text-muted-foreground text-sm print:text-black">
                        {{ invoice.statusLabel }}
                    </p>
                </div>
            </header>

            <section class="grid gap-6 text-sm sm:grid-cols-2">
                <div>
                    <p class="text-muted-foreground print:text-black">
                        Billed to
                    </p>
                    <p class="font-medium">{{ invoice.clientName }}</p>
                    <p v-if="invoice.contactName">{{ invoice.contactName }}</p>
                    <p v-if="invoice.contactEmail">
                        {{ invoice.contactEmail }}
                    </p>
                </div>
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                    <dt class="text-muted-foreground print:text-black">
                        Issued
                    </dt>
                    <dd>{{ formatDate(invoice.issueDate) }}</dd>
                    <dt class="text-muted-foreground print:text-black">Due</dt>
                    <dd>{{ formatDate(invoice.dueDate) }}</dd>
                    <template v-if="invoice.servicePeriodStart">
                        <dt class="text-muted-foreground print:text-black">
                            Service period
                        </dt>
                        <dd>
                            {{ formatDate(invoice.servicePeriodStart) }}
                            <template v-if="invoice.servicePeriodEnd">
                                to {{ formatDate(invoice.servicePeriodEnd) }}
                            </template>
                        </dd>
                    </template>
                </dl>
            </section>

            <table class="w-full text-sm" data-test="public-invoice-lines">
                <thead>
                    <tr class="border-b text-left print:border-black">
                        <th class="py-2 font-medium">Description</th>
                        <th class="py-2 text-right font-medium">Qty</th>
                        <th class="py-2 text-right font-medium">Rate</th>
                        <th class="py-2 text-right font-medium">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(line, index) in invoice.lines"
                        :key="index"
                        class="border-b align-top print:border-black"
                    >
                        <td class="py-2 pr-4">
                            <p>{{ line.description }}</p>
                            <a
                                v-if="line.issueReference && line.referenceUrl"
                                :href="line.referenceUrl"
                                class="text-muted-foreground text-xs underline print:text-black"
                            >
                                {{ line.issueReference }}
                            </a>
                            <p
                                v-else-if="line.issueReference"
                                class="text-muted-foreground text-xs print:text-black"
                            >
                                {{ line.issueReference }}
                            </p>
                        </td>
                        <td class="py-2 text-right tabular-nums">
                            {{ line.quantity }} {{ line.unit }}
                        </td>
                        <td class="py-2 text-right tabular-nums">
                            {{ money(line.rate) }}
                        </td>
                        <td class="py-2 text-right tabular-nums">
                            {{ money(line.amount) }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <section
                v-if="invoice.status === 'paid'"
                class="rounded-md border border-green-300 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 print:border-black print:bg-white print:text-black"
                data-test="public-invoice-paid"
            >
                Paid — thank you
            </section>

            <section
                v-if="invoice.paymentProcessing"
                class="rounded-md border px-4 py-3 text-sm font-medium print:hidden"
                data-test="public-invoice-processing"
            >
                Your payment is processing
            </section>

            <section
                v-if="invoice.canPayOnline"
                class="print:hidden"
                data-test="public-invoice-pay"
            >
                <Button :disabled="paying" @click="pay">
                    Pay {{ money(invoice.amountDue) }}
                </Button>
            </section>

            <section
                class="ml-auto w-full max-w-xs space-y-1 text-sm"
                data-test="public-invoice-totals"
            >
                <div class="flex justify-between">
                    <span>Subtotal</span>
                    <span class="tabular-nums">{{
                        money(invoice.subtotal)
                    }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Tax</span>
                    <span class="tabular-nums">{{
                        money(invoice.taxAmount)
                    }}</span>
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
                <div
                    v-if="Number(invoice.depositAmount) > 0"
                    class="flex justify-between"
                >
                    <span>Deposit</span>
                    <span class="tabular-nums">
                        {{ money(invoice.depositAmount) }}
                    </span>
                </div>
                <div
                    v-for="(payment, index) in invoice.payments"
                    :key="index"
                    class="flex justify-between"
                >
                    <span>
                        Paid {{ formatDate(payment.paidOn) }},
                        {{ payment.methodLabel }}
                    </span>
                    <span class="tabular-nums">{{
                        money(payment.amount)
                    }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Paid</span>
                    <span class="tabular-nums">
                        {{ money(invoice.amountPaid) }}
                    </span>
                </div>
                <div class="flex justify-between text-base font-semibold">
                    <span>Amount due</span>
                    <span class="tabular-nums">
                        {{ money(invoice.amountDue) }}
                    </span>
                </div>
            </section>

            <section
                v-if="invoice.paymentInstructions"
                class="text-sm"
                data-test="public-invoice-instructions"
            >
                <h2 class="font-medium">Other ways to pay</h2>
                <p class="whitespace-pre-line">
                    {{ invoice.paymentInstructions }}
                </p>
            </section>

            <section v-if="invoice.notes" class="text-sm">
                <h2 class="font-medium">Notes</h2>
                <p class="whitespace-pre-line">{{ invoice.notes }}</p>
            </section>

            <section v-if="invoice.terms" class="text-sm">
                <h2 class="font-medium">Terms</h2>
                <p class="whitespace-pre-line">{{ invoice.terms }}</p>
            </section>
        </article>

        <Toaster />
    </main>
</template>
