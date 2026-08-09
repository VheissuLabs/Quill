<script setup lang="ts">
    import { Form, Head } from '@inertiajs/vue3'
    import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController'
    import Heading from '@/components/Heading.vue'
    import type { Props as ManagePasskeysProps } from '@/components/ManagePasskeys.vue'
    import ManagePasskeys from '@/components/ManagePasskeys.vue'
    import type { Props as ManageTwoFactorProps } from '@/components/ManageTwoFactor.vue'
    import ManageTwoFactor from '@/components/ManageTwoFactor.vue'
    import PasswordInput from '@/components/PasswordInput.vue'
    import Stack from '@/components/Stack.vue'
    import { Button } from '@/components/ui/button'
    import { edit } from '@/routes/security'

    type Props = {
        passwordRules: string
    } & ManagePasskeysProps &
        ManageTwoFactorProps

    const props = defineProps<Props>()

    defineOptions({
        layout: {
            breadcrumbs: [
                {
                    title: 'Security settings',
                    href: edit(),
                },
            ],
        },
    })
</script>

<template>
    <Head title="Security settings" />

    <h1 class="sr-only">Security settings</h1>

    <Stack>
        <Heading
            variant="small"
            title="Update password"
            description="Ensure your account is using a long, random password to stay secure"
        />

        <Form
            v-bind="SecurityController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            v-slot="{ errors, processing }"
        >
            <Stack>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    label="Current password"
                    :error="errors.current_password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                    placeholder="Current password"
                />

                <PasswordInput
                    id="password"
                    name="password"
                    label="New password"
                    :error="errors.password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="New password"
                    :passwordrules="props.passwordRules"
                />

                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    label="Confirm password"
                    :error="errors.password_confirmation"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="Confirm password"
                    :passwordrules="props.passwordRules"
                />

                <div class="flex items-center gap-4">
                    <Button
                        :disabled="processing"
                        data-test="update-password-button"
                    >
                        Save
                    </Button>
                </div>
            </Stack>
        </Form>
    </Stack>

    <ManageTwoFactor
        :canManageTwoFactor="canManageTwoFactor"
        :requiresConfirmation="requiresConfirmation"
        :twoFactorEnabled="twoFactorEnabled"
    />

    <ManagePasskeys
        :canManagePasskeys="canManagePasskeys"
        :passkeys="passkeys"
    />
</template>
