<script setup lang="ts">
    import { Head } from '@inertiajs/vue3'
    import AppearanceTabs from '@/components/AppearanceTabs.vue'
    import Heading from '@/components/Heading.vue'
    import Stack from '@/components/Stack.vue'
    import { edit } from '@/routes/appearance'

    defineOptions({
        layout: {
            breadcrumbs: [
                {
                    title: 'Appearance settings',
                    href: edit(),
                },
            ],
        },
    })
</script>

<template>
    <Head title="Appearance settings" />

    <h1 class="sr-only">Appearance settings</h1>

    <Stack>
        <Heading
            variant="small"
            title="Appearance settings"
            description="Update the appearance settings for your account"
        />
        <AppearanceTabs />
    </Stack>
</template>
