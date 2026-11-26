<script setup lang="ts">
defineProps<{
    phone: string;
}>();
</script>

<template>
    <a
        :href="`tel:${phone.replace(/[^\d+]/g, '')}`"
        class="hover:text-foreground underline-offset-2 hover:underline"
        @click.stop
        ><slot>{{ phone }}</slot></a
    >
</template>
