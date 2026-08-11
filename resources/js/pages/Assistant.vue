<script setup lang="ts">
    import { Head } from '@inertiajs/vue3'
    import { Send } from '@lucide/vue'
    import { nextTick, ref, useTemplateRef, watch } from 'vue'
    import { Button } from '@/components/ui/button'
    import { Textarea } from '@/components/ui/textarea'
    import { assistant } from '@/routes'
    import { store as sendAssistantMessage } from '@/routes/assistant/messages'
    import type { AssistantMessage } from '@/types'

    const props = defineProps<{
        messages: AssistantMessage[]
    }>()

    defineOptions({
        layout: () => ({
            breadcrumbs: [{ title: 'Assistant', href: assistant().url }],
        }),
    })

    const messages = ref<AssistantMessage[]>([...props.messages])
    const draft = ref('')
    const isStreaming = ref(false)
    const error = ref<string | null>(null)
    const transcript = useTemplateRef<HTMLElement>('transcript')

    /**
     * Laravel refreshes the XSRF-TOKEN cookie on every response, so reading it at
     * send time is always current. The <meta> tag is baked in when the page
     * renders and goes stale if the session regenerates while the chat is open,
     * which returned an intermittent 419.
     */
    const csrfToken = (): string => {
        const cookie = document.cookie
            .split('; ')
            .find((entry) => entry.startsWith('XSRF-TOKEN='))

        if (cookie) {
            return decodeURIComponent(cookie.split('=').slice(1).join('='))
        }

        return (
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') ?? ''
        )
    }

    const scrollToBottom = async (): Promise<void> => {
        await nextTick()
        transcript.value?.scrollTo({ top: transcript.value.scrollHeight })
    }

    watch(() => messages.value.length, scrollToBottom)

    const send = async (): Promise<void> => {
        const message = draft.value.trim()

        if (message === '' || isStreaming.value) {
            return
        }

        draft.value = ''
        error.value = null
        isStreaming.value = true

        messages.value.push({
            id: `local-${Date.now()}`,
            role: 'user',
            content: message,
        })

        const reply = ref<AssistantMessage>({
            id: `local-${Date.now()}-reply`,
            role: 'assistant',
            content: '',
        })

        messages.value.push(reply.value)

        try {
            const response = await fetch(sendAssistantMessage.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/event-stream',
                    'X-XSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ message }),
            })

            if (response.status === 419) {
                throw new Error(
                    'Your session expired. Reload the page and try again.',
                )
            }

            if (!response.ok || response.body === null) {
                throw new Error(
                    `The assistant responded with ${response.status}.`,
                )
            }

            const reader = response.body
                .pipeThrough(new TextDecoderStream())
                .getReader()

            let buffer = ''

            for (;;) {
                const { done, value } = await reader.read()

                if (done) {
                    break
                }

                buffer += value

                const frames = buffer.split('\n\n')
                buffer = frames.pop() ?? ''

                for (const frame of frames) {
                    const payload = frame.replace(/^data: /, '').trim()

                    if (payload === '' || payload === '[DONE]') {
                        continue
                    }

                    const event = JSON.parse(payload)

                    if (event.type === 'text_delta') {
                        reply.value.content += event.delta
                        await scrollToBottom()
                    }

                    if (event.type === 'error') {
                        throw new Error(
                            event.message ?? 'The assistant could not reply.',
                        )
                    }
                }
            }

            if (reply.value.content === '') {
                throw new Error('The assistant returned an empty reply.')
            }
        } catch (thrown) {
            error.value =
                thrown instanceof Error
                    ? thrown.message
                    : 'The assistant is unreachable.'

            if (reply.value.content === '') {
                messages.value = messages.value.filter(
                    (item) => item.id !== reply.value.id,
                )
            }
        } finally {
            isStreaming.value = false
        }
    }

    const onKeydown = (event: KeyboardEvent): void => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault()
            void send()
        }
    }
</script>

<template>
    <Head title="Assistant" />

    <div class="flex h-full flex-1 flex-col overflow-hidden">
        <div
            ref="transcript"
            data-test="assistant-transcript"
            class="flex-1 overflow-y-auto"
        >
            <div class="mx-auto w-full max-w-3xl space-y-6 px-4 py-6">
                <p
                    v-if="messages.length === 0"
                    data-test="assistant-empty"
                    class="py-16 text-center text-sm text-muted-foreground"
                >
                    Ask about your organization, its clients, or its teams.
                </p>

                <div
                    v-for="message in messages"
                    :key="message.id"
                    :data-test="`assistant-message-${message.role}`"
                    class="flex"
                    :class="
                        message.role === 'user'
                            ? 'justify-end'
                            : 'justify-start'
                    "
                >
                    <div
                        class="text-sm leading-relaxed whitespace-pre-wrap"
                        :class="
                            message.role === 'user'
                                ? 'max-w-[80%] rounded-2xl bg-muted px-4 py-2.5'
                                : 'w-full'
                        "
                    >
                        <template v-if="message.content">{{
                            message.content
                        }}</template>
                        <span
                            v-else-if="isStreaming"
                            data-test="assistant-thinking"
                            class="text-muted-foreground"
                            >Thinking…</span
                        >
                    </div>
                </div>
            </div>
        </div>

        <div class="shrink-0 px-4 pb-4">
            <div class="mx-auto w-full max-w-3xl space-y-2">
                <p
                    v-if="error"
                    data-test="assistant-error"
                    class="text-sm text-destructive"
                >
                    {{ error }}
                </p>

                <form
                    class="rounded-2xl border border-input bg-background shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50"
                    @submit.prevent="send"
                >
                    <Textarea
                        v-model="draft"
                        data-test="assistant-input"
                        rows="1"
                        placeholder="Ask the assistant…"
                        :disabled="isStreaming"
                        class="max-h-40 resize-none border-0 bg-transparent px-4 pt-3 text-sm shadow-none focus-visible:border-0 focus-visible:ring-0 dark:bg-transparent"
                        @keydown="onKeydown"
                    />

                    <div
                        class="flex items-center justify-between pr-3 pb-3 pl-4"
                    >
                        <span class="text-xs text-muted-foreground">
                            Enter to send, Shift + Enter for a new line
                        </span>
                        <Button
                            type="submit"
                            size="sm"
                            data-test="assistant-send"
                            :disabled="isStreaming || draft.trim() === ''"
                        >
                            <Send />
                            Send
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
