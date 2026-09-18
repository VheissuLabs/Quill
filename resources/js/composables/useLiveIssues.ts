import { router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { useDebounceFn } from '@vueuse/core';
import { broadcastingEnabled, organizationChannel } from '@/lib/broadcasting';

export function useLiveIssues(only: string[]): void {
    const page = usePage();
    const organizationId = page.props.currentOrganization?.id;

    if (import.meta.env.SSR || !broadcastingEnabled || !organizationId) {
        return;
    }

    const refresh = useDebounceFn(
        () => router.reload({ only, showProgress: false }),
        300,
    );

    useEcho(organizationChannel(organizationId), '.issues.changed', () => {
        void refresh();
    });
}
