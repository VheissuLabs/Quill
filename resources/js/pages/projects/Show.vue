<script setup lang="ts">
    import { Head } from '@inertiajs/vue3'
    import { show } from '@/routes/projects'

    type Props = {
        project: {
            id: string
            name: string
            slug: string
            ownerName: string | null
            ownerType: string | null
            description: string | null
            defaultForClients: string[]
        }
    }

    const props = defineProps<Props>()

    defineOptions({
        layout: (props: Props) => ({
            breadcrumbs: [
                {
                    title: props.project.name,
                    href: show(props.project.slug).url,
                },
            ],
        }),
    })
</script>

<template>
    <Head :title="props.project.name" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <h1 data-test="project-name" class="text-lg font-medium">
                {{ props.project.name }}
            </h1>

            <p
                v-if="props.project.ownerName"
                data-test="project-owner"
                class="mt-1 text-sm text-muted-foreground"
            >
                Owned by the {{ props.project.ownerType }}
                {{ props.project.ownerName }}
            </p>

            <p
                v-if="props.project.description"
                class="mt-3 text-sm whitespace-pre-wrap"
            >
                {{ props.project.description }}
            </p>

            <p
                v-if="props.project.defaultForClients.length > 0"
                data-test="project-default-for"
                class="mt-3 text-sm text-muted-foreground"
            >
                Work from
                {{ props.project.defaultForClients.join(', ') }}
                lands here by default.
            </p>
        </div>

        <div
            data-test="project-issues-placeholder"
            class="flex flex-1 items-center justify-center rounded-xl border border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground dark:border-sidebar-border"
        >
            Issues live here once they exist.
        </div>
    </div>
</template>
