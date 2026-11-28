<script setup lang="ts">
import { router, useHttp, usePage } from '@inertiajs/vue3';
import { useDebounceFn, useEventListener } from '@vueuse/core';
import { VisuallyHidden } from 'reka-ui';
import { computed, ref, useTemplateRef, watch } from 'vue';
import CreateClientModal from '@/components/CreateClientModal.vue';
import CreateContactModal from '@/components/CreateContactModal.vue';
import CreateInvoiceModal from '@/components/CreateInvoiceModal.vue';
import CreateIssueModal from '@/components/CreateIssueModal.vue';
import CreateOrganizationModal from '@/components/CreateOrganizationModal.vue';
import CreateProjectModal from '@/components/CreateProjectModal.vue';
import CreateSprintModal from '@/components/CreateSprintModal.vue';
import CreateTeamModal from '@/components/CreateTeamModal.vue';
import CreateTicketModal from '@/components/CreateTicketModal.vue';
import CreateWorkTypeModal from '@/components/CreateWorkTypeModal.vue';
import InviteMemberModal from '@/components/InviteMemberModal.vue';
import RoleFormModal from '@/components/RoleFormModal.vue';
import SubmitFeedbackModal from '@/components/SubmitFeedbackModal.vue';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useCommandPalette } from '@/composables/useCommandPalette';
import { actionCommands, matches, navigationCommands } from '@/lib/commands';
import {
    options as paletteOptions,
    search as paletteSearch,
} from '@/routes/palette';
import type {
    PaletteActionName,
    PaletteCommand,
    PaletteOptions,
    PaletteResult,
} from '@/types';

const MINIMUM_TERM = 2;

const page = usePage();
const { open, hide, toggle } = useCommandPalette();

const query = ref('');
const results = ref<PaletteResult[]>([]);
const options = ref<PaletteOptions | null>(null);
const activeAction = ref<PaletteActionName | null>(null);
const loadingAction = ref(false);
const pendingSearch = ref(false);
const searchError = ref<string | null>(null);

const searchRequest = useHttp<Record<string, never>, PaletteResult[]>();
const optionsRequest = useHttp<Record<string, never>, PaletteOptions>();

const organization = computed(() => page.props.currentOrganization);

const navigation = computed(() =>
    organization.value
        ? navigationCommands(organization.value).filter((command) =>
              matches(command, query.value),
          )
        : [],
);

const actions = computed(() =>
    organization.value
        ? actionCommands(
              organization.value,
              page.props.permissions ?? null,
          ).filter((command) => matches(command, query.value))
        : [],
);

const searching = computed(
    () => pendingSearch.value || searchRequest.processing,
);

const nothingFound = computed(
    () =>
        navigation.value.length === 0 &&
        actions.value.length === 0 &&
        results.value.length === 0 &&
        !searching.value &&
        searchError.value === null,
);

const runSearch = useDebounceFn(async () => {
    const term = query.value.trim();
    const slug = organization.value?.slug;

    if (!slug || term.length < MINIMUM_TERM) {
        results.value = [];
        pendingSearch.value = false;

        return;
    }

    searchRequest.cancel();

    try {
        results.value = await searchRequest.get(
            paletteSearch(slug, { query: { q: term } }).url,
        );
        searchError.value = null;
    } catch (error) {
        results.value = [];
        searchError.value =
            (error as { response?: { status?: number } })?.response?.status ===
            401
                ? 'Your session expired. Reload the page to sign in again.'
                : 'Search is unavailable right now.';
    } finally {
        pendingSearch.value = false;
    }
}, 250);

const command = useTemplateRef<InstanceType<typeof Command>>('command');

watch(results, () => command.value?.highlightFirstItem(), { flush: 'post' });

watch(query, () => {
    pendingSearch.value = query.value.trim().length >= MINIMUM_TERM;
    void runSearch();
});

watch(open, (isOpen) => {
    if (isOpen) {
        return;
    }

    query.value = '';
    results.value = [];
    pendingSearch.value = false;
    searchError.value = null;
    searchRequest.cancel();
});

const ensureOptions = async (): Promise<void> => {
    const slug = organization.value?.slug;

    if (options.value || !slug) {
        return;
    }

    loadingAction.value = true;

    try {
        options.value = await optionsRequest.get(paletteOptions(slug).url);
    } finally {
        loadingAction.value = false;
    }
};

const run = async (command: PaletteCommand): Promise<void> => {
    if (command.url) {
        hide();
        router.visit(command.url);

        return;
    }

    if (!command.action) {
        return;
    }

    hide();
    await ensureOptions();
    activeAction.value = command.action;
};

const openResult = (result: PaletteResult): void => {
    hide();

    if (result.type === 'document') {
        window.open(result.url, '_blank', 'noopener');

        return;
    }

    router.visit(result.url);
};

const closeAction = (isOpen: boolean): void => {
    if (!isOpen) {
        activeAction.value = null;
    }
};

useEventListener('keydown', (event: KeyboardEvent) => {
    if (event.key === 'k' && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        toggle();
    }
});
</script>

<template>
    <div v-if="organization">
        <Dialog v-model:open="open">
            <DialogContent
                class="overflow-hidden p-0 sm:max-w-xl"
                :show-close-button="false"
            >
                <VisuallyHidden>
                    <DialogTitle>Command palette</DialogTitle>
                    <DialogDescription>
                        Jump to a page or record, or start something new.
                    </DialogDescription>
                </VisuallyHidden>

                <Command ref="command">
                    <CommandInput
                        v-model="query"
                        placeholder="Search issues, projects, contacts…"
                    />

                    <CommandList>
                        <CommandEmpty v-if="nothingFound">
                            Nothing matches “{{ query }}”.
                        </CommandEmpty>

                        <p
                            v-if="searchError"
                            class="text-destructive px-3 py-6 text-center text-sm"
                            data-test="palette-search-error"
                        >
                            {{ searchError }}
                        </p>

                        <CommandGroup v-if="navigation.length" heading="Go to">
                            <CommandItem
                                v-for="command in navigation"
                                :key="command.id"
                                :value="command.id"
                                @select="run(command)"
                            >
                                <component :is="command.icon" />
                                {{ command.title }}
                            </CommandItem>
                        </CommandGroup>

                        <CommandGroup v-if="actions.length" heading="Create">
                            <CommandItem
                                v-for="command in actions"
                                :key="command.id"
                                :value="command.id"
                                @select="run(command)"
                            >
                                <component :is="command.icon" />
                                {{ command.title }}
                            </CommandItem>
                        </CommandGroup>

                        <CommandGroup v-if="results.length" heading="Results">
                            <CommandItem
                                v-for="result in results"
                                :key="`${result.type}-${result.url}`"
                                :value="result.url"
                                @select="openResult(result)"
                            >
                                <span class="truncate">{{ result.title }}</span>
                                <span
                                    v-if="result.subtitle"
                                    class="text-muted-foreground truncate text-xs"
                                >
                                    {{ result.subtitle }}
                                </span>
                                <span
                                    class="text-muted-foreground ml-auto text-xs capitalize"
                                >
                                    {{ result.type }}
                                </span>
                            </CommandItem>
                        </CommandGroup>

                        <div
                            v-if="searching"
                            class="text-muted-foreground flex items-center gap-2 px-3 py-2 text-sm"
                        >
                            <Spinner class="size-4" />
                            Searching…
                        </div>
                    </CommandList>
                </Command>
            </DialogContent>
        </Dialog>

        <CreateIssueModal
            v-if="activeAction === 'create-issue' && options?.issue"
            :open="true"
            :organization="organization.slug"
            :options="options.issue"
            @update:open="closeAction"
        />

        <CreateProjectModal
            v-if="activeAction === 'create-project' && options"
            :open="true"
            :organization="organization.slug"
            :visibilities="options.visibilities"
            @update:open="closeAction"
        />

        <CreateTeamModal
            v-if="activeAction === 'create-team'"
            :open="true"
            :organization="organization.slug"
            @update:open="closeAction"
        />

        <CreateClientModal
            v-if="activeAction === 'create-client'"
            :open="true"
            :organization="organization.slug"
            @update:open="closeAction"
        />

        <CreateContactModal
            v-if="activeAction === 'create-contact' && options"
            :open="true"
            :organization="organization.slug"
            :clients="options.clients"
            @update:open="closeAction"
        />

        <CreateInvoiceModal
            v-if="activeAction === 'create-invoice' && options"
            :open="true"
            :organization="organization.slug"
            :clients="options.clients"
            :projects="options.invoiceProjects"
            @update:open="closeAction"
        />

        <CreateTicketModal
            v-if="activeAction === 'create-ticket' && options"
            :open="true"
            :organization="organization.slug"
            :clients="options.clients"
            @update:open="closeAction"
        />

        <CreateSprintModal
            v-if="activeAction === 'create-sprint' && options"
            :open="true"
            :organization="organization.slug"
            :team="options.sprintBoard"
            @update:open="closeAction"
        />

        <SubmitFeedbackModal
            v-if="activeAction === 'create-feedback' && options"
            :open="true"
            :organization="organization.slug"
            :projects="options.feedbackProjects"
            @update:open="closeAction"
        />

        <CreateWorkTypeModal
            v-if="activeAction === 'create-work-type' && options"
            :open="true"
            :organization="organization.slug"
            :colors="options.workTypeColors"
            @update:open="closeAction"
        />

        <InviteMemberModal
            v-if="activeAction === 'invite-member' && options"
            :open="true"
            :organization="organization"
            :available-roles="options.roles"
            @update:open="closeAction"
        />

        <RoleFormModal
            v-if="activeAction === 'create-role' && options"
            :open="true"
            :organization="organization"
            :role="null"
            :available-permissions="options.permissions"
            @update:open="closeAction"
        />

        <CreateOrganizationModal
            v-if="activeAction === 'create-organization'"
            :open="true"
            @update:open="closeAction"
        />

        <div
            v-if="loadingAction"
            class="text-muted-foreground fixed right-4 bottom-4 flex items-center gap-2 rounded-md border px-3 py-2 text-sm shadow-sm"
        >
            <Spinner class="size-4" />
            Loading…
        </div>
    </div>
</template>
