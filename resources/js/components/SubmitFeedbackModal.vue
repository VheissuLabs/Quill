<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Bug, Lightbulb } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/portal/feedback';

defineProps<{
    organization: string;
    project: string;
}>();

const open = ref(false);
const formKey = ref(0);

/**
 * The prompt is the point of asking. An idea wants to know what problem it solves; a bug is
 * worth nothing without what happened and what was expected instead.
 */
const type = ref<'feature' | 'bug'>('feature');

const choices = [
    { value: 'feature', label: 'Idea', icon: Lightbulb },
    { value: 'bug', label: 'Something is broken', icon: Bug },
] as const;

const copy = computed(() =>
    type.value === 'bug'
        ? {
              description: 'Tell us what went wrong so it can be reproduced.',
              title: 'What is broken, in a few words',
              details: 'What happened, and what did you expect instead?',
              submit: 'Submit report',
          }
        : {
              description:
                  'Describe what you need and why. Others can vote for it.',
              title: 'Short summary',
              details: 'What problem would this solve for you?',
              submit: 'Submit request',
          },
);

function handleOpenChange(value: boolean) {
    open.value = value;

    if (!value) {
        formKey.value++;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent>
            <Form
                :key="formKey"
                v-bind="store.form([organization, project])"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <DialogHeader>
                    <DialogTitle>Submit feedback</DialogTitle>
                    <DialogDescription>
                        {{ copy.description }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <input type="hidden" name="type" :value="type" />

                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="choice in choices"
                            :key="choice.value"
                            type="button"
                            :data-test="`feedback-type-${choice.value}`"
                            :aria-pressed="type === choice.value"
                            :class="[
                                'flex cursor-pointer items-center justify-center gap-2 rounded-md border p-3 text-sm transition-colors',
                                type === choice.value
                                    ? 'border-foreground bg-accent font-medium'
                                    : 'text-muted-foreground hover:bg-accent/50',
                            ]"
                            @click="type = choice.value"
                        >
                            <component :is="choice.icon" class="size-4" />
                            {{ choice.label }}
                        </button>
                    </div>

                    <div class="grid gap-2">
                        <Label for="title">Title</Label>
                        <Input
                            id="title"
                            name="title"
                            data-test="feedback-title"
                            :placeholder="copy.title"
                            required
                        />
                        <InputError :message="errors.title" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Details</Label>
                        <Textarea
                            id="description"
                            name="description"
                            data-test="feedback-description"
                            :placeholder="copy.details"
                        />
                        <InputError :message="errors.description" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary"> Cancel </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        data-test="feedback-submit"
                        :disabled="processing"
                    >
                        {{ copy.submit }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
