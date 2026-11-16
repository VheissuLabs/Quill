<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { edit as editSettings } from '@/routes/organizations';
import { edit, update } from '@/routes/organizations/billing';
import {
    destroy as destroyWorkType,
    store as storeWorkType,
    update as updateWorkType,
} from '@/routes/organizations/work-types';
import type { Organization } from '@/types';

type WorkType = {
    id: number;
    name: string;
    color: string;
    is_active: boolean;
};

type Props = {
    currentOrganization: Organization;
    settings: {
        invoice_prefix: string;
        default_rate: string | null;
        payment_terms_days: number;
        tax_rate: string | null;
        default_notes: string | null;
        default_terms: string | null;
        from_address: string | null;
    };
    square: {
        environment: 'sandbox' | 'production';
        hasAccessToken: boolean;
        locationId: string | null;
        hasSignatureKey: boolean;
        webhookUrl: string;
    };
    paymentInstructions: string | null;
    workTypes: WorkType[];
    colors: string[];
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { currentOrganization: Organization }) => ({
        breadcrumbs: [
            {
                title: 'Settings',
                href: editSettings(props.currentOrganization.slug),
            },
            {
                title: 'Billing',
                href: edit(props.currentOrganization.slug),
            },
        ],
    }),
});

const selectClass =
    'border-input bg-background h-9 rounded-md border px-3 text-sm shadow-xs';

const copyWebhookUrl = async () => {
    await navigator.clipboard.writeText(props.square.webhookUrl);
};

const deleteDialogOpen = ref(false);
const workTypeToDelete = ref<WorkType | null>(null);

const confirmDelete = (workType: WorkType) => {
    workTypeToDelete.value = workType;
    deleteDialogOpen.value = true;
};
</script>

<template>
    <Head title="Billing" />

    <div class="flex flex-col gap-10 p-6">
        <div class="max-w-xl space-y-6">
            <Heading variant="small" title="Billing settings" />

            <Form
                v-bind="update.form(currentOrganization.slug)"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="invoice_prefix">Invoice prefix</Label>
                    <Input
                        id="invoice_prefix"
                        name="invoice_prefix"
                        data-test="billing-prefix-input"
                        :default-value="settings.invoice_prefix"
                        required
                    />
                    <InputError :message="errors.invoice_prefix" />
                </div>

                <div class="grid gap-2">
                    <Label for="default_rate">Default hourly rate</Label>
                    <Input
                        id="default_rate"
                        name="default_rate"
                        type="number"
                        step="0.01"
                        min="0"
                        data-test="billing-rate-input"
                        :default-value="settings.default_rate ?? ''"
                    />
                    <InputError :message="errors.default_rate" />
                </div>

                <div class="grid gap-2">
                    <Label for="payment_terms_days">Payment terms (days)</Label>
                    <Input
                        id="payment_terms_days"
                        name="payment_terms_days"
                        type="number"
                        min="0"
                        max="365"
                        data-test="billing-terms-days-input"
                        :default-value="settings.payment_terms_days"
                        required
                    />
                    <InputError :message="errors.payment_terms_days" />
                </div>

                <div class="grid gap-2">
                    <Label for="tax_rate">Tax rate (%)</Label>
                    <Input
                        id="tax_rate"
                        name="tax_rate"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        data-test="billing-tax-input"
                        :default-value="settings.tax_rate ?? ''"
                    />
                    <InputError :message="errors.tax_rate" />
                </div>

                <div class="grid gap-2">
                    <Label for="from_address">From address</Label>
                    <Textarea
                        id="from_address"
                        name="from_address"
                        rows="3"
                        :default-value="settings.from_address ?? ''"
                    />
                    <InputError :message="errors.from_address" />
                </div>

                <div class="grid gap-2">
                    <Label for="default_notes">Default notes</Label>
                    <Textarea
                        id="default_notes"
                        name="default_notes"
                        rows="3"
                        :default-value="settings.default_notes ?? ''"
                    />
                    <InputError :message="errors.default_notes" />
                </div>

                <div class="grid gap-2">
                    <Label for="default_terms">Default terms</Label>
                    <Textarea
                        id="default_terms"
                        name="default_terms"
                        rows="3"
                        :default-value="settings.default_terms ?? ''"
                    />
                    <InputError :message="errors.default_terms" />
                </div>

                <div class="space-y-6 border-t pt-6">
                    <Heading variant="small" title="Online payments (Square)" />

                    <div class="grid gap-2">
                        <Label for="square_environment">Environment</Label>
                        <select
                            id="square_environment"
                            name="square_environment"
                            data-test="square-environment-select"
                            :class="selectClass"
                            :value="square.environment"
                        >
                            <option value="sandbox">Sandbox</option>
                            <option value="production">Production</option>
                        </select>
                        <p class="text-muted-foreground text-sm">
                            Sandbox and production each need their own webhook
                            and signature key in Square.
                        </p>
                        <InputError :message="errors.square_environment" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="square_access_token">Access token</Label>
                        <Input
                            id="square_access_token"
                            name="square_access_token"
                            type="password"
                            autocomplete="off"
                            data-test="square-token-input"
                        />
                        <p
                            v-if="square.hasAccessToken"
                            class="text-muted-foreground text-sm"
                        >
                            Saved
                        </p>
                        <InputError :message="errors.square_access_token" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="square_location_id">Location ID</Label>
                        <Input
                            id="square_location_id"
                            name="square_location_id"
                            data-test="square-location-input"
                            :default-value="square.locationId ?? ''"
                        />
                        <InputError :message="errors.square_location_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="square_webhook_signature_key">
                            Webhook signature key
                        </Label>
                        <Input
                            id="square_webhook_signature_key"
                            name="square_webhook_signature_key"
                            type="password"
                            autocomplete="off"
                            data-test="square-signature-input"
                        />
                        <p
                            v-if="square.hasSignatureKey"
                            class="text-muted-foreground text-sm"
                        >
                            Saved
                        </p>
                        <InputError
                            :message="errors.square_webhook_signature_key"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="square_webhook_url">Webhook URL</Label>
                        <div class="flex gap-2">
                            <Input
                                id="square_webhook_url"
                                data-test="square-webhook-url"
                                :model-value="square.webhookUrl"
                                readonly
                            />
                            <Button
                                type="button"
                                variant="secondary"
                                @click="copyWebhookUrl"
                            >
                                Copy
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="payment_instructions">
                            Other ways to pay
                        </Label>
                        <Textarea
                            id="payment_instructions"
                            name="payment_instructions"
                            rows="3"
                            :default-value="paymentInstructions ?? ''"
                        />
                        <InputError :message="errors.payment_instructions" />
                    </div>
                </div>

                <Button
                    type="submit"
                    data-test="billing-save-button"
                    :disabled="processing"
                >
                    Save
                </Button>
            </Form>
        </div>

        <div class="max-w-xl space-y-6">
            <Heading variant="small" title="Work types" />

            <ul v-if="workTypes.length" class="space-y-2">
                <li v-for="workType in workTypes" :key="workType.id">
                    <Form
                        v-bind="
                            updateWorkType.form([
                                currentOrganization.slug,
                                workType.id,
                            ])
                        "
                        class="flex items-start gap-2"
                        v-slot="{ errors, processing }"
                        data-test="work-type-row"
                    >
                        <div class="grid flex-1 gap-1">
                            <Input
                                name="name"
                                aria-label="Name"
                                :default-value="workType.name"
                                required
                            />
                            <InputError :message="errors.name" />
                        </div>
                        <select
                            name="color"
                            aria-label="Color"
                            :class="selectClass"
                            :value="workType.color"
                        >
                            <option
                                v-for="color in colors"
                                :key="color"
                                :value="color"
                            >
                                {{ color }}
                            </option>
                        </select>
                        <label class="flex h-9 items-center gap-2 text-sm">
                            <input type="hidden" name="is_active" value="0" />
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                :checked="workType.is_active"
                            />
                            Active
                        </label>
                        <Button
                            type="submit"
                            variant="secondary"
                            :disabled="processing"
                        >
                            Save
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :aria-label="`Delete ${workType.name}`"
                            @click="confirmDelete(workType)"
                        >
                            <Trash2 />
                        </Button>
                    </Form>
                </li>
            </ul>
            <p v-else class="text-muted-foreground text-sm">
                No work types yet.
            </p>

            <Form
                v-bind="storeWorkType.form(currentOrganization.slug)"
                class="flex items-start gap-2"
                reset-on-success
                v-slot="{ errors, processing }"
            >
                <div class="grid flex-1 gap-1">
                    <Input
                        name="name"
                        aria-label="New work type"
                        data-test="work-type-input"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>
                <select
                    name="color"
                    aria-label="Color"
                    :class="selectClass"
                    value="gray"
                >
                    <option v-for="color in colors" :key="color" :value="color">
                        {{ color }}
                    </option>
                </select>
                <Button
                    type="submit"
                    data-test="work-type-add-button"
                    :disabled="processing"
                >
                    Add
                </Button>
            </Form>
        </div>
    </div>

    <ConfirmDeleteModal
        v-model:open="deleteDialogOpen"
        :title="`Delete ${workTypeToDelete?.name ?? 'work type'}?`"
        confirm-label="Delete work type"
        :href="
            workTypeToDelete
                ? destroyWorkType([
                      currentOrganization.slug,
                      workTypeToDelete.id,
                  ])
                : null
        "
    />
</template>
