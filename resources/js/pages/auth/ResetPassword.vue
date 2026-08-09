<script setup lang="ts">
    import { Form, Head } from '@inertiajs/vue3'
    import { ref } from 'vue'
    import PasswordInput from '@/components/PasswordInput.vue'
    import Stack from '@/components/Stack.vue'
    import TextInput from '@/components/TextInput.vue'
    import { Button } from '@/components/ui/button'
    import { Spinner } from '@/components/ui/spinner'
    import { update } from '@/routes/password'

    defineOptions({
        layout: {
            title: 'Reset password',
            description: 'Please enter your new password below',
        },
    })

    const props = defineProps<{
        token: string
        email: string
        passwordRules: string
    }>()

    const inputEmail = ref(props.email)
</script>

<template>
    <Head title="Reset password" />

    <Form
        v-bind="update.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
    >
        <Stack>
            <TextInput
                id="email"
                type="email"
                name="email"
                label="Email"
                :error="errors.email"
                autocomplete="email"
                v-model="inputEmail"
                class="mt-1 block w-full"
                readonly
            />

            <PasswordInput
                id="password"
                name="password"
                label="Password"
                :error="errors.password"
                autocomplete="new-password"
                class="mt-1 block w-full"
                autofocus
                placeholder="Password"
                :passwordrules="passwordRules"
            />

            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                label="Confirm password"
                :error="errors.password_confirmation"
                autocomplete="new-password"
                class="mt-1 block w-full"
                placeholder="Confirm password"
                :passwordrules="passwordRules"
            />

            <Button
                type="submit"
                class="mt-4 w-full"
                :disabled="processing"
                data-test="reset-password-button"
            >
                <Spinner v-if="processing" />
                Reset password
            </Button>
        </Stack>
    </Form>
</template>
