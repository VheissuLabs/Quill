<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import DatePicker from '@/components/DatePicker.vue';
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
import { store } from '@/routes/sprints';

type Props = {
    organization: string;
    team: string;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { default: false });
const formKey = ref(0);

const startOfWeek = (): Date => {
    const date = new Date();
    const day = (date.getDay() + 6) % 7;

    date.setDate(date.getDate() - day);

    return date;
};

const toDateInput = (date: Date) => date.toISOString().slice(0, 10);

const defaultStart = toDateInput(startOfWeek());
const defaultEnd = toDateInput(
    new Date(startOfWeek().getTime() + 13 * 24 * 60 * 60 * 1000),
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
                    <DialogTitle>New sprint</DialogTitle>
                    <DialogDescription>
                        A sprint for {{ props.team }}. Issues from any project
                        can be planned into it.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="sprint-name">Name</Label>
                        <Input
                            id="sprint-name"
                            name="name"
                            data-test="create-sprint-name"
                            placeholder="Sprint 12"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="sprint-start">Starts</Label>
                            <DatePicker
                                id="sprint-start"
                                name="starts_at"
                                :default-value="defaultStart"
                                required
                            />
                            <InputError :message="errors.starts_at" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="sprint-end">Ends</Label>
                            <DatePicker
                                id="sprint-end"
                                name="ends_at"
                                :default-value="defaultEnd"
                                required
                            />
                            <InputError :message="errors.ends_at" />
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary"> Cancel </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        data-test="create-sprint-submit"
                        :disabled="processing"
                    >
                        Create sprint
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
