<script setup lang="ts">
    import { Form, Head, usePage } from '@inertiajs/vue3'
    import { Link } from '@inertiajs/vue3'
    import { computed } from 'vue'
    import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController'
    import DeleteUser from '@/components/DeleteUser.vue'
    import Heading from '@/components/Heading.vue'
    import Stack from '@/components/Stack.vue'
    import TextInput from '@/components/TextInput.vue'
    import { Button } from '@/components/ui/button'
    import { edit } from '@/routes/profile'
    import { send } from '@/routes/verification'

    defineOptions({
        layout: {
            breadcrumbs: [
                {
                    title: 'Profile settings',
                    href: edit(),
                },
            ],
        },
    })

    const page = usePage()
    const user = computed(() => page.props.auth.user)
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile"
            description="Update your name and email address"
        />

        <Form
            v-bind="ProfileController.update.form()"
            v-slot="{ errors, processing }"
        >
            <Stack>
                <TextInput
                    id="name"
                    name="name"
                    label="Name"
                    :error="errors.name"
                    class="mt-1 block w-full"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Full name"
                />

                <TextInput
                    id="email"
                    type="email"
                    name="email"
                    label="Email address"
                    :error="errors.email"
                    class="mt-1 block w-full"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="Email address"
                />

                <div
                    v-if="page.props.mustVerifyEmail && !user.email_verified_at"
                >
                    <p class="-mt-4 text-sm text-muted-foreground">
                        Your email address is unverified.
                        <Link
                            :href="send()"
                            as="button"
                            class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                        >
                            Click here to re-send the verification email.
                        </Link>
                    </p>

                    <div
                        v-if="page.props.status === 'verification-link-sent'"
                        class="mt-2 text-sm font-medium text-green-600"
                    >
                        A new verification link has been sent to your email
                        address.
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <Button
                        :disabled="processing"
                        data-test="update-profile-button"
                        >Save</Button
                    >
                </div>
            </Stack>
        </Form>
    </div>

    <DeleteUser />
</template>
