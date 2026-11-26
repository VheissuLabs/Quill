<script setup lang="ts">
defineProps<{
    email: string;
}>();
</script>

<template>
    <a
        :href="`mailto:${email}`"
        class="hover:text-foreground underline-offset-2 hover:underline"
        @click.stop
        ><slot>{{ email }}</slot></a
    >
</template>
