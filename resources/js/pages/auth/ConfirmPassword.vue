<script setup lang="ts">
    import { Form, Head } from '@inertiajs/vue3'
    import {
        index as confirmOptions,
        store as confirmStore,
    } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController'
    import PasskeyVerify from '@/components/PasskeyVerify.vue'
    import PasswordInput from '@/components/PasswordInput.vue'
    import Stack from '@/components/Stack.vue'
    import { Button } from '@/components/ui/button'
    import { Spinner } from '@/components/ui/spinner'
    import { store } from '@/routes/password/confirm'

    defineOptions({
        layout: {
            title: 'Confirm password',
            description:
                'This is a secure area of the application. Please confirm your password before continuing.',
        },
    })
</script>

<template>
    <Head title="Confirm password" />

    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        label="Confirm with passkey"
        loading-label="Confirming..."
        separator="Or confirm with password"
    />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <Stack>
            <PasswordInput
                id="password"
                name="password"
                label="Password"
                :error="errors.password"
                class="mt-1 block w-full"
                required
                autocomplete="current-password"
                autofocus
            />

            <div class="flex items-center">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    Confirm password
                </Button>
            </div>
        </Stack>
    </Form>
</template>
