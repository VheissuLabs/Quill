<script setup lang="ts">
    import { Form } from '@inertiajs/vue3'
    import { ref } from 'vue'
    import InputError from '@/components/InputError.vue'
    import Stack from '@/components/Stack.vue'
    import TextInput from '@/components/TextInput.vue'
    import { Button } from '@/components/ui/button'
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog'
    import { Label } from '@/components/ui/label'
    import {
        Select,
        SelectContent,
        SelectItem,
        SelectTrigger,
        SelectValue,
    } from '@/components/ui/select'
    import { Textarea } from '@/components/ui/textarea'
    import { store as storeIssue } from '@/routes/projects/issues'
    import type { IssueType } from '@/types'

    type Props = {
        project: string
        issueTypes: IssueType[]
        open: boolean
    }

    const props = defineProps<Props>()
    const emit = defineEmits<{
        'update:open': [value: boolean]
    }>()

    const formKey = ref(0)

    function handleOpenChange(value: boolean) {
        emit('update:open', value)

        if (!value) {
            formKey.value++
        }
    }
</script>

<template>
    <Dialog :open="props.open" @update:open="handleOpenChange">
        <DialogContent>
            <Form
                :key="formKey"
                v-bind="storeIssue.form(props.project)"
                v-slot="{ errors, processing }"
                @success="emit('update:open', false)"
            >
                <Stack>
                    <DialogHeader>
                        <DialogTitle>File an issue</DialogTitle>
                        <DialogDescription>
                            Describe the problem so it can be scheduled and
                            worked.
                        </DialogDescription>
                    </DialogHeader>

                    <Stack gap="4">
                        <div class="grid gap-2">
                            <Label for="issue_type_id">Type</Label>
                            <Select name="issue_type_id" required>
                                <SelectTrigger
                                    id="issue_type_id"
                                    data-test="issue-type"
                                    class="w-full"
                                >
                                    <SelectValue placeholder="Select a type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="issueType in props.issueTypes"
                                        :key="issueType.id"
                                        :value="issueType.id"
                                    >
                                        {{ issueType.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.issue_type_id" />
                        </div>

                        <TextInput
                            id="title"
                            name="title"
                            label="Title"
                            :error="errors.title"
                            data-test="issue-title"
                            required
                        />

                        <div class="grid gap-2">
                            <Label for="description">Description</Label>
                            <Textarea
                                id="description"
                                name="description"
                                data-test="issue-description"
                                required
                            />
                            <InputError :message="errors.description" />
                        </div>
                    </Stack>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary"> Cancel </Button>
                        </DialogClose>

                        <Button
                            type="submit"
                            data-test="issue-submit"
                            :disabled="processing"
                        >
                            File issue
                        </Button>
                    </DialogFooter>
                </Stack>
            </Form>
        </DialogContent>
    </Dialog>
</template>
