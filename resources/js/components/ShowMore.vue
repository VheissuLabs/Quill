<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useIntersectionObserver } from '@vueuse/core';
import { ref, useTemplateRef } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    prop: string;
    shown: number;
    hasMore: boolean;
}>();

const trigger = useTemplateRef<HTMLElement>('trigger');
const loading = ref(false);

const showMore = () => {
    if (loading.value || !props.hasMore) {
        return;
    }

    loading.value = true;

    router.reload({
        only: [props.prop],
        data: { show: props.shown * 2 },
        showProgress: false,
        onFinish: () => (loading.value = false),
    });
};

useIntersectionObserver(trigger, ([entry]) => {
    if (entry?.isIntersecting) {
        showMore();
    }
});
</script>

<template>
    <div v-if="hasMore" ref="trigger" class="flex justify-center">
        <Button
            variant="ghost"
            size="sm"
            :disabled="loading"
            data-test="show-more"
            @click="showMore"
        >
            {{ loading ? 'Loading…' : 'Show more' }}
        </Button>
    </div>
</template>
