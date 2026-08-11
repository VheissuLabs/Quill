<script setup lang="ts">
    import { Form, Head } from '@inertiajs/vue3'
    import { Info } from '@lucide/vue'
    import PasswordInput from '@/components/PasswordInput.vue'
    import Stack from '@/components/Stack.vue'
    import TextInput from '@/components/TextInput.vue'
    import { Alert, AlertDescription } from '@/components/ui/alert'
    import { Button } from '@/components/ui/button'
    import { Spinner } from '@/components/ui/spinner'
    import { store } from '@/routes/join'
    import type { JoinInvitationContext } from '@/types'

    const props = defineProps<{
        passwordRules: string
        invitation: JoinInvitationContext
    }>()

    defineOptions({
        layout: {
            title: 'Accept your invitation',
            description: 'Choose a password to finish setting up your account',
        },
    })
</script>

<template>
    <Head title="Accept your invitation" />

    <div data-test="join-invitation-alert">
        <Alert
            class="border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/50 dark:text-blue-100 [&>svg]:text-blue-600 dark:[&>svg]:text-blue-400"
        >
            <Info class="size-4" />
            <AlertDescription class="text-blue-900 dark:text-blue-100">
                {{ props.invitation.inviterName }} invited you to
                {{ props.invitation.organizationName }}<template
                    v-if="props.invitation.clientName"
                >
                    as a contact for
                    {{ props.invitation.clientName }}</template
                >.
            </AlertDescription>
        </Alert>
    </div>

    <Form
        v-bind="store.form({ invitation: props.invitation.code })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <Stack>
            <TextInput
                id="email"
                type="email"
                name="email"
                label="Email address"
                :model-value="props.invitation.email"
                disabled
                :tabindex="-1"
                data-test="join-email"
            />

            <TextInput
                id="name"
                type="text"
                name="name"
                label="Your name"
                :error="errors.name"
                required
                autofocus
                :tabindex="1"
                autocomplete="name"
                placeholder="Full name"
            />

            <PasswordInput
                id="password"
                name="password"
                label="Password"
                :error="errors.password"
                required
                :tabindex="2"
                autocomplete="new-password"
                placeholder="Password"
                :passwordrules="props.passwordRules"
            />

            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                label="Confirm password"
                :error="errors.password_confirmation"
                required
                :tabindex="3"
                autocomplete="new-password"
                placeholder="Confirm password"
                :passwordrules="props.passwordRules"
            />

            <Button
                type="submit"
                class="mt-2 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="join-button"
            >
                <Spinner v-if="processing" />
                Accept invitation
            </Button>
        </Stack>
    </Form>
</template>
