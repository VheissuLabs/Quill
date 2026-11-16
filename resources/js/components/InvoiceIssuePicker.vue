<script setup lang="ts">
import { Link, useHttp } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import { Input } from '@/components/ui/input';

export type IssueSuggestion = {
    id: number;
    reference: string;
    title: string;
    statusLabel: string;
};

type Props = {
    lookup: string;
    reference?: string | null;
    href?: string | null;
    disabled?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    reference: null,
    href: null,
    disabled: false,
});

const emit = defineEmits<{
    select: [issue: IssueSuggestion | null];
}>();

const query = ref('');
const results = ref<IssueSuggestion[]>([]);
const request = useHttp<Record<string, never>, IssueSuggestion[]>();

const search = useDebounceFn(async (term: string) => {
    if (term.trim().length < 2) {
        results.value = [];

        return;
    }

    request.cancel();

    try {
        const separator = props.lookup.includes('?') ? '&' : '?';

        results.value = await request.get(
            `${props.lookup}${separator}q=${encodeURIComponent(term.trim())}`,
        );
    } catch {
        results.value = [];
    }
}, 200);

const onInput = (value: string | number) => {
    query.value = String(value);
    void search(query.value);
};

const pick = (issue: IssueSuggestion) => {
    query.value = '';
    results.value = [];
    emit('select', issue);
};
</script>

<template>
    <div class="relative min-w-40">
        <p v-if="reference && query === ''" class="flex items-center gap-1">
            <Link v-if="href" :href="href" class="text-sm hover:underline">
                {{ reference }}
            </Link>
            <span v-else class="text-sm">{{ reference }}</span>
            <button
                v-if="!disabled"
                type="button"
                class="text-muted-foreground hover:text-foreground text-xs"
                data-test="invoice-line-issue-clear"
                @click="emit('select', null)"
            >
                Clear
            </button>
        </p>
        <Input
            v-if="!reference || query !== ''"
            :model-value="query"
            :disabled="disabled"
            placeholder="Issue"
            aria-label="Issue"
            data-test="invoice-line-issue-input"
            @update:model-value="onInput"
        />
        <ul
            v-if="results.length > 0"
            class="bg-popover absolute z-20 mt-1 max-h-60 w-72 overflow-auto rounded-md border p-1 shadow-md"
        >
            <li v-for="issue in results" :key="issue.id">
                <button
                    type="button"
                    class="hover:bg-accent w-full rounded px-2 py-1.5 text-left text-sm"
                    data-test="invoice-line-issue-option"
                    @click="pick(issue)"
                >
                    <span class="text-muted-foreground">{{
                        issue.reference
                    }}</span>
                    {{ issue.title }}
                </button>
            </li>
        </ul>
    </div>
</template>
