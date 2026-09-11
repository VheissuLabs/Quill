<script setup lang="ts">
import { computed } from 'vue';
import type { CommentMention } from '@/types';

const props = defineProps<{
    body: string;
    mentions: CommentMention[];
}>();

type Segment = { text: string; mention: boolean };

/**
 * Split the body around "@Name" for each mentioned person so names can be highlighted without
 * rendering the body as HTML.
 */
const segments = computed<Segment[]>(() => {
    if (props.mentions.length === 0) {
        return [{ text: props.body, mention: false }];
    }

    const names = props.mentions
        .map((mention) => mention.name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
        .sort((a, b) => b.length - a.length)
        .join('|');
    const pattern = new RegExp(`(@(?:${names}))`, 'g');

    return props.body
        .split(pattern)
        .filter((text) => text !== '')
        .map((text) => ({
            text,
            mention: pattern.test(text) && text.startsWith('@'),
        }));
});
</script>

<template>
    <p class="text-sm leading-relaxed whitespace-pre-line">
        <template v-for="(segment, index) in segments" :key="index">
            <span
                v-if="segment.mention"
                class="bg-muted rounded px-1 py-0.5 font-medium"
                >{{ segment.text }}</span
            >
            <template v-else>{{ segment.text }}</template>
        </template>
    </p>
</template>
