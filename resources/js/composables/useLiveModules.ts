import { router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { useDebounceFn } from '@vueuse/core';
import { broadcastingEnabled, organizationChannel } from '@/lib/broadcasting';

/**
 * Switching a module on or off changes what the sidebar may show, for everyone in the
 * organization at once. The event carries nothing: the shared props are refetched so the
 * server decides again what this particular viewer is allowed to see.
 *
 * Browser only, like the notifications menu. Echo connects synchronously in setup, and
 * server rendering has neither a key nor a socket.
 */
export function useLiveModules(): void {
    const page = usePage();
    const organizationId = page.props.currentOrganization?.id;

    if (import.meta.env.SSR || !broadcastingEnabled || !organizationId) {
        return;
    }

    const refresh = useDebounceFn(
        () => router.reload({ only: ['currentOrganization', 'currentTeam'] }),
        300,
    );

    useEcho(organizationChannel(organizationId), '.modules.changed', () => {
        void refresh();
    });
}
