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
import RichTextEditor from '@/components/RichTextEditor.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store } from '@/routes/portal/feedback';

const props = defineProps<{
    organization: string;
    project?: string;
    projects?: { name: string; slug: string }[];
}>();

const open = defineModel<boolean>('open', { default: false });
const formKey = ref(0);
const chosenProject = ref(props.project ?? '');

const type = ref<'feature' | 'bug'>('feature');
const description = ref('');

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
        description.value = '';
        type.value = 'feature';
        chosenProject.value = props.project ?? '';
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogTrigger v-if="$slots.default" as-child>
            <slot />
        </DialogTrigger>
        <DialogContent>
            <Form
                :key="formKey"
                v-bind="store.form([organization, chosenProject || '_'])"
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

                    <div v-if="!project && projects" class="grid gap-2">
                        <Label for="feedback-project">Project</Label>
                        <Select v-model="chosenProject">
                            <SelectTrigger id="feedback-project" class="w-full">
                                <SelectValue placeholder="Choose a project" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in projects"
                                    :key="option.slug"
                                    :value="option.slug"
                                >
                                    {{ option.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

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
                        <input
                            type="hidden"
                            name="description"
                            :value="description"
                        />
                        <RichTextEditor
                            v-model="description"
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
                        :disabled="processing || !chosenProject"
                    >
                        {{ copy.submit }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
