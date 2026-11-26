<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { FileText, Image, Paperclip, Upload, X } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDate } from '@/lib/dates';
import { destroy } from '@/routes/documents';
import type { DocumentItem } from '@/types';
import type { RouteDefinition } from '@/wayfinder';

const props = defineProps<{
    organization: string;
    documents: DocumentItem[];
    canManage: boolean;
    upload: RouteDefinition<'post'>;
}>();

const accept = '.pdf,.png,.jpg,.jpeg,.gif,.webp,.heic';

const input = ref<HTMLInputElement | null>(null);
const toRemove = ref<DocumentItem | null>(null);

const form = useForm<{ file: File | null }>({ file: null });

const sendFile = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    form.file = file;
    form.submit(props.upload, {
        preserveScroll: true,
        onFinish: () => {
            form.reset();

            if (input.value) {
                input.value.value = '';
            }
        },
    });
};

const size = (bytes: number) =>
    bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
</script>

<template>
    <div class="space-y-4">
        <ul v-if="documents.length" class="divide-y rounded-lg border">
            <li
                v-for="document in documents"
                :key="document.id"
                class="flex items-center gap-3 px-4 py-3 text-sm"
                data-test="document-row"
            >
                <component
                    :is="document.isImage ? Image : FileText"
                    class="text-muted-foreground size-4 shrink-0"
                />
                <div class="min-w-0 flex-1">
                    <a
                        :href="document.url"
                        target="_blank"
                        rel="noopener"
                        class="block truncate font-medium hover:underline"
                    >
                        {{ document.name }}
                    </a>
                    <p class="text-muted-foreground truncate text-xs">
                        {{ size(document.size) }}
                        <template v-if="document.uploadedAt">
                            · {{ formatDate(document.uploadedAt) }}
                        </template>
                        <template v-if="document.uploadedBy">
                            · {{ document.uploadedBy }}
                        </template>
                    </p>
                </div>
                <Button
                    v-if="canManage"
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="`Delete ${document.name}`"
                    @click="toRemove = document"
                >
                    <X />
                </Button>
            </li>
        </ul>
        <p v-else class="text-muted-foreground flex items-center gap-2 text-sm">
            <Paperclip class="size-4" /> No documents yet.
        </p>

        <div v-if="canManage" class="space-y-2">
            <input
                ref="input"
                type="file"
                class="hidden"
                :accept="accept"
                data-test="document-input"
                @change="sendFile"
            />
            <Button
                variant="outline"
                size="sm"
                :disabled="form.processing"
                @click="input?.click()"
            >
                <Spinner v-if="form.processing" class="size-4" />
                <Upload v-else />
                Upload a PDF or image
            </Button>
            <InputError :message="form.errors.file" />
        </div>

        <ConfirmDeleteModal
            :open="toRemove !== null"
            title="Delete document"
            confirm-label="Delete document"
            :href="toRemove ? destroy([organization, toRemove.id]) : null"
            @update:open="toRemove = null"
        >
            <strong>{{ toRemove?.name }}</strong> will be deleted for everyone.
        </ConfirmDeleteModal>
    </div>
</template>
