<script setup lang="ts">
    import { Form, Head } from '@inertiajs/vue3'
    import { Button } from '@/components/ui/button'
    import { usePermissions } from '@/composables/usePermissions'
    import { show } from '@/routes/projects'
    import closure from '@/routes/projects/issues/closure'
    import type { Issue } from '@/types'

    type Props = {
        project: {
            name: string
            slug: string
        }
        issue: Issue
    }

    const props = defineProps<Props>()

    defineOptions({
        layout: (props: Props) => ({
            breadcrumbs: [
                {
                    title: props.project.name,
                    href: show(props.project.slug).url,
                },
                {
                    title: `#${props.issue.number} ${props.issue.title}`,
                    href: '',
                },
            ],
        }),
    })

    const { can } = usePermissions()
</script>

<template>
    <Head :title="`#${props.issue.number} ${props.issue.title}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p
                        data-test="issue-number"
                        class="text-sm text-muted-foreground"
                    >
                        #{{ props.issue.number }} · {{ props.issue.type }}
                    </p>

                    <h1 data-test="issue-title" class="text-lg font-medium">
                        {{ props.issue.title }}
                    </h1>

                    <p
                        data-test="issue-status"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{ props.issue.isOpen ? 'Open' : 'Closed' }}
                    </p>
                </div>

                <Form
                    v-if="can('issue:close')"
                    v-bind="
                        props.issue.isOpen
                            ? closure.store.form([
                                  props.project.slug,
                                  props.issue.number,
                              ])
                            : closure.destroy.form([
                                  props.project.slug,
                                  props.issue.number,
                              ])
                    "
                    v-slot="{ processing }"
                >
                    <Button
                        type="submit"
                        data-test="issue-close-reopen"
                        :variant="
                            props.issue.isOpen ? 'destructive' : 'default'
                        "
                        :disabled="processing"
                    >
                        {{ props.issue.isOpen ? 'Close' : 'Reopen' }}
                    </Button>
                </Form>
            </div>

            <p
                data-test="issue-description"
                class="mt-4 text-sm whitespace-pre-wrap"
            >
                {{ props.issue.description }}
            </p>

            <div
                v-if="props.issue.acceptanceCriteria"
                class="mt-4 border-t pt-4"
            >
                <h2 class="text-sm font-medium">Acceptance criteria</h2>
                <p
                    data-test="issue-acceptance-criteria"
                    class="mt-1 text-sm whitespace-pre-wrap"
                >
                    {{ props.issue.acceptanceCriteria }}
                </p>
            </div>

            <div
                class="mt-4 flex flex-wrap gap-4 border-t pt-4 text-sm text-muted-foreground"
            >
                <span v-if="props.issue.clientName" data-test="issue-client">
                    {{ props.issue.clientName }}
                </span>
                <span
                    v-if="props.issue.reporterName"
                    data-test="issue-reporter"
                >
                    Reported by {{ props.issue.reporterName }}
                </span>
                <span v-if="props.issue.createdAt" data-test="issue-created">
                    Filed {{ props.issue.createdAt }}
                </span>
                <span
                    v-if="props.issue.fromConversation"
                    data-test="issue-from-conversation"
                >
                    Drafted from a conversation
                </span>
            </div>
        </div>
    </div>
</template>
