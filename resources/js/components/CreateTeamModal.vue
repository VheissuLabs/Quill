<script setup lang="ts">
    import { Form } from '@inertiajs/vue3'
    import { ref } from 'vue'
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
        DialogTrigger,
    } from '@/components/ui/dialog'
    import { store } from '@/routes/teams'

    const open = ref(false)
    const formKey = ref(0)

    function handleOpenChange(value: boolean) {
        open.value = value

        if (!value) {
            formKey.value++
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
                v-bind="store.form()"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <DialogHeader>
                    <DialogTitle>Create a new team</DialogTitle>
                    <DialogDescription>
                        Create a new team to collaborate with others.
                    </DialogDescription>
                </DialogHeader>

                <TextInput
                    id="name"
                    name="name"
                    label="Team name"
                    :error="errors.name"
                    data-test="create-team-name"
                    placeholder="My team"
                    required
                />

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary"> Cancel </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        data-test="create-team-submit"
                        :disabled="processing"
                    >
                        Create team
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
