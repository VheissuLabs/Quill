<script setup lang="ts">
import { useForm, useHttp } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, nextTick, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import RichTextEditor from '@/components/RichTextEditor.vue';
import type { CommentCommand, CommentMention, SelectOption } from '@/types';

type Props = {
    action: string;
    mentionable: CommentMention[];
    issueLookup?: string;
    commands?: CommentCommand[];
    statuses?: SelectOption[];
    canCommentInternally?: boolean;
    organizationName?: string;
    parentId?: number | null;
    forceInternal?: boolean;
    placeholder?: string;
    submitLabel?: string;
    autofocus?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    issueLookup: '',
    canCommentInternally: false,
    organizationName: '',
    commands: () => [],
    statuses: () => [],
    parentId: null,
    forceInternal: false,
    placeholder: 'Add to the discussion',
    submitLabel: 'Comment',
    autofocus: false,
});

const emit = defineEmits<{
    posted: [];
    cancel: [];
    typing: [];
}>();

const form = useForm({
    body: '',
    internal: props.forceInternal,
    parent_id: props.parentId,
    mentions: [] as number[],
});

const editorRef = ref<InstanceType<typeof RichTextEditor> | null>(null);
const highlighted = ref(0);

type Suggestion = {
    key: string;
    label: string;
    description?: string;
    /** Replace the text from `start` up to the caret with this. */
    start: number;
    insertion: string;
    mention?: CommentMention;
};

/**
 * What the user is in the middle of typing at the caret: a "@name" mention, a "/command"
 * on its own line, or the argument of "/status".
 */
const prompt = ref<
    | { kind: 'mention'; start: number; query: string }
    | { kind: 'command'; start: number; query: string }
    | { kind: 'status'; start: number; query: string }
    | { kind: 'issue'; start: number; query: string }
    | null
>(null);

type IssueSuggestion = {
    id: number;
    reference: string;
    title: string;
    statusLabel: string;
};

const issueResults = ref<IssueSuggestion[]>([]);
const issueRequest = useHttp<Record<string, never>, IssueSuggestion[]>();

/** Commands whose argument is an issue id, taken from what the server offered. */
const issueCommands = computed(() =>
    props.commands
        .filter((command) => command.usage.includes('<issue id>'))
        .map((command) => command.name),
);

const lookUpIssues = useDebounceFn(async (query: string) => {
    if (!props.issueLookup || query.trim().length < 2) {
        issueResults.value = [];

        return;
    }

    issueRequest.cancel();

    try {
        const separator = props.issueLookup.includes('?') ? '&' : '?';

        issueResults.value = await issueRequest.get(
            `${props.issueLookup}${separator}q=${encodeURIComponent(query.trim())}`,
        );
    } catch {
        issueResults.value = [];
    }
}, 200);

/**
 * What has been typed in the paragraph the caret sits in. A command or a mention never spans
 * paragraphs, and inside one the string offsets line up with the editor's own positions, so the
 * suggestion logic below reads exactly as it did when this was a textarea.
 */
const context = () => {
    const editor = editorRef.value?.editor;

    if (!editor) {
        return null;
    }

    const { $from } = editor.state.selection;

    return {
        editor,
        blockStart: $from.start(),
        caret: $from.pos,
        text: $from.parent.textBetween(0, $from.parentOffset, '\n', '\n'),
    };
};

const updatePrompt = () => {
    const here = context();

    if (!here) {
        prompt.value = null;

        return;
    }

    const beforeCaret = here.text;
    const lineStart = 0;
    const line = beforeCaret;
    const mention = /(?:^|\s)@([^@\n]{0,40})$/.exec(beforeCaret);
    const status = /^\/status\s+([^\n]{0,30})$/i.exec(line);
    const command = /^\/(\w{0,20})$/.exec(line);
    const linked = /^\/(\w+)\s+([^\n]{0,60})$/.exec(line);

    highlighted.value = 0;

    if (mention) {
        prompt.value = {
            kind: 'mention',
            start: beforeCaret.lastIndexOf('@'),
            query: mention[1],
        };
    } else if (status && props.commands.some((c) => c.name === 'status')) {
        prompt.value = {
            kind: 'status',
            start: lineStart + line.length - status[1].length,
            query: status[1],
        };
    } else if (
        linked &&
        issueCommands.value.includes(linked[1].toLowerCase())
    ) {
        prompt.value = {
            kind: 'issue',
            start: lineStart + line.length - linked[2].length,
            query: linked[2],
        };
        void lookUpIssues(linked[2]);
    } else if (command && props.commands.length > 0) {
        prompt.value = { kind: 'command', start: lineStart, query: command[1] };
    } else {
        prompt.value = null;
    }
};

const suggestions = computed<Suggestion[]>(() => {
    if (prompt.value === null) {
        return [];
    }

    const { kind, start } = prompt.value;
    const query = prompt.value.query.toLowerCase();

    if (kind === 'mention') {
        return props.mentionable
            .filter((person) => !form.mentions.includes(person.id))
            .filter((person) => person.name.toLowerCase().includes(query))
            .slice(0, 6)
            .map((person) => ({
                key: `mention-${person.id}`,
                label: person.name,
                start,
                insertion: `@${person.name} `,
                mention: person,
            }));
    }

    if (kind === 'status') {
        return props.statuses
            .filter((status) => status.label.toLowerCase().startsWith(query))
            .map((status) => ({
                key: `status-${status.value}`,
                label: status.label,
                start,
                insertion: `${status.label} `,
            }));
    }

    if (kind === 'issue') {
        return issueResults.value.map((issue) => ({
            key: `issue-${issue.id}`,
            label: issue.title,
            description: `${issue.reference} · ${issue.statusLabel}`,
            start,
            insertion: `${issue.id} `,
        }));
    }

    return props.commands
        .filter((command) => command.name.startsWith(query))
        .map((command) => ({
            key: `command-${command.name}`,
            label: command.usage,
            description: command.description,
            start,
            insertion: `/${command.name} `,
        }));
});

const applySuggestion = (suggestion: Suggestion) => {
    const here = context();

    if (!here) {
        return;
    }

    here.editor
        .chain()
        .focus()
        .insertContentAt(
            { from: here.blockStart + suggestion.start, to: here.caret },
            suggestion.insertion,
        )
        .run();

    if (suggestion.mention) {
        form.mentions = [...form.mentions, suggestion.mention.id];
    }

    prompt.value = null;

    void nextTick(() => {
        if (suggestion.insertion === '/status ') {
            updatePrompt();
        }
    });
};

/**
 * Enter submits the comment (Shift+Enter for a new line) unless a suggestion list
 * is open, where the arrow keys move and Enter or Tab picks.
 */
const onKeydown = (event: KeyboardEvent): boolean => {
    if (suggestions.value.length === 0) {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            submit();

            return true;
        }

        return false;
    }

    if (event.key === 'ArrowDown') {
        highlighted.value = (highlighted.value + 1) % suggestions.value.length;

        return true;
    }

    if (event.key === 'ArrowUp') {
        highlighted.value =
            (highlighted.value - 1 + suggestions.value.length) %
            suggestions.value.length;

        return true;
    }

    if (event.key === 'Enter' || event.key === 'Tab') {
        applySuggestion(suggestions.value[highlighted.value]);

        return true;
    }

    if (event.key === 'Escape') {
        prompt.value = null;

        return true;
    }

    return false;
};

/**
 * Only people whose "@Name" is still in the text are submitted as mentions.
 */
const submit = () => {
    if (form.body.trim() === '' || form.processing) {
        return;
    }

    form.transform((data) => ({
        ...data,
        mentions: data.mentions.filter((id) => {
            const person = props.mentionable.find(
                (candidate) => candidate.id === id,
            );

            return (
                person !== undefined && data.body.includes(`@${person.name}`)
            );
        }),
    })).post(props.action, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('posted');
        },
    });
};
</script>

<template>
    <form class="space-y-3" @submit.prevent="submit">
        <div class="relative">
            <Label :for="`comment-${parentId ?? 'new'}`" class="sr-only">
                Comment
            </Label>
            <RichTextEditor
                ref="editorRef"
                v-model="form.body"
                data-test="comment-body"
                :placeholder="placeholder"
                :autofocus="autofocus"
                :on-key="onKeydown"
                :on-caret-move="
                    () => {
                        updatePrompt();
                        emit('typing');
                    }
                "
            />
            <ul
                v-if="suggestions.length > 0"
                data-test="comment-suggestions"
                class="bg-popover text-popover-foreground absolute right-0 left-0 z-20 mt-1 max-h-56 overflow-y-auto rounded-md border p-1 shadow-md"
                role="listbox"
            >
                <li
                    v-for="(suggestion, index) in suggestions"
                    :key="suggestion.key"
                    role="option"
                    :aria-selected="index === highlighted"
                    :class="[
                        'flex cursor-pointer items-baseline gap-2 rounded-sm px-2 py-1.5 text-sm',
                        index === highlighted &&
                            'bg-accent text-accent-foreground',
                    ]"
                    @mousedown.prevent="applySuggestion(suggestion)"
                    @mouseenter="highlighted = index"
                >
                    <span
                        :class="
                            suggestion.description !== undefined &&
                            'font-mono text-xs'
                        "
                    >
                        {{ suggestion.label }}
                    </span>
                    <span
                        v-if="suggestion.description"
                        class="text-muted-foreground truncate text-xs"
                    >
                        {{ suggestion.description }}
                    </span>
                </li>
            </ul>
        </div>
        <InputError :message="form.errors.body" />
        <InputError :message="form.errors.mentions" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <Button
                    type="submit"
                    size="sm"
                    data-test="comment-submit"
                    :disabled="form.processing"
                >
                    {{ submitLabel }}
                </Button>
                <Button
                    v-if="parentId !== null"
                    type="button"
                    size="sm"
                    variant="ghost"
                    @click="emit('cancel')"
                >
                    Cancel
                </Button>
            </div>
            <label
                v-if="canCommentInternally && !forceInternal"
                class="text-muted-foreground flex items-center gap-2 text-sm"
            >
                <Checkbox
                    :model-value="form.internal"
                    data-test="comment-internal"
                    @update:model-value="form.internal = $event === true"
                />
                Internal note, only {{ organizationName }} members see it
            </label>
            <span
                v-else-if="forceInternal"
                class="text-muted-foreground text-xs"
            >
                Replies in an internal thread stay internal.
            </span>
        </div>
    </form>
</template>
