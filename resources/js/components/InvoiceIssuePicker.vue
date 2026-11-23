<script setup lang="ts">
import { Link, useHttp } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ExternalLink, SquareKanban, X } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

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

const open = ref(false);
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

const onInput = (value: string) => {
    void search(value);
};

const pick = (issue: IssueSuggestion) => {
    open.value = false;
    query.value = '';
    results.value = [];
    emit('select', issue);
};
</script>

<template>
    <div class="flex items-center gap-1">
        <Popover v-model:open="open">
            <PopoverTrigger as-child>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="disabled"
                    class="font-normal"
                    :class="reference ? '' : 'text-muted-foreground'"
                    data-test="invoice-line-issue-input"
                >
                    <SquareKanban />
                    {{ reference ?? 'Link issue' }}
                </Button>
            </PopoverTrigger>
            <PopoverContent class="w-80 p-0" align="start">
                <Command>
                    <CommandInput
                        v-model="query"
                        placeholder="Search issues…"
                        @update:model-value="onInput"
                    />
                    <CommandList>
                        <CommandEmpty v-if="query.trim().length < 2">
                            Type to search issues.
                        </CommandEmpty>
                        <CommandEmpty
                            v-else-if="
                                results.length === 0 && !request.processing
                            "
                        >
                            No issues found.
                        </CommandEmpty>
                        <CommandGroup v-if="results.length > 0">
                            <CommandItem
                                v-for="issue in results"
                                :key="issue.id"
                                :value="issue.id"
                                data-test="invoice-line-issue-option"
                                @select="pick(issue)"
                            >
                                <span class="text-muted-foreground shrink-0">
                                    {{ issue.reference }}
                                </span>
                                <span class="truncate">{{ issue.title }}</span>
                            </CommandItem>
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>

        <Button
            v-if="reference && href"
            as-child
            variant="ghost"
            size="icon-sm"
            aria-label="Open issue"
        >
            <Link :href="href"><ExternalLink /></Link>
        </Button>
        <Button
            v-if="reference && !disabled"
            type="button"
            variant="ghost"
            size="icon-sm"
            aria-label="Unlink issue"
            data-test="invoice-line-issue-clear"
            @click="emit('select', null)"
        >
            <X />
        </Button>
    </div>
</template>
