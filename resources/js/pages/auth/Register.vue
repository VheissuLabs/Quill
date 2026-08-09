<script setup lang="ts">
    import { Form, Head } from '@inertiajs/vue3'
    import PasswordInput from '@/components/PasswordInput.vue'
    import Stack from '@/components/Stack.vue'
    import TeamInvitationAlert from '@/components/TeamInvitationAlert.vue'
    import TextInput from '@/components/TextInput.vue'
    import TextLink from '@/components/TextLink.vue'
    import { Button } from '@/components/ui/button'
    import { Spinner } from '@/components/ui/spinner'
    import { login } from '@/routes'
    import { store } from '@/routes/register'
    import type { TeamInvitationContext } from '@/types'

    defineProps<{
        passwordRules: string
        teamInvitation?: TeamInvitationContext | null
    }>()

    defineOptions({
        layout: {
            title: 'Create an account',
            description: 'Enter your details below to create your account',
        },
    })
</script>

<template>
    <Head title="Register" />

    <TeamInvitationAlert
        v-if="teamInvitation"
        :invitation="teamInvitation"
        action="Register"
    />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <Stack>
            <TextInput
                id="name"
                type="text"
                name="name"
                label="Name"
                :error="errors.name"
                required
                autofocus
                :tabindex="1"
                autocomplete="name"
                placeholder="Full name"
            />

            <TextInput
                id="email"
                type="email"
                name="email"
                label="Email address"
                :error="errors.email"
                required
                :tabindex="2"
                autocomplete="email"
                placeholder="email@example.com"
            />

            <PasswordInput
                id="password"
                name="password"
                label="Password"
                :error="errors.password"
                required
                :tabindex="3"
                autocomplete="new-password"
                placeholder="Password"
                :passwordrules="passwordRules"
            />

            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                label="Confirm password"
                :error="errors.password_confirmation"
                required
                :tabindex="4"
                autocomplete="new-password"
                placeholder="Confirm password"
                :passwordrules="passwordRules"
            />

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Create account
            </Button>
        </Stack>

        <div class="text-center text-sm text-muted-foreground">
            Already have an account?
            <TextLink
                :href="
                    teamInvitation
                        ? login.url({
                              query: {
                                  invitation: teamInvitation.code,
                              },
                          })
                        : login()
                "
                class="underline underline-offset-4"
                :tabindex="6"
                data-test="team-invitation-login-link"
            >
                Log in
            </TextLink>
        </div>
    </Form>
</template>
