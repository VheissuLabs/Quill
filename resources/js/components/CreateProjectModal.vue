<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store } from '@/routes/projects';
import type { ProjectVisibility, SelectOption } from '@/types';

type Props = {
    organization: string;
    visibilities: SelectOption[];
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { default: false });
const formKey = ref(0);
const visibility = ref<ProjectVisibility>('private');

function handleOpenChange(value: boolean) {
    open.value = value;

    if (!value) {
        visibility.value = 'private';
        formKey.value++;
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
                v-bind="store.form(props.organization)"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <DialogHeader>
                    <DialogTitle>Create a new project</DialogTitle>
                    <DialogDescription>
                        Every project gets a board where feedback are requested
                        and voted on. You can pick a client and restrict the
                        board to teams afterwards.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="name">Project name</Label>
                        <Input
                            id="name"
                            name="name"
                            data-test="create-project-name"
                            placeholder="GitDifferent"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="visibility">Board visibility</Label>
                        <Select
                            v-model="visibility"
                            name="visibility"
                            data-test="create-project-visibility"
                        >
                            <SelectTrigger id="visibility" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in props.visibilities"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-muted-foreground text-sm">
                            {{
                                visibility === 'public'
                                    ? 'Anyone can view the board. Signed in users can request feedback and vote.'
                                    : 'Only members of this organization can view the board.'
                            }}
                        </p>
                        <InputError :message="errors.visibility" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary"> Cancel </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        data-test="create-project-submit"
                        :disabled="processing"
                    >
                        Create project
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
