<script setup lang="ts">
    import { Form, Head } from '@inertiajs/vue3'
    import Stack from '@/components/Stack.vue'
    import TextInput from '@/components/TextInput.vue'
    import TextLink from '@/components/TextLink.vue'
    import { Button } from '@/components/ui/button'
    import { Spinner } from '@/components/ui/spinner'
    import { login } from '@/routes'
    import { email } from '@/routes/password'

    defineOptions({
        layout: {
            title: 'Forgot password',
            description: 'Enter your email to receive a password reset link',
        },
    })

    defineProps<{
        status?: string
    }>()
</script>

<template>
    <Head title="Forgot password" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Stack>
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <TextInput
                id="email"
                type="email"
                name="email"
                label="Email address"
                :error="errors.email"
                autocomplete="off"
                autofocus
                placeholder="email@example.com"
            />
            <Stack>
                <div class="my-6 flex items-center justify-start">
                    <Button
                        class="w-full"
                        :disabled="processing"
                        data-test="email-password-reset-link-button"
                    >
                        <Spinner v-if="processing" />
                        Email password reset link
                    </Button>
                </div>
            </Stack>
        </Form>

        <div class="space-x-1 text-center text-sm text-muted-foreground">
            <span>Or, return to</span>
            <TextLink :href="login()">log in</TextLink>
        </div>
    </Stack>
</template>
