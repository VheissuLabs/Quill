<script setup lang="ts">
import { MapPin, Pencil, Plus, X } from '@lucide/vue';
import { ref } from 'vue';
import AddressForm from '@/components/AddressForm.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { mutate } from '@/lib/optimistic';
import type { Address, AddressTypeOption } from '@/types';

import type { FormTarget } from '@/components/AddressForm.vue';

type RouteTarget = { url: string; method: 'delete' };

const props = defineProps<{
    addresses: Address[];
    types: AddressTypeOption[];
    canManage: boolean;
    store: FormTarget;
    update: (id: number) => FormTarget;
    destroy: (id: number) => RouteTarget;
    emptyText?: string;
}>();

const editing = ref<number | 'new' | null>(null);

const fields = (address?: Address) => ({
    type: address?.type ?? props.types[0]?.value ?? 'billing',
    line1: address?.line1 ?? '',
    line2: address?.line2 ?? '',
    city: address?.city ?? '',
    region: address?.region ?? '',
    postalCode: address?.postalCode ?? '',
    country: address?.country ?? '',
});
</script>

<template>
    <div class="space-y-3" data-test="address-list">
        <ul v-if="addresses.length > 0" class="divide-y rounded-lg border">
            <li
                v-for="address in addresses"
                :key="address.id"
                class="px-4 py-3"
                data-test="address"
            >
                <template v-if="editing !== address.id">
                    <div class="flex items-start justify-between gap-3 text-sm">
                        <div class="flex min-w-0 gap-3">
                            <MapPin
                                class="text-muted-foreground mt-0.5 size-4 shrink-0"
                            />
                            <div class="min-w-0">
                                <Badge variant="secondary" class="mb-1">
                                    {{ address.typeLabel }}
                                </Badge>
                                <p v-for="line in address.lines" :key="line">
                                    {{ line }}
                                </p>
                            </div>
                        </div>
                        <span v-if="canManage" class="flex shrink-0 gap-1">
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                :aria-label="`Edit ${address.typeLabel} address`"
                                @click="editing = address.id"
                            >
                                <Pencil />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                :aria-label="`Remove ${address.typeLabel} address`"
                                @click="
                                    mutate(destroy(address.id), {
                                        errorMessage:
                                            'The address could not be removed.',
                                    })
                                "
                            >
                                <X />
                            </Button>
                        </span>
                    </div>
                </template>
                <AddressForm
                    v-else
                    :target="update(address.id)"
                    :types="types"
                    :initial="fields(address)"
                    submit-label="Save"
                    @done="editing = null"
                />
            </li>
        </ul>
        <p v-else-if="editing !== 'new'" class="text-muted-foreground text-sm">
            {{ emptyText ?? 'No addresses yet.' }}
        </p>

        <div v-if="canManage">
            <AddressForm
                v-if="editing === 'new'"
                class="rounded-lg border px-4 py-3"
                :target="store"
                :types="types"
                :initial="fields()"
                submit-label="Add address"
                @done="editing = null"
            />
            <Button
                v-else
                variant="outline"
                size="sm"
                data-test="address-add"
                @click="editing = 'new'"
            >
                <Plus /> Add address
            </Button>
        </div>
    </div>
</template>
