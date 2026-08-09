<script setup lang="ts">
    import { Form } from '@inertiajs/vue3'
    import { ref } from 'vue'
    import FormField from '@/components/FormField.vue'
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
    import {
        Select,
        SelectContent,
        SelectItem,
        SelectTrigger,
        SelectValue,
    } from '@/components/ui/select'
    import { store as storeInvitation } from '@/routes/teams/invitations'
    import type { RoleOption, Team } from '@/types'

    type Props = {
        team: Team
        availableRoles: RoleOption[]
        open: boolean
    }

    const props = defineProps<Props>()
    const emit = defineEmits<{
        'update:open': [value: boolean]
    }>()

    const inviteRole = ref('member')
    const formKey = ref(0)

    function handleOpenChange(value: boolean) {
        emit('update:open', value)

        if (!value) {
            inviteRole.value = 'member'
            formKey.value++
        }
    }
</script>

<template>
    <Dialog :open="props.open" @update:open="handleOpenChange">
        <DialogContent>
            <Form
                :key="formKey"
                v-bind="storeInvitation.form(props.team.slug)"
                v-slot="{ errors, processing }"
                @success="emit('update:open', false)"
            >
                <Stack>
                    <DialogHeader>
                        <DialogTitle>Invite a team member</DialogTitle>
                        <DialogDescription>
                            Send an invitation to join this team.
                        </DialogDescription>
                    </DialogHeader>

                    <Stack gap="4">
                        <TextInput
                            id="email"
                            name="email"
                            label="Email address"
                            :error="errors.email"
                            data-test="invite-email"
                            type="email"
                            placeholder="colleague@example.com"
                            required
                        />

                        <FormField label="Role" for="role" :error="errors.role">
                            <Select
                                v-model="inviteRole"
                                name="role"
                                data-test="invite-role"
                            >
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Select a role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="role in props.availableRoles"
                                        :key="role.value"
                                        :value="role.value"
                                    >
                                        {{ role.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>
                    </Stack>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary"> Cancel </Button>
                        </DialogClose>

                        <Button
                            type="submit"
                            data-test="invite-submit"
                            :disabled="processing"
                        >
                            Send invitation
                        </Button>
                    </DialogFooter>
                </Stack>
            </Form>
        </DialogContent>
    </Dialog>
</template>
