<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
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
import type { AddressTypeOption } from '@/types';

export type FormTarget = { action: string; method: 'post' | 'patch' };

defineProps<{
    target: FormTarget;
    types: AddressTypeOption[];
    initial: {
        type: string;
        line1: string;
        line2: string;
        city: string;
        region: string;
        postalCode: string;
        country: string;
    };
    submitLabel: string;
}>();

const emit = defineEmits<{ done: [] }>();
</script>

<template>
    <Form
        v-bind="target"
        class="grid gap-3 sm:grid-cols-6"
        v-slot="{ errors, processing }"
        @success="emit('done')"
    >
        <div class="grid gap-1 sm:col-span-2">
            <Label>Type</Label>
            <Select name="type" :default-value="initial.type">
                <SelectTrigger class="w-full" aria-label="Address type">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="type in types"
                        :key="type.value"
                        :value="type.value"
                    >
                        {{ type.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="errors.type" />
        </div>
        <div class="grid gap-1 sm:col-span-4">
            <Label>Street</Label>
            <Input
                name="line1"
                :default-value="initial.line1"
                placeholder="123 Main St"
                aria-label="Street"
                required
                data-test="address-line1"
            />
            <InputError :message="errors.line1" />
        </div>
        <div class="grid gap-1 sm:col-span-6">
            <Input
                name="line2"
                :default-value="initial.line2"
                placeholder="Suite, unit, floor (optional)"
                aria-label="Street, line 2"
            />
            <InputError :message="errors.line2" />
        </div>
        <div class="grid gap-1 sm:col-span-2">
            <Label>City</Label>
            <Input
                name="city"
                :default-value="initial.city"
                aria-label="City"
                required
                data-test="address-city"
            />
            <InputError :message="errors.city" />
        </div>
        <div class="grid gap-1 sm:col-span-2">
            <Label>State / region</Label>
            <Input
                name="region"
                :default-value="initial.region"
                aria-label="State or region"
            />
            <InputError :message="errors.region" />
        </div>
        <div class="grid gap-1 sm:col-span-2">
            <Label>Postal code</Label>
            <Input
                name="postal_code"
                :default-value="initial.postalCode"
                aria-label="Postal code"
            />
            <InputError :message="errors.postal_code" />
        </div>
        <div class="grid gap-1 sm:col-span-4">
            <Label>Country</Label>
            <Input
                name="country"
                :default-value="initial.country"
                placeholder="United States"
                aria-label="Country"
            />
            <InputError :message="errors.country" />
        </div>
        <div class="flex items-end justify-end gap-2 sm:col-span-2">
            <Button
                type="button"
                variant="ghost"
                size="sm"
                @click="emit('done')"
            >
                Cancel
            </Button>
            <Button
                type="submit"
                size="sm"
                :disabled="processing"
                data-test="address-save"
            >
                {{ submitLabel }}
            </Button>
        </div>
    </Form>
</template>
