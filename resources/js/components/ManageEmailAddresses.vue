<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { Mail, X } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import SectionCard from '@/components/SectionCard.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store } from '@/routes/email-addresses';
import { send as sendVerification } from '@/routes/email-addresses/verification';
import type { EmailAddress } from '@/types';

defineProps<{
    emailAddresses: EmailAddress[];
}>();

const addressToRemove = ref<EmailAddress | null>(null);
</script>

<template>
    <SectionCard
        title="Email addresses"
        description="Invitations sent to any verified address reach this account, and you can log in with any of them"
    >
        <div class="space-y-3">
            <div
                v-for="emailAddress in emailAddresses"
                :key="emailAddress.id"
                data-test="email-address-row"
                class="flex items-center justify-between gap-4 rounded-lg border p-4"
            >
                <div class="flex min-w-0 items-center gap-4">
                    <div
                        class="bg-muted flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                    >
                        <Mail class="text-muted-foreground h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                        <div class="truncate font-medium">
                            {{ emailAddress.email }}
                        </div>
                        <div class="mt-1 flex items-center gap-2 text-sm">
                            <Badge
                                v-if="emailAddress.verified"
                                variant="secondary"
                            >
                                Verified
                            </Badge>
                            <template v-else>
                                <Badge variant="outline">Unverified</Badge>
                                <Link
                                    :href="sendVerification(emailAddress.id)"
                                    as="button"
                                    preserve-scroll
                                    data-test="resend-verification-button"
                                    class="text-muted-foreground hover:text-foreground underline underline-offset-4"
                                >
                                    Resend verification link
                                </Link>
                            </template>
                        </div>
                    </div>
                </div>

                <Button
                    data-test="remove-email-address-button"
                    variant="ghost"
                    size="sm"
                    aria-label="Remove email address"
                    @click="addressToRemove = emailAddress"
                >
                    <X class="h-4 w-4" />
                </Button>
            </div>
        </div>

        <Form
            v-bind="store.form()"
            class="space-y-4"
            reset-on-success
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="new-email">Add an email address</Label>
                <div class="flex gap-2">
                    <Input
                        id="new-email"
                        name="email"
                        type="email"
                        data-test="new-email-address-input"
                        placeholder="you@company.com"
                        autocomplete="email"
                        required
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        data-test="add-email-address-button"
                        :disabled="processing"
                    >
                        Add
                    </Button>
                </div>
                <InputError :message="errors.email" />
            </div>
        </Form>
    </SectionCard>

    <ConfirmDeleteModal
        :open="addressToRemove !== null"
        title="Remove email address"
        confirm-label="Remove"
        :href="addressToRemove ? destroy(addressToRemove.id) : null"
        @update:open="addressToRemove = null"
    >
        Invitations sent to <strong>{{ addressToRemove?.email }}</strong> will
        no longer reach this account.
    </ConfirmDeleteModal>
</template>
