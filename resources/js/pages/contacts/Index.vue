<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Contact, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmailLink from '@/components/EmailLink.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PhoneLink from '@/components/PhoneLink.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import { getInitials } from '@/composables/useInitials';
import { show as showClient } from '@/routes/clients';
import { show as showContact } from '@/routes/clients/contacts';
import { index } from '@/routes/contacts';
import type { Organization } from '@/types';

type DirectoryContact = {
    id: number;
    name: string | null;
    title: string | null;
    email: string;
    phone: string | null;
    client: { id: number; name: string; slug: string };
};

const props = defineProps<{
    currentOrganization: Organization;
    contacts: DirectoryContact[];
}>();

defineOptions({
    layout: (props: { currentOrganization: Organization }) => ({
        breadcrumbs: [
            {
                title: 'Contacts',
                href: index(props.currentOrganization.slug),
            },
        ],
    }),
});

const alphabet = [...'ABCDEFGHIJKLMNOPQRSTUVWXYZ', '#'];

const search = ref('');

const displayName = (contact: DirectoryContact) =>
    contact.name ?? contact.email;

const letterFor = (contact: DirectoryContact) => {
    const first = displayName(contact).trim().charAt(0).toUpperCase();

    return /[A-Z]/.test(first) ? first : '#';
};

const matches = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (term === '') {
        return props.contacts;
    }

    return props.contacts.filter((contact) =>
        [
            contact.name,
            contact.title,
            contact.email,
            contact.phone,
            contact.client.name,
        ].some((value) => value?.toLowerCase().includes(term)),
    );
});

const sections = computed(() => {
    const grouped = new Map<string, DirectoryContact[]>();

    for (const contact of matches.value) {
        const letter = letterFor(contact);
        grouped.set(letter, [...(grouped.get(letter) ?? []), contact]);
    }

    return alphabet
        .filter((letter) => grouped.has(letter))
        .map((letter) => ({ letter, contacts: grouped.get(letter)! }));
});

const availableLetters = computed(
    () => new Set(sections.value.map((section) => section.letter)),
);

const jumpTo = (letter: string) => {
    document
        .getElementById(`contacts-${letter}`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};
</script>

<template>
    <Head title="Contacts" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <Heading
                variant="small"
                title="Contacts"
                description="Everyone you work with at your clients"
            />
            <div class="relative w-full sm:w-72">
                <Search
                    class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                />
                <Input
                    v-model="search"
                    type="search"
                    class="pl-9"
                    placeholder="Search name, company, email…"
                    aria-label="Search contacts"
                    data-test="contacts-search"
                />
            </div>
        </div>

        <EmptyState
            v-if="contacts.length === 0"
            :icon="Contact"
            title="No contacts yet"
            description="Add contacts from a client's page. People who email support from a client's domain show up here too."
        />

        <template v-else>
            <nav
                class="bg-background/95 supports-[backdrop-filter]:bg-background/80 sticky top-0 z-10 -mx-6 flex flex-wrap gap-0.5 border-b px-6 py-2 backdrop-blur"
                aria-label="Jump to letter"
            >
                <button
                    v-for="letter in alphabet"
                    :key="letter"
                    type="button"
                    class="hover:bg-muted size-7 rounded-md text-xs font-medium tabular-nums disabled:pointer-events-none disabled:opacity-30"
                    :disabled="!availableLetters.has(letter)"
                    @click="jumpTo(letter)"
                >
                    {{ letter }}
                </button>
            </nav>

            <p
                v-if="sections.length === 0"
                class="text-muted-foreground text-sm"
            >
                No contacts match “{{ search }}”.
            </p>

            <section
                v-for="section in sections"
                :id="`contacts-${section.letter}`"
                :key="section.letter"
                class="scroll-mt-16 space-y-3"
            >
                <h2
                    class="text-muted-foreground text-sm font-semibold tracking-wide"
                >
                    {{ section.letter }}
                </h2>
                <ul
                    class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
                >
                    <li
                        v-for="contact in section.contacts"
                        :key="contact.id"
                        class="bg-card flex gap-3 rounded-xl border p-4 shadow-sm"
                        data-test="contact-card"
                    >
                        <Avatar class="size-10 shrink-0">
                            <AvatarFallback class="text-xs">
                                {{ getInitials(displayName(contact)) }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="min-w-0 flex-1 space-y-1 text-sm">
                            <Link
                                :href="
                                    showContact([
                                        currentOrganization.slug,
                                        contact.client.slug,
                                        contact.id,
                                    ])
                                "
                                class="block truncate font-medium hover:underline"
                            >
                                {{ displayName(contact) }}
                            </Link>
                            <p class="text-muted-foreground truncate">
                                <template v-if="contact.title">
                                    {{ contact.title }} ·
                                </template>
                                <Link
                                    :href="
                                        showClient([
                                            currentOrganization.slug,
                                            contact.client.slug,
                                        ])
                                    "
                                    class="hover:text-foreground hover:underline"
                                >
                                    {{ contact.client.name }}
                                </Link>
                            </p>
                            <p class="text-muted-foreground truncate">
                                <EmailLink :email="contact.email" />
                            </p>
                            <p
                                v-if="contact.phone"
                                class="text-muted-foreground truncate"
                            >
                                <PhoneLink :phone="contact.phone" />
                            </p>
                        </div>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>
