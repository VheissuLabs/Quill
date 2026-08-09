<script setup lang="ts">
    import { Form, Head } from '@inertiajs/vue3'
    import PasskeyVerify from '@/components/PasskeyVerify.vue'
    import PasswordInput from '@/components/PasswordInput.vue'
    import Stack from '@/components/Stack.vue'
    import TeamInvitationAlert from '@/components/TeamInvitationAlert.vue'
    import TextInput from '@/components/TextInput.vue'
    import TextLink from '@/components/TextLink.vue'
    import { Button } from '@/components/ui/button'
    import { Checkbox } from '@/components/ui/checkbox'
    import { Label } from '@/components/ui/label'
    import { Spinner } from '@/components/ui/spinner'
    import { register } from '@/routes'
    import { store } from '@/routes/login'
    import { request } from '@/routes/password'
    import type { TeamInvitationContext } from '@/types'

    defineOptions({
        layout: {
            title: 'Log in to your account',
            description: 'Enter your email and password below to log in',
        },
    })

    defineProps<{
        status?: string
        canResetPassword: boolean
        teamInvitation?: TeamInvitationContext | null
    }>()
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <TeamInvitationAlert
        v-if="teamInvitation"
        :invitation="teamInvitation"
        action="Log in"
    />

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <Stack>
            <TextInput
                id="email"
                type="email"
                name="email"
                label="Email address"
                :error="errors.email"
                required
                autofocus
                :tabindex="1"
                autocomplete="email"
                placeholder="email@example.com"
            />

            <PasswordInput
                id="password"
                name="password"
                label="Password"
                :error="errors.password"
                required
                :tabindex="2"
                autocomplete="current-password"
                placeholder="Password"
            >
                <template #labelAction>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        Forgot password?
                    </TextLink>
                </template>
            </PasswordInput>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </Button>
        </Stack>

        <div class="text-center text-sm text-muted-foreground">
            Don't have an account?
            <TextLink
                :href="
                    register({
                        query: {
                            invitation: teamInvitation?.code,
                        },
                    })
                "
                :tabindex="5"
                data-test="register-link"
            >
                Sign up
            </TextLink>
        </div>
    </Form>
</template>
