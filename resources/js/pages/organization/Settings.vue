<script setup lang="ts">
import { Form, Head, router, useForm } from '@inertiajs/vue3';
import { ChevronDown, Mail, Pencil, Trash2, UserPlus, X } from '@lucide/vue';
import { useLocalStorage } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import CancelInvitationModal from '@/components/CancelInvitationModal.vue';
import DeleteOrganizationModal from '@/components/DeleteOrganizationModal.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import InviteMemberModal from '@/components/InviteMemberModal.vue';
import RemoveMemberModal from '@/components/RemoveMemberModal.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/composables/useInitials';
import { edit, update } from '@/routes/organizations';
import { update as updateModules } from '@/routes/organizations/modules';
import {
    destroy as destroyLogo,
    store as storeLogo,
} from '@/routes/organizations/logo';
import { update as updateMember } from '@/routes/organizations/members';
import type {
    RoleOption,
    Organization,
    OrganizationInvitation,
    OrganizationMember,
    OrganizationPermissions,
} from '@/types';

type Props = {
    organization: Organization;
    members: OrganizationMember[];
    invitations: OrganizationInvitation[];
    permissions: OrganizationPermissions;
    availableRoles: RoleOption[];
    memberRoles: RoleOption[];
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { organization: Organization }) => ({
        breadcrumbs: [
            {
                title: 'Settings',
                href: edit(props.organization.slug),
            },
        ],
    }),
});

const { getInitials } = useInitials();

const inviteDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const removeMemberDialogOpen = ref(false);
const memberToRemove = ref<OrganizationMember | null>(null);
const cancelInvitationDialogOpen = ref(false);
const invitationToCancel = ref<OrganizationInvitation | null>(null);
/**
 * Each section remembers whether it is open in this browser.
 */
const organizationSettingsOpen = useLocalStorage(
    'quill.organization-settings.open',
    true,
);
const modulesOpen = useLocalStorage('quill.organization-modules.open', true);
const membersOpen = useLocalStorage('quill.organization-members.open', true);
const invitationsOpen = useLocalStorage(
    'quill.organization-invitations.open',
    true,
);
const deleteOrganizationOpen = useLocalStorage(
    'quill.organization-delete.open',
    false,
);
const moduleValues = (modules: Organization['modules']) => ({
    feedback_enabled: modules.feedback.enabled,
    feedback_boards_enabled: modules.feedback.capabilities.boards,
    feedback_voting_enabled: modules.feedback.capabilities.voting,
    development_enabled: modules.development.enabled,
    development_issues_enabled: modules.development.capabilities.issues,
    development_sprints_enabled: modules.development.capabilities.sprints,
    development_workflow_enabled: modules.development.capabilities.workflow,
    teams_enabled: modules.teams.enabled,
    billing_enabled: modules.billing.enabled,
    billing_invoices_enabled: modules.billing.capabilities.invoices,
});

const modulesForm = useForm(moduleValues(props.organization.modules));

/**
 * Enabling a module re-enables its capabilities on the server, so take the
 * saved settings back from the props rather than trusting the local form.
 */
watch(
    () => props.organization.modules,
    (modules) => Object.assign(modulesForm, moduleValues(modules)),
);

const logoForm = useForm<{ logo: File | null }>({
    logo: null,
});

const uploadLogo = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    if (!file) {
        return;
    }

    logoForm.logo = file;

    logoForm.post(storeLogo.url(props.organization.slug), {
        preserveScroll: true,
        onFinish: () => {
            input.value = '';
            logoForm.logo = null;
        },
    });
};

const pageTitle = computed(() =>
    props.permissions.canUpdateOrganization
        ? `Edit ${props.organization.name}`
        : `View ${props.organization.name}`,
);

const updateMemberRole = (member: OrganizationMember, roleId: number) => {
    router.visit(updateMember([props.organization.slug, member.id]), {
        data: { role_id: roleId },
        preserveScroll: true,
    });
};

const confirmRemoveMember = (member: OrganizationMember) => {
    memberToRemove.value = member;
    removeMemberDialogOpen.value = true;
};

const confirmCancelInvitation = (invitation: OrganizationInvitation) => {
    invitationToCancel.value = invitation;
    cancelInvitationDialogOpen.value = true;
};

type ModuleField =
    | 'feedback_enabled'
    | 'feedback_boards_enabled'
    | 'feedback_voting_enabled'
    | 'development_enabled'
    | 'development_issues_enabled'
    | 'development_sprints_enabled'
    | 'development_workflow_enabled'
    | 'teams_enabled'
    | 'billing_enabled'
    | 'billing_invoices_enabled';

const updateModule = (field: ModuleField, value: boolean) => {
    const previousValue = modulesForm[field];
    modulesForm[field] = value;

    modulesForm.patch(updateModules.url(props.organization.slug), {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({
                only: ['currentOrganization'],
            });
        },
        onError: () => {
            modulesForm[field] = previousValue;
        },
    });
};
</script>

<template>
    <Head :title="pageTitle" />

    <h1 class="sr-only">{{ pageTitle }}</h1>

    <div class="flex flex-col gap-10 p-6">
        <!-- Organization Name Section -->
        <div v-if="permissions.canUpdateOrganization" class="space-y-10">
            <Collapsible
                v-model:open="organizationSettingsOpen"
                class="space-y-6"
            >
                <div class="flex items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Organization settings"
                        description="Update your organization name and settings"
                    />
                    <CollapsibleTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            data-test="organization-settings-toggle"
                            :aria-label="
                                organizationSettingsOpen
                                    ? 'Collapse organization settings'
                                    : 'Expand organization settings'
                            "
                        >
                            <ChevronDown
                                class="transition-transform"
                                :class="
                                    organizationSettingsOpen && 'rotate-180'
                                "
                            />
                        </Button>
                    </CollapsibleTrigger>
                </div>

                <CollapsibleContent class="space-y-6">
                    <Form
                        v-bind="update.form(organization.slug)"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="name">Organization name</Label>
                            <Input
                                id="name"
                                name="name"
                                data-test="organization-name-input"
                                :default-value="organization.name"
                                required
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="logo">Portal logo</Label>
                            <p class="text-muted-foreground text-sm">
                                Shown in the header of your public boards
                                instead of the {{ $page.props.name }} logo. PNG,
                                JPG or WebP up to 2 MB.
                            </p>
                            <div class="flex flex-wrap items-center gap-4">
                                <img
                                    v-if="organization.logoUrl"
                                    :src="organization.logoUrl"
                                    :alt="`${organization.name} logo`"
                                    class="size-14 rounded-md border object-contain"
                                    data-test="organization-logo-preview"
                                />
                                <div
                                    v-else
                                    class="bg-muted text-muted-foreground flex size-14 items-center justify-center rounded-md border text-xs"
                                >
                                    None
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <input
                                        id="logo"
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        class="sr-only"
                                        data-test="organization-logo-input"
                                        @change="uploadLogo"
                                    />
                                    <Button
                                        variant="outline"
                                        as-child
                                        :disabled="logoForm.processing"
                                    >
                                        <label
                                            for="logo"
                                            class="cursor-pointer"
                                        >
                                            {{
                                                organization.logoUrl
                                                    ? 'Replace'
                                                    : 'Upload logo'
                                            }}
                                        </label>
                                    </Button>
                                    <Button
                                        v-if="organization.logoUrl"
                                        variant="ghost"
                                        type="button"
                                        data-test="organization-logo-remove"
                                        @click="
                                            router.delete(
                                                destroyLogo(organization.slug),
                                                { preserveScroll: true },
                                            )
                                        "
                                    >
                                        Remove
                                    </Button>
                                </div>
                            </div>
                            <InputError :message="logoForm.errors.logo" />
                        </div>

                        <div class="flex items-center gap-4">
                            <Button
                                type="submit"
                                data-test="organization-save-button"
                                :disabled="processing"
                            >
                                Save
                            </Button>
                        </div>
                    </Form>
                </CollapsibleContent>
            </Collapsible>

            <Collapsible
                v-if="organization.isOwner"
                v-model:open="modulesOpen"
                class="space-y-6"
            >
                <div class="flex items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Product modules"
                        description="Changes save immediately. Existing data is kept and can be restored later."
                    />
                    <CollapsibleTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            data-test="organization-modules-toggle"
                            :aria-label="
                                modulesOpen
                                    ? 'Collapse product modules'
                                    : 'Expand product modules'
                            "
                        >
                            <ChevronDown
                                class="transition-transform"
                                :class="modulesOpen && 'rotate-180'"
                            />
                        </Button>
                    </CollapsibleTrigger>
                </div>

                <CollapsibleContent>
                    <div
                        class="space-y-6"
                        data-test="organization-modules-form"
                    >
                        <div class="space-y-6 rounded-lg border p-4">
                            <div class="space-y-3">
                                <label
                                    class="flex cursor-pointer items-start justify-between gap-4"
                                >
                                    <span class="min-w-0">
                                        <span class="block font-medium">
                                            Feedback
                                        </span>
                                        <span
                                            class="text-muted-foreground mt-1 block text-sm"
                                        >
                                            Let people request and vote on
                                            ideas.
                                        </span>
                                    </span>
                                    <Switch
                                        id="feedback-voting-enabled"
                                        data-test="module-feedback-voting"
                                        class="mt-1 shrink-0"
                                        :model-value="
                                            modulesForm.feedback_enabled
                                        "
                                        :disabled="modulesForm.processing"
                                        @update:model-value="
                                            updateModule(
                                                'feedback_enabled',
                                                $event === true,
                                            )
                                        "
                                    />
                                </label>
                                <div class="ml-4 space-y-3 border-l pl-4">
                                    <label
                                        class="flex cursor-pointer items-start justify-between gap-4"
                                    >
                                        <span class="min-w-0">
                                            <span class="block font-medium">
                                                Boards
                                            </span>
                                            <span
                                                class="text-muted-foreground mt-1 block text-sm"
                                            >
                                                Public, private, and
                                                team-restricted boards.
                                            </span>
                                        </span>
                                        <Switch
                                            id="feedback-voting-boards-enabled"
                                            data-test="module-feedback-voting-boards"
                                            class="mt-1 shrink-0"
                                            :model-value="
                                                modulesForm.feedback_boards_enabled
                                            "
                                            :disabled="
                                                modulesForm.processing ||
                                                !modulesForm.feedback_enabled
                                            "
                                            @update:model-value="
                                                updateModule(
                                                    'feedback_boards_enabled',
                                                    $event === true,
                                                )
                                            "
                                        />
                                    </label>
                                    <label
                                        class="flex cursor-pointer items-start justify-between gap-4"
                                    >
                                        <span class="min-w-0">
                                            <span class="block font-medium">
                                                Voting
                                            </span>
                                            <span
                                                class="text-muted-foreground mt-1 block text-sm"
                                            >
                                                Let people vote for requests.
                                            </span>
                                        </span>
                                        <Switch
                                            id="feedback-voting-voting-enabled"
                                            data-test="module-feedback-voting-voting"
                                            class="mt-1 shrink-0"
                                            :model-value="
                                                modulesForm.feedback_voting_enabled
                                            "
                                            :disabled="
                                                modulesForm.processing ||
                                                !modulesForm.feedback_enabled
                                            "
                                            @update:model-value="
                                                updateModule(
                                                    'feedback_voting_enabled',
                                                    $event === true,
                                                )
                                            "
                                        />
                                    </label>
                                </div>
                            </div>

                            <div class="space-y-3 border-t pt-6">
                                <label
                                    class="flex cursor-pointer items-start justify-between gap-4"
                                >
                                    <span class="min-w-0">
                                        <span class="block font-medium">
                                            Development
                                        </span>
                                        <span
                                            class="text-muted-foreground mt-1 block text-sm"
                                        >
                                            Issues, sprints, and workflow tools.
                                        </span>
                                    </span>
                                    <Switch
                                        id="development-enabled"
                                        data-test="module-development"
                                        class="mt-1 shrink-0"
                                        :model-value="
                                            modulesForm.development_enabled
                                        "
                                        :disabled="modulesForm.processing"
                                        @update:model-value="
                                            updateModule(
                                                'development_enabled',
                                                $event === true,
                                            )
                                        "
                                    />
                                </label>
                                <div class="ml-4 space-y-3 border-l pl-4">
                                    <label
                                        class="flex cursor-pointer items-start justify-between gap-4"
                                    >
                                        <span class="min-w-0">
                                            <span class="block font-medium"
                                                >Issues</span
                                            >
                                            <span
                                                class="text-muted-foreground mt-1 block text-sm"
                                            >
                                                Track work that needs to be
                                                done.
                                            </span>
                                        </span>
                                        <Switch
                                            id="development-issues-enabled"
                                            data-test="module-development-issues"
                                            class="mt-1 shrink-0"
                                            :model-value="
                                                modulesForm.development_issues_enabled
                                            "
                                            :disabled="
                                                modulesForm.processing ||
                                                !modulesForm.development_enabled
                                            "
                                            @update:model-value="
                                                updateModule(
                                                    'development_issues_enabled',
                                                    $event === true,
                                                )
                                            "
                                        />
                                    </label>
                                    <label
                                        class="flex cursor-pointer items-start justify-between gap-4"
                                    >
                                        <span class="min-w-0">
                                            <span class="block font-medium"
                                                >Sprints</span
                                            >
                                            <span
                                                class="text-muted-foreground mt-1 block text-sm"
                                            >
                                                Plan issues into time-boxed
                                                cycles.
                                            </span>
                                        </span>
                                        <Switch
                                            id="development-sprints-enabled"
                                            data-test="module-development-sprints"
                                            class="mt-1 shrink-0"
                                            :model-value="
                                                modulesForm.development_sprints_enabled
                                            "
                                            :disabled="
                                                modulesForm.processing ||
                                                !modulesForm.development_enabled
                                            "
                                            @update:model-value="
                                                updateModule(
                                                    'development_sprints_enabled',
                                                    $event === true,
                                                )
                                            "
                                        />
                                    </label>
                                    <label
                                        class="flex cursor-pointer items-start justify-between gap-4"
                                    >
                                        <span class="min-w-0">
                                            <span class="block font-medium">
                                                Workflow
                                            </span>
                                            <span
                                                class="text-muted-foreground mt-1 block text-sm"
                                            >
                                                Customize issue board columns.
                                            </span>
                                        </span>
                                        <Switch
                                            id="development-workflow-enabled"
                                            data-test="module-development-workflow"
                                            class="mt-1 shrink-0"
                                            :model-value="
                                                modulesForm.development_workflow_enabled
                                            "
                                            :disabled="
                                                modulesForm.processing ||
                                                !modulesForm.development_enabled
                                            "
                                            @update:model-value="
                                                updateModule(
                                                    'development_workflow_enabled',
                                                    $event === true,
                                                )
                                            "
                                        />
                                    </label>
                                </div>
                            </div>

                            <div class="space-y-3 border-t pt-6">
                                <label
                                    class="flex cursor-pointer items-start justify-between gap-4"
                                >
                                    <span class="min-w-0">
                                        <span class="block font-medium">
                                            Teams
                                        </span>
                                        <span
                                            class="text-muted-foreground mt-1 block text-sm"
                                        >
                                            Departments that run their own
                                            boards and sprints. Turning this off
                                            moves their work back to the
                                            organization.
                                        </span>
                                    </span>
                                    <Switch
                                        id="teams-enabled"
                                        data-test="module-teams"
                                        class="mt-1 shrink-0"
                                        :model-value="modulesForm.teams_enabled"
                                        :disabled="modulesForm.processing"
                                        @update:model-value="
                                            updateModule(
                                                'teams_enabled',
                                                $event === true,
                                            )
                                        "
                                    />
                                </label>
                            </div>

                            <div class="space-y-3 border-t pt-6">
                                <label
                                    class="flex cursor-pointer items-start justify-between gap-4"
                                >
                                    <span class="min-w-0">
                                        <span class="block font-medium">
                                            Billing
                                        </span>
                                        <span
                                            class="text-muted-foreground mt-1 block text-sm"
                                        >
                                            Invoices and payments.
                                        </span>
                                    </span>
                                    <Switch
                                        id="billing-enabled"
                                        data-test="module-billing"
                                        class="mt-1 shrink-0"
                                        :model-value="
                                            modulesForm.billing_enabled
                                        "
                                        :disabled="modulesForm.processing"
                                        @update:model-value="
                                            updateModule(
                                                'billing_enabled',
                                                $event === true,
                                            )
                                        "
                                    />
                                </label>
                                <div class="ml-4 space-y-3 border-l pl-4">
                                    <label
                                        class="flex cursor-pointer items-start justify-between gap-4"
                                    >
                                        <span class="min-w-0">
                                            <span class="block font-medium"
                                                >Invoices</span
                                            >
                                            <span
                                                class="text-muted-foreground mt-1 block text-sm"
                                            >
                                                Bill clients for completed work.
                                            </span>
                                        </span>
                                        <Switch
                                            id="billing-invoices-enabled"
                                            data-test="module-billing-invoices"
                                            class="mt-1 shrink-0"
                                            :model-value="
                                                modulesForm.billing_invoices_enabled
                                            "
                                            :disabled="
                                                modulesForm.processing ||
                                                !modulesForm.billing_enabled
                                            "
                                            @update:model-value="
                                                updateModule(
                                                    'billing_invoices_enabled',
                                                    $event === true,
                                                )
                                            "
                                        />
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </div>

        <div v-else class="space-y-6">
            <Heading variant="small" :title="organization.name" />
        </div>

        <!-- Members Section -->
        <Collapsible v-model:open="membersOpen" class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <Heading
                    variant="small"
                    title="Organization members"
                    :description="
                        permissions.canCreateInvitation
                            ? 'Manage who belongs to this organization'
                            : ''
                    "
                />

                <div class="flex shrink-0 items-center gap-2">
                    <Button
                        v-if="permissions.canCreateInvitation"
                        data-test="invite-member-button"
                        @click="inviteDialogOpen = true"
                    >
                        <UserPlus /> Invite member
                    </Button>
                    <CollapsibleTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            data-test="organization-members-toggle"
                            :aria-label="
                                membersOpen
                                    ? 'Collapse organization members'
                                    : 'Expand organization members'
                            "
                        >
                            <ChevronDown
                                class="transition-transform"
                                :class="membersOpen && 'rotate-180'"
                            />
                        </Button>
                    </CollapsibleTrigger>
                </div>
            </div>

            <CollapsibleContent class="space-y-3">
                <div
                    v-for="member in members"
                    :key="member.id"
                    data-test="member-row"
                    class="flex flex-wrap items-center gap-4 rounded-lg border p-4"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-4">
                        <Avatar class="h-10 w-10 shrink-0">
                            <AvatarImage
                                v-if="member.avatar"
                                :src="member.avatar"
                                :alt="member.name"
                            />
                            <AvatarFallback>{{
                                getInitials(member.name)
                            }}</AvatarFallback>
                        </Avatar>
                        <div class="min-w-0">
                            <div
                                class="truncate font-medium"
                                :title="member.name"
                            >
                                {{ member.name }}
                            </div>
                            <div
                                class="text-muted-foreground truncate text-sm"
                                :title="member.email"
                            >
                                {{ member.email }}
                            </div>
                        </div>
                    </div>

                    <div class="ml-auto flex shrink-0 items-center gap-2">
                        <DropdownMenu v-if="member.canUpdate">
                            <DropdownMenuTrigger as-child>
                                <Button
                                    data-test="member-role-trigger"
                                    variant="outline"
                                    size="sm"
                                >
                                    {{ member.role }}
                                    <ChevronDown
                                        class="ml-2 h-4 w-4 opacity-50"
                                    />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent>
                                <DropdownMenuItem
                                    v-for="role in memberRoles"
                                    :key="role.value"
                                    data-test="member-role-option"
                                    @click="
                                        updateMemberRole(member, role.value)
                                    "
                                >
                                    {{ role.label }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <Badge v-else variant="secondary">
                            {{ member.role }}
                        </Badge>

                        <TooltipProvider v-if="member.canRemove">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        data-test="member-remove-button"
                                        variant="ghost"
                                        size="sm"
                                        @click="confirmRemoveMember(member)"
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Remove member</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                </div>
            </CollapsibleContent>
        </Collapsible>

        <!-- Pending Invitations Section -->
        <Collapsible
            v-if="invitations.length > 0"
            v-model:open="invitationsOpen"
            class="space-y-6"
        >
            <div class="flex items-start justify-between gap-4">
                <Heading
                    variant="small"
                    title="Pending invitations"
                    description="Invitations that haven't been accepted yet"
                />
                <CollapsibleTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        data-test="pending-invitations-toggle"
                        :aria-label="
                            invitationsOpen
                                ? 'Collapse pending invitations'
                                : 'Expand pending invitations'
                        "
                    >
                        <ChevronDown
                            class="transition-transform"
                            :class="invitationsOpen && 'rotate-180'"
                        />
                    </Button>
                </CollapsibleTrigger>
            </div>

            <CollapsibleContent class="space-y-3">
                <div
                    v-for="invitation in invitations"
                    :key="invitation.code"
                    data-test="invitation-row"
                    class="flex flex-wrap items-center gap-4 rounded-lg border p-4"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-4">
                        <div
                            class="bg-muted flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                        >
                            <Mail class="text-muted-foreground h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <div
                                class="truncate font-medium"
                                :title="invitation.email"
                            >
                                {{ invitation.email }}
                            </div>
                            <div class="text-muted-foreground truncate text-sm">
                                {{ invitation.role }}
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="permissions.canCancelInvitation"
                        class="ml-auto shrink-0"
                    >
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        data-test="invitation-cancel-button"
                                        variant="ghost"
                                        size="sm"
                                        @click="
                                            confirmCancelInvitation(invitation)
                                        "
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Cancel invitation</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                </div>
            </CollapsibleContent>
        </Collapsible>

        <!-- Danger Zone -->
        <Collapsible
            v-if="permissions.canDeleteOrganization"
            v-model:open="deleteOrganizationOpen"
            class="space-y-6"
        >
            <div class="flex items-start justify-between gap-4">
                <Heading
                    variant="small"
                    title="Delete organization"
                    description="Permanently delete your organization"
                />
                <CollapsibleTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        data-test="delete-organization-toggle"
                        :aria-label="
                            deleteOrganizationOpen
                                ? 'Collapse delete organization'
                                : 'Expand delete organization'
                        "
                    >
                        <ChevronDown
                            class="transition-transform"
                            :class="deleteOrganizationOpen && 'rotate-180'"
                        />
                    </Button>
                </CollapsibleTrigger>
            </div>

            <CollapsibleContent>
                <div
                    class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
                >
                    <div
                        class="relative space-y-0.5 text-red-600 dark:text-red-100"
                    >
                        <p class="font-medium">Warning</p>
                        <p class="text-sm">
                            Please proceed with caution, this cannot be undone.
                        </p>
                    </div>
                    <Button
                        data-test="delete-organization-button"
                        variant="destructive"
                        @click="deleteDialogOpen = true"
                        >Delete organization</Button
                    >
                </div>
            </CollapsibleContent>
        </Collapsible>
    </div>

    <InviteMemberModal
        v-if="permissions.canCreateInvitation"
        :organization="organization"
        :available-roles="availableRoles"
        :open="inviteDialogOpen"
        @update:open="inviteDialogOpen = $event"
    />

    <RemoveMemberModal
        :organization="organization"
        :member="memberToRemove"
        :open="removeMemberDialogOpen"
        @update:open="removeMemberDialogOpen = $event"
    />

    <CancelInvitationModal
        :organization="organization"
        :invitation="invitationToCancel"
        :open="cancelInvitationDialogOpen"
        @update:open="cancelInvitationDialogOpen = $event"
    />

    <DeleteOrganizationModal
        v-if="permissions.canDeleteOrganization"
        :organization="organization"
        :open="deleteDialogOpen"
        @update:open="deleteDialogOpen = $event"
    />
</template>
