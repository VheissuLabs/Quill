<script setup lang="ts">
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import {
    Bold,
    Code,
    Italic,
    List,
    ListOrdered,
    Quote,
    Strikethrough,
} from '@lucide/vue';
import { watch } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        placeholder?: string;
        autofocus?: boolean;
        class?: string;
    }>(),
    { placeholder: '', autofocus: false, class: '' },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    keydownEnter: [event: KeyboardEvent];
}>();

/**
 * Empty is a paragraph with nothing in it, which is not the same as no content at all, so the
 * value handed back is blanked rather than passed on as markup nobody typed.
 */
const asValue = (html: string) => (html === '<p></p>' ? '' : html);

const editor = useEditor({
    content: props.modelValue,
    autofocus: props.autofocus,
    extensions: [
        StarterKit.configure({
            heading: { levels: [1, 2, 3] },
        }),
    ],
    editorProps: {
        attributes: {
            class: 'rich-text min-h-24 px-3 py-2 focus:outline-none',
        },
    },
    onUpdate: ({ editor }) =>
        emit('update:modelValue', asValue(editor.getHTML())),
});

watch(
    () => props.modelValue,
    (value) => {
        if (editor.value && asValue(editor.value.getHTML()) !== value) {
            editor.value.commands.setContent(value, { emitUpdate: false });
        }
    },
);

const marks = [
    {
        name: 'bold',
        icon: Bold,
        label: 'Bold',
        run: () => editor.value?.chain().focus().toggleBold().run(),
    },
    {
        name: 'italic',
        icon: Italic,
        label: 'Italic',
        run: () => editor.value?.chain().focus().toggleItalic().run(),
    },
    {
        name: 'strike',
        icon: Strikethrough,
        label: 'Strikethrough',
        run: () => editor.value?.chain().focus().toggleStrike().run(),
    },
    {
        name: 'code',
        icon: Code,
        label: 'Code',
        run: () => editor.value?.chain().focus().toggleCode().run(),
    },
    {
        name: 'bulletList',
        icon: List,
        label: 'Bulleted list',
        run: () => editor.value?.chain().focus().toggleBulletList().run(),
    },
    {
        name: 'orderedList',
        icon: ListOrdered,
        label: 'Numbered list',
        run: () => editor.value?.chain().focus().toggleOrderedList().run(),
    },
    {
        name: 'blockquote',
        icon: Quote,
        label: 'Quote',
        run: () => editor.value?.chain().focus().toggleBlockquote().run(),
    },
];

defineExpose({ editor });
</script>

<template>
    <div
        :class="
            cn(
                'border-input focus-within:border-ring focus-within:ring-ring/50 rounded-md border bg-transparent shadow-xs transition-[color,box-shadow] focus-within:ring-[3px]',
                props.class,
            )
        "
    >
        <div class="flex flex-wrap items-center gap-0.5 border-b px-1.5 py-1">
            <button
                v-for="mark in marks"
                :key="mark.name"
                type="button"
                :title="mark.label"
                :aria-label="mark.label"
                :aria-pressed="editor?.isActive(mark.name) ?? false"
                :data-test="`editor-${mark.name}`"
                :class="[
                    'cursor-pointer rounded p-1.5 transition-colors',
                    editor?.isActive(mark.name)
                        ? 'bg-accent text-accent-foreground'
                        : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground',
                ]"
                @click="mark.run()"
            >
                <component :is="mark.icon" class="size-3.5" />
            </button>
        </div>

        <EditorContent
            :editor="editor"
            data-test="rich-text-editor"
            @keydown.enter="emit('keydownEnter', $event)"
        />

        <p
            v-if="placeholder && editor?.isEmpty"
            class="text-muted-foreground pointer-events-none -mt-9 px-3 text-sm"
        >
            {{ placeholder }}
        </p>
    </div>
</template>
