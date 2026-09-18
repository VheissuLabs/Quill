<script setup lang="ts">
import { computed } from 'vue';
import type { CommentMention } from '@/types';

const props = defineProps<{
    body: string;
    mentions: CommentMention[];
}>();

const escape = (value: string) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/**
 * The body is markup already sanitised by the server, so it is rendered as it was written. Names
 * are wrapped after the fact, and only in the text between tags, so a name that happens to appear
 * inside an attribute is left alone.
 */
const html = computed(() => {
    if (props.mentions.length === 0) {
        return props.body;
    }

    const names = props.mentions
        .map((mention) => escape(mention.name))
        .sort((a, b) => b.length - a.length)
        .join('|');

    const pattern = new RegExp(`@(?:${names})`, 'g');

    return props.body
        .split(/(<[^>]*>)/)
        .map((part) =>
            part.startsWith('<')
                ? part
                : part.replace(
                      pattern,
                      (name) =>
                          `<span class="bg-muted rounded px-1 py-0.5 font-medium">${name}</span>`,
                  ),
        )
        .join('');
});
</script>

<template>
    <div
        class="prose prose-sm dark:prose-invert max-w-none text-sm leading-relaxed"
        data-test="comment-body-rendered"
        v-html="html"
    />
</template>
