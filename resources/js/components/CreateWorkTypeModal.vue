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
import { store } from '@/routes/organizations/work-types';

defineProps<{
    organization: string;
    colors: string[];
}>();

const open = defineModel<boolean>('open', { default: false });
const formKey = ref(0);

function handleOpenChange(value: boolean) {
    open.value = value;

    if (!value) {
        formKey.value++;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent>
            <Form
                :key="formKey"
                v-bind="store.form(organization)"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <DialogHeader>
                    <DialogTitle>New work type</DialogTitle>
                    <DialogDescription>
                        A kind of work that time and invoice lines are filed
                        under.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 sm:grid-cols-[1fr_10rem]">
                    <div class="grid gap-2">
                        <Label for="work-type-name">Name</Label>
                        <Input
                            id="work-type-name"
                            name="name"
                            placeholder="Development"
                            data-test="palette-work-type-name"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="work-type-color">Color</Label>
                        <Select name="color" default-value="gray">
                            <SelectTrigger id="work-type-color" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="color in colors"
                                    :key="color"
                                    :value="color"
                                    class="capitalize"
                                >
                                    {{ color }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.color" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        Add work type
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
