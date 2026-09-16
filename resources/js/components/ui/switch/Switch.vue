<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import type { SwitchRootEmits, SwitchRootProps } from 'reka-ui';
import { reactiveOmit } from '@vueuse/core';
import {
    SwitchRoot,
    SwitchThumb,
    useForwardPropsEmits,
} from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps<
    SwitchRootProps & { class?: HTMLAttributes['class'] }
>();
const emits = defineEmits<SwitchRootEmits>();

const delegatedProps = reactiveOmit(props, 'class');
const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <SwitchRoot
        v-bind="forwarded"
        :class="
            cn(
                'peer inline-flex h-5 w-9 shrink-0 items-center rounded-full border-2 border-transparent shadow-xs transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=unchecked]:bg-input',
                props.class,
            )
        "
    >
        <SwitchThumb
            class="pointer-events-none block size-4 rounded-full bg-background shadow-lg ring-0 transition-transform data-[state=checked]:translate-x-4 data-[state=unchecked]:translate-x-0"
        />
    </SwitchRoot>
</template>
