import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { RouteDefinition } from '@/wayfinder';

type PageProps = Record<string, unknown>;

type MutateOptions = {
    data?: RequestPayload;
    optimistic?: (props: PageProps) => Partial<PageProps> | void;
    errorMessage?: string;
    only?: string[];
};

/**
 * Applies the change to the page props straight away and lets Inertia roll it back
 * if the request fails, so a move lands under the cursor instead of after a round trip.
 */
export function mutate(
    href: RouteDefinition<'get' | 'post' | 'put' | 'patch' | 'delete'> | string,
    { data, optimistic, errorMessage, only }: MutateOptions = {},
): void {
    router.visit(href, {
        ...(data ? { data } : {}),
        ...(optimistic ? { optimistic } : {}),
        ...(only ? { only } : {}),
        preserveScroll: true,
        preserveState: true,
        onError: () =>
            toast.error(errorMessage ?? 'That change could not be saved.'),
    });
}
